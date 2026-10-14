<?php

namespace App\Models\Tracking;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDailySummary extends Model
{
    protected $fillable = [
        'user_id', 'date', 'distance_m', 'drive_sec', 'stop_sec', 'office_sec', 'site_sec',
        'unknown_stop_sec', 'long_unknown_stop_sec', 'gap_sec', 'stops_count', 'points_count',
        'mocked_points', 'first_point_at', 'last_point_at', 'max_speed_kmh', 'efficiency_score',
        'needs_processing', 'processed_at', 'ai_summary', 'ai_generated_at',
    ];

    protected $casts = [
        'first_point_at' => 'datetime',
        'last_point_at' => 'datetime',
        'processed_at' => 'datetime',
        'ai_generated_at' => 'datetime',
        'needs_processing' => 'boolean',
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

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class, 'user_id', 'user_id');
    }

    public function getTotalDistanceKmAttribute(): float
    {
        return round($this->distance_m / 1000, 1);
    }

    public function getTransitMinutesAttribute(): int
    {
        return round($this->drive_sec / 60);
    }

    public function getStopMinutesAttribute(): int
    {
        return round($this->stop_sec / 60);
    }

    public function getOfficeMinutesAttribute(): int
    {
        return round($this->office_sec / 60);
    }

    public function getStopCountAttribute(): int
    {
        return $this->stops_count ?: 0;
    }

    public function getAiNarrativeAttribute()
    {
        return $this->ai_summary;
    }
}
