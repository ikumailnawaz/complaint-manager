<?php

namespace App\Models\Tracking;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDay extends Model
{
    protected $fillable = [
        'user_id', 'date', 'geofence_id', 'first_in', 'last_out', 'still_inside', 'office_visits',
        'office_minutes', 'status', 'late_minutes', 'manual_first_in', 'manual_last_out',
        'manual_status', 'manual_note', 'overridden_by_id', 'overridden_at',
    ];

    protected $casts = [
        'first_in' => 'datetime',
        'last_out' => 'datetime',
        'manual_first_in' => 'datetime',
        'manual_last_out' => 'datetime',
        'overridden_at' => 'datetime',
        'still_inside' => 'boolean',
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

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_id');
    }

    /** Effective values: manual override wins over automatic GPS detection. */
    public function getEffectiveFirstInAttribute()
    {
        return $this->manual_first_in ?? $this->first_in;
    }

    public function getEffectiveLastOutAttribute()
    {
        return $this->manual_last_out ?? $this->last_out;
    }

    public function getEffectiveStatusAttribute(): string
    {
        return $this->manual_status ?: $this->status;
    }

    public function getFirstInAtAttribute()
    {
        return $this->getEffectiveFirstInAttribute();
    }

    public function getLastOutAtAttribute()
    {
        return $this->getEffectiveLastOutAttribute();
    }

    public function getIsLateAttribute(): bool
    {
        return ($this->status === 'late') || ($this->manual_status === 'late');
    }

    public function getIsManualOverrideAttribute(): bool
    {
        return !empty($this->manual_status);
    }

    public function getOverrideReasonAttribute()
    {
        return $this->manual_note;
    }

    public function getEntriesCountAttribute(): int
    {
        return $this->office_visits ?: 1;
    }

    public function getFieldMinutesAttribute(): int
    {
        return max(0, 480 - ($this->office_minutes ?: 0));
    }
}
