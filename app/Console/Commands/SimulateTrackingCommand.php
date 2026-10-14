<?php

namespace App\Console\Commands;

use App\Models\Tracking\Geofence;
use App\Models\Tracking\LocationPoint;
use App\Models\Tracking\TrackingDailySummary;
use App\Models\Tracking\TrackingDevice;
use App\Models\Tracking\TrackingEvent;
use App\Models\Tracking\TripSegment;
use App\Models\User;
use App\Services\Tracking\TrackingAiService;
use App\Services\Tracking\TrackingEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SimulateTrackingCommand extends Command
{
    protected $signature = 'tracking:simulate 
                            {--engineer= : User ID or email of engineer (default: first engineer)}
                            {--date= : Date in YYYY-MM-DD format (default: today)}
                            {--clear : Clear existing tracking data for this user/date first}';

    protected $description = 'Simulate realistic GPS tracking route with drives, branch stops and office attendance in Lahore';

    public function handle(TrackingEngine $engine, TrackingAiService $aiService): int
    {
        $dateStr = $this->option('date') ?: Carbon::today()->toDateString();
        $userQuery = User::query();

        if ($this->option('engineer')) {
            $val = $this->option('engineer');
            $userQuery->where(fn($q) => $q->where('id', $val)->orWhere('email', $val)->orWhere('name', 'like', "%{$val}%"));
        } else {
            $userQuery->where('role', 'engineer');
        }

        $user = $userQuery->first();
        if (!$user) {
            $this->error('No engineer found to simulate tracking.');
            return 1;
        }

        $this->info("Simulating realistic tracking day for: {$user->name} (#{$user->id}) on {$dateStr}");

        if ($this->option('clear')) {
            $start = Carbon::parse($dateStr)->startOfDay();
            $end = Carbon::parse($dateStr)->endOfDay();
            LocationPoint::where('user_id', $user->id)->whereBetween('recorded_at', [$start, $end])->delete();
            TrackingEvent::where('user_id', $user->id)->whereBetween('recorded_at', [$start, $end])->delete();
            TripSegment::where('user_id', $user->id)->where('date', $dateStr)->delete();
            TrackingDailySummary::where('user_id', $user->id)->where('date', $dateStr)->delete();
            $this->warn("Cleared previous data for {$dateStr}.");
        }

        // Setup tracking device if not exists
        TrackingDevice::firstOrCreate(
            ['user_id' => $user->id, 'device_id' => 'simulated-dev-' . $user->id],
            [
                'key_hash' => hash('sha256', 'simulated_key_' . $user->id),
                'device_model' => 'Simulation Studio Device (Lahore)',
                'os_version' => 'Android 14',
                'app_version' => '1.0.0',
                'last_seen_at' => now(),
            ]
        );

        // Office Geofence (Lahore Central Office)
        $office = Geofence::firstOrCreate(
            ['name' => 'Lahore Office'],
            ['lat' => 31.5397357, 'lng' => 74.3306892, 'radius_m' => 75, 'is_attendance_point' => true, 'is_active' => true]
        );

        // Waypoint itinerary for the day:
        // 1. 08:48 AM: Office Arrival
        // 2. 09:30 AM: Ride to UBL Mall Road
        // 3. 10:05 - 11:20 AM: Stop UBL Mall Road
        // 4. 11:20 - 11:45 AM: Ride to HBL Main Boulevard Gulberg
        // 5. 11:45 - 12:45 PM: Stop HBL Gulberg
        // 6. 12:45 - 01:00 PM: Ride to Restaurant
        // 7. 01:00 - 01:45 PM: Unplanned Lunch Stop (45 min)
        // 8. 01:45 - 02:20 PM: Ride to MCB Model Town
        // 9. 02:20 - 03:30 PM: Stop MCB Model Town
        // 10. 03:30 - 04:15 PM: Ride back to Office
        // 11. 04:15 - 05:45 PM: Office stay then departure

        $itinerary = [
            // Stop 1: Office Check-in
            ['type' => 'stop', 'lat' => 31.5397357, 'lng' => 74.3306892, 'start' => '08:48', 'end' => '09:30', 'label' => 'Lahore Office'],
            // Drive 1: Office -> Mall Road
            ['type' => 'drive', 'from' => [31.5397357, 74.3306892], 'to' => [31.5580, 74.3260], 'start' => '09:30', 'end' => '10:05'],
            // Stop 2: UBL Mall Road
            ['type' => 'stop', 'lat' => 31.5580, 'lng' => 74.3260, 'start' => '10:05', 'end' => '11:20', 'label' => 'UBL Mall Road Branch'],
            // Drive 2: Mall Road -> Gulberg
            ['type' => 'drive', 'from' => [31.5580, 74.3260], 'to' => [31.5180, 74.3480], 'start' => '11:20', 'end' => '11:45'],
            // Stop 3: HBL Gulberg
            ['type' => 'stop', 'lat' => 31.5180, 'lng' => 74.3480, 'start' => '11:45', 'end' => '12:45', 'label' => 'HBL Gulberg Main Boulevard'],
            // Drive 3: Gulberg -> Lunch Spot
            ['type' => 'drive', 'from' => [31.5180, 74.3480], 'to' => [31.5050, 74.3320], 'start' => '12:45', 'end' => '13:00'],
            // Stop 4: Lunch (Unplanned)
            ['type' => 'stop', 'lat' => 31.5050, 'lng' => 74.3320, 'start' => '13:00', 'end' => '13:45', 'label' => 'Lunch Break (Liberty Roundabout)'],
            // Drive 4: Lunch -> Model Town
            ['type' => 'drive', 'from' => [31.5050, 74.3320], 'to' => [31.4820, 74.3210], 'start' => '13:45', 'end' => '14:20'],
            // Stop 5: MCB Model Town
            ['type' => 'stop', 'lat' => 31.4820, 'lng' => 74.3210, 'start' => '14:20', 'end' => '15:30', 'label' => 'MCB Bank Model Town Branch'],
            // Drive 5: Model Town -> Office
            ['type' => 'drive', 'from' => [31.4820, 74.3210], 'to' => [31.5397357, 74.3306892], 'start' => '15:30', 'end' => '16:15'],
            // Stop 6: Office End of Day
            ['type' => 'stop', 'lat' => 31.5397357, 'lng' => 74.3306892, 'start' => '16:15', 'end' => '17:45', 'label' => 'Lahore Office (End of Day)'],
        ];

        $points = [];
        $events = [];

        // Geofence Enter Event at 08:48
        $events[] = [
            'uuid' => (string)Str::uuid(),
            'type' => 'geofence_enter',
            'geofence_id' => $office->id,
            'lat' => $office->lat,
            'lng' => $office->lng,
            'recorded_at' => Carbon::parse("{$dateStr} 08:48:00")->toIso8601String(),
        ];

        foreach ($itinerary as $step) {
            $t1 = Carbon::parse("{$dateStr} {$step['start']}:00");
            $t2 = Carbon::parse("{$dateStr} {$step['end']}:00");

            if ($step['type'] === 'stop') {
                // Generate stationary points with realistic indoor GPS jitter (2-12m)
                $cur = $t1->copy();
                while ($cur->lte($t2)) {
                    $jitterLat = (mt_rand(-12, 12) / 111111);
                    $jitterLng = (mt_rand(-12, 12) / (111111 * cos(deg2rad($step['lat']))));
                    $points[] = [
                        'uuid' => (string)Str::uuid(),
                        'lat' => $step['lat'] + $jitterLat,
                        'lng' => $step['lng'] + $jitterLng,
                        'accuracy' => (float)mt_rand(6, 18),
                        'speed' => 0.0,
                        'bearing' => 0.0,
                        'battery' => mt_rand(50, 95),
                        'is_charging' => false,
                        'is_mocked' => false,
                        'recorded_at' => $cur->toIso8601String(),
                    ];
                    $cur->addSeconds(120); // 2 min intervals when stationary
                }
            } elseif ($step['type'] === 'drive') {
                // Generate moving points along straight-line interpolation with curvature
                $durSec = $t2->diffInSeconds($t1);
                $stepSec = 15; // 15 sec intervals while moving
                $totalSteps = max(1, (int)floor($durSec / $stepSec));

                $fromLat = $step['from'][0];
                $fromLng = $step['from'][1];
                $toLat = $step['to'][0];
                $toLng = $step['to'][1];

                for ($s = 0; $s <= $totalSteps; $s++) {
                    $fraction = $s / $totalSteps;
                    // Add road deviation wobble
                    $wobble = sin($fraction * M_PI) * 0.003;
                    $lat = $fromLat + ($toLat - $fromLat) * $fraction + $wobble;
                    $lng = $fromLng + ($toLng - $fromLng) * $fraction;
                    $curTime = $t1->copy()->addSeconds($s * $stepSec);

                    $points[] = [
                        'uuid' => (string)Str::uuid(),
                        'lat' => $lat,
                        'lng' => $lng,
                        'accuracy' => (float)mt_rand(4, 12),
                        'speed' => (float)mt_rand(25, 45) / 3.6, // 25-45 km/h in m/s
                        'bearing' => (float)mt_rand(0, 359),
                        'battery' => mt_rand(50, 95),
                        'is_charging' => false,
                        'is_mocked' => false,
                        'recorded_at' => $curTime->toIso8601String(),
                    ];
                }
            }
        }

        // Geofence Exit Event at 17:45
        $events[] = [
            'uuid' => (string)Str::uuid(),
            'type' => 'geofence_exit',
            'geofence_id' => $office->id,
            'lat' => $office->lat,
            'lng' => $office->lng,
            'recorded_at' => Carbon::parse("{$dateStr} 17:45:00")->toIso8601String(),
        ];

        $this->info("Ingesting " . count($points) . " telemetry points and " . count($events) . " events...");
        $res = $engine->ingestBatch($user, $points, $events, now()->toIso8601String());

        $this->info("Accepted points: {$res['accepted_points']} | Events: {$res['accepted_events']}");

        // Trigger AI summary
        $this->info("Generating Gemini AI daily narrative summary...");
        $aiText = $aiService->generateDailySummary($user, $dateStr);
        $this->line("\n<fg=cyan>--- Gemini AI Daily Appraisal ---</>");
        $this->line($aiText);
        $this->line("<fg=cyan>---------------------------------</>\n");

        $summary = TrackingDailySummary::where('user_id', $user->id)->where('date', $dateStr)->first();
        if ($summary) {
            $distKm = round($summary->distance_m / 1000, 1);
            $driveMins = round($summary->drive_sec / 60);
            $this->info("Simulation Success! Total Distance: {$distKm} km | Drive Time: {$driveMins}m | Stops: {$summary->stops_count} | Efficiency Score: {$summary->efficiency_score}%");
        }

        return 0;
    }
}
