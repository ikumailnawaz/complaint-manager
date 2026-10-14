<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tracking\AttendanceDay;
use App\Models\Tracking\Geofence;
use App\Models\Tracking\TrackingDailySummary;
use App\Models\Tracking\TrackingDevice;
use App\Models\Tracking\TrackingSetting;
use App\Models\Tracking\TripSegment;
use App\Services\Tracking\TrackingEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrackingApiController extends Controller
{
    protected TrackingEngine $engine;

    public function __construct(TrackingEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * 1. Register device and issue long-lived tracking key.
     * Authenticated via Sanctum Bearer token.
     */
    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'device_id'    => 'required|string|max:100',
            'device_model' => 'nullable|string|max:150',
            'os_version'   => 'nullable|string|max:80',
            'app_version'  => 'nullable|string|max:40',
        ]);

        $plainKey = Str::random(64);
        $hash = TrackingDevice::hashKey($plainKey);

        // Revoke any previous key for this user on this device
        TrackingDevice::where('user_id', $user->id)
            ->where('device_id', $request->device_id)
            ->update([
                'revoked_at' => now(),
                'revoked_reason' => 'Re-registered by user',
            ]);

        $device = TrackingDevice::create([
            'user_id'      => $user->id,
            'device_id'    => $request->device_id,
            'key_hash'     => $hash,
            'device_model' => $request->device_model,
            'os_version'   => $request->os_version,
            'app_version'  => $request->app_version,
            'last_seen_at' => now(),
        ]);

        $configData = $this->buildConfigPayload();

        return response()->json([
            'success'      => true,
            'tracking_key' => $plainKey,
            'engineer'     => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'config'       => $configData,
        ]);
    }

    /**
     * 2. Get telemetry & geofence configuration.
     * Authenticated via X-Tracking-Key.
     */
    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config'  => $this->buildConfigPayload(),
        ]);
    }

    /**
     * 3. Batch upload of raw location points and tracking events.
     * Authenticated via X-Tracking-Key.
     */
    public function batch(Request $request): JsonResponse
    {
        $user = $request->user();

        $points = $request->input('points', []);
        $events = $request->input('events', []);
        $deviceTime = $request->input('device_time');

        if (!is_array($points)) $points = [];
        if (!is_array($events)) $events = [];

        // Ingest points & events idempotently
        $result = $this->engine->ingestBatch($user, $points, $events, $deviceTime);

        $settings = TrackingSetting::allSettings();
        $configVersion = md5(json_encode($settings));

        return response()->json([
            'success'          => true,
            'accepted_points'  => $result['accepted_points'],
            'duplicate_points' => $result['duplicate_points'],
            'accepted_events'  => $result['accepted_events'],
            'config_version'   => $configVersion,
            'server_time'      => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * 4. My Day summary for the engineer app.
     * Authenticated via X-Tracking-Key.
     */
    public function myDay(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->input('date', Carbon::today()->toDateString());

        $attendance = AttendanceDay::with('geofence')
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        $summary = TrackingDailySummary::where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        $stopSegments = TripSegment::where('user_id', $user->id)
            ->where('date', $date)
            ->where('type', 'stop')
            ->orderBy('started_at', 'asc')
            ->get();

        $stopsList = $stopSegments->map(function ($s) {
            return [
                'start'   => Carbon::parse($s->started_at)->format('H:i'),
                'end'     => Carbon::parse($s->ended_at)->format('H:i'),
                'minutes' => (int)round($s->duration_sec / 60),
                'label'   => $s->label,
                'address' => $s->address,
                'kind'    => $s->kind,
            ];
        });

        $live = \App\Models\Tracking\EngineerLiveStatus::where('user_id', $user->id)->first();

        return response()->json([
            'success'    => true,
            'date'       => $date,
            'attendance' => [
                'first_in'     => $attendance && $attendance->effective_first_in ? Carbon::parse($attendance->effective_first_in)->format('H:i') : null,
                'last_out'     => $attendance && $attendance->effective_last_out ? Carbon::parse($attendance->effective_last_out)->format('H:i') : null,
                'status'       => $attendance ? $attendance->effective_status : 'absent',
                'office'       => $attendance && $attendance->geofence ? $attendance->geofence->name : 'Lahore Central Office',
                'still_inside' => $attendance ? (bool)$attendance->still_inside : false,
            ],
            'summary'    => [
                'distance_km'      => $summary ? round($summary->distance_m / 1000, 1) : 0.0,
                'drive_minutes'    => $summary ? (int)round($summary->drive_sec / 60) : 0,
                'stop_minutes'     => $summary ? (int)round($summary->stop_sec / 60) : 0,
                'stops_count'      => $summary ? $summary->stops_count : 0,
                'efficiency_score' => $summary ? $summary->efficiency_score : null,
                'ai_summary'       => $summary ? $summary->ai_summary : null,
                'last_sync'        => $live && $live->last_sync_at ? Carbon::parse($live->last_sync_at)->format('Y-m-d H:i') : null,
            ],
            'stops'      => $stopsList,
        ]);
    }

    /**
     * Build config payload for mobile app.
     */
    private function buildConfigPayload(): array
    {
        $settings = TrackingSetting::allSettings();

        $geofences = Geofence::active()->get()->map(function ($g) {
            return [
                'id'       => $g->id,
                'name'     => $g->name,
                'lat'      => (float)$g->lat,
                'lng'      => (float)$g->lng,
                'radius_m' => (int)$g->radius_m,
            ];
        });

        return [
            'tracking_enabled'        => (bool)($settings['tracking_enabled'] ?? true),
            'timezone'                => $settings['timezone'] ?? 'Asia/Karachi',
            'duty_start'              => $settings['duty_start'] ?? '08:00',
            'duty_end'                => $settings['duty_end'] ?? '20:00',
            'work_days'               => $settings['work_days'] ?? [1, 2, 3, 4, 5, 6],
            'moving_interval_sec'     => (int)($settings['moving_interval_sec'] ?? 10),
            'moving_distance_m'       => (int)($settings['moving_distance_m'] ?? 20),
            'stationary_interval_sec' => (int)($settings['stationary_interval_sec'] ?? 120),
            'max_accuracy_m'          => (int)($settings['max_accuracy_m'] ?? 50),
            'upload_interval_sec'     => (int)($settings['upload_interval_sec'] ?? 120),
            'heartbeat_interval_sec'  => (int)($settings['heartbeat_interval_sec'] ?? 900),
            'attendance_dwell_sec'    => (int)($settings['attendance_dwell_sec'] ?? 120),
            'geofences'               => $geofences,
            'server_time'             => Carbon::now()->toIso8601String(),
            'config_version'          => md5(json_encode($settings) . json_encode($geofences)),
        ];
    }
}
