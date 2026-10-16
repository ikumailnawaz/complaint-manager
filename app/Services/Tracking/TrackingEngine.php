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

            $recAt = Carbon::parse($p['recorded_at'])->setTimezone(config('app.timezone', 'Asia/Karachi'));
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

            $recAt = Carbon::parse($ev['recorded_at'])->setTimezone(config('app.timezone', 'Asia/Karachi'));
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
            // Also process immediate attendance & trips for this day
            $this->processAttendance($user->id, $d);
            $this->processUserDate($user->id, $d);
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
        $stopMinSec = max(120, ((int)($settings['stop_min_minutes'] ?? 2)) * 60);
        $stopExitRadiusM = (float)($settings['stop_exit_radius_m'] ?? 150);
        $maxAccuracyM = (float)($settings['max_accuracy_m'] ?? 150);

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
            if ($pt->lat == 0 && $pt->lng == 0) {
                continue; // drop invalid zero coordinates
            }
            if ($pt->lat < -90 || $pt->lat > 90 || $pt->lng < -180 || $pt->lng > 180) {
                continue; // drop out-of-bounds coordinates
            }
            // Allow up to 150m accuracy (required for offline GPS fixes on moving vehicles).
            // But if point has extreme accuracy (>150m), drop it.
            if ($pt->accuracy !== null && $pt->accuracy > $maxAccuracyM) {
                continue;
            }
            if ($lastPt) {
                $dt = Carbon::parse($pt->recorded_at)->diffInSeconds(Carbon::parse($lastPt->recorded_at));
                if ($dt > 0) {
                    $dist = self::haversine($lastPt->lat, $lastPt->lng, $pt->lat, $pt->lng);
                    $speedKmh = ($dist / $dt) * 3.6;
                    if ($speedKmh > 160) {
                        continue; // impossible teleport jump (>160 km/h)
                    }
                    // Filter indoor low-speed drift jumps: if almost stationary (< 1.5 km/h) and accuracy is low (> 80m), drop jump
                    if ($speedKmh < 1.5 && $pt->accuracy !== null && $pt->accuracy > 80 && $dist > 25) {
                        continue;
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

            // Check if anchor is inside any known Office / Workshop geofence
            $anchorGf = null;
            foreach ($geofences as $gf) {
                if (self::haversine($anchor->lat, $anchor->lng, $gf->lat, $gf->lng) <= ($gf->radius_m + 60)) {
                    $anchorGf = $gf;
                    break;
                }
            }

            while ($j < $n) {
                $candidate = $cleanPoints[$j];
                $distToAnchor = self::haversine($anchor->lat, $anchor->lng, $candidate->lat, $candidate->lng);

                // If anchor is within workshop/office, candidate is inside if within geofence radius + tolerance
                $isInsideGf = false;
                if ($anchorGf) {
                    $distToGf = self::haversine($candidate->lat, $candidate->lng, $anchorGf->lat, $anchorGf->lng);
                    if ($distToGf <= ($anchorGf->radius_m + 60)) {
                        $isInsideGf = true;
                    }
                }

                if ($distToAnchor <= $stopRadiusM || $isInsideGf) {
                    $cluster[] = $candidate;
                    $j++;
                } else {
                    // Check exit hysteresis: must be > exit radius for 3 consecutive points
                    $exitConfirmed = true;
                    for ($k = $j; $k < min($j + 3, $n); $k++) {
                        $kDist = self::haversine($anchor->lat, $anchor->lng, $cleanPoints[$k]->lat, $cleanPoints[$k]->lng);
                        $kInsideGf = $anchorGf && (self::haversine($cleanPoints[$k]->lat, $cleanPoints[$k]->lng, $anchorGf->lat, $anchorGf->lng) <= ($anchorGf->radius_m + 60));
                        if ($kDist <= $stopRadiusM || $kInsideGf) {
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
                    $driveSeg = $this->buildDriveSegment($movingBuffer, $date, $geofences);
                    if ($driveSeg) {
                        $segments[] = $driveSeg;
                    } elseif (!empty($segments)) {
                        // Jitter buffer rejected as a drive: extend previous stop if applicable
                        $lastIdx = count($segments) - 1;
                        if ($segments[$lastIdx]['type'] === 'stop') {
                            $lastBufPt = end($movingBuffer);
                            $newEnd = Carbon::parse($lastBufPt->recorded_at);
                            if ($newEnd->gt(Carbon::parse($segments[$lastIdx]['ended_at']))) {
                                $segments[$lastIdx]['ended_at'] = $newEnd;
                                $segments[$lastIdx]['duration_sec'] = $newEnd->diffInSeconds(Carbon::parse($segments[$lastIdx]['started_at']));
                                $segments[$lastIdx]['point_count'] += count($movingBuffer);
                            }
                        }
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

                // Check if this stop can be merged into immediately preceding stop (same location/geofence)
                $mergedWithPrev = false;
                if (!empty($segments)) {
                    $lastIdx = count($segments) - 1;
                    $prevSeg = &$segments[$lastIdx];
                    if ($prevSeg['type'] === 'stop') {
                        $distBetweenStops = self::haversine($prevSeg['lat'], $prevSeg['lng'], $cLat, $cLng);
                        // If same geofence or within 80m of previous stop
                        if (($prevSeg['geofence_id'] && $prevSeg['geofence_id'] === $labelInfo['geofence_id']) || $distBetweenStops <= 80) {
                            $prevSeg['ended_at'] = $lastTime;
                            $prevSeg['duration_sec'] = $lastTime->diffInSeconds(Carbon::parse($prevSeg['started_at']));
                            $prevSeg['point_count'] += count($cluster);
                            $mergedWithPrev = true;
                        }
                    }
                }

                if (!$mergedWithPrev) {
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
                }

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
            $driveSeg = $this->buildDriveSegment($movingBuffer, $date, $geofences);
            if ($driveSeg) {
                $segments[] = $driveSeg;
            } elseif (!empty($segments)) {
                $lastIdx = count($segments) - 1;
                if ($segments[$lastIdx]['type'] === 'stop') {
                    $lastBufPt = end($movingBuffer);
                    $newEnd = Carbon::parse($lastBufPt->recorded_at);
                    if ($newEnd->gt(Carbon::parse($segments[$lastIdx]['ended_at']))) {
                        $segments[$lastIdx]['ended_at'] = $newEnd;
                        $segments[$lastIdx]['duration_sec'] = $newEnd->diffInSeconds(Carbon::parse($segments[$lastIdx]['started_at']));
                        $segments[$lastIdx]['point_count'] += count($movingBuffer);
                    }
                }
            }
        }

        // Determine office sign-in and final office departure boundaries
        $office = Geofence::where('is_attendance_point', true)->where('is_active', true)->first();
        $firstOfficeIn = null;
        $lastOfficeOut = null;
        if ($office) {
            $officePoints = [];
            foreach ($cleanPoints as $pt) {
                if (self::haversine($pt->lat, $pt->lng, $office->lat, $office->lng) <= ($office->radius_m + 20)) {
                    $officePoints[] = $pt;
                }
            }
            if (!empty($officePoints)) {
                $firstOfficeIn = Carbon::parse($officePoints[0]->recorded_at);
                $lastOfficeOut = Carbon::parse($officePoints[count($officePoints) - 1]->recorded_at);
            }
        }

        // Save segments transactionally
        DB::transaction(function () use ($userId, $date, $segments, $cleanPoints, $mockCount, $firstOfficeIn, $lastOfficeOut) {
            $dateClean = Carbon::parse($date)->toDateString();
            TripSegment::where('user_id', $userId)
                ->where(function($q) use ($dateClean) {
                    $q->where('date', $dateClean)->orWhere('date', 'like', $dateClean . '%');
                })
                ->delete();

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
                $segStart = Carbon::parse($s['started_at']);
                $segEnd = Carbon::parse($s['ended_at']);

                // Strictly count km only AFTER first office arrival and BEFORE final office departure
                $isDutyTravel = true;
                if ($firstOfficeIn && $lastOfficeOut) {
                    if ($segEnd->lte($firstOfficeIn)) {
                        $isDutyTravel = false; // Commute before signing into office
                    } elseif ($segStart->gte($lastOfficeOut)) {
                        $isDutyTravel = false; // Commute after leaving office for the day
                    }
                }

                $s['user_id'] = $userId;
                $s['date'] = $dateClean;
                $s['seq'] = $idx + 1;
                TripSegment::create($s);

                if ($s['type'] === 'drive') {
                    if ($isDutyTravel) {
                        $totalDistM += $s['distance_m'];
                        $driveSec += $s['duration_sec'];
                    }
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
     * Rejects stationary indoor jitter and geofence dwell hopping.
     */
    private function buildDriveSegment(array $points, string $date, $geofences = []): ?array
    {
        if (count($points) < 2) {
            return null;
        }

        $firstPt = $points[0];
        $lastPt = $points[count($points) - 1];
        $startTime = Carbon::parse($firstPt->recorded_at);
        $endTime = Carbon::parse($lastPt->recorded_at);
        $durSec = $endTime->diffInSeconds($startTime);

        $totalDist = 0;
        $maxSpeed = 0;
        $speeds = [];
        $maxDisplacementFromStart = 0;

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

        // Compute maximum distance reached from the start of the buffer
        foreach ($points as $p) {
            $disp = self::haversine($firstPt->lat, $firstPt->lng, $p->lat, $p->lng);
            if ($disp > $maxDisplacementFromStart) {
                $maxDisplacementFromStart = $disp;
            }
        }

        $avgSpeed = !empty($speeds) ? array_sum($speeds) / count($speeds) : 0;

        // 1. Negligible duration and distance
        if ($durSec < 15 && $totalDist < 30) {
            return null;
        }

        // 2. Workshop / Office Geofence Dwell Containment:
        // If max displacement from start is < 250m, check if points are within a workshop/office geofence
        if ($maxDisplacementFromStart < 250 && !empty($geofences)) {
            foreach ($geofences as $gf) {
                $distStart = self::haversine($firstPt->lat, $firstPt->lng, $gf->lat, $gf->lng);
                $distEnd = self::haversine($lastPt->lat, $lastPt->lng, $gf->lat, $gf->lng);
                if ($distStart <= ($gf->radius_m + 80) && $distEnd <= ($gf->radius_m + 80)) {
                    // Entire movement is inside the workshop/office boundary: stationary dwell, not a drive
                    return null;
                }
            }
        }

        // 3. Stationary Jitter Drift:
        // Accumulated hops are large, but device never actually moved > 180m away from starting position
        // and average speed is low (< 10 km/h) or maximum speed is < 15 km/h
        if ($maxDisplacementFromStart < 180 && $avgSpeed < 10) {
            return null;
        }

        // 4. Minimum vehicular displacement check:
        // A real transit drive must either travel at vehicular speeds (> 15 km/h) or reach at least 250m from start
        if ($maxDisplacementFromStart < 250 && $maxSpeed < 15) {
            return null;
        }

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
                $gfName = $gf->name;
                if (!str_contains(strtolower($gfName), 'office') && !str_contains(strtolower($gfName), 'workshop') && !str_contains(strtolower($gfName), 'hub')) {
                    $gfName .= ' Office';
                }
                $proximity = $dist <= $gf->radius_m ? "Inside {$gfName}" : "Near {$gfName} Gate (" . round($dist) . 'm)';
                return [
                    'kind'        => 'office',
                    'label'       => $gfName,
                    'address'     => "{$proximity} • Attendance Geofence",
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

        // Primary office geofence resolution:
        $user = User::find($userId);
        $attendanceGeofences = Geofence::where('is_attendance_point', true)->where('is_active', true)->get();
        if ($attendanceGeofences->isEmpty()) {
            return;
        }

        // Try to match geofence by user base_city first
        $office = null;
        if ($user && $user->base_city) {
            $office = $attendanceGeofences->first(function ($gf) use ($user) {
                return (stripos($gf->city ?? '', $user->base_city) !== false)
                    || (stripos($gf->name ?? '', $user->base_city) !== false);
            });
        }

        // Fetch points of the day sorted chronologically
        $rawPoints = LocationPoint::where('user_id', $userId)
            ->whereBetween('recorded_at', [$startOfDay, $endOfDay])
            ->orderBy('recorded_at', 'asc')
            ->get();

        // If engineer has points today, check if they visited ANY attendance geofence
        if (!$office && $rawPoints->isNotEmpty()) {
            foreach ($attendanceGeofences as $gf) {
                $gfThreshold = max((int)$gf->radius_m + 60, 150);
                foreach ($rawPoints as $pt) {
                    if (self::haversine($pt->lat, $pt->lng, $gf->lat, $gf->lng) <= $gfThreshold) {
                        $office = $gf;
                        break 2;
                    }
                }
            }
        }

        $office = $office ?? $attendanceGeofences->first();

        $att = AttendanceDay::firstOrNew(['user_id' => $userId, 'date' => $date]);
        $att->geofence_id = $office->id;

        if ($rawPoints->isEmpty()) {
            $isWorkDay = in_array(Carbon::parse($date)->dayOfWeekIso, $settings['work_days'] ?? [1,2,3,4,5,6]);
            $att->status = $isWorkDay ? 'absent' : 'off_day';
            $att->save();
            return;
        }

        // Generous buffer: office radius + 60m buffer, min 150m for indoor building GPS degradation
        $officeRadiusThreshold = max((int)$office->radius_m + 60, 150);

        // Segment raw points into distinct visits
        $visits = [];
        $currentVisitStart = null;
        $lastInsidePt = null;
        $consecutiveOutside = 0;

        foreach ($rawPoints as $pt) {
            $dist = self::haversine($pt->lat, $pt->lng, $office->lat, $office->lng);
            $isPtInside = ($dist <= $officeRadiusThreshold);
            $ptTime = Carbon::parse($pt->recorded_at);

            if ($isPtInside) {
                $consecutiveOutside = 0;
                if ($currentVisitStart === null) {
                    $currentVisitStart = $ptTime;
                }
                $lastInsidePt = $pt;
            } else {
                if ($currentVisitStart !== null) {
                    $consecutiveOutside++;
                    // If 3 or more points outside AND moved > radius + 60m, close this office visit
                    if ($consecutiveOutside >= 3 && $dist > ($officeRadiusThreshold + 40)) {
                        $endTime = $lastInsidePt ? Carbon::parse($lastInsidePt->recorded_at) : $currentVisitStart;
                        $visits[] = [
                            'start' => $currentVisitStart,
                            'end'   => $endTime,
                            'minutes' => (int)max(1, $currentVisitStart->diffInMinutes($endTime)),
                        ];
                        $currentVisitStart = null;
                        $lastInsidePt = null;
                    }
                }
            }
        }

        // Check latest point of the day: is engineer currently inside and actively reporting?
        $lastTotalPt = $rawPoints[count($rawPoints) - 1];
        $lastPtDist = self::haversine($lastTotalPt->lat, $lastTotalPt->lng, $office->lat, $office->lng);
        $isLastPtInside = ($lastPtDist <= $officeRadiusThreshold);
        $isPointFresh = Carbon::parse($lastTotalPt->recorded_at)->diffInMinutes(now()) <= 15;
        $stillInside = $isLastPtInside && $isPointFresh;

        // If currently inside, close active ongoing visit
        if ($currentVisitStart !== null) {
            $endTime = $lastInsidePt ? Carbon::parse($lastInsidePt->recorded_at) : $currentVisitStart;
            $visits[] = [
                'start' => $currentVisitStart,
                'end'   => $endTime,
                'minutes' => (int)max(1, $currentVisitStart->diffInMinutes($endTime)),
            ];
        }

        // Also check explicit geofence_enter events from mobile
        $enterEventsCount = TrackingEvent::where('user_id', $userId)
            ->where('geofence_id', $office->id)
            ->where('type', 'geofence_enter')
            ->whereBetween('recorded_at', [$startOfDay, $endOfDay])
            ->count();

        if (!empty($visits)) {
            $firstIn = $visits[0]['start'];
            $lastOut = $visits[count($visits) - 1]['end'];

            $att->first_in = $firstIn;
            $att->last_out = $stillInside ? null : $lastOut;
            $att->still_inside = $stillInside;
            $att->office_visits = max(count($visits), $enterEventsCount, 1);

            // Sum up total minutes spent inside office across all visits
            $totalOfficeMinutes = 0;
            foreach ($visits as $idx => $v) {
                // If this is the last visit and still inside today, calculate up to now()
                if ($stillInside && $idx === (count($visits) - 1) && Carbon::parse($date)->isToday()) {
                    $totalOfficeMinutes += (int)max(1, $v['start']->diffInMinutes(now()));
                } else {
                    $totalOfficeMinutes += $v['minutes'];
                }
            }
            $att->office_minutes = $totalOfficeMinutes;

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
