<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketCycle;
use App\Models\User;
use App\Services\TicketCycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankWiseReopenReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $leadEngineer;
    protected User $supportEngineer;

    protected function setUp(): void
    {
        parent::setUp();

        $mk = fn ($name, $role) => User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@test.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'base_city' => 'Lahore',
            'phone_whatsapp' => '0300' . random_int(1000000, 9999999),
            'is_available' => true,
        ]);

        $this->manager = $mk('Ops Manager', 'operations_manager');
        $this->leadEngineer = $mk('Tariq Lead', 'engineer');
        $this->supportEngineer = $mk('Hamza Support', 'engineer');
    }

    protected function ticket(array $over = []): Ticket
    {
        return Ticket::create(array_merge([
            'ticket_no' => 'HBL-' . random_int(1000, 99999),
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_name' => 'Main Branch',
            'branch_location' => 'Lahore',
            'machine_serial_no' => 'SN-' . random_int(1000, 9999),
            'issue_summary' => 'Cash dispenser jammed',
            'status' => 'open',
            'urgency' => 'normal',
            'assigned_engineer_id' => $this->leadEngineer->id,
            'sla_deadline' => Carbon::now()->addHours(24),
        ], $over));
    }

    public function test_bank_tickets_report_displays_reopened_complaints_with_cycle_history(): void
    {
        $ticket = $this->ticket([
            'ticket_no' => 'HBL-991122',
            'bank_name' => 'Habib Bank Limited (HBL)',
            'status' => 'resolved',
            'assigned_engineer_id' => $this->leadEngineer->id,
            'resolved_at' => Carbon::now()->subDays(2),
        ]);

        $service = app(TicketCycleService::class);
        $service->syncEngineers($ticket, $this->leadEngineer->id, [$this->supportEngineer->id], $this->manager);

        // Reopen ticket
        $service->reopen(
            $ticket,
            $this->manager,
            'Cash dispenser jamming recurring reported by branch manager',
            $this->leadEngineer->id,
            [$this->supportEngineer->id]
        );

        $response = $this->actingAs($this->manager)->get(route('reports.index', [
            'tab' => 'bank_tickets',
            'bank_filter' => 'Habib Bank Limited (HBL)',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Tour 2 &bull; Reopened 1x', false);
        $response->assertSee('Reopen &amp; Multi-Tour Lifecycle History (2 Tours Logged)', false);
        $response->assertSee('Cash dispenser jamming recurring reported by branch manager');
        $response->assertSee('Tariq Lead');
        $response->assertSee('Hamza Support');
    }

    public function test_bank_tickets_csv_export_contains_reopen_and_aligned_engineer_columns(): void
    {
        $ticket = $this->ticket([
            'ticket_no' => 'MCB-554433',
            'bank_name' => 'MCB Bank',
            'status' => 'resolved',
            'assigned_engineer_id' => $this->leadEngineer->id,
            'resolved_at' => Carbon::now()->subDays(3),
        ]);

        $service = app(TicketCycleService::class);
        $service->syncEngineers($ticket, $this->leadEngineer->id, [$this->supportEngineer->id], $this->manager);

        $service->reopen(
            $ticket,
            $this->manager,
            'Card reader error recurrence after 2 days',
            $this->leadEngineer->id,
            [$this->supportEngineer->id]
        );

        $response = $this->actingAs($this->manager)->get(route('reports.export-csv', [
            'type' => 'bank_tickets',
            'bank_filter' => 'MCB Bank',
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Reopen Count', $content);
        $this->assertStringContainsString('Current Tour', $content);
        $this->assertStringContainsString('Aligned Engineers', $content);
        $this->assertStringContainsString('Latest Reopen At', $content);
        $this->assertStringContainsString('Latest Reopen Reason', $content);
        $this->assertStringContainsString('Latest Re-Closed At', $content);
        $this->assertStringContainsString('Tour 2', $content);
        $this->assertStringContainsString('Card reader error recurrence after 2 days', $content);
        $this->assertStringContainsString('Tariq Lead [Lead]', $content);
        $this->assertStringContainsString('Hamza Support [Support]', $content);
    }

    public function test_cycle_model_duration_and_tat_helpers(): void
    {
        $ticket = $this->ticket();

        // Cycle 1 is auto-created by Ticket model hook
        $cycle = $ticket->cycles()->first();
        $this->assertNotNull($cycle);

        $cycle->update([
            'status' => 'resolved',
            'opened_at' => Carbon::now()->subHours(4),
            'resolved_at' => Carbon::now()->subHours(2),
            'sla_deadline' => Carbon::now()->addHours(2),
        ]);

        $this->assertEquals(120, $cycle->durationMinutes());
        $this->assertEquals(2.0, $cycle->durationHours());
        $this->assertEquals('2h 0m', $cycle->durationFormatted());
        $this->assertTrue($cycle->isInTat());
    }

    public function test_tickets_index_actions_dropdown_renders_reopen_option_for_manager(): void
    {
        $ticket = $this->ticket([
            'status' => 'resolved',
            'resolved_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->actingAs($this->manager)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertSee('Reopen Ticket (Tour 2)');
        $response->assertSee('14d left');
    }
}
