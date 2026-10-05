<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitors SLA turnaround deadlines, flags breached tickets, and auto-escalates to Regional Superior';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $this->info("Running SLA monitor check at {$now->toDateTimeString()}...");

        // 1. Find tickets that have breached their SLA deadline
        $breachedTickets = Ticket::whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', $now)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->get();

        $escalatedCount = 0;
        $superior = User::where('role', 'superior')->first();

        foreach ($breachedTickets as $ticket) {
            $isNewlyEscalated = false;

            if ($ticket->status !== 'escalated') {
                $ticket->update([
                    'status' => 'escalated',
                    'escalated_at' => $now,
                    'escalated_to_id' => $superior?->id,
                ]);
                $isNewlyEscalated = true;
                $escalatedCount++;

                TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null, // System automated action
                    'action' => 'escalated',
                    'notes' => "SLA BREACH DETECTED: Ticket auto-escalated to Regional Superior. Deadline was {$ticket->sla_deadline->format('d M Y, h:i A')} ({$ticket->sla_deadline->diffForHumans()}).",
                ]);

                $this->warn("Ticket #{$ticket->ticket_no} auto-escalated due to SLA breach.");
            }
        }

        // 2. Identify tickets with missing daily feedback for > 24 hours
        $activeTickets = Ticket::whereIn('status', ['assigned', 'in_progress'])
            ->where('created_at', '<', $now->copy()->subHours(24))
            ->get();

        $missingFeedbackCount = 0;
        foreach ($activeTickets as $ticket) {
            $lastFeedback = $ticket->feedbacks()->latest('submitted_at')->first();
            $lastActivityTime = $lastFeedback ? $lastFeedback->submitted_at : $ticket->assigned_at ?? $ticket->created_at;

            if ($lastActivityTime && $lastActivityTime->diffInHours($now) >= 24) {
                $missingFeedbackCount++;
                // Log operational alert if not logged in the last 24h
                $hasRecentLog = TicketLog::where('ticket_id', $ticket->id)
                    ->where('action', 'feedback_alert')
                    ->where('created_at', '>=', $now->copy()->subHours(24))
                    ->exists();

                if (!$hasRecentLog) {
                    TicketLog::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => null,
                        'action' => 'feedback_alert',
                        'notes' => "No daily feedback received in past 24 hours. Awaiting engineer status report.",
                    ]);
                }
            }
        }

        $this->info("SLA Check Complete: {$escalatedCount} tickets newly escalated, {$missingFeedbackCount} tickets overdue for feedback.");
        return Command::SUCCESS;
    }
}
