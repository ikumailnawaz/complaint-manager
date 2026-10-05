<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmRecord extends Model
{
    protected $fillable = [
        'pm_schedule_id',
        'pm_machine_id',
        'performed_by_id',
        'performed_at',
        'due_date',
        'status',
        'notes',
        'document_path',
        'document_original_name',
        'is_overdue',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'due_date'     => 'date',
        'is_overdue'   => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PmSchedule::class, 'pm_schedule_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(PmMachine::class, 'pm_machine_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isMissed(): bool
    {
        return $this->status === 'missed';
    }

    public function hasDocument(): bool
    {
        return !empty($this->document_path);
    }

    public function isImageDocument(): bool
    {
        if (!$this->document_path) return false;
        $ext = strtolower(pathinfo($this->document_path, PATHINFO_EXTENSION));
        return in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif']);
    }

    public function getDocumentUrlAttribute(): ?string
    {
        return $this->document_path ? asset('storage/' . $this->document_path) : null;
    }
}
