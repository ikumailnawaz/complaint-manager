<?php

namespace Tests\Feature;

use App\Models\ExpenseClaim;
use App\Models\Ticket;
use App\Models\TicketCycle;
use App\Models\TicketDocument;
use App\Models\TicketFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketReopenCyclesTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $lead;
    protected User $support;
    protected User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $mk = fn ($name, $role) => User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)) . '@test.com',
            'password' => bcrypt('password'), 'role' => $role, 'base_city' => 'Lahore',
            'phone_whatsapp' => '0300' . random_int(1000000, 9999999), 'is_available' => true,
        ]);

        $this->manager = $mk('Ops Manager', 'operations_manager');
        $this->lead = $mk('Lead Eng', 'engineer');
        $this->support = $mk('Support Eng', 'engineer');
        $this->outsider = $mk('Outsider Eng', 'engineer');
    }

    protected function ticket(array $over = []): Ticket
    {
        return Ticket::create(array_merge([
            'ticket_no' => 'HBL-' . random_int(1000, 99999),
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_name' => 'Main Branch',
            'branch_location' => 'Lahore',
            'machine_type' => 'Cash Sorting Machine',
            'machine_model' => 'JL-206',
            'machine_serial_no' => 'SN' . random_int(1000, 9999),
            'urgency' => 'high',
            'status' => 'in_progress',
        ], $over));
    }

    public function test_new_ticket_gets_cycle_one(): void
    {
        $t = $this->ticket();
        $this->assertSame(1, $t->cycles()->count());
        $this->assertSame('open', $t->currentCycle()->status);
    }

    public function test_manager_can_align_lead_and_support_engineers_and_all_see_ticket(): void
    {
        $t = $this->ticket(['status' => 'open']);

        $this->actingAs($this->manager)->post(route('tickets.assign', $t), [
            'engineer_id' => $this->lead->id,
            'support_engineer_ids' => [$this->support->id],
        ])->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame($this->lead->id, (int) $t->assigned_engineer_id);
        $this->assertTrue($t->hasEngineer($this->lead));
        $this->assertTrue($t->hasEngineer($this->support));
        $this->assertFalse($t->hasEngineer($this->outsider));
        $this->assertTrue(Ticket::forEngineer($this->support->id)->whereKey($t->id)->exists());
        $this->assertFalse(Ticket::forEngineer($this->outsider->id)->whereKey($t->id)->exists());

        // each engineer notified
        foreach ([$this->lead, $this->support] as $e) {
            $this->assertDatabaseHas('app_notifications', ['user_id' => $e->id, 'type' => 'ticket_assigned']);
        }
    }

    public function test_any_aligned_engineer_can_resolve_outsider_cannot(): void
    {
        $t = $this->ticket(['assigned_engineer_id' => $this->lead->id, 'status' => 'in_progress']);
        app(\App\Services\TicketCycleService::class)->syncEngineers($t, $this->lead->id, [$this->support->id], $this->manager);

        // Outsider engineer cannot resolve
        $this->actingAs($this->outsider)->post(route('tickets.resolve', $t), ['resolution_summary' => 'done by outsider'])
            ->assertStatus(403);

        // Aligned support engineer can resolve
        $this->actingAs($this->support)->post(route('tickets.resolve', $t), ['resolution_summary' => 'Support engineer completed fix'])
            ->assertSessionHasNoErrors();

        $this->assertSame('resolved', $t->fresh()->status);
        $this->assertSame('resolved', $t->currentCycle()->status);
    }

    public function test_only_manager_can_reopen_engineers_cannot(): void
    {
        $t = $this->ticket(['assigned_engineer_id' => $this->lead->id, 'status' => 'closed', 'closed_at' => now()]);

        $this->actingAs($this->lead)->post(route('tickets.reopen', $t), ['reason' => 'I want to claim more expense'])
            ->assertStatus(403);
        $this->assertSame('closed', $t->fresh()->status);
    }

    public function test_reopen_requires_reason_and_resolved_or_closed_state(): void
    {
        $open = $this->ticket(['assigned_engineer_id' => $this->lead->id, 'status' => 'in_progress']);
        $this->actingAs($this->manager)->post(route('tickets.reopen', $open), ['reason' => 'Machine still jamming again'])
            ->assertSessionHas('error');
        $this->assertSame(1, $open->cycles()->count());

        $closed = $this->ticket(['assigned_engineer_id' => $this->lead->id, 'status' => 'closed', 'closed_at' => now()]);
        $this->actingAs($this->manager)->post(route('tickets.reopen', $closed), ['reason' => 'short'])
            ->assertSessionHasErrors('reason');
    }

    public function test_reopen_window_enforces_15_day_policy(): void
    {
        // 1. Within 15 days (e.g. 5 days ago) -> can reopen
        $withinWindow = $this->ticket([
            'assigned_engineer_id' => $this->lead->id,
            'status' => 'closed',
            'closed_at' => now()->subDays(5),
            'resolved_at' => now()->subDays(6),
        ]);
        $this->actingAs($this->manager)->post(route('tickets.reopen', $withinWindow), [
            'reason' => 'Bank reports cash dispenser failure recurrent within 5 days',
        ])->assertSessionHas('success');
        $this->assertSame('in_progress', $withinWindow->fresh()->status);

        // 2. Beyond 15 days (e.g. 16 days ago) -> rejected
        $expiredTicket = $this->ticket([
            'assigned_engineer_id' => $this->lead->id,
            'status' => 'closed',
            'closed_at' => now()->subDays(16),
            'resolved_at' => now()->subDays(17),
        ]);
        $this->actingAs($this->manager)->post(route('tickets.reopen', $expiredTicket), [
            'reason' => 'Bank reports recurrence after 16 days past closing',
        ])->assertSessionHas('error');
        $this->assertSame('closed', $expiredTicket->fresh()->status);
    }

    public function test_reopen_preserves_history_creates_new_cycle_and_notifies_engineers(): void
    {
        $t = $this->ticket(['assigned_engineer_id' => $this->lead->id, 'status' => 'in_progress']);
        app(\App\Services\TicketCycleService::class)->syncEngineers($t, $this->lead->id, [$this->support->id], $this->manager);

        // Tour 1 data
        TicketFeedback::create(['ticket_id' => $t->id, 'engineer_id' => $this->lead->id, 'day_number' => 1, 'feedback_text' => 'Tour1 report', 'status' => 'submitted']);
        ExpenseClaim::create(['ticket_id' => $t->id, 'engineer_id' => $this->lead->id, 'from_city' => 'Lahore', 'to_city' => 'Multan', 'trip_type' => 'round', 'category' => 'travel', 'claimed_amount' => 5000, 'status' => 'pending']);
        $this->actingAs($this->lead)->post(route('tickets.resolve', $t), ['resolution_summary' => 'Tour 1 fix']);
        $this->actingAs($this->manager)->post(route('tickets.close', $t));
        $t->refresh();
        $this->assertSame('closed', $t->status);
        TicketDocument::create(['ticket_id' => $t->id, 'cycle_id' => $t->currentCycle()->id, 'path' => 'resolutions/t1.pdf', 'name' => 't1.pdf', 'uploaded_at' => now()]);

        // Reopen
        \App\Models\AppNotification::query()->delete();
        $this->actingAs($this->manager)->post(route('tickets.reopen', $t), ['reason' => 'Issue arrived again after two days'])
            ->assertSessionHas('success');

        $t->refresh();
        $this->assertSame('in_progress', $t->status);
        $this->assertSame(2, (int) $t->current_cycle_no);
        $this->assertSame(1, (int) $t->reopen_count);
        $this->assertNull($t->closed_at);
        $this->assertSame(2, $t->cycles()->count());

        $c1 = $t->cycles()->where('cycle_no', 1)->first();
        $this->assertSame('closed', $c1->status);
        $this->assertNotNull($c1->resolved_at);
        $this->assertNotNull($c1->closed_at);

        // Tour 1 data untouched
        $this->assertSame(1, $c1->feedbacks()->count());
        $this->assertSame(1, $c1->expenseClaims()->count());
        $this->assertSame(1, $c1->documents()->count());

        // Notifications for every engineer
        foreach ([$this->lead, $this->support] as $e) {
            $this->assertDatabaseHas('app_notifications', ['user_id' => $e->id, 'type' => 'ticket_reopened']);
        }

        // Tour 2 additions are tagged to cycle 2 and sit alongside tour 1
        $c2 = $t->currentCycle();
        $fb = TicketFeedback::create(['ticket_id' => $t->id, 'engineer_id' => $this->lead->id, 'day_number' => 1, 'feedback_text' => 'Tour2 report', 'status' => 'submitted']);
        $claim = ExpenseClaim::create(['ticket_id' => $t->id, 'engineer_id' => $this->lead->id, 'from_city' => 'Lahore', 'to_city' => 'Multan', 'trip_type' => 'round', 'category' => 'travel', 'claimed_amount' => 5200, 'status' => 'pending']);
        $this->assertSame($c2->id, (int) $fb->cycle_id);
        $this->assertSame($c2->id, (int) $claim->cycle_id);
        $this->assertSame(2, (int) $claim->tour_no);
        $this->assertSame(2, $t->expenseClaims()->count());
        $this->assertSame(2, $t->feedbacks()->count());

        // Second resolution + close, then it can be reopened again
        $this->actingAs($this->lead)->post(route('tickets.resolve', $t), ['resolution_summary' => 'Tour 2 fix']);
        $this->actingAs($this->manager)->post(route('tickets.close', $t));
        $this->assertSame('closed', $t->fresh()->status);
        $this->assertSame('closed', $t->cycles()->where('cycle_no', 2)->first()->status);

        $this->actingAs($this->manager)->post(route('tickets.reopen', $t), ['reason' => 'Third visit needed for same fault']);
        $this->assertSame(3, (int) $t->fresh()->current_cycle_no);
        $this->assertSame(2, (int) $t->fresh()->reopen_count);
    }

    public function test_double_reopen_is_blocked(): void
    {
        $t = $this->ticket(['assigned_engineer_id' => $this->lead->id, 'status' => 'closed', 'closed_at' => now()]);
        $this->actingAs($this->manager)->post(route('tickets.reopen', $t), ['reason' => 'Issue arrived again today']);
        $this->actingAs($this->manager)->post(route('tickets.reopen', $t), ['reason' => 'Issue arrived again today'])
            ->assertSessionHas('error');
        $this->assertSame(2, $t->cycles()->count());
    }

    public function test_engineer_cannot_replace_documents_on_closed_ticket(): void
    {
        $t = $this->ticket(['assigned_engineer_id' => $this->lead->id, 'status' => 'closed', 'closed_at' => now()]);
        $this->actingAs($this->lead)->post(route('tickets.update-document', $t), [])
            ->assertStatus(403);
    }
}
