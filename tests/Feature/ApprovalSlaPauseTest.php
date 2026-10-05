<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalSlaPauseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $fieldEngineer;
    protected User $otherEngineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Manager',
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

        $this->otherEngineer = User::create([
            'name' => 'Bilal Tech',
            'email' => 'bilal@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Karachi',
            'phone_whatsapp' => '03001122334',
            'is_available' => true,
        ]);
    }

    public function test_engineer_can_pause_sla_and_mark_ticket_awaiting_approval()
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-01',
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_name' => 'Main Mall Branch',
            'branch_location' => 'Multan',
            'machine_type' => 'Cash Sorting Machine',
            'machine_model' => 'Julong JL-206',
            'machine_serial_no' => 'JL206-8899',
            'urgency' => 'high',
            'status' => 'in_progress',
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'assigned_at' => Carbon::now()->subHours(10),
            'sla_deadline' => Carbon::now()->addHours(62), // 3 days TAT initially, 10 hours elapsed, 62 hours remaining
        ]);

        // Field engineer pauses SLA for approval
        $response = $this->actingAs($this->fieldEngineer)->post(route('tickets.request-approval', $ticket), [
            'approval_source' => 'Bank Branch Manager',
            'reason' => 'Vault access permission and board replacement cost authorization needed from branch manager.',
        ]);

        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('awaiting_approval', $ticket->status);
        $this->assertNotNull($ticket->sla_paused_at);
        $this->assertNotNull($ticket->approval_requested_at);
        $this->assertEquals($this->fieldEngineer->id, $ticket->approval_requested_by_id);
        $this->assertEquals('Bank Branch Manager', $ticket->approval_source);
        $this->assertEquals('Vault access permission and board replacement cost authorization needed from branch manager.', $ticket->approval_request_reason);

        // Ticket log recorded
        $log = TicketLog::where('ticket_id', $ticket->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('approval_requested', $log->action);

        // SLA Health reports frozen/paused
        $slaHealth = $ticket->getSlaHealth();
        $this->assertTrue($slaHealth['is_paused']);
        $this->assertEquals('paused', $slaHealth['status']);
        $this->assertStringContainsString('SLA Paused', $slaHealth['label']);
    }

    public function test_engineer_can_pause_sla_with_simple_1_click_without_form_inputs()
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-SIMPLE',
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_location' => 'Lahore',
            'status' => 'in_progress',
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'sla_deadline' => Carbon::now()->addHours(24),
        ]);

        // Simple 1-click POST with empty payload
        $response = $this->actingAs($this->fieldEngineer)->post(route('tickets.request-approval', $ticket), []);

        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('awaiting_approval', $ticket->status);
        $this->assertNotNull($ticket->sla_paused_at);
        $this->assertNotNull($ticket->approval_requested_at);
        $this->assertEquals('Customer / Bank Sign-off', $ticket->approval_source);
        $this->assertStringContainsString('Work put on hold', $ticket->approval_request_reason);

        // Ticket log auto-logged
        $log = TicketLog::where('ticket_id', $ticket->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('approval_requested', $log->action);
    }

    public function test_admin_views_pending_approvals_hub_and_engineer_is_forbidden()
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-02',
            'bank_name' => 'United Bank Limited (UBL)',
            'branch_name' => 'Cantt Branch',
            'branch_location' => 'Multan',
            'machine_type' => 'Note Sorter',
            'machine_model' => 'Kisan Newton-A',
            'status' => 'awaiting_approval',
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'sla_paused_at' => Carbon::now()->subHours(5),
            'approval_requested_at' => Carbon::now()->subHours(5),
            'approval_requested_by_id' => $this->fieldEngineer->id,
            'approval_source' => 'Bank Head Office',
            'approval_request_reason' => 'Sensor module quotation approval requested.',
            'sla_deadline' => Carbon::now()->addHours(40),
        ]);

        // Engineer forbidden from Admin Approvals hub
        $engResponse = $this->actingAs($this->fieldEngineer)->get(route('approvals.index'));
        $engResponse->assertStatus(403);

        // Admin can access and see ticket
        $adminResponse = $this->actingAs($this->admin)->get(route('approvals.index'));
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Pending Approvals Command Hub');
        $adminResponse->assertSee('CMP-2026-TEST-02');
        $adminResponse->assertSee('Sensor module quotation approval requested.');
        $adminResponse->assertSee('Bank Head Office');
    }

    public function test_admin_grants_approval_and_sla_deadline_shifts_by_paused_duration()
    {
        // Scenario from user:
        // TAT was 3 days (72 hours).
        // After 10 hours, engineer marked it as approval required.
        // SLA had 62 hours remaining at the time of pause.
        // Ticket remained paused for 24 hours.
        // Admin marks approval arrived: system shifts SLA deadline forward by 24 hours,
        // so engineer still gets their full 62 remaining hours!

        $now = Carbon::parse('2026-10-01 12:00:00');
        Carbon::setTestNow($now);

        $pausedAt = $now->copy()->subHours(24);
        $originalDeadline = $pausedAt->copy()->addHours(62); // 62 hours left at the time of pause

        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-03',
            'bank_name' => 'Meezan Bank Limited',
            'branch_name' => 'Gulberg Branch',
            'branch_location' => 'Lahore',
            'machine_type' => 'Cash Sorter',
            'machine_model' => 'Julong JL-206',
            'urgency' => 'high',
            'status' => 'awaiting_approval',
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'assigned_at' => $pausedAt->copy()->subHours(10),
            'sla_paused_at' => $pausedAt,
            'approval_requested_at' => $pausedAt,
            'approval_requested_by_id' => $this->fieldEngineer->id,
            'approval_source' => 'Branch Manager',
            'approval_request_reason' => 'Awaiting branch sign-off for parts.',
            'sla_deadline' => $originalDeadline,
        ]);

        // Admin grants approval 24 hours later
        $response = $this->actingAs($this->admin)->post(route('tickets.grant-approval', $ticket), [
            'remarks' => 'Written approval arrived from Branch Manager via official email ref #MBL-4421. Proceed with work.',
        ]);

        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNull($ticket->sla_paused_at);
        $this->assertNotNull($ticket->approval_arrived_at);
        $this->assertEquals($this->admin->id, $ticket->approval_arrived_by_id);
        $this->assertEquals('Written approval arrived from Branch Manager via official email ref #MBL-4421. Proceed with work.', $ticket->approval_arrived_remarks);

        // Verify paused seconds recorded (exact 24 hours = 86400 seconds)
        $this->assertEquals(86400, $ticket->approval_paused_seconds);

        // Verify SLA deadline was shifted forward by exactly 24 hours (1440 minutes)
        $diffMinutes = $originalDeadline->diffInMinutes($ticket->sla_deadline);
        $this->assertEquals(1440, $diffMinutes);

        // SLA deadline from NOW should be exactly 62 hours (engineer TAT fully preserved!)
        $remainingHours = $now->diffInHours($ticket->sla_deadline, false);
        $this->assertEquals(62, $remainingHours);

        // SLA is healthy again (not paused)
        $slaHealth = $ticket->getSlaHealth();
        $this->assertFalse($slaHealth['is_paused'] ?? false);
        $this->assertEquals('healthy', $slaHealth['status']);

        // Audit Trail: Log recorded
        $log = TicketLog::where('ticket_id', $ticket->id)->latest()->first();
        $this->assertEquals('approval_granted', $log->action);
        $this->assertStringContainsString('SLA clock resumed after', $log->notes);

        Carbon::setTestNow(); // clear mocked time
    }

    public function test_unassigned_engineer_cannot_request_approval()
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-04',
            'bank_name' => 'Bank Alfalah',
            'branch_location' => 'Lahore',
            'status' => 'in_progress',
            'assigned_engineer_id' => $this->fieldEngineer->id,
        ]);

        $response = $this->actingAs($this->otherEngineer)->post(route('tickets.request-approval', $ticket), [
            'approval_source' => 'Bank',
            'reason' => 'Unassigned engineer trying to pause.',
        ]);

        $response->assertStatus(403);
    }

    public function test_engineer_cannot_grant_approval()
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST-05',
            'bank_name' => 'Bank of Punjab',
            'branch_location' => 'Lahore',
            'status' => 'awaiting_approval',
            'assigned_engineer_id' => $this->fieldEngineer->id,
            'sla_paused_at' => Carbon::now()->subHours(2),
        ]);

        $response = $this->actingAs($this->fieldEngineer)->post(route('tickets.grant-approval', $ticket), [
            'remarks' => 'Engineer attempting to approve.',
        ]);

        $response->assertStatus(403);
    }
}
