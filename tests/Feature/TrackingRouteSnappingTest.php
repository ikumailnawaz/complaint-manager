<?php

namespace Tests\Feature;

use App\Models\Tracking\LocationPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackingRouteSnappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $engineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->engineer = User::factory()->create([
            'role' => 'field_engineer',
            'name' => 'Zain',
            'base_city' => 'Lahore',
        ]);
    }

    public function test_snapped_route_endpoint_requires_authenticated_session()
    {
        $response = $this->get(route('tracking.engineer.snapped-route', $this->engineer->id));
        $this->assertTrue($response->isRedirection());
    }

    public function test_snapped_route_returns_fallback_when_few_points()
    {
        $today = Carbon::today()->toDateString();

        // 1 single point
        LocationPoint::create([
            'uuid' => (string)Str::uuid(),
            'user_id' => $this->engineer->id,
            'lat' => 31.5397,
            'lng' => 74.3306,
            'speed' => 0,
            'recorded_at' => Carbon::now(),
            'received_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['tracking_module_unlocked' => true])
            ->get(route('tracking.engineer.snapped-route', ['id' => $this->engineer->id, 'date' => $today]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'snapped' => false,
            'point_count' => 1,
        ]);
    }

    public function test_snapped_route_matches_streets_via_osrm()
    {
        $today = Carbon::today()->toDateString();

        // Mock OSRM response
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'geometry' => [
                            'coordinates' => [
                                [74.330626, 31.539816],
                                [74.329963, 31.539923],
                                [74.330091, 31.540664],
                                [74.331538, 31.541319],
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        // Insert breadcrumb points
        LocationPoint::create([
            'uuid' => (string)Str::uuid(),
            'user_id' => $this->engineer->id,
            'lat' => 31.5397,
            'lng' => 74.3306,
            'speed' => 3.5,
            'recorded_at' => Carbon::now()->subMinutes(10),
            'received_at' => Carbon::now()->subMinutes(10),
        ]);

        LocationPoint::create([
            'uuid' => (string)Str::uuid(),
            'user_id' => $this->engineer->id,
            'lat' => 31.5413,
            'lng' => 74.3315,
            'speed' => 4.2,
            'recorded_at' => Carbon::now()->subMinutes(5),
            'received_at' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['tracking_module_unlocked' => true])
            ->get(route('tracking.engineer.snapped-route', ['id' => $this->engineer->id, 'date' => $today]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertTrue($data['snapped']);
        $this->assertCount(4, $data['coordinates']);

        // Check format is [lat, lng]
        $this->assertEquals(31.539816, $data['coordinates'][0][0]);
        $this->assertEquals(74.330626, $data['coordinates'][0][1]);
    }

    public function test_playback_view_renders_polyline_decorator_and_snap_badge()
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['tracking_module_unlocked' => true])
            ->get(route('tracking.engineer.playback', $this->engineer->id));

        $response->assertStatus(200);
        $response->assertSee('leaflet-polylinedecorator');
        $response->assertSee('road_snap_badge');
        $response->assertSee('applyDirectionDecorator');
        $response->assertSee('snapped-route');
    }
}
