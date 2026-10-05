<?php

namespace Tests\Feature;

use App\Models\ExpenseClaim;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superiorUser;
    protected User $engineerAli;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Operations Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03001111111',
        ]);

        $this->superiorUser = User::create([
            'name' => 'Regional Superior',
            'email' => 'superior@test.com',
            'password' => bcrypt('password'),
            'role' => 'superior',
            'base_city' => 'Islamabad',
            'phone_whatsapp' => '03002222222',
        ]);

        $this->engineerAli = User::create([
            'name' => 'Ali Khan',
            'email' => 'ali@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03003333333',
        ]);
    }

    public function test_admin_and_superior_can_access_reports_dashboard(): void
    {
        // Admin
        $response = $this->actingAs($this->adminUser)->get(route('reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Executive Reports &amp; SLA Compliance Dashboard', false);
        $response->assertSee('Download Executive CSV');

        // Superior
        $response = $this->actingAs($this->superiorUser)->get(route('reports.index'));
        $response->assertStatus(200);
    }

    public function test_engineer_cannot_access_reports_dashboard_and_is_redirected(): void
    {
        $response = $this->actingAs($this->engineerAli)->get(route('reports.index'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('warning');
    }

    public function test_reports_dashboard_computes_kpis_bank_metrics_and_engineer_scorecards(): void
    {
        // Create 2 tickets: 1 resolved within SLA, 1 active with breached SLA
        $resolvedTicket = Ticket::create([
            'ticket_no' => 'CMP-2026-0001',
            'customer_ref_no' => 'MCB-991',
            'bank_name' => 'Meezan Bank Ltd',
            'branch_name' => 'Gulberg Branch',
            'branch_location' => 'Lahore',
            'machine_type' => 'Cash Sorter',
            'machine_serial_no' => 'SN-1001',
            'urgency' => 'high',
            'status' => 'resolved',
            'assigned_engineer_id' => $this->engineerAli->id,
            'created_at' => Carbon::now()->subHours(3),
            'resolved_at' => Carbon::now()->subHour(),
            'sla_deadline' => Carbon::now()->addHour(),
        ]);

        $breachedTicket = Ticket::create([
            'ticket_no' => 'CMP-2026-0002',
            'customer_ref_no' => 'HBL-442',
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_name' => 'Mall Road',
            'branch_location' => 'Lahore',
            'machine_type' => 'ATM',
            'machine_serial_no' => 'SN-2002',
            'urgency' => 'critical',
            'status' => 'in_progress',
            'assigned_engineer_id' => $this->engineerAli->id,
            'created_at' => Carbon::now()->subHours(6),
            'sla_deadline' => Carbon::now()->subHours(2), // breached
        ]);

        // Add expense claim for Ali
        ExpenseClaim::create([
            'ticket_id' => $resolvedTicket->id,
            'engineer_id' => $this->engineerAli->id,
            'from_city' => 'Lahore',
            'to_city' => 'Gujranwala',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'ai_distance_km' => 140,
            'claimed_amount' => 3500.00,
            'suggested_amount' => 3500.00,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.index', ['preset' => 'all']));
        $response->assertStatus(200);

        // Check KPI cards
        $response->assertSee('Meezan Bank Ltd');
        $response->assertSee('Habib Bank Limited (HBL)');
        $response->assertSee('Ali Khan');
        $response->assertSee('140 km');
        $response->assertSee('3,500');
    }

    public function test_reports_chronic_machine_detection(): void
    {
        // Create 3 tickets for the same machine serial number
        for ($i = 1; $i <= 3; $i++) {
            Ticket::create([
                'ticket_no' => "CMP-CHRONIC-00{$i}",
                'bank_name' => 'Bank of Punjab',
                'branch_location' => 'Faisalabad',
                'machine_type' => 'Cash Recycler (CRM)',
                'machine_serial_no' => 'CRM-LEMON-77',
                'urgency' => 'high',
                'status' => 'open',
                'created_at' => Carbon::now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($this->adminUser)->get(route('reports.index', ['preset' => 'all', 'chronic_threshold' => 2]));
        $response->assertStatus(200);
        $response->assertSee('CRM-LEMON-77');
        $response->assertSee('3 Tickets');
        $response->assertSee('Workshop Overhaul / Module Replace');
    }

    public function test_reports_csv_export(): void
    {
        Ticket::create([
            'ticket_no' => 'CMP-EXP-900',
            'bank_name' => 'Allied Bank Limited',
            'branch_name' => 'Main Branch',
            'branch_location' => 'Multan',
            'machine_type' => 'ATM',
            'machine_serial_no' => 'ABL-99',
            'urgency' => 'medium',
            'status' => 'open',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.export-csv', ['preset' => 'all']));
        $response->assertStatus(200);
        $content = $response->streamedContent();
        $this->assertStringContainsString('CMP-EXP-900', $content);
        $this->assertStringContainsString('Allied Bank Limited', $content);
    }

    public function test_expenses_view_renders_cleanly_with_claim_button(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('expenses.index'));
        $response->assertStatus(200);
        $response->assertSee('Claim Tour Expense');
        $response->assertSee('Tour Expense Registry &amp; Disbursements', false);
    }
}
