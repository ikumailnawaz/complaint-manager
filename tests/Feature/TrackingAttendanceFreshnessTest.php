<?php

namespace Tests\Feature;

use App\Models\Tracking\Geofence;
use App\Models\Tracking\LocationPoint;
use App\Models\Tracking\AttendanceDay;
use App\Models\User;
use App\Services\Tracking\TrackingEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingAttendanceFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_engineer_does_not_stay_marked_still_inside_office()
    {
        $engineer = User::factory()->create([
            'role' => 'field_engineer',
            'base_city' => 'Lahore',
        ]);

        $office = Geofence::create([
            'name' => 'Lahore Office',
            'city' => 'Lahore',
            'lat' => 31.5397357,
            'lng' => 74.3306892,
            'radius_m' => 75,
            'is_attendance_point' => true,
            'is_active' => true,
        ]);

        $today = Carbon::today()->toDateString();

        // Arrived at 09:00 AM
        LocationPoint::create([
            'uuid' => (string)\Illuminate\Support\Str::uuid(),
            'user_id' => $engineer->id,
            'lat' => 31.5397357,
            'lng' => 74.3306892,
            'accuracy' => 15,
            'recorded_at' => Carbon::parse("{$today} 09:00:00"),
            'received_at' => Carbon::parse("{$today} 09:00:05"),
        ]);

        // Last point was at 09:45 AM (more than 15 minutes ago from now)
        LocationPoint::create([
            'uuid' => (string)\Illuminate\Support\Str::uuid(),
            'user_id' => $engineer->id,
            'lat' => 31.5397357,
            'lng' => 74.3306892,
            'accuracy' => 15,
            'recorded_at' => Carbon::parse("{$today} 09:45:00"),
            'received_at' => Carbon::parse("{$today} 09:45:05"),
        ]);

        $engine = new TrackingEngine();
        $engine->processAttendance($engineer->id, $today);

        $att = AttendanceDay::where('user_id', $engineer->id)->where('date', $today)->first();

        $this->assertNotNull($att);
        // Because the point is > 15 minutes old relative to now, still_inside must be FALSE
        $this->assertFalse((bool)$att->still_inside);
        $this->assertNotNull($att->last_out);
        $this->assertEquals('09:45:00', Carbon::parse($att->last_out)->format('H:i:s'));
        $this->assertEquals(45, $att->office_minutes);
    }
}
