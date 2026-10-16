<?php

namespace Tests\Feature;

use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobilePartRequestAndWorkshopFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $engineer;
    protected Ticket $ticket;
    protected MachineModel $model;
    protected Part $part1;
    protected Part $part2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Ronald Admin',
            'role' => 'admin',
        ]);

        $this->engineer = User::factory()->create([
            'name' => 'Sheryar Engineer',
            'role' => 'engineer',
        ]);

        $this->ticket = Ticket::create([
            'ticket_no'            => 'TKT-TEST-PR',
            'ticket_type'          => 'complaint',
            'bank_name'            => 'United Bank Limited (UBL)',
            'branch_name'          => 'College Road',
            'branch_location'      => 'Lahore',
            'status'               => 'in_progress',
            'assigned_engineer_id' => $this->engineer->id,
            'machine_serial_no'    => 'CMS-SN-123',
        ]);

        $this->ticket->ticketEngineers()->create([
            'engineer_id' => $this->engineer->id,
            'role'        => 'lead',
            'assigned_at' => now(),
        ]);

        $this->model = MachineModel::create([
            'name'         => 'VC870',
            'machine_type' => 'Cash Sorting Machine',
        ]);

        $this->part1 = Part::create([
            'name'        => 'Feed Roller',
            'part_number' => 'FR-001',
            'category'    => 'mechanical',
            'unit'        => 'PCS',
            'cost_price'  => 1500,
            'is_active'   => true,
        ]);

        $this->part2 = Part::create([
            'name'        => 'Main Sensor',
            'part_number' => 'MS-002',
            'category'    => 'electronic',
            'unit'        => 'PCS',
            'cost_price'  => 3000,
            'is_active'   => true,
        ]);
    }

    public function test_engineer_can_edit_part_request_before_approval()
    {
        $pr = PartRequest::create([
            'request_number'    => PartRequest::generateRequestNumber(),
            'ticket_id'         => $this->ticket->id,
            'engineer_id'       => $this->engineer->id,
            'machine_model_id'  => $this->model->id,
            'machine_serial_no' => 'CMS-SN-123',
            'fault_description' => 'Original fault description',
            'status'            => 'pending_stock_check',
        ]);

        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id'         => $this->part1->id,
            'qty_requested'   => 1,
        ]);

        // Engineer edits the request via mobile API
        $response = $this->actingAs($this->engineer, 'sanctum')->putJson("/api/v1/engineer/part-requests/{$pr->id}", [
            'machine_model_id'             => $this->model->id,
            'machine_serial_no'            => 'CMS-SN-UPDATED',
            'fault_description'            => 'Updated fault description with additional details',
            'has_additional_machine'       => true,
            'additional_machine_type'      => 'Shrink Wrapping Machine',
            'additional_machine_serial_no' => 'SHRINK-SN-999',
            'additional_fault_description' => 'Heating element defective',
            'items'                        => [
                [
                    'part_id' => $this->part2->id,
                    'qty'     => 2,
                    'note'    => 'Urgent replacement needed',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $pr->refresh();
        $this->assertEquals('CMS-SN-UPDATED', $pr->machine_serial_no);
        $this->assertEquals('Updated fault description with additional details', $pr->fault_description);
        $this->assertEquals('Shrink Wrapping Machine', $pr->additional_machine_type);
        $this->assertEquals('SHRINK-SN-999', $pr->additional_machine_serial_no);
        $this->assertEquals('Heating element defective', $pr->additional_fault_description);

        $this->assertCount(1, $pr->items);
        $this->assertEquals($this->part2->id, $pr->items->first()->part_id);
        $this->assertEquals(2, $pr->items->first()->qty_requested);
        $this->assertEquals('Urgent replacement needed', $pr->items->first()->note);
    }

    public function test_engineer_cannot_edit_part_request_after_approval()
    {
        $pr = PartRequest::create([
            'request_number'    => PartRequest::generateRequestNumber(),
            'ticket_id'         => $this->ticket->id,
            'engineer_id'       => $this->engineer->id,
            'machine_model_id'  => $this->model->id,
            'fault_description' => 'Original fault',
            'status'            => 'approved',
        ]);

        $response = $this->actingAs($this->engineer, 'sanctum')->putJson("/api/v1/engineer/part-requests/{$pr->id}", [
            'fault_description' => 'Attempt to edit approved request',
            'items'             => [
                ['part_id' => $this->part1->id, 'qty' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_workshop_ticket_in_return_transit_does_not_appear_in_repaired_tab()
    {
        $workshopTicket = Ticket::create([
            'ticket_no'               => 'UBL-128742',
            'ticket_type'             => 'complaint',
            'bank_name'               => 'United Bank Limited (UBL)',
            'branch_name'             => 'COLLEGE ROAD LAHORE',
            'branch_location'         => 'Lahore',
            'machine_serial_no'       => 'CMS11205131',
            'status'                  => 'return_transit',
            'workshop_location'       => 'Lahore Central Workshop',
            'workshop_dispatched_at'  => now()->subDays(2),
            'workshop_received_at'    => now()->subDays(1),
            'workshop_repaired_at'    => now()->subHours(10),
            'return_dispatched_at'    => now()->subHours(2),
            'return_courier'          => 'TCS Priority Cargo',
            'return_tracking_number'  => 'by hand sheryar',
        ]);

        // Access repaired tab
        $resRepaired = $this->actingAs($this->admin)->get(route('workshop.index', ['tab' => 'repaired']));
        $resRepaired->assertOk();
        $resRepaired->assertDontSee('UBL-128742');

        // Access return_transit tab
        $resTransit = $this->actingAs($this->admin)->get(route('workshop.index', ['tab' => 'return_transit']));
        $resTransit->assertOk();
        $resTransit->assertSee('UBL-128742');
        $resTransit->assertSee('Confirm Bank Receipt & Close');
        $resTransit->assertDontSee('Dispatch Back to Bank');
    }

    public function test_mobile_engineer_must_provide_supporting_document_for_waiting_for_approval()
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // Attempt without document -> fails
        $resWithoutDoc = $this->actingAs($this->engineer, 'sanctum')->postJson("/api/v1/engineer/tickets/{$this->ticket->id}/request-approval", [
            'reason'          => 'Waiting for customer quotation sign-off',
            'approval_source' => 'Operations Head',
        ]);
        $resWithoutDoc->assertStatus(422);
        $resWithoutDoc->assertJsonValidationErrors(['document']);

        // Upload with document -> succeeds
        $file = \Illuminate\Http\UploadedFile::fake()->create('approval_letter.pdf', 100, 'application/pdf');
        $resWithDoc = $this->actingAs($this->engineer, 'sanctum')->post("/api/v1/engineer/tickets/{$this->ticket->id}/request-approval", [
            'reason'          => 'Waiting for customer quotation sign-off',
            'approval_source' => 'Operations Head',
            'document'        => $file,
        ], ['Accept' => 'application/json']);

        $resWithDoc->assertOk();
        $resWithDoc->assertJson(['success' => true]);

        $this->ticket->refresh();
        $this->assertEquals('awaiting_approval', $this->ticket->status);
        $this->assertNotNull($this->ticket->approval_document);
        $this->assertEquals('approval_letter.pdf', $this->ticket->approval_document_name);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($this->ticket->approval_document);

        // Verify ticket detail API returns approval_document_url
        $detailRes = $this->actingAs($this->engineer, 'sanctum')->getJson("/api/v1/engineer/tickets/{$this->ticket->id}");
        $detailRes->assertOk();
        $this->assertNotNull($detailRes->json('ticket.approval_document_url'));
        $this->assertEquals('approval_letter.pdf', $detailRes->json('ticket.approval_document_name'));
    }

    public function test_mobile_engineer_must_provide_supporting_document_for_send_to_workshop()
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // Attempt without document -> fails
        $resWithoutDoc = $this->actingAs($this->engineer, 'sanctum')->postJson("/api/v1/engineer/tickets/{$this->ticket->id}/workshop", [
            'workshop_location' => 'Lahore Central Workshop',
            'notes'             => 'Optical sensor misaligned, requires bench lathe',
        ]);
        $resWithoutDoc->assertStatus(422);
        $resWithoutDoc->assertJsonValidationErrors(['document']);

        // Upload with consignment receipt -> succeeds
        $file = \Illuminate\Http\UploadedFile::fake()->image('tcs_consignment_bilty.jpg');
        $resWithDoc = $this->actingAs($this->engineer, 'sanctum')->post("/api/v1/engineer/tickets/{$this->ticket->id}/workshop", [
            'workshop_location'          => 'Lahore Central Workshop',
            'workshop_dispatch_courier'  => 'TCS Express Cargo',
            'workshop_dispatch_tracking' => 'TCS-998811',
            'notes'                      => 'Optical sensor misaligned, requires bench lathe',
            'document'                   => $file,
        ], ['Accept' => 'application/json']);

        $resWithDoc->assertOk();
        $resWithDoc->assertJson(['success' => true]);

        $this->ticket->refresh();
        $this->assertEquals('awaiting_workshop', $this->ticket->status);
        $this->assertNotNull($this->ticket->workshop_dispatch_document);
        $this->assertEquals('tcs_consignment_bilty.jpg', $this->ticket->workshop_dispatch_document_name);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($this->ticket->workshop_dispatch_document);

        // Verify ticket detail API returns workshop_dispatch_document_url
        $detailRes = $this->actingAs($this->engineer, 'sanctum')->getJson("/api/v1/engineer/tickets/{$this->ticket->id}");
        $detailRes->assertOk();
        $this->assertNotNull($detailRes->json('ticket.workshop_dispatch_document_url'));
        $this->assertEquals('tcs_consignment_bilty.jpg', $detailRes->json('ticket.workshop_dispatch_document_name'));
    }
}
