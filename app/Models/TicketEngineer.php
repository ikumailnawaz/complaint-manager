<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEngineer extends Model
{
    protected $fillable = [
        'ticket_id', 'engineer_id', 'role', 'assigned_by_id', 'assigned_at', 'released_at', 'cycle_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('released_at');
    }

    public function isLead(): bool
    {
        return $this->role === 'lead';
    }
}
