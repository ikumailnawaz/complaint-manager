<?php

namespace App\Services\Tracking;

use App\Models\Ticket;
use App\Models\Tracking\AttendanceDay;
use App\Models\Tracking\EngineerLiveStatus;
use App\Models\Tracking\Geofence;
use App\Models\Tracking\LocationPoint;
use App\Models\Tracking\TrackingDailySummary;
use App\Models\Tracking\TrackingEvent;
use App\Models\Tracking\TrackingSetting;
use App\Models\Tracking\TripSegment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackingEngine
{
    /**
     * Great-circle distance between two coordinates in meters (Haversine formula).
     */
    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Ingest a batch of points and events from the mobile device.
     */
    public function ingestBatch(User $user, array $points, array $events, ?string $deviceTime = null): array
    {
        $now = Carbon::now();
        $acceptedPoints = 0;
        $duplicatePoints = 0;
        $acceptedEvents = 0;
        $affectedDates = [];

        // 1. Process location points
        $latestPoint = null;
        foreach ($points as $p) {
            $uuid = $p['uuid'] ?? null;
            if (!$uuid || !isset($p['lat'], $p['lng'], $p['recorded_at'])) {
                continue;
            }

            $recAt = Carbon::parse($p['recorded_at']);
            $dateStr = $recAt->toDateString();
            $affectedDates[$dateStr] = true;

            try {
                $created = LocationPoint::firstOrCreate(
                    ['uuid' => $uuid],
                    [
                        'user_id'     => $user->id,
                        'lat'         => (float)$p['lat'],
                        'lng'         => (float)$p['lng'],
                        'accuracy'    => isset($p['accuracy']) ? (float)$p['accuracy'] : null,
                        'altitude'    => isset($p['altitude']) ? (float)$p['altitude'] : null,
                        'speed'       => isset($p['speed']) ? (float)$p['speed'] : null,
                        'bearing'     => isset($p['bearing']) ? (float)$p['bearing'] : null,
                        'battery'     => isset($p['battery']) ? (int)$p['battery'] : null,
                        'is_charging' => !empty($p['is_charging']),
                        'is_mocked'   => !empty($p['is_mocked']),
                        'recorded_at' => $recAt,
                        'received_at' => $now,
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $acceptedPoints++;
                } else {
                    $duplicatePoints++;
                }

                if (!$latestPoint || $recAt->gt($latestPoint['recAt'])) {
                    $latestPoint = ['point' => $p, 'recAt' => $recAt];
                }
            } catch (\Throwable $e) {
                // Ignore unique constraint collision gracefully
                $duplicatePoints++;
            }
        }

        // 2. Process tracking events
        $latestEvent = null;
        foreach ($events as $ev) {
            $uuid = $ev['uuid'] ?? null;
            $type = $ev['type'] ?? null;
            if (!$uuid || !$type || !isset($ev['recorded_at'])) {
                continue;
            }

            $recAt = Carbon::parse($ev['recorded_at']);
            $affectedDates[$recAt->toDateString()] = true;

            try {
                $created = TrackingEvent::firstOrCreate(
                    ['uuid' => $uuid],
                    [
                        'user_id'     => $user->id,
                        'type'        => $type,
                        'geofence_id' => $ev['geofence_id'] ?? null,
                        'lat'         => isset($ev['lat']) ? (float)$ev['lat'] : null,
                        'lng'         => isset($ev['lng']) ? (float)$ev['lng'] : null,
                        'recorded_at' => $recAt,
                        'received_at' => $now,
                        'meta'        => $ev['meta'] ?? null,
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $acceptedEvents++;
                }

                if (!$latestEvent || $recAt->gt($latestEvent['recAt'])) {
                    $latestEvent = ['event' => $ev, 'recAt' => $recAt];
                }
            } catch (\Throwable $e) {
                // ignore duplicate
            }
        }

        // 3. Update Engineer Live Status
        $live = EngineerLiveStatus::firstOrNew(['user_id' => $user->id]);
        $live->last_sync_at = $now;

        if ($latestPoint) {
            $lp = $latestPoint['point'];
            if (!$live->recorded_at || $latestPoint['recAt']->gte($live->recorded_at)) {
                $live->lat         = (float)$lp['lat'];
                $live->lng         = (float)$lp['lng'];
                $live->accuracy    = isset($lp['accuracy']) ? (float)$lp['accuracy'] : $live->accuracy;
                $live->speed       = isset($lp['speed']) ? (float)$lp['speed'] : $live->speed;
                $live->bearing     = isset($lp['bearing']) ? (float)$lp['bearing'] : $live->bearing;
                $live->battery     = isset($lp['battery']) ? (int)$lp['battery'] : $live->battery;
                $live->is_charging = !empty($lp['is_charging']);
                $live->is_mocked   = !empty($lp['is_mocked']);
                $live->recorded_at = $latestPoint['recAt'];
            }
        }

        if ($latestEvent) {
            $le = $latestEvent['event'];
            $live->last_event_type = $le['type'];
            $live->last_event_at   = $latestEvent['recAt'];
            if ($le['type'] === 'logout') {
                $live->is_logged_out = true;
            } elseif ($le['type'] === 'login') {
                $live->is_logged_out = false;
            } elseif ($le['type'] === 'gps_off') {
                $live->gps_enabled = false;
            } elseif ($le['type'] === 'gps_on') {
                $live->gps_enabled = true;
            }
        }
        $live->save();

        // 4. Mark daily summaries as needing processing
        foreach (array_keys($affectedDates) as $d) {
            TrackingDailySummary::updateOrCreate(
                ['user_id' => $user->id, 'date' => $d],
                ['needs_processing' => true]
            );
            // Also process immediate trips & attendance for this day
            $this->processUserDate($user->id, $d);
            $this->processAttendance($user->id, $d);
        }

        return [
            'accepted_points' => $acceptedPoints,
            'duplicate_points' => $duplicatePoints,
            'accepted_events' => $acceptedEvents,
        ];
    }

    /**
     * Process trips & stay-points for a specific user and date.
     */
    public function processUserDate(int $userId, string $date): void
    {
        $settings = TrackingSetting::allSettings();
        $stopRadiusM = (float)($settings['stop_radius_m'] ?? 80);
        $stopMinSec = ((int)($settings['stop_min_minutes'] ?? 5)) * 60;
        $stopExitRadiusM = (float)($settings['stop_exit_radius_m'] ?? 150);
        $maxAccuracyM = (float)($settings['max_accuracy_m'] ?? 50);

        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        // Retrieve raw points sorted by recorded_at
        $rawPoints = LocationPoint::where('user_id', $userId)
            ->whereBetween('recorded_at', [$startOfDay, $endOfDay])
            ->orderBy('recorded_at', 'asc')
            ->get();

        if ($rawPoints->isEmpty()) {
            return;
        }

        // Noise filtering
        $cleanPoints = [];
        $lastPt = null;
        $mockCount = 0;
        foreach ($rawPoints as $pt) {
            if ($pt->is_mocked) {
                $mockCount++;
            }
            if ($pt->accuracy !== null && $pt->accuracy > $maxAccuracyM) {
                continue; // drop inaccurate jump
            }
            if ($lastPt) {
                $dt = Carbon::parse($pt->recorded_at)->diffInSeconds(Carbon::parse($lastPt->recorded_at));
                if ($dt > 0) {
                    $dist = self::haversine($lastPt->lat, $lastPt->lng, $pt->lat, $pt->lng);
                    $speedKmh = ($dist / $dt) * 3.6;
                    if ($speedKmh > 160) {
                        continue; // impossible teleport jump (>160 km/h)
                    }
                }
            }
            $cleanPoints[] = $pt;
            $lastPt = $pt;
        }

        if (empty($cleanPoints)) {
            return;
        }

        // Load active geofences & tickets for labelling
        $geofences = Geofence::active()->get();
        $tickets = Ticket::where(function ($q) use ($userId) {
                $q->where('assigned_engineer_id', $userId)
                  ->orWhere('workshop_engineer_id', $userId);
            })
            ->whereDate('created_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('closed_at')->orWhereDate('closed_at', '>=', $date);
            })
            ->get();

        // Stay-Point Detection Algorithm
        $segments = [];
        $n = count($cleanPoints);
        $i = 0;

        $movingBuffer = [];

        while ($i < $n) {
            $j = $i + 1;
            $anchor = $cleanPoints[$i];
            $cluster = [$anchor];

            while ($j < $n) {
                $candidate = $cleanPoints[$j];
                $distToAnchor = self::haversine($anchor->lat, $anchor->lng, $candidate->lat, $candidate->lng);

                if ($distToAnchor <= $stopRadiusM) {
                    $cluster[] = $candidate;
                    $j++;
                } else {
                    // Check exit hysteresis: must be > exit radius for 3 consecutive points
                    $exitConfirmed = true;
                    for ($k = $j; $k < min($j + 3, $n); $k++) {
                        if (self::haversine($anchor->lat, $anchor->lng, $cleanPoints[$k]->lat, $cleanPoints[$k]->lng) <= $stopRadiusM) {
                            $exitConfirmed = false;
                            break;
                        }
                    }
                    if ($exitConfirmed) {
                        break;
                    } else {
                        $cluster[] = $candidate;
                        $j++;
                    }
                }
            }

            $firstTime = Carbon::parse($cluster[0]->recorded_at);
            $lastTime = Carbon::parse($cluster[count($cluster) - 1]->recorded_at);
            $clusterDuration = $lastTime->diffInSeconds($firstTime);

            if ($clusterDuration >= $stopMinSec) {
                // We have a confirmed STOP
                // First, flush any preceding moving buffer as a DRIVE
                if (!empty($movingBuffer)) {
                    $driveSeg = $this->buildDriveSegment($movingBuffer, $date);
                    if ($driveSeg) {
                        $segments[] = $driveSeg;
                    }
                    $movingBuffer = [];
                }

                // Compute centroid of the stop
                $latSum = 0;
                $lngSum = 0;
                foreach ($cluster as $c) {
                    $latSum += $c->lat;
                    $lngSum += $c->lng;
                }
                $cLat = $latSum / count($cluster);
                $cLng = $lngSum / count($cluster);

                // Label the stop
                $labelInfo = $this->labelStop($cLat, $cLng, $geofences, $tickets);

                $segments[] = [
                    'type'         => 'stop',
                    'started_at'   => $firstTime,
                    'ended_at'     => $lastTime,
                    'duration_sec' => $clusterDuration,
                    'distance_m'   => 0,
                    'lat'          => $cLat,
                    'lng'          => $cLng,
                    'end_lat'      => $cLat,
                    'end_lng'      => $cLng,
                    'kind'         => $labelInfo['kind'],
                    'label'        => $labelInfo['label'],
                    'address'      => $labelInfo['address'],
                    'ticket_id'    => $labelInfo['ticket_id'],
                    'geofence_id'  => $labelInfo['geofence_id'],
                    'point_count'  => count($cluster),
                    'avg_speed_kmh'=> 0,
                    'max_speed_kmh'=> 0,
                ];

                // Advance i to j
                $i = $j;
            } else {
                // Points were not a stationary stop; append to movingBuffer
                $movingBuffer[] = $cleanPoints[$i];
                $i++;
            }
        }

        // Flush remaining moving points
        if (!empty($movingBuffer)) {
            $driveSeg = $this->buildDriveSegment($movingBuffer, $date);
            if ($driveSeg) {
                $segments[] = $driveSeg;
            }
        }

        // Save segments transactionally
        DB::transaction(function () use ($userId, $date, $segments, $cleanPoints, $mockCount) {
            TripSegment::where('user_id', $userId)->where('date', $date)->delete();

            $totalDistM = 0;
            $driveSec = 0;
            $stopSec = 0;
            $officeSec = 0;
            $siteSec = 0;
            $unknownSec = 0;
            $longUnknownSec = 0;
            $stopsCount = 0;
            $maxSpeed = 0;

            foreach ($segments as $idx => $s) {
                $s['user_id'] = $userId;
                $s['date'] = $date;
                $s['seq'] = $idx + 1;
                TripSegment::create($s);

                if ($s['type'] === 'drive') {
                    $totalDistM += $s['distance_m'];
                    $driveSec += $s['duration_sec'];
                    if (($s['max_speed_kmh'] ?? 0) > $maxSpeed) {
                        $maxSpeed = $s['max_speed_kmh'];
                    }
                } elseif ($s['type'] === 'stop') {
                    $stopsCount++;
                    $stopSec += $s['duration_sec'];
                    if ($s['kind'] === 'office') {
                        $officeSec += $s['duration_sec'];
                    } elseif ($s['kind'] === 'ticket_site') {
                        $siteSec += $s['duration_sec'];
                    } else {
                        $unknownSec += $s['duration_sec'];
                        if ($s['duration_sec'] >= 1800) { // >= 30 min unknown stop
                            $longUnknownSec += $s['duration_sec'];
                        }
                    }
                }
            }

            // Calculate Efficiency Score (0-100)
            // Productive time: Ticket sites + reasonable transit.
            // Deduct points for excessive unknown stops.
            $productiveSec = $siteSec + min($driveSec, 4 * 3600);
            $totalActiveSec = $driveSec + $stopSec;
            $efficiencyScore = 100;

            if ($totalActiveSec > 3600) {
                $unknownRatio = $unknownSec / $totalActiveSec;
                if ($unknownRatio > 0.5) {
                    $efficiencyScore = max(30, (int)round((1 - $unknownRatio) * 100));
                } elseif ($unknownRatio > 0.25) {
                    $efficiencyScore = max(60, (int)round((1 - ($unknownRatio * 0.8)) * 100));
                }
                // Penalty for very long unknown stops
                if ($longUnknownSec > 3600) {
                    $efficiencyScore = max(20, $efficiencyScore - 20);
                }
            } elseif ($totalActiveSec < 1800) {
                $efficiencyScore = 50; // low tracking data
            }

            TrackingDailySummary::updateOrCreate(
                ['user_id' => $userId, 'date' => $date],
                [
                    'distance_m'            => (int)$totalDistM,
                    'drive_sec'             => (int)$driveSec,
                    'stop_sec'              => (int)$stopSec,
                    'office_sec'            => (int)$officeSec,
                    'site_sec'              => (int)$siteSec,
                    'unknown_stop_sec'      => (int)$unknownSec,
                    'long_unknown_stop_sec' => (int)$longUnknownSec,
                    'gap_sec'               => 0,
                    'stops_count'           => $stopsCount,
                    'points_count'          => count($cleanPoints),
                    'mocked_points'         => $mockCount,
                    'first_point_at'        => $cleanPoints[0]->recorded_at,
                    'last_point_at'         => $cleanPoints[count($cleanPoints) - 1]->recorded_at,
                    'max_speed_kmh'         => round($maxSpeed, 1),
                    'efficiency_score'      => $efficiencyScore,
                    'needs_processing'      => false,
                    'processed_at'          => now(),
                ]
            );
        });
    }

    /**
     * Build drive segment from moving points buffer.
     */
    private function buildDriveSegment(array $points, string $date): ?array
    {
        if (count($points) < 2) {
            return null;
        }

        $totalDist = 0;
        $maxSpeed = 0;
        $speeds = [];

        for ($k = 0; $k < count($points) - 1; $k++) {
            $p1 = $points[$k];
            $p2 = $points[$k + 1];
            $d = self::haversine($p1->lat, $p1->lng, $p2->lat, $p2->lng);
            $dt = Carbon::parse($p2->recorded_at)->diffInSeconds(Carbon::parse($p1->recorded_at));

            if ($dt > 0) {
                $speedKmh = ($d / $dt) * 3.6;
                if ($speedKmh < 150) {
                    $totalDist += $d;
                    $speeds[] = $speedKmh;
                    if ($speedKmh > $maxSpeed) {
                        $maxSpeed = $speedKmh;
                    }
                }
            }
        }

        $firstPt = $points[0];
        $lastPt = $points[count($points) - 1];
        $startTime = Carbon::parse($firstPt->recorded_at);
        $endTime = Carbon::parse($lastPt->recorded_at);
        $durSec = $endTime->diffInSeconds($startTime);

        if ($durSec < 15 && $totalDist < 30) {
            return null; // negligible noise
        }

        $avgSpeed = !empty($speeds) ? array_sum($speeds) / count($speeds) : 0;

        return [
            'type'         => 'drive',
            'started_at'   => $startTime,
            'ended_at'     => $endTime,
            'duration_sec' => $durSec,
            'distance_m'   => (int)round($totalDist),
            'lat'          => $firstPt->lat,
            'lng'          => $firstPt->lng,
            'end_lat'      => $lastPt->lat,
            'end_lng'      => $lastPt->lng,
            'kind'         => 'travel',
            'label'        => 'Transit (' . round($totalDist / 1000, 1) . ' km)',
            'address'      => null,
            'ticket_id'    => null,
            'geofence_id'  => null,
            'point_count'  => count($points),
            'avg_speed_kmh'=> round($avgSpeed, 1),
            'max_speed_kmh'=> round($maxSpeed, 1),
        ];
    }

    /**
     * Label a stop as office, ticket site, or unknown.
     */
    private function labelStop(float $lat, float $lng, $geofences, $tickets): array
    {
        // 1. Check geofences (Offices / Central Workshop)
        foreach ($geofences as $gf) {
            $dist = self::haversine($lat, $lng, $gf->lat, $gf->lng);
            if ($dist <= ($gf->radius_m + 30)) {
                return [
                    'kind'        => 'office',
                    'label'       => $gf->name,
                    'address'     => "Near {$gf->name} (within " . round($dist) . 'm)',
                    'ticket_id'   => null,
                    'geofence_id' => $gf->id,
                ];
            }
        }

        // 2. Check nearby tickets (unresolved complaints for this engineer within 2.5km via Gemini)
        if ($tickets && count($tickets) > 0) {
            try {
                $aiService = app(TrackingAiService::class);
                $match = $aiService->matchStopToUnresolvedComplaints($lat, $lng, $tickets, 2500);
                if ($match) {
                    return [
                        'kind'        => 'ticket_site',
                        'label'       => $match['label'],
                        'address'     => $match['address'],
                        'ticket_id'   => $match['ticket']->id,
                        'geofence_id' => null,
                    ];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Error matching tickets in labelStop: ' . $e->getMessage());
            }
        }

        return [
            'kind'        => 'unknown',
            'label'       => 'Stop at ' . round($lat, 4) . ', ' . round($lng, 4),
            'address'     => 'Unplanned Stop (' . round($lat, 4) . ', ' . round($lng, 4) . ')',
            'ticket_id'   => null,
            'geofence_id' => null,
        ];
    }

    /**
     * Calculate automated attendance for an engineer on a given date.
     */
    public function processAttendance(int $userId, string $date): void
    {
        $settings = TrackingSetting::allSettings();
        $lateTimeStr = $settings['attendance_late_time'] ?? '09:30';

        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();
        $lateCutoff = Carbon::parse("{$date} {$lateTimeStr}:00");

        // Primary office geofence
        $office = Geofence::where('is_attendance_point', true)->where('is_active', true)->first();
        if (!$office) {
            return;
        }

        // Fetch points of the day that are within the office geofence
        $rawPoints = LocationPoint::where('user_id', $userId)
            ->whereBetween('recorded_at', [$startOfDay, $endOfDay])
            ->orderBy('recorded_at', 'asc')
            ->get();

        $insidePoints = [];
        foreach ($rawPoints as $pt) {
            if (self::haversine($pt->lat, $pt->lng, $office->lat, $office->lng) <= ($office->radius_m + 20)) {
                $insidePoints[] = $pt;
            }
        }

        // Check if manual record exists
        $att = AttendanceDay::firstOrNew(['user_id' => $userId, 'date' => $date]);
        $att->geofence_id = $office->id;

        if (!empty($insidePoints)) {
            $firstIn = Carbon::parse($insidePoints[0]->recorded_at);
            $lastOut = Carbon::parse($insidePoints[count($insidePoints) - 1]->recorded_at);

            $lastTotalPt = $rawPoints[count($rawPoints) - 1];
            $stillInside = (self::haversine($lastTotalPt->lat, $lastTotalPt->lng, $office->lat, $office->lng) <= ($office->radius_m + 20));

            $att->first_in = $firstIn;
            $att->last_out = $stillInside ? null : $lastOut;
            $att->still_inside = $stillInside;
            $att->office_visits = max(1, $att->office_visits);

            // Determine status
            if ($firstIn->lte($lateCutoff)) {
                $att->status = 'present';
                $att->late_minutes = 0;
            } else {
                $att->status = 'late';
                $att->late_minutes = (int)$firstIn->diffInMinutes($lateCutoff);
            }
        } else {
            // Did engineer do field work?
            $hasFieldWork = TripSegment::where('user_id', $userId)
                ->where('date', $date)
                ->where('distance_m', '>', 3000)
                ->exists();

            if ($hasFieldWork) {
                $att->status = 'field';
            } else {
                $isWorkDay = in_array(Carbon::parse($date)->dayOfWeekIso, $settings['work_days'] ?? [1,2,3,4,5,6]);
                $att->status = $isWorkDay ? 'absent' : 'off_day';
            }
        }

        $att->save();
    }
}
