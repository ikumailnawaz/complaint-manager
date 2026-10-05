<?php

namespace Tests\Feature;

use App\Models\DailyFeedback;
use App\Models\Ticket;
use App\Models\TicketLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $fieldEngineer;
    protected User $workshopEngineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03001234567',
            'is_available' => true,
        ]);

        $this->fieldEngineer = User::create([
            'name' => 'Usman Field Tech',
            'email' => 'usman@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Multan',
            'phone_whatsapp' => '03007654321',
            'is_available' => true,
        ]);

        $this->workshopEngineer = User::create([
            'name' => 'Tariq Bench Engineer',
            'email' => 'tariq@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03009988776',
            'is_available' => true,
        ]);
    }

    public function test_full_workshop_lifecycle_and_smart_transit_tracking()
    {
        // 1. Complaint Created & Assigned to Field Engineer Usman
        $createdAt = Carbon::now()->subDays(4);
        $ticket = Ticket::create([
            'ticket_no'            => 'TKT-TEST-9901',
            'bank_name'            => 'Meezan Bank Ltd',
            'branch_name'          => 'Bosan Road Branch',
            'branch_location'      => 'Multan',
            'machine_type'         => 'Cash Sorter',
            'machine_model'        => 'Glory USF-52',
            'machine_serial_no'    => 'GLY-778899',
            'status'               => 'in_progress',
            'urgency'              => 'high',
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'assigned_at'          => $createdAt,
            'created_at'           => $createdAt,
        ]);

        $this->assertEquals($this->fieldEngineer->id, $ticket->assigned_engineer_id);

        // 2. STAGE 1: Field Visit & Dispatch to Workshop (Day 1 on-site)
        // Machine cannot be fixed on-site; field engineer / admin dispatches it via TCS Cargo.
        $dispatchResponse = $this->actingAs($this->admin)->post(route('tickets.workshop', $ticket), [
            'workshop_location'          => 'Lahore Central Workshop',
            'workshop_dispatch_courier'  => 'TCS Cargo Express',
            'workshop_dispatch_tracking' => 'TCS-88990011',
            'notes'                      => 'Main sensor PCB damaged, sent for multi-layer soldering and sensor calibration.',
        ]);

        $dispatchResponse->assertRedirect();
        $ticket->refresh();

        // Verify: Ticket status is awaiting_workshop
        $this->assertEquals('awaiting_workshop', $ticket->status);
        $this->assertEquals('Lahore Central Workshop', $ticket->workshop_location);
        $this->assertEquals('TCS Cargo Express', $ticket->workshop_dispatch_courier);
        $this->assertEquals('TCS-88990011', $ticket->workshop_dispatch_tracking);
        // CRUCIAL: Ticket MUST REMAIN assigned to field engineer Usman during transit!
        $this->assertEquals($this->fieldEngineer->id, $ticket->assigned_engineer_id);
        $this->assertEquals($this->fieldEngineer->id, $ticket->original_field_engineer_id);
        $this->assertTrue($ticket->isInWorkshopTransit());

        // 3. STAGE 2: Transit Days Feedbacks
        // Feedback logged while ticket is in transit under field engineer
        $fbResponse = $this->actingAs($this->admin)->post(route('tickets.feedback', $ticket), [
            'action_taken'  => 'Machine in Transit to Workshop',
            'feedback_text' => 'Bilty # TCS-88990011 tracked at Multan Cargo Hub, en route to Lahore Central Workshop.',
        ]);
        $fbResponse->assertRedirect();
        $this->assertDatabaseHas('ticket_feedbacks', [
            'ticket_id'    => $ticket->id,
            'action_taken' => 'Machine in Transit to Workshop',
        ]);

        // 4. STAGE 3: Physical Workshop Intake & Bench Handover
        // 3 days later, machine arrives at Lahore Workshop. Admin receives it and re-assigns Tariq (workshop bench tech).
        $intakeResponse = $this->actingAs($this->admin)->post(route('tickets.workshop-receive', $ticket), [
            'workshop_engineer_id'    => $this->workshopEngineer->id,
            'workshop_intake_remarks' => 'Received safely in wooden crate via TCS. Optical sensor PCB verified burnt, queuing for bench micro-soldering.',
        ]);

        $intakeResponse->assertRedirect();
        $ticket->refresh();

        // Verify: Status is in_workshop_repair
        $this->assertEquals('in_workshop_repair', $ticket->status);
        $this->assertNotNull($ticket->workshop_received_at);
        $this->assertEquals($this->admin->id, $ticket->workshop_received_by_id);
        // CRUCIAL: Ticket is NOW transferred to the Workshop Engineer!
        $this->assertEquals($this->workshopEngineer->id, $ticket->assigned_engineer_id);
        $this->assertEquals($this->workshopEngineer->id, $ticket->workshop_engineer_id);
        // Original field engineer Usman remains preserved!
        $this->assertEquals($this->fieldEngineer->id, $ticket->original_field_engineer_id);
        $this->assertTrue($ticket->isInWorkshopRepair());

        // SLA Check: Overall Day vs Workshop Bench Day
        $this->assertGreaterThanOrEqual(1, $ticket->overall_ticket_day);
        $this->assertEquals(1, $ticket->workshop_engineer_day); // Day 1 for workshop engineer!

        // 5. STAGE 4: Bench Repair & Testing QA
        // Tariq (Workshop Engineer) completes bench repair and marks it resolved
        $benchResolveResponse = $this->actingAs($this->workshopEngineer)->post(route('tickets.workshop-resolve', $ticket), [
            'workshop_repair_summary' => 'Replaced optical sensor IC and 28-pin flat ribbon cable. Calibrated roller tension. Ran 1,000 note test batch with zero errors.',
        ]);

        $benchResolveResponse->assertRedirect();
        $ticket->refresh();

        $this->assertEquals('workshop_repaired', $ticket->status);
        $this->assertNotNull($ticket->workshop_repaired_at);
        $this->assertStringContainsString('Replaced optical sensor IC', $ticket->workshop_repair_summary);
        $this->assertTrue($ticket->isWorkshopRepaired());

        // 6. STAGE 5: Return Dispatch to Bank Branch
        // Admin ships repaired machine back to Meezan Bank Multan branch
        $returnDispatchResponse = $this->actingAs($this->admin)->post(route('tickets.workshop-return-dispatch', $ticket), [
            'return_courier'         => 'Leopards Priority Cargo',
            'return_tracking_number' => 'LEO-44332211',
            'return_dispatch_notes'  => 'Sealed with tamper-evident tape, addressed to Branch Manager.',
        ]);

        $returnDispatchResponse->assertRedirect();
        $ticket->refresh();

        $this->assertEquals('return_transit', $ticket->status);
        $this->assertEquals('Leopards Priority Cargo', $ticket->return_courier);
        $this->assertEquals('LEO-44332211', $ticket->return_tracking_number);
        $this->assertNotNull($ticket->return_dispatched_at);
        $this->assertTrue($ticket->isReturnTransit());

        // 7. STAGE 6: Confirm Bank Receipt & Final Ticket Closure
        // Bank receives machine, confirms it is working, Admin closes the ticket
        $closeResponse = $this->actingAs($this->admin)->post(route('tickets.workshop-bank-received', $ticket));

        $closeResponse->assertRedirect();
        $ticket->refresh();

        $this->assertEquals('closed', $ticket->status);
        $this->assertNotNull($ticket->bank_received_at);
        $this->assertNotNull($ticket->closed_at);
        $this->assertEquals($this->admin->id, $ticket->bank_received_confirmed_by_id);

        // Turnaround Analytics Verification
        $this->assertNotNull($ticket->field_duration_text);
        $this->assertNotNull($ticket->inbound_transit_duration_text);
        $this->assertNotNull($ticket->workshop_repair_duration_text);
        $this->assertNotNull($ticket->return_transit_duration_text);
        $this->assertNotNull($ticket->total_resolution_duration_text);

        // Audit Trail Check
        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $ticket->id,
            'action'    => 'workshop_completed_closed',
        ]);
    }

    public function test_workshop_hub_index_view_and_filtering()
    {
        // Create tickets in different workshop stages
        $t1 = Ticket::create([
            'ticket_no'            => 'TKT-INBOUND-01',
            'bank_name'            => 'HBL',
            'branch_name'          => 'Main Branch',
            'branch_location'      => 'Karachi',
            'status'               => 'awaiting_workshop',
            'workshop_location'    => 'Lahore Central Workshop',
            'workshop_dispatched_at' => now()->subDays(2),
            'assigned_engineer_id' => $this->fieldEngineer->id,
        ]);

        $t2 = Ticket::create([
            'ticket_no'            => 'TKT-ACTIVE-02',
            'bank_name'            => 'UBL',
            'branch_name'          => 'Mall Branch',
            'branch_location'      => 'Lahore',
            'status'               => 'in_workshop_repair',
            'workshop_location'    => 'Lahore Central Workshop',
            'workshop_dispatched_at' => now()->subDays(3),
            'workshop_received_at' => now()->subDay(),
            'workshop_engineer_id' => $this->workshopEngineer->id,
            'assigned_engineer_id' => $this->workshopEngineer->id,
        ]);

        $responseInbound = $this->actingAs($this->admin)->get(route('workshop.index', ['tab' => 'inbound']));
        $responseInbound->assertOk();
        $responseInbound->assertSee($t1->ticket_no);

        $responseActive = $this->actingAs($this->admin)->get(route('workshop.index', ['tab' => 'active']));
        $responseActive->assertOk();
        $responseActive->assertSee($t2->ticket_no);
    }

    public function test_engineer_cannot_access_workshop_hub()
    {
        // Field Engineer should receive 403 Forbidden
        $response = $this->actingAs($this->fieldEngineer)->get(route('workshop.index'));
        $response->assertStatus(403);

        // Workshop Bench Tech should receive 403 Forbidden
        $responseBench = $this->actingAs($this->workshopEngineer)->get(route('workshop.index'));
        $responseBench->assertStatus(403);

        // Admin can access successfully
        $responseAdmin = $this->actingAs($this->admin)->get(route('workshop.index'));
        $responseAdmin->assertOk();
    }

    public function test_actions_locked_while_ticket_in_workshop_transit()
    {
        $ticket = Ticket::create([
            'ticket_no'            => 'TKT-TRANSIT-LOCKED',
            'bank_name'            => 'Allied Bank',
            'branch_name'          => 'Gulberg Branch',
            'branch_location'      => 'Lahore',
            'status'               => 'awaiting_workshop',
            'workshop_location'    => 'Lahore Central Workshop',
            'workshop_dispatched_at' => now()->subDay(),
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'original_field_engineer_id' => $this->fieldEngineer->id,
        ]);

        // 1. Cannot mark done while in transit
        $resMark = $this->actingAs($this->fieldEngineer)->post(route('tickets.resolve', $ticket), [
            'resolution_summary' => 'Attempting to resolve in transit',
        ]);
        $resMark->assertSessionHas('error');
        $ticket->refresh();
        $this->assertEquals('awaiting_workshop', $ticket->status);

        // 2. Cannot send to workshop again while in transit
        $resWorkshop = $this->actingAs($this->fieldEngineer)->post(route('tickets.workshop', $ticket), [
            'workshop_location' => 'Karachi Workshop',
        ]);
        $resWorkshop->assertSessionHas('error');

        // 3. Cannot request parts while in transit
        $resPartCreate = $this->actingAs($this->fieldEngineer)->get(route('parts.requests.create', ['ticket_id' => $ticket->id]));
        $resPartCreate->assertRedirect();
        $resPartCreate->assertSessionHas('error');
    }

    public function test_strict_expense_claim_rules_for_workshop_tickets()
    {
        $ticket = Ticket::create([
            'ticket_no'            => 'TKT-EXP-POLICY',
            'bank_name'            => 'Bank Alfalah',
            'branch_name'          => 'Cantt Branch',
            'branch_location'      => 'Multan',
            'status'               => 'awaiting_workshop',
            'workshop_location'    => 'Lahore Central Workshop',
            'workshop_dispatched_at' => now()->subDays(2),
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'original_field_engineer_id' => $this->fieldEngineer->id,
        ]);

        // Rule: Field engineer CANNOT claim expense while machine is in transit
        $this->assertFalse($ticket->canClaimExpense($this->fieldEngineer->id));
        $resTransitClaim = $this->actingAs($this->fieldEngineer)->post(route('expenses.store'), [
            'ticket_id'      => $ticket->id,
            'trip_type'      => 'round_trip',
            'from_city'      => 'Multan',
            'to_city'        => 'Multan City',
            'claimed_amount' => 500,
        ]);
        $resTransitClaim->assertSessionHas('error');

        // Intake machine into workshop and assign bench technician
        $ticket->update([
            'status' => 'in_workshop_repair',
            'workshop_received_at' => now(),
            'workshop_engineer_id' => $this->workshopEngineer->id,
            'assigned_engineer_id' => $this->workshopEngineer->id,
        ]);
        $ticket->refresh();

        // Rule: Bench technician CANNOT claim tour expense
        $this->assertFalse($ticket->canClaimExpense($this->workshopEngineer->id));
        $resBenchClaim = $this->actingAs($this->workshopEngineer)->post(route('expenses.store'), [
            'ticket_id'      => $ticket->id,
            'trip_type'      => 'round_trip',
            'from_city'      => 'Lahore',
            'to_city'        => 'Workshop',
            'claimed_amount' => 300,
        ]);
        $resBenchClaim->assertSessionHas('error');

        // Rule: Field engineer CAN claim tour expense once unit is received at workshop
        $this->assertTrue($ticket->canClaimExpense($this->fieldEngineer->id));
        $resFieldClaim = $this->actingAs($this->fieldEngineer)->post(route('expenses.store'), [
            'ticket_id'      => $ticket->id,
            'trip_type'      => 'round_trip',
            'from_city'      => 'Multan',
            'to_city'        => 'Multan Branch',
            'claimed_amount' => 800,
        ]);
        $resFieldClaim->assertRedirect();
        $this->assertDatabaseHas('expense_claims', [
            'ticket_id'   => $ticket->id,
            'engineer_id' => $this->fieldEngineer->id,
        ]);
        // Rule: Normal on-site ticket in_progress CANNOT claim tour expense before mark done
        $normalTicket = Ticket::create([
            'ticket_no'            => 'TKT-NORMAL-INPROG',
            'bank_name'            => 'Standard Chartered',
            'branch_name'          => 'Main Branch',
            'branch_location'      => 'Multan',
            'status'               => 'in_progress',
            'assigned_engineer_id' => $this->fieldEngineer->id,
        ]);
        $this->assertFalse($normalTicket->canClaimExpense($this->fieldEngineer->id));
        $resNormalClaim = $this->actingAs($this->fieldEngineer)->post(route('expenses.store'), [
            'ticket_id'      => $normalTicket->id,
            'trip_type'      => 'round_trip',
            'from_city'      => 'Multan',
            'to_city'        => 'SCB Branch',
            'claimed_amount' => 400,
        ]);
        $resNormalClaim->assertSessionHas('error');

        // Once normal ticket is marked done (resolved), expense claim unlocks!
        $normalTicket->update(['status' => 'resolved']);
        $this->assertTrue($normalTicket->canClaimExpense($this->fieldEngineer->id));
    }

    public function test_bench_technician_mark_done_transitions_to_workshop_repaired_and_visible_in_central_workshop()
    {
        $ticket = Ticket::create([
            'ticket_no'            => 'TKT-BENCH-DONE',
            'bank_name'            => 'MCB Bank',
            'branch_name'          => 'Main Branch',
            'branch_location'      => 'Sialkot',
            'status'               => 'in_workshop_repair',
            'workshop_location'    => 'Lahore Central Workshop',
            'workshop_dispatched_at' => now()->subDays(3),
            'workshop_received_at' => now()->subDay(),
            'original_field_engineer_id' => $this->fieldEngineer->id,
            'workshop_engineer_id' => $this->workshopEngineer->id,
            'assigned_engineer_id' => $this->workshopEngineer->id,
        ]);

        // When bench engineer marks ticket done from engineer dashboard or ticket index
        $res = $this->actingAs($this->workshopEngineer)->post(route('tickets.resolve', $ticket), [
            'resolution_summary' => 'Bench repair finished and tested successfully.',
        ]);
        $res->assertRedirect();

        $ticket->refresh();
        // Status should be workshop_repaired (not generic resolved)
        $this->assertEquals('workshop_repaired', $ticket->status);
        $this->assertNotNull($ticket->workshop_repaired_at);

        // It MUST appear in Central Workshop under 'repaired' tab for admin to dispatch back to bank
        $resWorkshop = $this->actingAs($this->admin)->get(route('workshop.index', ['tab' => 'repaired']));
        $resWorkshop->assertOk();
        $resWorkshop->assertSee($ticket->ticket_no);
        $resWorkshop->assertSee('Dispatch Back to Bank');
    }

    public function test_original_field_engineer_retains_visibility_and_expense_claim_in_tickets_registry()
    {
        // Ticket dispatched from field and received at Central Workshop
        $ticket = Ticket::create([
            'ticket_no'                  => 'TKT-FIELD-REGISTRY-01',
            'bank_name'                  => 'Habib Metropolitan Bank',
            'branch_name'                => 'Gulberg Branch',
            'branch_location'            => 'Lahore',
            'status'                     => 'in_workshop_repair',
            'workshop_location'          => 'Lahore Central Workshop',
            'workshop_dispatched_at'     => now()->subDays(2),
            'workshop_received_at'       => now()->subDay(),
            'original_field_engineer_id' => $this->fieldEngineer->id,
            'workshop_engineer_id'       => $this->workshopEngineer->id,
            'assigned_engineer_id'       => $this->workshopEngineer->id,
        ]);

        // Original field engineer visits Complaints & Tickets Registry (/tickets)
        $resIndex = $this->actingAs($this->fieldEngineer)->get(route('tickets.index'));
        $resIndex->assertOk();
        // Must be visible in his registry!
        $resIndex->assertSee($ticket->ticket_no);
        // Must show Claim Tour Expense button for him
        $resIndex->assertSee('Claim Tour Expense');

        // Original field engineer visits Engineer Dashboard (/dashboard)
        $resDash = $this->actingAs($this->fieldEngineer)->get(route('dashboard'));
        $resDash->assertOk();
        $resDash->assertSee($ticket->ticket_no);
        $resDash->assertSee('Claim Tour Expense');

        // Bench tech visits Complaints Registry: sees ticket but NO Claim Tour Expense option
        $resBenchIndex = $this->actingAs($this->workshopEngineer)->get(route('tickets.index'));
        $resBenchIndex->assertOk();
        $resBenchIndex->assertSee($ticket->ticket_no);
        $resBenchIndex->assertSee('Mark Bench Done');
    }
}

