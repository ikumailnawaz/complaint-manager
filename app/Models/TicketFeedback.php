<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFeedback extends Model
{
    use HasFactory;
    use \App\Models\Concerns\TagsTicketCycle;

    protected $table = 'ticket_feedbacks';

    protected $fillable = [
        'ticket_id',
        'engineer_id',
        'submitted_by_id',
        'day_number',
        'feedback_text',
        'action_taken',
        'parts_required',
        'eta_completion',
        'photo_evidence',
        'status',
        'due_at',
        'submitted_at',
        'cycle_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'eta_completion' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }
}
