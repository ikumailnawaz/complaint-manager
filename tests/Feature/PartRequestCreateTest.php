<?php

namespace Tests\Feature;

use App\Models\MachineModel;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartRequestCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_preselects_active_ticket_passed_in_query_parameter(): void
    {
        $engineer = User::factory()->create([
            'role' => 'engineer',
            'is_available' => true,
        ]);

        $model = MachineModel::create([
            'name' => 'BM-350',
            'machine_type' => 'crm',
            'is_active' => true,
        ]);

        // Ticket in 'assigned' status (which was previously omitted by whereIn status)
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-008',
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_location' => 'Lahore',
            'status' => 'assigned',
            'machine_type' => 'Cash Recycler',
            'machine_model' => 'BM-350',
            'machine_serial_no' => 'BM350-SN-008',
            'urgency' => 'high',
            'assigned_engineer_id' => $engineer->id,
        ]);

        $response = $this->actingAs($engineer)
            ->get(route('parts.requests.create', ['ticket_id' => $ticket->id]));

        $response->assertStatus(200);
        $response->assertSee('value="' . $ticket->id . '"', false);
        $response->assertSee('selected', false);
        $response->assertSee('CMP-2026-TEST-008');
    }

    public function test_selected_ticket_is_included_even_if_assigned_to_another_engineer_or_open(): void
    {
        $engineer = User::factory()->create([
            'role' => 'engineer',
            'is_available' => true,
        ]);

        $otherEngineer = User::factory()->create([
            'role' => 'engineer',
            'is_available' => true,
        ]);

        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-009',
            'bank_name' => 'MCB Bank',
            'branch_location' => 'Karachi',
            'status' => 'open',
            'machine_type' => 'ATM',
            'machine_model' => 'Wincor Nixdorf',
            'machine_serial_no' => 'WN-SN-999',
            'urgency' => 'medium',
            'assigned_engineer_id' => $otherEngineer->id,
        ]);

        $response = $this->actingAs($engineer)
            ->get(route('parts.requests.create', ['ticket_id' => $ticket->id]));

        $response->assertStatus(200);
        $response->assertSee('value="' . $ticket->id . '"', false);
        $response->assertSee('selected', false);
        $response->assertSee('CMP-2026-TEST-009');
    }
}
