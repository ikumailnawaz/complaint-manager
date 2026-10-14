<?php

namespace App\Models\Tracking;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'uuid', 'type', 'geofence_id', 'lat', 'lng',
        'recorded_at', 'received_at', 'meta',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }
}
