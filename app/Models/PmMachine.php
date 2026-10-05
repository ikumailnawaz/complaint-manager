<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmMachine extends Model
{
    protected $fillable = [
        'machine_model_id',
        'serial_number',
        'asset_tag',
        'location',
        'bank_name',
        'installed_at',
        'assigned_engineer_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'installed_at' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function machineModel(): BelongsTo
    {
        return $this->belongsTo(MachineModel::class);
    }

    public function assignedEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_engineer_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(PmRecord::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns the earliest next_due_date among all active schedules */
    public function getNextDueDateAttribute()
    {
        return $this->schedules()->where('is_active', true)->min('next_due_date');
    }

    /** true if any active schedule is past its due date */
    public function getIsOverdueAttribute(): bool
    {
        return $this->schedules()
            ->where('is_active', true)
            ->where('next_due_date', '<', now()->toDateString())
            ->exists();
    }

    /** true if any active schedule is due within 7 days */
    public function getIsDueSoonAttribute(): bool
    {
        return $this->schedules()
            ->where('is_active', true)
            ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->exists();
    }
}
