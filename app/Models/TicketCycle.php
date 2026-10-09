<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCycle extends Model
{
    protected $fillable = [
        'ticket_id', 'cycle_no', 'status', 'opened_at', 'opened_by_id', 'reopen_reason',
        'resolved_at', 'resolved_by_id', 'resolution_summary', 'closed_at', 'closed_by_id', 'sla_deadline',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_deadline' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(TicketFeedback::class, 'cycle_id');
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class, 'cycle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TicketDocument::class, 'cycle_id');
    }

    public function engineers(): HasMany
    {
        return $this->hasMany(TicketEngineer::class, 'cycle_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TicketLog::class, 'cycle_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Minutes between this cycle opening and its resolution/closure (or now if still running). */
    public function durationMinutes(): int
    {
        $start = $this->opened_at;
        if (!$start) {
            return 0;
        }
        $end = $this->closed_at ?? $this->resolved_at ?? now();
        return (int) max(0, $start->diffInMinutes($end));
    }

    public function durationHours(): float
    {
        return round($this->durationMinutes() / 60, 1);
    }

    public function durationFormatted(): string
    {
        $mins = $this->durationMinutes();
        $h = floor($mins / 60);
        $m = $mins % 60;
        return "{$h}h {$m}m";
    }

    public function isInTat(): bool
    {
        if (!$this->sla_deadline) {
            return true;
        }
        $end = $this->resolved_at ?? $this->closed_at;
        if (!$end) {
            return now() <= $this->sla_deadline;
        }
        return $end <= $this->sla_deadline;
    }
}
