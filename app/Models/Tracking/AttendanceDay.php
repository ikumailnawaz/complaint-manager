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

    protected $appends = [
        'first_in_at',
        'last_out_at',
        'is_late',
        'is_manual_override',
        'override_reason',
        'entries_count',
        'field_minutes',
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

    /**
     * Accurately compute office minutes.
     * If still inside today, dynamic time from arrival up to now().
     */
    public function getOfficeMinutesAttribute($value): int
    {
        $val = (int)($value ?? 0);
        $firstIn = $this->effective_first_in;
        $lastOut = $this->effective_last_out;

        if ($this->still_inside && $firstIn) {
            $inTime = Carbon::parse($firstIn);
            if (Carbon::parse($this->date)->isToday()) {
                return (int)max($val, $inTime->diffInMinutes(now()));
            }
        } elseif ($firstIn && $lastOut) {
            $diff = (int)Carbon::parse($firstIn)->diffInMinutes(Carbon::parse($lastOut));
            return max($val, $diff);
        }

        return $val;
    }

    /**
     * Actual transit/drive minutes tracked by GPS.
     * Do NOT hardcode fictitious workday hours.
     */
    public function getFieldMinutesAttribute(): int
    {
        $driveSec = TrackingDailySummary::where('user_id', $this->user_id)
            ->where('date', $this->date)
            ->value('drive_sec');

        if ($driveSec && $driveSec > 0) {
            return (int)round($driveSec / 60);
        }

        return 0;
    }
}
