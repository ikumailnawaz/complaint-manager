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
use App\Models\Ticket;
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
        $password = (string)$request->input('password');
        $expected = (string)TrackingSetting::get('lock_password', 'admin');

        if ($password !== $expected) {
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

        $bankBranches = Ticket::whereNotNull('bank_name')
            ->whereNotIn('status', ['closed'])
            ->get(['id', 'ticket_no', 'bank_name', 'branch_name', 'branch_location', 'branch_address', 'status'])
            ->map(function ($ticket) {
                $coords = $this->aiService->resolveBranchCoordinates($ticket);
                if ($coords) {
                    return [
                        'id' => $ticket->id,
                        'ticket_no' => $ticket->ticket_no,
                        'bank_name' => $ticket->bank_name,
                        'branch_name' => $ticket->branch_name,
                        'address' => $ticket->branch_address ?? $ticket->branch_location,
                        'lat' => $coords['lat'],
                        'lng' => $coords['lng'],
                        'landmark' => $coords['landmark'] ?? '',
                        'status' => $ticket->status,
                    ];
                }
                return null;
            })
            ->filter()
            ->values();

        return view('tracking.live', compact('engineers', 'geofences', 'bankBranches'));
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

            $diffMins = $s->recorded_at ? Carbon::parse($s->recorded_at)->diffInMinutes($now) : null;
            $isStale = ($diffMins === null || $diffMins >= 5);

            return [
                'engineer_id'      => $s->user_id,
                'name'             => $s->user?->name ?? 'Engineer',
                'lat'              => (float)$s->lat,
                'lng'              => (float)$s->lng,
                'accuracy'         => $s->accuracy ? round((float)$s->accuracy, 1) : null,
                'is_stale'         => $isStale,
                'stale_minutes'    => $diffMins,
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

        $summary = TrackingDailySummary::where('user_id', $engineer->id)
            ->where(function($q) use ($date) {
                $q->where('date', $date)->orWhere('date', 'like', $date . '%');
            })
            ->orderByDesc('distance_m')
            ->first();

        $attendance = AttendanceDay::where('user_id', $engineer->id)
            ->where(function($q) use ($date) {
                $q->where('date', $date)->orWhere('date', 'like', $date . '%');
            })
            ->first();

        $segments = TripSegment::where('user_id', $engineer->id)
            ->where(function($q) use ($date) {
                $q->where('date', $date)->orWhere('date', 'like', $date . '%');
            })
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

        $bankBranches = Ticket::whereNotNull('bank_name')
            ->whereNotIn('status', ['closed'])
            ->get(['id', 'ticket_no', 'bank_name', 'branch_name', 'branch_location', 'branch_address', 'status'])
            ->map(function ($ticket) {
                $coords = $this->aiService->resolveBranchCoordinates($ticket);
                if ($coords) {
                    return [
                        'id' => $ticket->id,
                        'ticket_no' => $ticket->ticket_no,
                        'bank_name' => $ticket->bank_name,
                        'branch_name' => $ticket->branch_name,
                        'address' => $ticket->branch_address ?? $ticket->branch_location,
                        'lat' => $coords['lat'],
                        'lng' => $coords['lng'],
                        'landmark' => $coords['landmark'] ?? '',
                        'status' => $ticket->status,
                    ];
                }
                return null;
            })
            ->filter()
            ->values();

        return view('tracking.playback', compact(
            'engineer', 'date', 'summary', 'attendance', 'segments', 'rawPoints', 'allEngineers', 'geofences', 'bankBranches'
        ));
    }

    /**
     * 3. Comprehensive Tracking Reports.
     */
    /**
     * 3. Comprehensive Tracking Reports with Date Range (From Date -> To Date)
     * and Automated Gemini Complaint Matching (< 2.5km radius).
     */
    public function reports(Request $request)
    {
        $selectedEngineerId = $request->query('engineer_id');
        $fromDate = $request->query('from_date', $request->query('date', Carbon::today()->toDateString()));
        $toDate = $request->query('to_date', $fromDate);

        // Ensure chronological order
        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $engineers = User::whereIn('role', ['engineer', 'workshop_technician', 'admin'])->orderBy('name')->get();

        if (!$selectedEngineerId && $engineers->isNotEmpty()) {
            $farhan = $engineers->firstWhere('name', 'farhankhalid');
            $selectedEngineerId = $farhan ? $farhan->id : $engineers->first()->id;
        }

        $isSingleDay = ($fromDate === $toDate);

        // Fetch unresolved tickets for matching stops within 2.5km
        $unresolvedTickets = collect();
        if ($selectedEngineerId) {
            $unresolvedTickets = Ticket::where(function ($q) use ($selectedEngineerId) {
                    $q->where('assigned_engineer_id', $selectedEngineerId)
                      ->orWhere('workshop_engineer_id', $selectedEngineerId);
                })
                ->whereNotIn('status', ['closed'])
                ->get();
        }

        $summary = null;
        $attendance = null;
        $stops = collect();
        $trips = collect();
        $rangeSummaries = collect();
        $rangeStats = [
            'total_distance_km' => 0,
            'total_transit_min' => 0,
            'total_stop_min'    => 0,
            'total_stops_count' => 0,
            'days_present'      => 0,
            'avg_score'         => 0,
        ];

        if ($selectedEngineerId) {
            if ($isSingleDay) {
                $summary = TrackingDailySummary::where('user_id', $selectedEngineerId)
                    ->where(function ($q) use ($fromDate) {
                        $q->where('date', $fromDate)->orWhere('date', 'like', $fromDate . '%');
                    })
                    ->orderByDesc('distance_m')
                    ->first();

                $attendance = AttendanceDay::where('user_id', $selectedEngineerId)
                    ->where(function ($q) use ($fromDate) {
                        $q->where('date', $fromDate)->orWhere('date', 'like', $fromDate . '%');
                    })
                    ->first();

                $segments = TripSegment::with('ticket')
                    ->where('user_id', $selectedEngineerId)
                    ->where(function ($q) use ($fromDate) {
                        $q->where('date', $fromDate)->orWhere('date', 'like', $fromDate . '%');
                    })
                    ->orderBy('started_at', 'asc')
                    ->get();

                // Process stops and match to complaints if unknown or within 2.5km
                $stops = $segments->where('type', 'stop')->values()->map(function ($stop) use ($unresolvedTickets) {
                    if (($stop->kind === 'unknown' || str_contains($stop->address ?? '', 'Unplanned Stop') || !$stop->ticket_id) && $stop->lat && $stop->lng) {
                        $match = $this->aiService->matchStopToUnresolvedComplaints((float)$stop->lat, (float)$stop->lng, $unresolvedTickets, 2500);
                        if ($match) {
                            $stop->kind = 'ticket_site';
                            $stop->label = $match['label'];
                            $stop->address = $match['address'];
                            $stop->ticket_id = $match['ticket']->id;
                            $stop->save();
                        }
                    }
                    return $stop;
                });

                $trips = $segments->where('type', 'drive')->values();
            } else {
                // Multi-day range
                $summariesQuery = TrackingDailySummary::with(['engineer', 'attendance'])
                    ->where('user_id', $selectedEngineerId)
                    ->whereBetween('date', [$fromDate, $toDate])
                    ->orderBy('date', 'desc');

                $rangeSummaries = $summariesQuery->get();

                if ($rangeSummaries->isNotEmpty()) {
                    $totalDistM = $rangeSummaries->sum('distance_m');
                    $totalDriveSec = $rangeSummaries->sum('drive_sec');
                    $totalStopSec = $rangeSummaries->sum('stop_sec');
                    $totalStops = $rangeSummaries->sum('stops_count');
                    $avgScore = round($rangeSummaries->avg('efficiency_score'));

                    $rangeStats = [
                        'total_distance_km' => round($totalDistM / 1000, 1),
                        'total_transit_min' => round($totalDriveSec / 60),
                        'total_stop_min'    => round($totalStopSec / 60),
                        'total_stops_count' => $totalStops,
                        'days_present'      => $rangeSummaries->filter(fn($s) => $s->attendance && $s->attendance->status !== 'absent')->count(),
                        'avg_score'         => $avgScore,
                    ];
                }

                $segments = TripSegment::with('ticket')
                    ->where('user_id', $selectedEngineerId)
                    ->whereBetween('date', [$fromDate, $toDate])
                    ->orderBy('started_at', 'asc')
                    ->get();

                $stops = $segments->where('type', 'stop')->values()->map(function ($stop) use ($unresolvedTickets) {
                    if (($stop->kind === 'unknown' || str_contains($stop->address ?? '', 'Unplanned Stop') || !$stop->ticket_id) && $stop->lat && $stop->lng) {
                        $match = $this->aiService->matchStopToUnresolvedComplaints((float)$stop->lat, (float)$stop->lng, $unresolvedTickets, 2500);
                        if ($match) {
                            $stop->kind = 'ticket_site';
                            $stop->label = $match['label'];
                            $stop->address = $match['address'];
                            $stop->ticket_id = $match['ticket']->id;
                            $stop->save();
                        }
                    }
                    return $stop;
                });

                $trips = $segments->where('type', 'drive')->values();
            }
        }

        return view('tracking.reports', compact(
            'engineers', 'selectedEngineerId', 'fromDate', 'toDate', 'isSingleDay',
            'summary', 'attendance', 'stops', 'trips', 'rangeSummaries', 'rangeStats'
        ));
    }

    /**
     * Export Reports to CSV (Excel compatible).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $engineerId = $request->query('engineer_id');
        $fromDate = $request->query('from_date', $request->query('start_date', $request->query('date', Carbon::today()->toDateString())));
        $toDate = $request->query('to_date', $request->query('end_date', $fromDate));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $query = TrackingDailySummary::with(['user', 'attendance'])
            ->whereBetween('date', [$fromDate, $toDate])
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
                'Site Time (h:m)', 'Unknown Stop Time (h:m)', 'Attendance',
                'Efficiency Score (%)', 'Max Speed (km/h)', 'AI Narrative Appraisal'
            ]);

            foreach ($records as $r) {
                fputcsv($out, [
                    $r->date ? (is_object($r->date) ? $r->date->format('Y-m-d') : substr($r->date, 0, 10)) : 'N/A',
                    $r->user?->name ?? 'N/A',
                    round(($r->distance_m ?? 0) / 1000, 1),
                    floor(($r->drive_sec ?? 0) / 3600) . 'h ' . round((($r->drive_sec ?? 0) % 3600) / 60) . 'm',
                    $r->stops_count ?? 0,
                    floor(($r->stop_sec ?? 0) / 3600) . 'h ' . round((($r->stop_sec ?? 0) % 3600) / 60) . 'm',
                    floor(($r->office_sec ?? 0) / 3600) . 'h ' . round((($r->office_sec ?? 0) % 3600) / 60) . 'm',
                    floor(($r->site_sec ?? 0) / 3600) . 'h ' . round((($r->site_sec ?? 0) % 3600) / 60) . 'm',
                    floor(($r->unknown_stop_sec ?? 0) / 3600) . 'h ' . round((($r->unknown_stop_sec ?? 0) % 3600) / 60) . 'm',
                    $r->attendance ? ($r->attendance->is_late ? 'Late' : 'Present') : 'Absent',
                    ($r->efficiency_score ?? 0) . '%',
                    $r->max_speed_kmh ?? 0,
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
        $mapping = [
            'lock_password'           => 'lock_password',
            'module_password'         => 'lock_password',
            'duty_start'              => 'duty_start',
            'duty_start_time'         => 'duty_start',
            'duty_end'                => 'duty_end',
            'duty_end_time'           => 'duty_end',
            'attendance_late_time'    => 'attendance_late_time',
            'attendance_start_time'   => 'attendance_start_time',
            'attendance_grace_minutes'=> 'attendance_grace_minutes',
            'moving_interval_sec'     => 'moving_interval_sec',
            'moving_distance_m'       => 'moving_distance_m',
            'stationary_interval_sec' => 'stationary_interval_sec',
            'max_accuracy_m'          => 'max_accuracy_m',
            'upload_interval_sec'     => 'upload_interval_sec',
            'stop_radius_m'           => 'stop_radius_m',
            'stay_radius_meters'      => 'stop_radius_m',
            'stop_min_minutes'        => 'stop_min_minutes',
            'stay_min_minutes'        => 'stop_min_minutes',
            'stop_exit_radius_m'      => 'stop_exit_radius_m',
            'exit_radius_meters'      => 'stop_exit_radius_m',
        ];

        foreach ($mapping as $inputKey => $settingKey) {
            if ($request->has($inputKey) && $request->input($inputKey) !== null && $request->input($inputKey) !== '') {
                $val = $request->input($inputKey);
                if (in_array($settingKey, ['moving_interval_sec', 'moving_distance_m', 'stationary_interval_sec', 'max_accuracy_m', 'upload_interval_sec', 'stop_radius_m', 'stop_min_minutes', 'stop_exit_radius_m', 'attendance_grace_minutes'])) {
                    $val = (int)$val;
                }
                TrackingSetting::set($settingKey, $val);
            }
        }

        return redirect()->route('tracking.settings')->with('success', 'Telemetry & Security settings saved successfully.');
    }

    /**
     * Save/Update Geofence.
     */
    public function saveGeofence(Request $request)
    {
        $lat = $request->input('lat') ?? $request->input('latitude');
        $lng = $request->input('lng') ?? $request->input('longitude');
        $radius = $request->input('radius_m') ?? $request->input('radius_meters');

        $request->merge([
            'lat'      => $lat,
            'lng'      => $lng,
            'radius_m' => $radius,
        ]);

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
                'is_attendance_point' => $request->has('is_attendance_point') || $request->has('is_attendance'),
                'is_active'           => true,
            ]
        );

        return redirect()->route('tracking.settings')->with('success', "Geofence '{$request->name}' saved successfully.");
    }

    /**
     * Delete Geofence.
     */
    public function deleteGeofence($id)
    {
        Geofence::findOrFail($id)->delete();
        return redirect()->route('tracking.settings')->with('success', 'Geofence removed successfully.');
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
