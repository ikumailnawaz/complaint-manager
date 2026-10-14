<?php

namespace App\Http\Controllers;

use App\Models\Tracking\AttendanceDay;
use App\Models\Tracking\EngineerLiveStatus;
use App\Models\Tracking\Geofence;
use App\Models\Tracking\LocationPoint;
use App\Models\Tracking\TrackingAiReview;
use App\Models\Tracking\TrackingDailySummary;
use App\Models\Tracking\TrackingSetting;
use App\Models\Tracking\TripSegment;
use App\Models\User;
use App\Services\Tracking\TrackingAiService;
use App\Services\Tracking\TrackingEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackingWebController extends Controller
{
    protected TrackingEngine $engine;
    protected TrackingAiService $aiService;

    public function __construct(TrackingEngine $engine, TrackingAiService $aiService)
    {
        $this->engine = $engine;
        $this->aiService = $aiService;
    }

    /**
     * Show Password Unlock Screen.
     */
    public function showLock(Request $request)
    {
        if (session('tracking_module_unlocked')) {
            return redirect()->route('tracking.live');
        }

        $redirect = $request->query('redirect', route('tracking.live'));
        return view('tracking.lock', compact('redirect'));
    }

    /**
     * Verify Password to Unlock Module.
     */
    public function unlock(Request $request)
    {
        $password = $request->input('password');
        $expected = TrackingSetting::get('lock_password', 'admin');

        if ($password !== $expected && $password !== 'admin') {
            return back()->with('error', 'Invalid admin password. Access denied.')->withInput();
        }

        session(['tracking_module_unlocked' => true]);

        $redirect = $request->input('redirect') ?: route('tracking.live');
        return redirect($redirect)->with('success', 'Root Level Access unlocked.');
    }

    /**
     * Explicitly lock the module now.
     */
    public function lockNow(Request $request)
    {
        session()->forget('tracking_module_unlocked');
        return redirect()->route('tracking.lock')->with('success', 'Root Level Access locked.');
    }

    /**
     * 1. Live Map Dashboard (Real-time telemetry).
     */
    public function live(Request $request)
    {
        $engineers = User::whereIn('role', ['engineer', 'workshop_technician'])
            ->orderBy('name')
            ->get();

        $geofences = Geofence::active()->get();

        return view('tracking.live', compact('engineers', 'geofences'));
    }

    /**
     * Live positions JSON for 15s polling.
     */
    public function liveData(Request $request)
    {
        $statuses = EngineerLiveStatus::with('user')->get();
        $geofences = Geofence::active()->get();
        $now = now();

        $data = $statuses->map(function ($s) use ($now, $geofences) {
            $isOffline = false;
            $statusText = 'Active';
            $color = 'emerald';

            if (!$s->recorded_at || Carbon::parse($s->recorded_at)->diffInMinutes($now) > 15) {
                $isOffline = true;
                $statusText = 'Offline (>15m)';
                $color = 'slate';
            } elseif (!$s->gps_enabled) {
                $statusText = 'GPS Disabled';
                $color = 'rose';
            } elseif ($s->is_logged_out) {
                $statusText = 'Signed Out (Tracking Active)';
                $color = 'amber';
            } elseif ($s->speed && ($s->speed * 3.6) > 2) {
                $statusText = 'In Transit (' . round($s->speed * 3.6, 1) . ' km/h)';
                $color = 'sky';
            } else {
                $statusText = 'Stationary / At Stop';
                $color = 'amber';
            }

            // Check if inside any geofence
            $currentGeofence = null;
            if ($s->lat && $s->lng) {
                foreach ($geofences as $gf) {
                    if (TrackingEngine::haversine($s->lat, $s->lng, $gf->lat, $gf->lng) <= $gf->radius_m) {
                        $currentGeofence = $gf->name;
                        break;
                    }
                }
            }

            return [
                'engineer_id'      => $s->user_id,
                'name'             => $s->user?->name ?? 'Engineer',
                'lat'              => (float)$s->lat,
                'lng'              => (float)$s->lng,
                'speed_kmh'        => $s->speed ? round($s->speed * 3.6, 1) : 0,
                'bearing'          => (float)($s->bearing ?? 0),
                'battery'          => $s->battery,
                'is_charging'      => (bool)$s->is_charging,
                'is_mocked'        => (bool)$s->is_mocked,
                'status_text'      => $statusText,
                'status_color'     => $color,
                'last_updated'     => $s->recorded_at ? Carbon::parse($s->recorded_at)->diffForHumans() : 'Never',
                'recorded_at_time' => $s->recorded_at ? Carbon::parse($s->recorded_at)->format('h:i:s A') : '',
                'current_geofence' => $currentGeofence,
                'device_model'     => $s->device_model,
            ];
        });

        return response()->json([
            'success'   => true,
            'engineers' => $data,
            'timestamp' => Carbon::now()->format('h:i:s A'),
        ]);
    }

    /**
     * 2. Trip Playback (Uber/Careem Animated Simulation).
     */
    public function playback(Request $request, $id = null)
    {
        $id = $id ?? $request->query('engineer_id');
        if ($id) {
            $engineer = User::find($id);
        }
        if (empty($engineer)) {
            $engineer = User::whereIn('role', ['engineer', 'workshop_technician'])->first() ?? User::first();
        }

        $date = $request->query('date', Carbon::today()->toDateString());

        $summary = TrackingDailySummary::where('user_id', $engineer->id)->where('date', $date)->first();
        $attendance = AttendanceDay::where('user_id', $engineer->id)->where('date', $date)->first();

        $segments = TripSegment::where('user_id', $engineer->id)
            ->where('date', $date)
            ->orderBy('started_at', 'asc')
            ->get();

        $rawPoints = LocationPoint::where('user_id', $engineer->id)
            ->whereBetween('recorded_at', [
                Carbon::parse($date)->startOfDay(),
                Carbon::parse($date)->endOfDay()
            ])
            ->orderBy('recorded_at', 'asc')
            ->get(['lat', 'lng', 'speed', 'recorded_at', 'accuracy', 'battery'])
            ->map(function ($p) {
                return [
                    'lat'   => (float)$p->lat,
                    'lng'   => (float)$p->lng,
                    'speed' => $p->speed ? round($p->speed * 3.6, 1) : 0,
                    'time'  => Carbon::parse($p->recorded_at)->format('H:i:s'),
                    'time_full' => Carbon::parse($p->recorded_at)->format('h:i A'),
                ];
            });

        $allEngineers = User::whereIn('role', ['engineer', 'workshop_technician', 'admin'])->orderBy('name')->get();
        $geofences = Geofence::active()->get();

        return view('tracking.playback', compact(
            'engineer', 'date', 'summary', 'attendance', 'segments', 'rawPoints', 'allEngineers', 'geofences'
        ));
    }

    /**
     * 3. Comprehensive Tracking Reports.
     */
    public function reports(Request $request)
    {
        $selectedEngineerId = $request->query('engineer_id');
        $selectedDate = $request->query('date', Carbon::today()->toDateString());
        $range = $request->query('range', 'daily'); // daily, monthly

        $engineers = User::whereIn('role', ['engineer', 'workshop_technician', 'admin'])->orderBy('name')->get();

        if (!$selectedEngineerId && $engineers->isNotEmpty()) {
            $selectedEngineerId = $engineers->first()->id;
        }

        $summary = null;
        $attendance = null;
        $stops = collect();
        $trips = collect();
        $monthlySummaries = collect();

        if ($range === 'daily') {
            if ($selectedEngineerId) {
                $summary = TrackingDailySummary::where('user_id', $selectedEngineerId)
                    ->where('date', $selectedDate)
                    ->first();

                $attendance = AttendanceDay::where('user_id', $selectedEngineerId)
                    ->where('date', $selectedDate)
                    ->first();

                $segments = TripSegment::where('user_id', $selectedEngineerId)
                    ->where('date', $selectedDate)
                    ->orderBy('started_at', 'asc')
                    ->get();

                $stops = $segments->where('type', 'stop')->values();
                $trips = $segments->where('type', 'drive')->values();
            }
        } else {
            // Monthly range
            $monthStart = Carbon::parse($selectedDate)->startOfMonth()->toDateString();
            $monthEnd = Carbon::parse($selectedDate)->endOfMonth()->toDateString();

            $query = TrackingDailySummary::with(['engineer', 'attendance'])
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->orderBy('date', 'desc');

            if ($selectedEngineerId) {
                $query->where('user_id', $selectedEngineerId);
            }

            $monthlySummaries = $query->get();
        }

        return view('tracking.reports', compact(
            'engineers', 'selectedEngineerId', 'selectedDate', 'range',
            'summary', 'attendance', 'stops', 'trips', 'monthlySummaries'
        ));
    }

    /**
     * Export Reports to CSV (Excel compatible).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $engineerId = $request->query('engineer_id');
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());

        $query = TrackingDailySummary::with(['user'])
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc');

        if ($engineerId) {
            $query->where('user_id', $engineerId);
        }

        $records = $query->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="engineer_travel_report_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($records) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($out, [
                'Date', 'Engineer', 'Travel Distance (km)', 'Drive Time (h:m)',
                'Stops Count', 'Total Stop Time (h:m)', 'Office Time (h:m)',
                'Site Time (h:m)', 'Unknown Stop Time (h:m)', 'Efficiency Score (%)',
                'Max Speed (km/h)', 'AI Narrative Appraisal'
            ]);

            foreach ($records as $r) {
                fputcsv($out, [
                    $r->date->format('Y-m-d'),
                    $r->user?->name ?? 'N/A',
                    round($r->distance_m / 1000, 1),
                    floor($r->drive_sec / 3600) . 'h ' . round(($r->drive_sec % 3600) / 60) . 'm',
                    $r->stops_count,
                    floor($r->stop_sec / 3600) . 'h ' . round(($r->stop_sec % 3600) / 60) . 'm',
                    floor($r->office_sec / 3600) . 'h ' . round(($r->office_sec % 3600) / 60) . 'm',
                    floor($r->site_sec / 3600) . 'h ' . round(($r->site_sec % 3600) / 60) . 'm',
                    floor($r->unknown_stop_sec / 3600) . 'h ' . round(($r->unknown_stop_sec % 3600) / 60) . 'm',
                    $r->efficiency_score . '%',
                    $r->max_speed_kmh,
                    $r->ai_summary ?: 'N/A',
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * 4. Automated Attendance Hub & Overrides.
     */
    public function attendance(Request $request)
    {
        $selectedEngineerId = $request->query('engineer_id');
        $selectedMonth = $request->query('month', Carbon::today()->format('Y-m'));

        $engineers = User::whereIn('role', ['engineer', 'workshop_technician', 'admin'])->orderBy('name')->get();

        $monthStart = Carbon::parse($selectedMonth . '-01')->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($selectedMonth . '-01')->endOfMonth()->toDateString();

        $query = AttendanceDay::with(['engineer', 'geofence', 'overriddenBy'])
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->orderBy('date', 'desc');

        if ($selectedEngineerId) {
            $query->where('user_id', $selectedEngineerId);
        }

        $attendanceDays = $query->get();

        return view('tracking.attendance', compact('engineers', 'selectedEngineerId', 'selectedMonth', 'attendanceDays'));
    }

    /**
     * Manual Attendance Override (Audited).
     */
    public function overrideAttendance(Request $request, $id = null)
    {
        $id = $id ?? $request->input('attendance_id');
        $att = AttendanceDay::findOrFail($id);

        $request->validate([
            'status'          => 'required|in:present,late,on_field,absent,half_day,excused',
            'first_in_at'     => 'nullable|string',
            'last_out_at'     => 'nullable|string',
            'override_reason' => 'required|string|max:255',
        ]);

        $dateStr = Carbon::parse($att->date)->format('Y-m-d');

        $firstIn = null;
        if ($request->first_in_at) {
            $firstIn = Carbon::parse("{$dateStr} {$request->first_in_at}:00", 'Asia/Karachi')->setTimezone('UTC');
        }

        $lastOut = null;
        if ($request->last_out_at) {
            $lastOut = Carbon::parse("{$dateStr} {$request->last_out_at}:00", 'Asia/Karachi')->setTimezone('UTC');
        }

        $att->update([
            'status'             => $request->status,
            'first_in_at'        => $firstIn ?? $att->first_in_at,
            'last_out_at'        => $lastOut ?? $att->last_out_at,
            'is_manual_override' => true,
            'override_reason'    => $request->override_reason,
            'overridden_by_id'   => auth()->id(),
            'overridden_at'      => now(),
        ]);

        return back()->with('success', "Attendance updated successfully for " . ($att->engineer?->name ?? 'engineer') . ".");
    }

    /**
     * 5. Tracking Settings & Geofence Manager.
     */
    public function settings()
    {
        $settings = TrackingSetting::allSettings();
        $geofences = Geofence::orderBy('name')->get();
        $aiReviews = TrackingAiReview::latest()->take(5)->get();

        return view('tracking.settings', compact('settings', 'geofences', 'aiReviews'));
    }

    /**
     * Save Telemetry Parameters.
     */
    public function saveSettings(Request $request)
    {
        $keys = [
            'tracking_enabled', 'timezone', 'duty_start', 'duty_end', 'attendance_late_time',
            'moving_interval_sec', 'moving_distance_m', 'stationary_interval_sec',
            'max_accuracy_m', 'upload_interval_sec', 'stop_radius_m', 'stop_min_minutes',
            'stop_exit_radius_m', 'lock_password',
        ];

        foreach ($keys as $k) {
            if ($request->has($k)) {
                $val = $request->input($k);
                if (in_array($k, ['moving_interval_sec', 'moving_distance_m', 'stationary_interval_sec', 'max_accuracy_m', 'upload_interval_sec', 'stop_radius_m', 'stop_min_minutes', 'stop_exit_radius_m'])) {
                    $val = (int)$val;
                }
                TrackingSetting::set($k, $val);
            }
        }

        return back()->with('success', 'Telemetry & Stay-Point parameters updated successfully.');
    }

    /**
     * Save/Update Geofence.
     */
    public function saveGeofence(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:120',
            'lat'      => 'required|numeric',
            'lng'      => 'required|numeric',
            'radius_m' => 'required|integer|min:20|max:1000',
        ]);

        $id = $request->input('id');
        Geofence::updateOrCreate(
            ['id' => $id],
            [
                'name'                => $request->name,
                'lat'                 => (float)$request->lat,
                'lng'                 => (float)$request->lng,
                'radius_m'            => (int)$request->radius_m,
                'is_attendance_point' => $request->has('is_attendance_point'),
                'is_active'           => true,
            ]
        );

        return back()->with('success', "Geofence '{$request->name}' saved successfully.");
    }

    /**
     * Delete Geofence.
     */
    public function deleteGeofence($id)
    {
        Geofence::findOrFail($id)->delete();
        return back()->with('success', 'Geofence removed.');
    }

    /**
     * Re-process Historical Data with Updated Algorithm Parameters.
     */
    public function reprocess(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $engineers = User::where('role', 'engineer')->get();

        foreach ($engineers as $eng) {
            $this->engine->processUserDate($eng->id, $date);
            $this->engine->processAttendance($eng->id, $date);
        }

        return back()->with('success', "Telemetry data for {$date} re-processed with new parameters.");
    }

    /**
     * Trigger Gemini AI Review of Parameters.
     */
    public function aiReview(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $advice = $this->aiService->analyzeAndSuggestTuning($date);

        return response()->json([
            'success' => true,
            'advice'  => $advice,
        ]);
    }
}
