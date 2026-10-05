<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketLog;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendFeedbackRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:send-reminders {--dry-run : Only simulate reminders without dispatching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch daily SLA feedback WhatsApp reminders to assigned field engineers';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsapp): int
    {
        $this->info('Scanning active assigned tickets for missing daily feedback today...');
        $isDryRun = $this->option('dry-run');

        // Tickets in progress or assigned that need feedback today
        $tickets = Ticket::whereIn('status', ['assigned', 'in_progress', 'awaiting_workshop'])
            ->whereNotNull('assigned_engineer_id')
            ->with(['engineer', 'feedbacks'])
            ->get();

        $remindersSent = 0;

        foreach ($tickets as $ticket) {
            // Check if feedback already submitted today
            $hasTodayFeedback = $ticket->feedbacks->contains(function ($feedback) {
                return $feedback->created_at->isToday();
            });

            if (!$hasTodayFeedback) {
                $engineer = $ticket->engineer;
                if (!$engineer) continue;

                $nextDayNumber = ($ticket->feedbacks->max('day_number') ?? 0) + 1;

                $this->line("Dispatching Day {$nextDayNumber} reminder for Ticket #{$ticket->ticket_no} to {$engineer->name} (@{$engineer->phone_whatsapp})");

                if (!$isDryRun) {
                    $result = $whatsapp->sendFeedbackReminder($ticket, $engineer, $nextDayNumber);

                    TicketLog::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => null, // System Scheduler
                        'action' => 'reminder_sent',
                        'notes' => "Automated WhatsApp feedback reminder dispatched to engineer {$engineer->name} (@{$engineer->phone_whatsapp}) for Day {$nextDayNumber} update.",
                    ]);
                }

                $remindersSent++;
            }
        }

        $this->info("Completed: {$remindersSent} daily feedback reminders sent to field engineers.");

        return Command::SUCCESS;
    }
}
