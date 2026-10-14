<?php

namespace App\Models\Tracking;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSegment extends Model
{
    protected $fillable = [
        'user_id', 'date', 'seq', 'type', 'started_at', 'ended_at', 'duration_sec', 'distance_m',
        'lat', 'lng', 'end_lat', 'end_lng', 'kind', 'label', 'address', 'ticket_id', 'geofence_id',
        'avg_speed_kmh', 'max_speed_kmh', 'point_count',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
        'end_lat' => 'float',
        'end_lng' => 'float',
        'avg_speed_kmh' => 'float',
        'max_speed_kmh' => 'float',
    ];

    public function setDateAttribute($value)
    {
        $this->attributes['date'] = Carbon::parse($value)->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function getStartTimeAttribute()
    {
        return $this->started_at;
    }

    public function getEndTimeAttribute()
    {
        return $this->ended_at;
    }

    public function getDurationMinutesAttribute(): int
    {
        return round(($this->duration_sec ?: 0) / 60);
    }

    public function getDistanceKmAttribute(): float
    {
        return round(($this->distance_m ?: 0) / 1000, 2);
    }

    public function getClassificationAttribute(): string
    {
        return $this->kind ?: 'transit';
    }

    public function getStartLatAttribute(): float
    {
        return (float) $this->lat;
    }

    public function getStartLngAttribute(): float
    {
        return (float) $this->lng;
    }

    public function getEndAddressAttribute()
    {
        return $this->address;
    }
}
