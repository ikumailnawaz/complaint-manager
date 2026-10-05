<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\PartStockLedger;
use App\Models\PartTransfer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PartsSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase6PartsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superior;
    protected User $officeStaff;
    protected User $engineer;
    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);

        $this->superior = User::create([
            'name' => 'Superior Manager',
            'email' => 'superior@test.com',
            'password' => bcrypt('secret'),
            'role' => 'superior',
        ]);

        $this->officeStaff = User::create([
            'name' => 'Office Staff User',
            'email' => 'staff@test.com',
            'password' => bcrypt('secret'),
            'role' => 'office_staff',
        ]);

        $this->engineer = User::create([
            'name' => 'Ali Engineer',
            'email' => 'engineer@test.com',
            'password' => bcrypt('secret'),
            'role' => 'engineer',
            'base_city' => 'Lahore',
        ]);

        $this->ticket = Ticket::create([
            'ticket_no' => 'TKT-2026-0001',
            'complaint_number' => '174712',
            'bank_name' => 'Bank Alfalah',
            'branch_name' => 'Main Mall Branch',
            'branch_location' => 'Lahore',
            'issue_summary' => 'ATM Cash dispenser error',
            'status' => 'in_progress',
            'urgency' => 'high',
            'assigned_engineer_id' => $this->engineer->id,
            'assigned_at' => now(),
        ]);

        // Seed user parts, locations, and models
        $this->seed(PartsSystemSeeder::class);
    }

    public function test_parts_catalog_is_accessible_and_lists_seeded_parts(): void
    {
        $response = $this->actingAs($this->admin)->get(route('parts.master.parts', ['search' => 'BM350-0002']));
        $response->assertStatus(200);
        $response->assertSee('BM350-0002');
        $response->assertSee('cutter');
    }

    public function test_admin_can_create_new_part(): void
    {
        $response = $this->actingAs($this->admin)->post(route('parts.master.parts.store'), [
            'part_number' => 'TEST-SENSOR-01',
            'name' => 'Optical Pick Sensor',
            'unit' => 'PCS',
            'unit_cost' => 12500,
            'reorder_level' => 3,
        ]);

        $response->assertRedirect(route('parts.master.parts'));
        $this->assertDatabaseHas('parts', ['part_number' => 'TEST-SENSOR-01', 'name' => 'Optical Pick Sensor']);
    }

    public function test_stock_grid_shows_seeded_inventory_levels(): void
    {
        $response = $this->actingAs($this->admin)->get(route('parts.stock.index'));
        $response->assertStatus(200);
        $response->assertSee('Parts Stock Levels');
        $response->assertSee('Lahore Head Office');
        $response->assertSee('Karachi Regional Office');
    }

    public function test_grn_creation_and_confirmation_updates_stock_ledger(): void
    {
        $part = Part::where('part_number', 'BM350-0002')->first();
        $location = Location::where('name', 'Islamabad Hub')->first();

        // Initial stock at Islamabad is 0
        $initialStock = PartStockLedger::where('part_id', $part->id)->where('location_id', $location->id)->value('qty_on_hand') ?? 0;
        $this->assertEquals(0, $initialStock);

        // 1. Create GRN as draft
        $response = $this->actingAs($this->superior)->post(route('parts.grn.store'), [
            'location_id' => $location->id,
            'supplier_name' => 'Direct Procurement',
            'received_at' => now()->format('Y-m-d'),
            'items' => [
                [
                    'part_id' => $part->id,
                    'qty_received' => 10,
                    'unit_cost' => 1500,
                    'condition' => 'new',
                ]
            ]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('grns', ['location_id' => $location->id, 'status' => 'draft']);
        $grn = \App\Models\Grn::latest()->first();

        // Stock should still be 0 before confirmation
        $this->assertEquals(0, PartStockLedger::where('part_id', $part->id)->where('location_id', $location->id)->value('qty_on_hand') ?? 0);

        // 2. Confirm GRN
        $confirmResponse = $this->actingAs($this->superior)->post(route('parts.grn.confirm', $grn));
        $confirmResponse->assertRedirect();

        // Stock at Islamabad should now be 10!
        $updatedStock = PartStockLedger::where('part_id', $part->id)->where('location_id', $location->id)->value('qty_on_hand');
        $this->assertEquals(10, $updatedStock);
    }

    public function test_inter_location_transfer_workflow_debits_and_credits_stock(): void
    {
        $part = Part::where('part_number', 'BM350-0002')->first();
        $lahore = Location::where('name', 'Lahore Head Office')->first();
        $islamabad = Location::where('name', 'Islamabad Hub')->first();

        // Provide 5 pieces at Lahore first
        PartStockLedger::updateOrCreate(
            ['part_id' => $part->id, 'location_id' => $lahore->id],
            ['qty_on_hand' => 5, 'qty_reserved' => 0]
        );

        $lahoreStart = PartStockLedger::where('part_id', $part->id)->where('location_id', $lahore->id)->value('qty_on_hand');
        $this->assertEquals(5, $lahoreStart);

        // 1. Initiate transfer of 2 pieces from Lahore to Islamabad
        $response = $this->actingAs($this->superior)->post(route('parts.transfers.store'), [
            'from_location_id' => $lahore->id,
            'to_location_id' => $islamabad->id,
            'remarks' => 'Urgent replenishment',
            'items' => [
                ['part_id' => $part->id, 'qty' => 2]
            ]
        ]);

        $response->assertRedirect();
        $transfer = PartTransfer::latest()->first();
        $this->assertEquals('pending', $transfer->status);

        // 2. Dispatch transfer -> debits Lahore
        $dispatchResponse = $this->actingAs($this->superior)->post(route('parts.transfers.dispatch', $transfer));
        $dispatchResponse->assertRedirect();

        $transfer->refresh();
        $this->assertEquals('in_transit', $transfer->status);

        $lahoreAfterDispatch = PartStockLedger::where('part_id', $part->id)->where('location_id', $lahore->id)->value('qty_on_hand');
        $this->assertEquals($lahoreStart - 2, $lahoreAfterDispatch);

        // 3. Receive transfer -> credits Islamabad
        $item = $transfer->items->first();
        $islamabadStart = PartStockLedger::where('part_id', $part->id)->where('location_id', $islamabad->id)->value('qty_on_hand') ?? 0;

        $receiveResponse = $this->actingAs($this->superior)->post(route('parts.transfers.receive', $transfer), [
            'received_qtys' => [$item->id => 2]
        ]);
        $receiveResponse->assertRedirect();

        $transfer->refresh();
        $this->assertEquals('received', $transfer->status);

        $islamabadAfter = PartStockLedger::where('part_id', $part->id)->where('location_id', $islamabad->id)->value('qty_on_hand');
        $this->assertEquals($islamabadStart + 2, $islamabadAfter);
    }

    public function test_engineer_part_request_complete_approval_and_dispatch_workflow(): void
    {
        Storage::fake('public');
        $part = Part::where('part_number', 'BM350-0002')->first();
        $model = MachineModel::where('name', 'BM-350')->first();
        $lahore = Location::where('name', 'Lahore Head Office')->first();

        // Give Lahore 3 items in stock
        PartStockLedger::updateOrCreate(
            ['part_id' => $part->id, 'location_id' => $lahore->id],
            ['qty_on_hand' => 3, 'qty_reserved' => 0]
        );
        $lahoreStockStart = 3;

        // Step 1: Engineer submits Part Request
        $submitResponse = $this->actingAs($this->engineer)->post(route('parts.requests.store'), [
            'ticket_id' => $this->ticket->id,
            'machine_model_id' => $model->id,
            'machine_serial_no' => 'ATM-4412-X',
            'fault_description' => 'Cutter blade blunt',
            'items' => [
                ['part_id' => $part->id, 'qty' => 1, 'note' => 'Replacement needed']
            ]
        ]);

        $submitResponse->assertRedirect();
        $this->assertDatabaseHas('part_requests', [
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'pending_stock_check',
        ]);
        $partRequest = PartRequest::latest()->first();

        // Step 2: Office staff verifies stock and uploads video evidence
        $video = UploadedFile::fake()->create('fault_video.mp4', 500, 'video/mp4');
        $verifyResponse = $this->actingAs($this->officeStaff)->post(route('parts.requests.verify-stock', $partRequest), [
            'stock_remarks' => 'Stock is available in Lahore Head Office',
            'fault_video' => $video,
        ]);
        $verifyResponse->assertRedirect();

        $partRequest->refresh();
        $this->assertEquals('pending_approval', $partRequest->status);
        $this->assertNotNull($partRequest->fault_video_path);

        // Office staff cannot approve (Stage 2 is restricted to Super Admin / Admin)
        $item = $partRequest->items->first();
        $this->actingAs($this->officeStaff)->post(route('parts.requests.approve', $partRequest), [
            'approved_qtys' => [$item->id => 1],
            'approval_remarks' => 'Office staff cannot approve',
        ])->assertStatus(403);

        // Step 3: Admin / Super Admin approves the request
        $approveResponse = $this->actingAs($this->admin)->post(route('parts.requests.approve', $partRequest), [
            'approved_qtys' => [$item->id => 1],
            'approval_remarks' => 'Approved for immediate dispatch',
        ]);
        $approveResponse->assertRedirect();

        $partRequest->refresh();
        $this->assertEquals('approved', $partRequest->status);

        // Step 4: Office staff dispatches the approved part
        $dispatchResponse = $this->actingAs($this->officeStaff)->post(route('parts.requests.dispatch', $partRequest), [
            'dispatch_location_id' => $lahore->id,
            'dispatch_courier' => 'TCS Express',
            'dispatch_tracking_number' => 'TCS-99881122',
        ]);
        $dispatchResponse->assertRedirect();

        $partRequest->refresh();
        $this->assertEquals('dispatched', $partRequest->status);
        $this->assertEquals('TCS-99881122', $partRequest->dispatch_tracking_number);

        // Stock in Lahore should be decremented by 1
        $lahoreStockEnd = PartStockLedger::where('part_id', $part->id)->where('location_id', $lahore->id)->value('qty_on_hand');
        $this->assertEquals($lahoreStockStart - 1, $lahoreStockEnd);

        // History appears on the ticket detail page
        $ticketResponse = $this->actingAs($this->admin)->get(route('tickets.show', $this->ticket));
        $ticketResponse->assertStatus(200);
        $ticketResponse->assertSee($partRequest->request_number);
        $ticketResponse->assertSee('TCS-99881122');
        $ticketResponse->assertSee('cutter');
    }

    public function test_unauthorized_engineer_cannot_perform_superior_operations(): void
    {
        // Engineer cannot access location settings
        $response = $this->actingAs($this->engineer)->get(route('parts.master.locations'));
        $response->assertStatus(403);

        // Engineer cannot access GRN creation
        $grnResponse = $this->actingAs($this->engineer)->get(route('parts.grn.create'));
        $grnResponse->assertStatus(403);
    }

    public function test_part_request_filters_unselected_items_and_accepts_manual_serial(): void
    {
        $part = Part::where('part_number', 'BM350-0002')->first();
        $model = MachineModel::where('name', 'BM-350')->first();

        // Simulate form submission where unchecked rows submitted empty part_ids alongside a checked part
        $response = $this->actingAs($this->engineer)->post(route('parts.requests.store'), [
            'ticket_id' => $this->ticket->id,
            'machine_model_id' => $model->id,
            'machine_serial_no' => 'MANUAL-SN-9999',
            'fault_description' => 'Motor jammed and roller worn out',
            'items' => [
                ['qty' => 1, 'note' => ''], // unchecked row 0
                ['part_id' => $part->id, 'qty' => 3, 'note' => 'Need immediately'], // checked row 1
                ['qty' => 1, 'note' => ''], // unchecked row 2
                ['part_id' => '', 'qty' => 1], // unchecked row 3
            ]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('part_requests', [
            'ticket_id' => $this->ticket->id,
            'machine_serial_no' => 'MANUAL-SN-9999',
            'status' => 'pending_stock_check',
        ]);

        $pr = PartRequest::where('machine_serial_no', 'MANUAL-SN-9999')->first();
        $this->assertCount(1, $pr->items);
        $this->assertEquals($part->id, $pr->items->first()->part_id);
        $this->assertEquals(3, $pr->items->first()->qty_requested);

        // Verify index page renders with this request and links to tickets.show
        $indexResponse = $this->actingAs($this->admin)->get(route('parts.requests.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee($pr->request_number);
        $indexResponse->assertSee('#' . $this->ticket->ticket_no);

        // Verify show page renders
        $showResponse = $this->actingAs($this->admin)->get(route('parts.requests.show', $pr));
        $showResponse->assertStatus(200);
        $showResponse->assertSee($pr->request_number);
        $showResponse->assertSee('#' . $this->ticket->ticket_no);
    }

    public function test_part_request_editable_before_approval_and_locked_afterwards(): void
    {
        $part1 = Part::where('part_number', 'BM350-0002')->first();
        $part2 = Part::where('part_number', 'BM350-0005')->first();
        $model = MachineModel::where('name', 'BM-350')->first();

        // 1. Create request
        $pr = PartRequest::create([
            'request_number'    => PartRequest::generateRequestNumber(),
            'ticket_id'         => $this->ticket->id,
            'engineer_id'       => $this->engineer->id,
            'machine_model_id'  => $model->id,
            'machine_serial_no' => 'ORIG-SN-1234',
            'fault_description' => 'Original fault',
            'status'            => 'pending_stock_check',
        ]);
        \App\Models\PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id'         => $part1->id,
            'qty_requested'   => 1,
        ]);

        // 2. Engineer can access edit page
        $editResponse = $this->actingAs($this->engineer)->get(route('parts.requests.edit', $pr));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('ORIG-SN-1234');

        // 3. Engineer updates request
        $updateResponse = $this->actingAs($this->engineer)->put(route('parts.requests.update', $pr), [
            'ticket_id'          => $this->ticket->id,
            'machine_model_id'   => $model->id,
            'machine_serial_no'  => 'UPDATED-SN-5678',
            'fault_description'  => 'Updated fault description',
            'items'              => [
                ['part_id' => $part2->id, 'qty' => 4, 'note' => 'Display replacement'],
            ],
        ]);
        $updateResponse->assertRedirect(route('parts.requests.show', $pr));

        $pr->refresh();
        $this->assertEquals('UPDATED-SN-5678', $pr->machine_serial_no);
        $this->assertEquals('Updated fault description', $pr->fault_description);
        $this->assertCount(1, $pr->items);
        $this->assertEquals($part2->id, $pr->items->first()->part_id);
        $this->assertEquals(4, $pr->items->first()->qty_requested);

        // 4. Lock once approved
        $pr->update(['status' => 'approved']);
        $lockedEditResponse = $this->actingAs($this->engineer)->get(route('parts.requests.edit', $pr));
        $lockedEditResponse->assertRedirect(route('parts.requests.show', $pr));
    }

    public function test_courier_tracking_id_is_strictly_mandatory_on_dispatch(): void
    {
        $lahore = Location::where('name', 'Lahore Head Office')->first();
        $part = Part::where('part_number', 'BM350-0002')->first();

        $pr = PartRequest::create([
            'request_number' => 'PR-2026-9001',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'approved',
            'fault_description' => 'Tested',
        ]);
        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $part->id,
            'qty_requested' => 1,
            'qty_approved' => 1,
        ]);

        // Attempt dispatch without tracking number
        $response = $this->actingAs($this->superior)->post(route('parts.requests.dispatch', $pr), [
            'dispatch_location_id' => $lahore->id,
            'dispatch_courier' => 'TCS Express',
            'dispatch_tracking_number' => '', // missing
        ]);
        $response->assertSessionHasErrors(['dispatch_tracking_number']);
        $this->assertEquals('approved', $pr->fresh()->status);

        // Attempt dispatch without courier
        $response2 = $this->actingAs($this->superior)->post(route('parts.requests.dispatch', $pr), [
            'dispatch_location_id' => $lahore->id,
            'dispatch_courier' => '',
            'dispatch_tracking_number' => 'TRACK-12345',
        ]);
        $response2->assertSessionHasErrors(['dispatch_courier']);
        $this->assertEquals('approved', $pr->fresh()->status);
    }

    public function test_faulty_part_reverse_return_tracking_workflow(): void
    {
        $lahore = Location::where('name', 'Lahore Head Office')->first();
        $karachi = Location::where('name', 'Karachi Regional Office')->first();
        $part = Part::where('part_number', 'BM350-0002')->first();

        $pr = PartRequest::create([
            'request_number' => 'PR-2026-9002',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'approved',
            'fault_description' => 'Motor replacement',
        ]);
        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $part->id,
            'qty_requested' => 1,
            'qty_approved' => 1,
        ]);

        // Seed stock for dispatch
        PartStockLedger::updateOrCreate(
            ['part_id' => $part->id, 'location_id' => $lahore->id],
            ['qty_on_hand' => 5, 'qty_reserved' => 0]
        );

        // Dispatch sets status to dispatched and faulty_return_status to pending_return
        $this->actingAs($this->superior)->post(route('parts.requests.dispatch', $pr), [
            'dispatch_location_id' => $lahore->id,
            'dispatch_courier' => 'TCS Express',
            'dispatch_tracking_number' => 'TCS-RETURN-TEST',
        ]);

        $pr->refresh();
        $this->assertEquals('dispatched', $pr->status);
        $this->assertEquals('pending_return', $pr->faulty_return_status);

        // Office staff records reverse return of defective core part
        $verifyResponse = $this->actingAs($this->superior)->post(route('parts.requests.verify-faulty-return', $pr), [
            'faulty_return_status' => 'returned',
            'faulty_return_location_id' => $karachi->id,
            'faulty_return_courier_tracking' => 'BY-HAND-ENG-01',
            'faulty_return_remarks' => 'Defective cutter unit received back from engineer in good core condition.',
        ]);
        $verifyResponse->assertRedirect(route('parts.requests.show', $pr));

        $pr->refresh();
        $this->assertEquals('returned', $pr->faulty_return_status);
        $this->assertEquals($karachi->id, $pr->faulty_return_location_id);
        $this->assertEquals('BY-HAND-ENG-01', $pr->faulty_return_courier_tracking);
        $this->assertEquals($this->superior->id, $pr->faulty_return_received_by_id);
        $this->assertNotNull($pr->faulty_returned_at);
        $this->assertStringContainsString('Defective cutter unit received back', $pr->faulty_return_remarks);
    }

    public function test_super_admin_can_approve_directly_from_pending_stock_check(): void
    {
        $part = Part::where('part_number', 'BM350-0002')->first();
        $pr = PartRequest::create([
            'request_number' => 'PR-2026-9003',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'pending_stock_check',
            'fault_description' => 'Urgent replacement needed',
        ]);
        $item = PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $part->id,
            'qty_requested' => 2,
        ]);

        // Super Admin approves directly without staff stock check
        $response = $this->actingAs($this->admin)->post(route('parts.requests.approve', $pr), [
            'approved_qtys' => [$item->id => 2],
            'approval_remarks' => 'Direct Super Admin Fast-Track Approval',
        ]);
        $response->assertRedirect(route('parts.requests.show', $pr));

        $pr->refresh();
        $this->assertEquals('approved', $pr->status);
        $this->assertEquals(2, $item->fresh()->qty_approved);
        $this->assertEquals($this->admin->id, $pr->approved_by_id);
    }

    public function test_video_evidence_streaming_and_download(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $videoFile = \Illuminate\Http\UploadedFile::fake()->create('fault_evidence.mp4', 1024, 'video/mp4');
        $storedPath = $videoFile->store('part-requests/videos', 'public');

        $pr = PartRequest::create([
            'request_number' => 'PR-2026-9004',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'pending_approval',
            'fault_description' => 'Video evidence test',
            'fault_video_path' => $storedPath,
        ]);

        // Stream video endpoint
        $streamResponse = $this->actingAs($this->superior)->get(route('parts.requests.video', $pr));
        $streamResponse->assertStatus(200);

        // Download video endpoint
        $downloadResponse = $this->actingAs($this->superior)->get(route('parts.requests.video.download', $pr));
        $downloadResponse->assertStatus(200);
        $this->assertTrue($downloadResponse->headers->has('content-disposition'));
    }

    public function test_gate_pass_view_accessible_and_formatted_for_gatekeeper(): void
    {
        $part = Part::where('part_number', 'BM350-0002')->first();
        $pr = PartRequest::create([
            'request_number' => 'PR-2026-9005',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'approved',
            'fault_description' => 'Display repair',
            'dispatch_courier' => 'TCS Express',
            'dispatch_tracking_number' => 'TCS-GATEPASS-99',
        ]);
        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $part->id,
            'qty_requested' => 2,
            'qty_approved' => 2,
        ]);

        $response = $this->actingAs($this->superior)->get(route('parts.requests.gate-pass', $pr));
        $response->assertStatus(200);
        $response->assertSee('OUTWARD MATERIAL GATE PASS');
        $response->assertSee('Gate Security Clearance');
        $response->assertSee('GP-PR-2026-9005');
        $response->assertSee('TCS-GATEPASS-99');
        $response->assertSee('BM350-0002');
    }

    public function test_dispatch_automatically_balances_stock_without_manual_checkbox(): void
    {
        $islamabad = Location::where('name', 'Islamabad Hub')->first();
        $part = Part::where('part_number', 'BM350-0002')->first();

        // Ensure Islamabad Hub has 0 stock for this part
        PartStockLedger::updateOrCreate(
            ['part_id' => $part->id, 'location_id' => $islamabad->id],
            ['qty_on_hand' => 0, 'qty_reserved' => 0]
        );

        $pr = PartRequest::create([
            'request_number' => 'PR-2026-9006',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'approved',
            'fault_description' => 'Auto stock detection test',
        ]);
        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $part->id,
            'qty_requested' => 3,
            'qty_approved' => 3,
        ]);

        // Dispatch WITHOUT any checkbox parameter — stock should be auto-detected and auto-adjusted
        $dispatchResponse = $this->actingAs($this->superior)->post(route('parts.requests.dispatch', $pr), [
            'dispatch_location_id' => $islamabad->id,
            'dispatch_courier' => 'Leopard Courier',
            'dispatch_tracking_number' => 'LEO-AUTO-999',
        ]);

        $dispatchResponse->assertRedirect(route('parts.requests.show', $pr));
        $pr->refresh();
        $this->assertEquals('dispatched', $pr->status);
        $this->assertEquals('pending_return', $pr->faulty_return_status);
        $this->assertEquals('LEO-AUTO-999', $pr->dispatch_tracking_number);

        // Verify stock ledger has 0 net shortfall (auto-adjusted 3 and deducted 3)
        $ledger = PartStockLedger::where('part_id', $part->id)->where('location_id', $islamabad->id)->first();
        $this->assertEquals(0, $ledger->qty_on_hand);
    }

    public function test_part_requests_index_filtering_and_gate_pass_actions(): void
    {
        $lahore = Location::where('name', 'Lahore Head Office')->first();
        $part = Part::where('part_number', 'BM350-0002')->first();

        // Create an approved PR
        $prApproved = PartRequest::create([
            'request_number' => 'PR-2026-FILTER-1',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'approved',
            'fault_description' => 'Filter test approved',
            'created_at' => now()->subDays(2),
        ]);
        PartRequestItem::create([
            'part_request_id' => $prApproved->id,
            'part_id' => $part->id,
            'qty_requested' => 1,
            'qty_approved' => 1,
        ]);

        // Create a dispatched PR
        $prDispatched = PartRequest::create([
            'request_number' => 'PR-2026-FILTER-2',
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'dispatched',
            'fault_description' => 'Filter test dispatched',
            'dispatch_location_id' => $lahore->id,
            'dispatch_courier' => 'TCS Express',
            'dispatch_tracking_number' => 'FILTER-TRK-100',
            'dispatched_at' => now(),
            'faulty_return_status' => 'pending_return',
            'created_at' => now()->subDay(),
        ]);
        PartRequestItem::create([
            'part_request_id' => $prDispatched->id,
            'part_id' => $part->id,
            'qty_requested' => 1,
            'qty_approved' => 1,
        ]);

        // 1. Test status filter tabs: status=dispatched
        $responseDispatched = $this->actingAs($this->superior)->get(route('parts.requests.index', ['status' => 'dispatched']));
        $responseDispatched->assertStatus(200);
        $responseDispatched->assertSee('PR-2026-FILTER-2');
        $responseDispatched->assertDontSee('PR-2026-FILTER-1');
        $responseDispatched->assertSee('Gate Pass');
        $responseDispatched->assertSee('FILTER-TRK-100');
        $responseDispatched->assertSee('Return Pending');

        // 2. Test status filter tabs: status=approved
        $responseApproved = $this->actingAs($this->superior)->get(route('parts.requests.index', ['status' => 'approved']));
        $responseApproved->assertStatus(200);
        $responseApproved->assertSee('PR-2026-FILTER-1');
        $responseApproved->assertDontSee('PR-2026-FILTER-2');
        $responseApproved->assertSee('Gate Pass');

        // 3. Test date range filtering
        $responseDateMatch = $this->actingAs($this->superior)->get(route('parts.requests.index', [
            'from_date' => now()->subDays(3)->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
        ]));
        $responseDateMatch->assertStatus(200);
        $responseDateMatch->assertSee('PR-2026-FILTER-1');
        $responseDateMatch->assertSee('PR-2026-FILTER-2');

        // Test out-of-range date
        $responseDateMismatch = $this->actingAs($this->superior)->get(route('parts.requests.index', [
            'from_date' => now()->addDays(5)->format('Y-m-d'),
            'to_date' => now()->addDays(10)->format('Y-m-d'),
        ]));
        $responseDateMismatch->assertStatus(200);
        $responseDateMismatch->assertDontSee('PR-2026-FILTER-1');
        $responseDateMismatch->assertDontSee('PR-2026-FILTER-2');

        // 4. Test Faulty Core Return filter
        $responseReturnPending = $this->actingAs($this->superior)->get(route('parts.requests.index', [
            'faulty_return_status' => 'pending_return',
        ]));
        $responseReturnPending->assertStatus(200);
        $responseReturnPending->assertSee('PR-2026-FILTER-2');
        $responseReturnPending->assertDontSee('PR-2026-FILTER-1');

        $responseReturnReturned = $this->actingAs($this->superior)->get(route('parts.requests.index', [
            'faulty_return_status' => 'returned',
        ]));
        $responseReturnReturned->assertStatus(200);
        $responseReturnReturned->assertDontSee('PR-2026-FILTER-2');
        $responseReturnReturned->assertDontSee('PR-2026-FILTER-1');
    }

    public function test_office_staff_role_permissions_and_report_restrictions(): void
    {
        // 1. Office staff can access permitted operational modules
        $this->actingAs($this->officeStaff)->get(route('tickets.open'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('parts.envelopes.index'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('workshop.index'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('approvals.index'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('parts.stock.index'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('parts.grn.index'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('parts.transfers.index'))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('parts.requests.index'))->assertStatus(200);

        // 2. Office staff is blocked from financial expenses
        $this->actingAs($this->officeStaff)->get(route('expenses.index'))->assertStatus(403);

        // 3. Office staff reports access is strictly restricted to machine_faults tab
        $this->actingAs($this->officeStaff)->get(route('reports.index', ['tab' => 'machine_faults']))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('reports.index', ['tab' => 'overview']))->assertRedirect(route('reports.index', ['tab' => 'machine_faults']));
        $this->actingAs($this->officeStaff)->get(route('reports.index', ['tab' => 'bank_tickets']))->assertRedirect(route('reports.index', ['tab' => 'machine_faults']));

        // 4. Office staff CSV/PDF export restrictions
        $this->actingAs($this->officeStaff)->get(route('reports.export-csv', ['type' => 'machine_faults']))->assertStatus(200);
        $this->actingAs($this->officeStaff)->get(route('reports.export-csv', ['type' => 'bank_tickets']))->assertStatus(403);
        $this->actingAs($this->officeStaff)->get(route('reports.export-pdf', ['type' => 'bank_tickets']))->assertStatus(403);

        // 5. Office staff does not see or access Dashboard and Complaints Registry
        $this->actingAs($this->officeStaff)->get(route('dashboard'))->assertRedirect(route('tickets.open'));
        $this->actingAs($this->officeStaff)->get(route('tickets.index'))->assertRedirect(route('tickets.open'));

        $openView = $this->actingAs($this->officeStaff)->get(route('tickets.open'));
        $openView->assertStatus(200);
        $openView->assertDontSee('Complaints Registry');
        $openView->assertDontSee('<span class="flex-1">Dashboard</span>', false);
    }
}

