<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'engineer_id',
        'from_city',
        'to_city',
        'trip_type',
        'category',
        'description',
        'ai_distance_km',
        'ai_estimated_hours',
        'claimed_amount',
        'suggested_amount',
        'status',
        'admin_notes',
        'resubmission_count',
        'voucher_file',
        'supporting_doc',
        'payment_method',
        'payment_reference',
        'paid_at',
        'paid_by_id',
    ];

    protected $casts = [
        'ai_distance_km' => 'float',
        'ai_estimated_hours' => 'float',
        'claimed_amount' => 'float',
        'suggested_amount' => 'float',
        'paid_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }
}
