<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmSchedule extends Model
{
    protected $fillable = [
        'pm_machine_id',
        'title',
        'frequency_days',
        'assigned_engineer_id',
        'last_performed_at',
        'next_due_date',
        'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'last_performed_at'  => 'date',
        'next_due_date'      => 'date',
        'frequency_days'     => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function machine(): BelongsTo
    {
        return $this->belongsTo(PmMachine::class, 'pm_machine_id');
    }

    public function assignedEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_engineer_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(PmRecord::class);
    }

    public function getLatestCompletedRecordAttribute(): ?PmRecord
    {
        return $this->records->where('status', 'completed')->sortByDesc('performed_at')->first();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->next_due_date && $this->next_due_date->lt(now());
    }

    public function getIsDueSoonAttribute(): bool
    {
        if (!$this->next_due_date) return false;
        return $this->next_due_date->between(now(), now()->addDays(7));
    }

    public function getDaysUntilDueAttribute(): int
    {
        if (!$this->next_due_date) return 0;
        return (int) now()->startOfDay()->diffInDays($this->next_due_date->startOfDay(), false);
    }

    /** Frequency label for display */
    public function getFrequencyLabelAttribute(): string
    {
        return match(true) {
            $this->frequency_days === 30  => 'Monthly',
            $this->frequency_days === 60  => 'Bi-Monthly',
            $this->frequency_days === 90  => 'Quarterly',
            $this->frequency_days === 180 => 'Semi-Annual',
            $this->frequency_days === 365 => 'Annual',
            default                       => "Every {$this->frequency_days} days",
        };
    }

    /** Responsible engineer: schedule override → machine default → null */
    public function getResponsibleEngineerAttribute(): ?User
    {
        return $this->assignedEngineer ?? $this->machine?->assignedEngineer;
    }
}
