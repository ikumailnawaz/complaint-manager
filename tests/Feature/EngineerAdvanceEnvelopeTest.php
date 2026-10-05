<?php

namespace Tests\Feature;

use App\Models\EngineerAdvanceTransaction;
use App\Models\EngineerInventory;
use App\Models\Location;
use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartStockLedger;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EngineerEnvelopeService;
use App\Services\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineerAdvanceEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $engineer;
    protected Location $warehouse;
    protected MachineModel $model;
    protected Part $part;
    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@bankcomplaints.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']
        );

        $this->manager = User::firstOrCreate(
            ['email' => 'ops_test@bankcomplaints.com'],
            ['name' => 'Operations Manager', 'password' => bcrypt('password'), 'role' => 'operations_manager']
        );

        $this->engineer = User::firstOrCreate(
            ['email' => 'engineer_test@bankcomplaints.com'],
            ['name' => 'Field Engineer Kumail', 'password' => bcrypt('password'), 'role' => 'engineer', 'base_city' => 'Lahore']
        );

        $this->warehouse = Location::where('name', 'Lahore Head Office')->first() ?? Location::create([
            'name' => 'Lahore Head Office', 'city' => 'Lahore', 'type' => 'warehouse', 'is_active' => true,
        ]);

        $this->model = MachineModel::firstOrCreate(
            ['name' => 'BM-350'],
            ['brand' => 'Glory', 'is_active' => true]
        );

        $this->part = Part::firstOrCreate(
            ['part_number' => 'BM350-0002'],
            ['name' => 'cutter', 'unit' => 'pcs', 'unit_cost' => 1500, 'is_active' => true]
        );

        // Ensure initial warehouse stock
        PartStockLedger::updateOrCreate(
            ['part_id' => $this->part->id, 'location_id' => $this->warehouse->id],
            ['qty_on_hand' => 10, 'qty_reserved' => 0]
        );

        $this->ticket = Ticket::create([
            'ticket_no' => 'TKT-TEST-901',
            'bank_name' => 'Habib Bank Limited',
            'branch_name' => 'Main Mall Branch',
            'branch_location' => 'Lahore',
            'customer_name' => 'Branch Manager',
            'machine_type' => 'Cash Sorter',
            'machine_model' => 'BM-350',
            'machine_serial_no' => 'SN-BM-8899',
            'status' => 'in_progress',
            'issue_summary' => 'Paper cutter jamming',
            'assigned_engineer_id' => $this->engineer->id,
        ]);
    }

    public function test_manager_can_lend_advance_parts_to_engineer(): void
    {
        $response = $this->actingAs($this->manager)->post(route('parts.envelopes.lend'), [
            'engineer_id'        => $this->engineer->id,
            'source_location_id' => $this->warehouse->id,
            'part_id'            => $this->part->id,
            'qty'                => 3,
            'notes'              => 'Advance float for monthly calls',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify engineer envelope balance
        $envelope = EngineerInventory::where('engineer_id', $this->engineer->id)
            ->where('part_id', $this->part->id)
            ->first();

        $this->assertNotNull($envelope);
        $this->assertEquals(3, $envelope->qty_allocated);
        $this->assertEquals(3, $envelope->qty_on_hand);
        $this->assertEquals(0, $envelope->qty_used);

        // Verify warehouse stock was debited from 10 to 7
        $ledger = PartStockLedger::where('part_id', $this->part->id)
            ->where('location_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(7, $ledger->qty_on_hand);

        // Verify transaction logged
        $tx = EngineerAdvanceTransaction::where('engineer_id', $this->engineer->id)
            ->where('part_id', $this->part->id)
            ->where('type', 'advance_issue')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(3, $tx->qty);
        $this->assertEquals($this->warehouse->id, $tx->source_location_id);
    }

    public function test_engineer_cannot_lend_advance_stock_to_others(): void
    {
        $response = $this->actingAs($this->engineer)->post(route('parts.envelopes.lend'), [
            'engineer_id'        => $this->engineer->id,
            'source_location_id' => $this->warehouse->id,
            'part_id'            => $this->part->id,
            'qty'                => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_part_request_submission_always_routes_to_pending_stock_check_without_engineer_self_deduction(): void
    {
        // 1. Lend 2 units of cutter to engineer
        $envelopeService = app(EngineerEnvelopeService::class);
        $envelopeService->lendAdvanceStock($this->engineer->id, $this->warehouse->id, $this->part->id, 2, 'Advance Float');

        $envelope = EngineerInventory::where('engineer_id', $this->engineer->id)->where('part_id', $this->part->id)->first();
        $this->assertEquals(2, $envelope->qty_on_hand);

        // 2. Engineer files a Part Request for 1 unit of cutter on ticket
        $response = $this->actingAs($this->engineer)->post(route('parts.requests.store'), [
            'ticket_id'         => $this->ticket->id,
            'machine_model_id'  => $this->model->id,
            'machine_serial_no' => 'SN-BM-8899',
            'fault_description' => 'Cutter blade blunt',
            'items'             => [
                ['part_id' => $this->part->id, 'qty' => 1, 'note' => 'Replacement cutter needed'],
            ],
        ]);

        $response->assertRedirect();
        $pr = PartRequest::where('ticket_id', $this->ticket->id)->first();
        $this->assertNotNull($pr);

        // Engineer does NOT self-deduct: qty_requested = 1, qty_from_envelope = 0
        $item = $pr->items->first();
        $this->assertEquals(0, $item->qty_from_envelope);
        $this->assertEquals(1, $item->qty_requested);

        // Request stays in pending_stock_check for management review
        $this->assertEquals('pending_stock_check', $pr->fresh()->status);

        // Envelope on hand is STILL untouched at 2
        $envelope->refresh();
        $this->assertEquals(2, $envelope->qty_on_hand);
        $this->assertEquals(0, $envelope->qty_used);
    }

    public function test_admin_can_dispatch_and_deduct_directly_from_engineer_envelope(): void
    {
        $envelopeService = app(EngineerEnvelopeService::class);
        $envelopeService->lendAdvanceStock($this->engineer->id, $this->warehouse->id, $this->part->id, 3, 'Advance Float');

        // Create approved Part Request
        $pr = PartRequest::create([
            'request_number'    => 'PR-TEST-ENV01',
            'ticket_id'         => $this->ticket->id,
            'engineer_id'       => $this->engineer->id,
            'machine_model_id'  => $this->model->id,
            'machine_serial_no' => 'SN-BM-8899',
            'fault_description' => 'Replace cutter',
            'status'            => 'approved',
            'approved_at'       => now(),
            'approved_by_id'    => $this->manager->id,
        ]);
        $item = \App\Models\PartRequestItem::create([
            'part_request_id'   => $pr->id,
            'part_id'           => $this->part->id,
            'qty_requested'     => 2,
            'qty_approved'      => 2,
            'qty_from_envelope' => 0,
        ]);

        // Admin dispatches using "Employee Envelope" as dispatch source
        $dispatchResp = $this->actingAs($this->manager)->post(route('parts.requests.dispatch', $pr), [
            'dispatch_source' => 'envelope',
        ]);
        $dispatchResp->assertRedirect();

        $pr->refresh();
        $this->assertEquals('dispatched', $pr->status);

        // Item reflects envelope deduction
        $item->refresh();
        $this->assertEquals(2, $item->qty_from_envelope);

        // Envelope on hand is decremented to 1
        $envelope = EngineerInventory::where('engineer_id', $this->engineer->id)->where('part_id', $this->part->id)->first();
        $this->assertEquals(1, $envelope->qty_on_hand);
        $this->assertEquals(2, $envelope->qty_used);

        // Usage audit transaction is logged
        $usageTx = EngineerAdvanceTransaction::where('engineer_id', $this->engineer->id)
            ->where('part_id', $this->part->id)
            ->where('type', 'consumed_complaint')
            ->first();

        $this->assertNotNull($usageTx);
        $this->assertEquals(2, $usageTx->qty);
        $this->assertEquals($this->ticket->id, $usageTx->ticket_id);
    }

    public function test_advance_inventory_dashboard_and_usage_log(): void
    {
        // Lend 2 units and consume 1 unit
        $envelopeService = app(EngineerEnvelopeService::class);
        $envelopeService->lendAdvanceStock($this->engineer->id, $this->warehouse->id, $this->part->id, 2);
        $envelopeService->consumeFromEnvelope($this->engineer->id, $this->part->id, 1, $this->ticket->id);

        // 1. Visit envelopes index as manager
        $response = $this->actingAs($this->manager)->get(route('parts.envelopes.index'));
        $response->assertStatus(200);
        $response->assertSee('Field Engineer Kumail');
        $response->assertSee('BM350-0002');
        $response->assertSee('cutter');
        $response->assertSee('Lend Advance Parts');

        // 2. Visit usage log ("Where & How Much Used")
        $usageResponse = $this->actingAs($this->manager)->get(route('parts.envelopes.index', ['tab' => 'usage']));
        $usageResponse->assertStatus(200);
        $usageResponse->assertSee('TKT-TEST-901');
        $usageResponse->assertSee('Habib Bank Limited');
        $usageResponse->assertSee('-1');

        // 3. Visit as engineer
        $engResponse = $this->actingAs($this->engineer)->get(route('parts.envelopes.index'));
        $engResponse->assertStatus(200);
        $engResponse->assertSee('My Advance Parts Envelope');
    }

    public function test_engineer_or_manager_can_return_unused_advance_parts_to_warehouse(): void
    {
        $envelopeService = app(EngineerEnvelopeService::class);
        $envelopeService->lendAdvanceStock($this->engineer->id, $this->warehouse->id, $this->part->id, 5);

        // Return 2 units back to warehouse
        $response = $this->actingAs($this->manager)->post(route('parts.envelopes.return'), [
            'engineer_id'             => $this->engineer->id,
            'destination_location_id' => $this->warehouse->id,
            'part_id'                 => $this->part->id,
            'qty'                     => 2,
            'notes'                   => 'Returned surplus back to store',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify envelope has 3 remaining
        $envelope = EngineerInventory::where('engineer_id', $this->engineer->id)->where('part_id', $this->part->id)->first();
        $this->assertEquals(3, $envelope->qty_on_hand);

        // Verify transaction logged
        $tx = EngineerAdvanceTransaction::where('engineer_id', $this->engineer->id)
            ->where('part_id', $this->part->id)
            ->where('type', 'returned_to_warehouse')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(2, $tx->qty);
    }

    public function test_admin_can_dispatch_split_between_envelope_and_warehouse(): void
    {
        // 1. Lend 1 unit to engineer
        $envelopeService = app(EngineerEnvelopeService::class);
        $envelopeService->lendAdvanceStock($this->engineer->id, $this->warehouse->id, $this->part->id, 1, 'Advance Float');

        $envelope = EngineerInventory::where('engineer_id', $this->engineer->id)->where('part_id', $this->part->id)->first();
        $this->assertEquals(1, $envelope->qty_on_hand);

        // 2. Part request for 3 units approved
        $pr = PartRequest::create([
            'request_number'    => 'PR-TEST-SPLIT01',
            'ticket_id'         => $this->ticket->id,
            'engineer_id'       => $this->engineer->id,
            'machine_model_id'  => $this->model->id,
            'machine_serial_no' => 'SN-BM-8899',
            'fault_description' => 'Need 3 cutters',
            'status'            => 'approved',
            'approved_at'       => now(),
            'approved_by_id'    => $this->manager->id,
        ]);
        $item = \App\Models\PartRequestItem::create([
            'part_request_id'   => $pr->id,
            'part_id'           => $this->part->id,
            'qty_requested'     => 3,
            'qty_approved'      => 3,
            'qty_from_envelope' => 0,
        ]);

        // 3. Admin dispatches with split: 1 from envelope, remaining 2 from warehouse
        $dispatchResp = $this->actingAs($this->manager)->post(route('parts.requests.dispatch', $pr), [
            'dispatch_source'      => 'split',
            'dispatch_location_id' => $this->warehouse->id,
            'split_envelope_qtys'  => [$item->id => 1],
        ]);
        $dispatchResp->assertRedirect();

        $pr->refresh();
        $this->assertEquals('dispatched', $pr->status);

        // Item quantities
        $item->refresh();
        $this->assertEquals(1, $item->qty_from_envelope);
        $this->assertEquals(2, $item->qty_dispatched);

        // Envelope has 0 on hand, 1 used
        $envelope->refresh();
        $this->assertEquals(0, $envelope->qty_on_hand);
        $this->assertEquals(1, $envelope->qty_used);

        // Warehouse ledger debited by 2
        $ledger = PartStockLedger::where('part_id', $this->part->id)->where('location_id', $this->warehouse->id)->first();
        // Started with 10, lent 1 -> 9, dispatched 2 -> 7
        $this->assertEquals(7, $ledger->qty_on_hand);
    }
}
