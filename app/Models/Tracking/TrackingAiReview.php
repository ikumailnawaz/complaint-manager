<?php

namespace App\Models\Tracking;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingAiReview extends Model
{
    protected $fillable = [
        'user_id', 'date', 'kind', 'response', 'applied', 'created_by_id',
    ];

    protected $casts = [
        'date' => 'date',
        'applied' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
