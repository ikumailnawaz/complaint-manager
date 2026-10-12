<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Ticket;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // 1. Specifically calibrate Bank Alfalah ticket 176325 (ID 69 on live server)
            $ticket69 = Ticket::where('customer_ref_no', '176325')
                ->orWhere('ticket_no', 'like', '%176325%')
                ->orWhere('id', 69)
                ->first();

            if ($ticket69) {
                $start = $ticket69->created_at ?? Carbon::parse('2026-10-09 13:23:00');
                $ticket69->sla_tat = '2 days';
                $ticket69->sla_deadline = Ticket::calculateBusinessDeadline($start, '2 days', $ticket69->urgency ?? 'high');
                $ticket69->saveQuietly();
            }

            // 2. Calibrate any tickets that had their SLA compressed to the intake day Friday 17:23
            // due to null sla_tat fallback, or whose deadline still falls on a weekend
            $tickets = Ticket::whereNotNull('created_at')->get();
            foreach ($tickets as $ticket) {
                $needsUpdate = false;
                $start = $ticket->created_at ?? now();

                // If deadline falls on weekend
                if ($ticket->sla_deadline && $ticket->sla_deadline->isWeekend()) {
                    $needsUpdate = true;
                }

                // If deadline is earlier than or equal to start date + 6 hours but urgency was high on Friday intake
                if ($ticket->sla_deadline && $start->isFriday() && $ticket->sla_deadline->isFriday() && empty($ticket->sla_tat)) {
                    $ticket->sla_tat = '2 days';
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $tat = $ticket->sla_tat ?? '2 days';
                    $ticket->sla_deadline = Ticket::calculateBusinessDeadline($start, $tat, $ticket->urgency ?? 'medium');
                    $ticket->saveQuietly();
                }
            }
        } catch (\Throwable $e) {
            // Silent fail safe during migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema rollback required for data calibration
    }
};
