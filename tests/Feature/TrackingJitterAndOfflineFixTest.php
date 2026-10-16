<?php

namespace Tests\Feature;

use App\Models\Tracking\Geofence;
use App\Models\Tracking\LocationPoint;
use App\Models\Tracking\TripSegment;
use App\Models\User;
use App\Services\Tracking\TrackingEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackingJitterAndOfflineFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $engineer;
    protected Geofence $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engineer = User::factory()->create([
            'role' => 'field_engineer',
            'name' => 'sheryar',
            'email' => 'sheryar@banksupport.com',
            'base_city' => 'Lahore',
        ]);

        $this->workshop = Geofence::create([
            'name' => 'Lahore Central Workshop',
            'lat' => 31.5397357,
            'lng' => 74.3306892,
            'radius_m' => 75,
            'is_attendance_point' => true,
            'is_active' => true,
        ]);
    }

    public function test_workshop_indoor_jitter_does_not_create_false_transit_trip()
    {
        $date = '2026-10-16';
        $startTime = Carbon::parse("{$date} 11:54:00");

        // Simulate 20 minutes of indoor GPS multipath jitter inside workshop:
        // Hopping around within 30-50m of workshop center every 15 seconds.
        // Total accumulated hop distance would be ~3.5km, but device never left the workshop!
        for ($i = 0; $i < 60; $i++) {
            $offsetLat = (sin($i) * 0.0003); // ~33m hop
            $offsetLng = (cos($i) * 0.0003); // ~30m hop
            LocationPoint::create([
                'uuid' => (string)Str::uuid(),
                'user_id' => $this->engineer->id,
                'lat' => $this->workshop->lat + $offsetLat,
                'lng' => $this->workshop->lng + $offsetLng,
                'accuracy' => 45.0,
                'speed' => 0.2, // indoor stationary sensor speed
                'recorded_at' => $startTime->copy()->addSeconds($i * 15),
                'received_at' => $startTime->copy()->addSeconds($i * 15),
            ]);
        }

        $engine = app(TrackingEngine::class);
        $engine->processUserDate($this->engineer->id, $date);

        // Verify NO false transit drive segment was created
        $driveSegments = TripSegment::where('user_id', $this->engineer->id)
            ->where('date', $date)
            ->where('type', 'drive')
            ->get();

        $this->assertCount(0, $driveSegments, 'Indoor workshop GPS jitter must NOT produce any drive segment.');

        // Verify stay point was created and correctly identified as workshop
        $stopSegments = TripSegment::where('user_id', $this->engineer->id)
            ->where('date', $date)
            ->where('type', 'stop')
            ->get();

        $this->assertGreaterThanOrEqual(1, $stopSegments->count());
        $this->assertEquals('office', $stopSegments->first()->kind);
    }

    public function test_offline_transit_points_with_standard_accuracy_are_not_dropped()
    {
        $date = '2026-10-16';
        $startTime = Carbon::parse("{$date} 09:19:00");

        // Simulate 6-minute real transit drive (Trip #1) moving 3 km away from workshop.
        // Several points have offline accuracy between 55m and 120m.
        for ($i = 0; $i <= 10; $i++) {
            $lat = $this->workshop->lat + ($i * 0.0025); // ~270m per step, total ~2.7km
            $lng = $this->workshop->lng + ($i * 0.0025);
            LocationPoint::create([
                'uuid' => (string)Str::uuid(),
                'user_id' => $this->engineer->id,
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => 75.0, // > 50m accuracy (offline GPS)
                'speed' => 8.5,     // ~30 km/h vehicular speed
                'recorded_at' => $startTime->copy()->addSeconds($i * 30),
                'received_at' => $startTime->copy()->addSeconds($i * 30),
            ]);
        }

        $engine = app(TrackingEngine::class);
        $engine->processUserDate($this->engineer->id, $date);

        $driveSegments = TripSegment::where('user_id', $this->engineer->id)
            ->where('date', $date)
            ->where('type', 'drive')
            ->get();

        $this->assertCount(1, $driveSegments, 'Valid transit drive with offline GPS accuracy must be preserved.');
        $this->assertGreaterThan(2000, $driveSegments->first()->distance_m);
    }
}
