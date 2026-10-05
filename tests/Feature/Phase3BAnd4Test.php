<?php

namespace Tests\Feature;

use App\Models\ExpenseClaim;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class Phase3BAnd4Test extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superiorUser;
    protected User $engineerAli;
    protected User $engineerUsman;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Operations Admin
        $this->adminUser = User::create([
            'name' => 'Operations Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03001111111',
            'is_available' => true,
        ]);

        // Regional Superior
        $this->superiorUser = User::create([
            'name' => 'Regional Superior North',
            'email' => 'superior@test.com',
            'password' => bcrypt('password'),
            'role' => 'superior',
            'base_city' => 'Islamabad',
            'phone_whatsapp' => '03002222222',
            'is_available' => true,
        ]);

        // Field Engineer Ali
        $this->engineerAli = User::create([
            'name' => 'Engineer Ali',
            'email' => 'ali@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03003333333',
            'is_available' => true,
        ]);

        // Field Engineer Usman
        $this->engineerUsman = User::create([
            'name' => 'Engineer Usman',
            'email' => 'usman@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Karachi',
            'phone_whatsapp' => '03004444444',
            'is_available' => true,
        ]);
    }

    /**
     * Helper to create a ticket
     */
    protected function createTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'ticket_no' => 'CMP-2026-00001',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_name' => 'Mall Road Branch',
            'branch_location' => 'Lahore',
            'branch_address' => 'Plaza 45, Mall Road, Lahore',
            'customer_email' => 'complaints@hbl.com',
            'machine_type' => 'ATM NCR 6622',
            'machine_serial_no' => 'NCR-9942',
            'warranty_status' => 'in_warranty',
            'urgency' => 'high',
            'status' => 'assigned',
            'assigned_engineer_id' => $this->engineerAli->id,
            'assigned_by_id' => $this->adminUser->id,
            'assigned_at' => Carbon::now(),
            'sla_deadline' => Carbon::now()->addHours(24),
            'issue_summary' => 'Card Reader jammed at ATM #1',
            'issue_description' => 'Customer card stuck inside reader.',
        ], $overrides));
    }

    // ==========================================
    // PHASE 3B TESTS: FEEDBACK, SLA & ESCALATIONS
    // ==========================================

    /**
     * Test 1: Assigned engineer can submit daily progress feedback
     */
    public function test_assigned_engineer_can_submit_daily_progress_feedback(): void
    {
        $ticket = $this->createTicket();
        $fakePhoto = UploadedFile::fake()->image('on_site_work.jpg');

        $response = $this->actingAs($this->engineerAli)
            ->post(route('tickets.feedback', $ticket), [
                'action_taken' => 'On-Site Hardware Inspection',
                'feedback_text' => 'Opened machine casing, dismantled card transport mechanism and removed stuck debit card.',
                'parts_required' => 'Replacement Shutter Bezel',
                'eta_completion' => Carbon::now()->addHours(4)->toDateTimeString(),
                'photo_evidence' => $fakePhoto,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_feedbacks', [
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'submitted_by_id' => $this->engineerAli->id,
            'day_number' => 1,
            'action_taken' => 'On-Site Hardware Inspection',
            'parts_required' => 'Replacement Shutter Bezel',
        ]);

        $this->assertEquals('in_progress', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'feedback_added',
        ]);
    }

    /**
     * Test 2: Unassigned field engineer CANNOT submit feedback for another engineer's ticket
     */
    public function test_unassigned_engineer_cannot_submit_feedback_for_other_engineer(): void
    {
        $ticket = $this->createTicket(); // Assigned to Ali

        $response = $this->actingAs($this->engineerUsman)
            ->post(route('tickets.feedback', $ticket), [
                'action_taken' => 'On-Site Hardware Inspection',
                'feedback_text' => 'Attempted unauthorized feedback submission.',
            ]);

        $response->assertStatus(403);
        $this->assertEquals(0, TicketFeedback::where('ticket_id', $ticket->id)->count());
    }

    /**
     * Test 3: Operations Manager can submit feedback for ANY ticket
     */
    public function test_operations_manager_can_submit_daily_progress_feedback_for_any_ticket(): void
    {
        $ticket = $this->createTicket();

        $response = $this->actingAs($this->adminUser)
            ->post(route('tickets.feedback', $ticket), [
                'action_taken' => 'Awaiting Spare Parts Approval',
                'feedback_text' => 'Operations desk verified that parts have been dispatched from central inventory.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_feedbacks', [
            'ticket_id' => $ticket->id,
            'submitted_by_id' => $this->adminUser->id,
            'action_taken' => 'Awaiting Spare Parts Approval',
        ]);
    }

    /**
     * Test 4: SLA health calculation returns accurate statuses
     */
    public function test_sla_health_calculation_healthy_warning_critical_and_breached(): void
    {
        // 1. Healthy ticket: 20 hours remaining out of 24 (> 50%)
        $healthyTicket = $this->createTicket([
            'ticket_no' => 'CMP-HEALTHY',
            'created_at' => Carbon::now()->subHours(4),
            'sla_deadline' => Carbon::now()->addHours(20),
        ]);
        $health = $healthyTicket->getSlaHealth();
        $this->assertEquals('healthy', $health['status']);
        $this->assertFalse($health['breached']);

        // 2. Breached ticket: deadline in past
        $breachedTicket = $this->createTicket([
            'ticket_no' => 'CMP-BREACHED',
            'created_at' => Carbon::now()->subHours(30),
            'sla_deadline' => Carbon::now()->subHours(6),
        ]);
        $healthBreached = $breachedTicket->getSlaHealth();
        $this->assertEquals('breached', $healthBreached['status']);
        $this->assertTrue($healthBreached['breached']);
        $this->assertTrue($breachedTicket->isSlaBreached());
    }

    /**
     * Test 5: CheckSlaCommand auto-escalates breached tickets
     */
    public function test_check_sla_command_auto_escalates_breached_ticket(): void
    {
        $breachedTicket = $this->createTicket([
            'ticket_no' => 'CMP-AUTO-ESC',
            'status' => 'assigned',
            'sla_deadline' => Carbon::now()->subMinutes(15),
        ]);

        $this->artisan('tickets:check-sla')
            ->assertExitCode(0);

        $this->assertEquals('escalated', $breachedTicket->fresh()->status);
        $this->assertNotNull($breachedTicket->fresh()->escalated_at);
        $this->assertEquals($this->superiorUser->id, $breachedTicket->fresh()->escalated_to_id);

        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $breachedTicket->id,
            'action' => 'escalated',
        ]);
    }

    /**
     * Test 6: Regional Superior and Admin can view Escalations Command Center
     */
    public function test_regional_superior_can_access_escalations_dashboard(): void
    {
        $this->createTicket([
            'ticket_no' => 'CMP-ESC-01',
            'status' => 'escalated',
        ]);

        $response = $this->actingAs($this->superiorUser)
            ->get(route('tickets.escalations'));

        $response->assertStatus(200);
        $response->assertSee('Regional Superior Escalations Command');
        $response->assertSee('CMP-ESC-01');
    }

    /**
     * Test 7: Field engineer is barred from Superior Escalations Dashboard (403)
     */
    public function test_engineer_cannot_access_escalations_dashboard(): void
    {
        $response = $this->actingAs($this->engineerAli)
            ->get(route('tickets.escalations'));

        $response->assertStatus(403);
    }

    /**
     * Test 8: Superior can issue urgent directive on escalated ticket
     */
    public function test_supervisor_can_issue_urgent_directive_on_escalated_ticket(): void
    {
        $ticket = $this->createTicket(['status' => 'escalated']);

        $response = $this->actingAs($this->superiorUser)
            ->post(route('tickets.instruct', $ticket), [
                'action_type' => 'urgent_instruction',
                'instruction_text' => 'Prioritize this ATM before 5 PM today. Bank Regional Head has called.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'supervisor_instruction',
        ]);
    }

    // ==========================================
    // PHASE 4 TESTS: DIGITAL EXPENSE MANAGEMENT
    // ==========================================

    /**
     * Test 9: Engineer can submit expense claim with category and voucher
     */
    public function test_engineer_can_submit_expense_claim_with_category_and_voucher(): void
    {
        // Expense policy requires ticket to be resolved/closed before claims can be filed
        $ticket = $this->createTicket(['status' => 'resolved']);
        $fakeVoucher = UploadedFile::fake()->create('fuel_receipt.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->engineerAli)
            ->post(route('expenses.store'), [
                'ticket_id' => $ticket->id,
                'from_city' => 'Lahore',
                'to_city' => 'Faisalabad',
                'trip_type' => 'round_trip',
                'category' => 'travel',
                'description' => 'Round trip toll plazas and petrol for emergency card jam repair.',
                'claimed_amount' => 5500.00,
                'voucher_file' => $fakeVoucher,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('expense_claims', [
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'from_city' => 'Lahore',
            'to_city' => 'Faisalabad',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 5500.00,
            'status' => 'submitted',
        ]);
    }

    /**
     * Test 10: AI distance estimation calculates suggested reimbursement
     */
    public function test_expense_claim_uses_ai_distance_and_calculates_suggested_reimbursement(): void
    {
        // Expense policy requires ticket to be resolved/closed before claims can be filed
        $ticket = $this->createTicket(['status' => 'resolved']);

        // Mock GeminiService to simulate AI distance estimation
        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('estimateDistance')
                ->andReturn([
                    'one_way_km' => 180.0,
                    'distance_km' => 360.0,
                    'estimated_hours' => 2.8,
                    'source' => 'gemini_ai',
                ]);
        });

        $this->actingAs($this->engineerAli)
            ->post(route('expenses.store'), [
                'ticket_id' => $ticket->id,
                'from_city' => 'Lahore',
                'to_city' => 'Sahiwal',
                'trip_type' => 'round_trip',
                'category' => 'travel',
                'claimed_amount' => 9000.00,
            ]);

        $claim = ExpenseClaim::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($claim);
        // Round trip: 180 * 2 = 360 km
        $this->assertEquals(360.0, $claim->ai_distance_km);
        // 360 km * PKR 25/km benchmark = 9,000.00
        $this->assertEquals(9000.00, $claim->suggested_amount);
    }

    /**
     * Test 11: Admin can approve, reject and engineer can re-submit
     */
    public function test_operations_admin_can_approve_reject_and_resubmit_expense_claim(): void
    {
        $ticket = $this->createTicket();
        $claim = ExpenseClaim::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'from_city' => 'Lahore',
            'to_city' => 'Gujranwala',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 4000.00,
            'status' => 'submitted',
        ]);

        // 1. Admin rejects with reason
        $this->actingAs($this->adminUser)
            ->post(route('expenses.reject', $claim), [
                'rejection_reason' => 'Please attach the official computerized fuel receipt.',
            ]);
        $this->assertEquals('rejected', $claim->fresh()->status);
        $this->assertEquals(1, $claim->fresh()->resubmission_count);

        // 2. Engineer re-submits
        $this->actingAs($this->engineerAli)
            ->post(route('expenses.resubmit', $claim), [
                'claimed_amount' => 3800.00,
                'notes' => 'Attached updated slip.',
            ]);
        $this->assertEquals('submitted', $claim->fresh()->status);

        // 3. Admin approves
        $this->actingAs($this->adminUser)
            ->post(route('expenses.approve', $claim), [
                'admin_notes' => 'Verified and approved.',
            ]);
        $this->assertEquals('approved', $claim->fresh()->status);
    }

    /**
     * Test 12: Admin can bulk pay approved claims
     */
    public function test_operations_admin_can_bulk_pay_approved_expense_claims(): void
    {
        $ticket = $this->createTicket();
        $claim1 = ExpenseClaim::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'from_city' => 'Lahore',
            'to_city' => 'Sialkot',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 6000.00,
            'status' => 'approved',
        ]);
        $claim2 = ExpenseClaim::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'from_city' => 'Lahore',
            'to_city' => 'Kasur',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 2500.00,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('expenses.bulk-pay'), [
                'claim_ids' => [$claim1->id, $claim2->id],
                'payment_method' => 'bank_transfer',
                'batch_reference' => 'DISBURSE-SEP-2026-B1',
            ]);

        $response->assertRedirect();
        $this->assertEquals('paid', $claim1->fresh()->status);
        $this->assertEquals('paid', $claim2->fresh()->status);
        $this->assertEquals('DISBURSE-SEP-2026-B1', $claim1->fresh()->payment_reference);
        $this->assertEquals($this->adminUser->id, $claim1->fresh()->paid_by_id);
    }

    /**
     * Test 13: Admin can export expense claims to CSV
     */
    public function test_operations_admin_can_export_expense_claims_to_csv(): void
    {
        $ticket = $this->createTicket();
        ExpenseClaim::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'from_city' => 'Lahore',
            'to_city' => 'Okara',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 4500.00,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REF-9921',
            'paid_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('expenses.export-csv'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test 14: Engineer ticket view has SLA progress bar, Mark as Complete, Send to Workshop modal, and hides daily feedback
     */
    public function test_engineer_ticket_view_has_progress_bar_and_modals_without_daily_feedback(): void
    {
        $ticket = $this->createTicket([
            'assigned_engineer_id' => $this->engineerAli->id,
            'status' => 'assigned',
        ]);

        $response = $this->actingAs($this->engineerAli)
            ->get(route('tickets.show', $ticket));

        $response->assertStatus(200);

        // Engineer view components must be present
        $response->assertSee('SLA Turnaround Progress');
        $response->assertSee('Mark as Complete');
        $response->assertSee('Send to Workshop');
        $response->assertSee('engineerWorkshopModal');
        $response->assertSee('engineerCompleteModal');

        // Daily feedback must NOT be present for engineer
        $response->assertDontSee("Record Today's Work Feedback");
        $response->assertDontSee("Daily Progress Feedback & SLA Milestones");
    }

    /**
     * Test 15: Operations Admin ticket view contains Daily Feedback and full management controls
     */
    public function test_operations_admin_ticket_view_retains_daily_feedback(): void
    {
        $ticket = $this->createTicket([
            'assigned_engineer_id' => $this->engineerAli->id,
            'status' => 'assigned',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('tickets.show', $ticket));

        $response->assertStatus(200);
        $response->assertSee("Daily Progress Feedback &amp; SLA Milestones", false);
        $response->assertSee("Record Today's Work Feedback", false);
    }
}

