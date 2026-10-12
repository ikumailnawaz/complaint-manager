<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaBusinessCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_day_tat_from_friday_skips_weekends_and_lands_on_tuesday()
    {
        // Friday 09 Oct 2026, 01:23 PM
        $friday = Carbon::parse('2026-10-09 13:23:00');

        $deadline = Ticket::calculateBusinessDeadline($friday, '2 days', 'high');

        // Must skip Saturday and Sunday and land on Tuesday 13 Oct 2026, 01:23 PM
        $this->assertEquals('2026-10-13 13:23:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse($deadline->isWeekend());
    }

    public function test_one_day_tat_from_friday_skips_weekends_and_lands_on_monday()
    {
        // Friday 09 Oct 2026, 12:10 PM
        $friday = Carbon::parse('2026-10-09 12:10:00');

        $deadline = Ticket::calculateBusinessDeadline($friday, '1 day', 'high');

        // Must skip Saturday and Sunday and land on Monday 12 Oct 2026, 12:10 PM
        $this->assertEquals('2026-10-12 12:10:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse($deadline->isWeekend());
    }

    public function test_four_hours_tat_on_friday_afternoon_lands_on_friday()
    {
        $friday = Carbon::parse('2026-10-09 12:10:00');

        $deadline = Ticket::calculateBusinessDeadline($friday, '4 hours', 'high');

        $this->assertEquals('2026-10-09 16:10:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse($deadline->isWeekend());
    }

    public function test_four_hours_tat_on_friday_night_skips_weekend_and_lands_on_monday_morning()
    {
        $fridayNight = Carbon::parse('2026-10-09 22:00:00');

        $deadline = Ticket::calculateBusinessDeadline($fridayNight, '4 hours', 'high');

        // Friday 22:00 to 24:00 (2h), weekend skipped, Monday 00:00 + 2h = Monday 02:00:00
        $this->assertEquals('2026-10-12 02:00:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse($deadline->isWeekend());
    }

    public function test_intake_on_weekend_starts_from_monday()
    {
        $saturday = Carbon::parse('2026-10-10 14:00:00');

        $deadline = Ticket::calculateBusinessDeadline($saturday, '4 hours', 'high');

        $this->assertEquals('2026-10-12 04:00:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse($deadline->isWeekend());
    }

    public function test_breached_open_ticket_audit_metrics_shows_out_of_tat()
    {
        // Ticket created 3 days ago with deadline that has now passed
        $now = Carbon::parse('2026-10-12 17:30:00');
        Carbon::setTestNow($now);

        $intake = Carbon::parse('2026-10-09 12:10:00');
        $passedDeadline = Carbon::parse('2026-10-11 13:23:00'); // Sunday deadline in the past

        $ticket = Ticket::create([
            'ticket_no' => 'CMP-AUDIT-BREACH-01',
            'bank_name' => 'Bank Alfalah',
            'branch_location' => 'Lahore',
            'urgency' => 'high',
            'status' => 'assigned',
            'created_at' => $intake,
            'sla_deadline' => $passedDeadline,
        ]);

        $this->assertTrue($ticket->isSlaBreached());

        $metrics = $ticket->calculateAuditTrailMetrics();

        // Must NOT show In-TAT when milestone 2 is breached
        $this->assertFalse($metrics['is_in_tat']);
        $this->assertEquals('Out-of-TAT (Breached)', $metrics['tat_status_label']);
    }
}
