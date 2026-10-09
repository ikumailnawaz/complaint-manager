<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketCycle;
use App\Models\TicketDocument;
use App\Models\TicketEngineer;
use App\Models\TicketLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single place for multi-engineer alignment and reopen-cycle state changes.
 * All timestamps are generated server-side; callers never pass times in.
 */
class TicketCycleService
{
    /**
     * Align a lead + support engineers to a ticket.
     * Engineers no longer in the list are released (never deleted) so history is kept.
     *
     * @return array{added: User[], removed: User[]}
     */
    public function syncEngineers(Ticket $ticket, int $leadId, array $supportIds, User $by): array
    {
        $supportIds = array_values(array_unique(array_filter(array_map('intval', $supportIds))));
        $supportIds = array_values(array_diff($supportIds, [$leadId]));

        return DB::transaction(function () use ($ticket, $leadId, $supportIds, $by) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $cycle = $ticket->currentCycle();
            $now = Carbon::now();

            $wanted = [$leadId => 'lead'];
            foreach ($supportIds as $sid) {
                $wanted[$sid] = 'support';
            }

            $active = TicketEngineer::where('ticket_id', $ticket->id)->whereNull('released_at')->get();
            $removedIds = [];
            $addedIds = [];

            foreach ($active as $row) {
                if (!isset($wanted[$row->engineer_id])) {
                    $row->update(['released_at' => $now]);
                    $removedIds[] = $row->engineer_id;
                } elseif ($row->role !== $wanted[$row->engineer_id]) {
                    $row->update(['role' => $wanted[$row->engineer_id]]);
                }
            }

            $activeIds = $active->pluck('engineer_id')->all();
            foreach ($wanted as $engineerId => $role) {
                if (!in_array($engineerId, $activeIds, true)) {
                    TicketEngineer::create([
                        'ticket_id' => $ticket->id,
                        'engineer_id' => $engineerId,
                        'role' => $role,
                        'assigned_by_id' => $by->id,
                        'assigned_at' => $now,
                        'cycle_id' => $cycle?->id,
                    ]);
                    $addedIds[] = $engineerId;
                }
            }

            $ticket->update(['assigned_engineer_id' => $leadId]);

            return [
                'added' => User::whereIn('id', $addedIds)->get()->all(),
                'removed' => User::whereIn('id', $removedIds)->get()->all(),
            ];
        });
    }

    /**
     * Record the engineer's "done" against the current cycle and keep the proof document.
     */
    public function recordResolution(Ticket $ticket, User $by, string $summary, ?string $docPath, ?string $docName): void
    {
        $cycle = $ticket->currentCycle();
        if (!$cycle) {
            return;
        }

        $cycle->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now(),
            'resolved_by_id' => $by->id,
            'resolution_summary' => $summary,
        ]);

        if ($docPath) {
            $exists = TicketDocument::where('ticket_id', $ticket->id)
                ->where('path', $docPath)
                ->exists();
            if (!$exists) {
                $this->addDocument($ticket, $by, $docPath, $docName, 'resolution', $cycle);
            }
        }
    }

    public function addDocument(Ticket $ticket, User $by, string $path, ?string $name, string $type = 'resolution', ?TicketCycle $cycle = null): TicketDocument
    {
        $cycle = $cycle ?: $ticket->currentCycle();

        return TicketDocument::create([
            'ticket_id' => $ticket->id,
            'cycle_id' => $cycle?->id,
            'uploaded_by_id' => $by->id,
            'type' => $type,
            'path' => $path,
            'name' => $name ?: basename($path),
            'uploaded_at' => Carbon::now(),
        ]);
    }

    /** Undoing a resolution re-opens the same (still current) cycle. */
    public function undoResolution(Ticket $ticket): void
    {
        $cycle = $ticket->currentCycle();
        if ($cycle && $cycle->status === 'resolved') {
            $cycle->update(['status' => 'open', 'resolved_at' => null, 'resolved_by_id' => null]);
        }
    }

    public function recordClosure(Ticket $ticket, User $by): void
    {
        $cycle = $ticket->currentCycle();
        if ($cycle) {
            $cycle->update([
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'closed_by_id' => $by->id,
            ]);
        }
    }

    /**
     * Reopen a resolved/closed ticket as a new cycle. Everything from earlier cycles is preserved.
     * Caller must have already verified the actor is allowed to reopen.
     *
     * @throws RuntimeException when the ticket cannot be reopened
     */
    public function reopen(Ticket $ticket, User $by, string $reason, int $leadId, array $supportIds = []): TicketCycle
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RuntimeException('A clear reason (at least 10 characters) is required to reopen a ticket.');
        }

        $newCycle = DB::transaction(function () use ($ticket, $by, $reason) {
            // Row lock prevents two managers (or a double click) from reopening twice.
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);

            if (!$ticket->canBeReopened()) {
                if ($ticket->isReopenWindowExpired()) {
                    throw new RuntimeException("Ticket {$ticket->ticket_no} cannot be reopened because the 15-day reopen window has expired.");
                }
                throw new RuntimeException("Ticket {$ticket->ticket_no} is already open (status: {$ticket->status}) and cannot be reopened.");
            }

            $previous = $ticket->currentCycle();
            // A resolved-but-not-closed cycle gets sealed so its times stay fixed in reports.
            if ($previous && $previous->status !== 'closed') {
                $previous->update([
                    'status' => 'closed',
                    'closed_at' => $previous->closed_at ?? Carbon::now(),
                    'closed_by_id' => $previous->closed_by_id ?? $by->id,
                ]);
            }

            $now = Carbon::now();
            $nextNo = ((int) $ticket->cycles()->max('cycle_no')) + 1;
            $slaDeadline = $now->copy()->addHours($ticket->expectedResponseHours());

            $cycle = TicketCycle::create([
                'ticket_id' => $ticket->id,
                'cycle_no' => $nextNo,
                'status' => 'open',
                'opened_at' => $now,
                'opened_by_id' => $by->id,
                'reopen_reason' => $reason,
                'sla_deadline' => $slaDeadline,
            ]);

            $ticket->update([
                'status' => 'in_progress',
                'resolved_at' => null,
                'closed_at' => null,
                'current_cycle_no' => $nextNo,
                'reopen_count' => ((int) $ticket->reopen_count) + 1,
                'sla_deadline' => $slaDeadline,
                'whatsapp_notified' => false,
                'whatsapp_notified_at' => null,
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'cycle_id' => $cycle->id,
                'user_id' => $by->id,
                'action' => 'reopened',
                'notes' => "Ticket REOPENED (Tour {$nextNo}) by {$by->name}. Reason: {$reason}",
            ]);

            return $cycle;
        });

        // Re-align engineers for the new cycle (history of previous alignment is kept as released rows).
        $ticket->refresh();
        $this->syncEngineers($ticket, $leadId, $supportIds, $by);

        return $newCycle;
    }
}
