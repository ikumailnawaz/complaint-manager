<?php

namespace App\Models\Tracking;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineerLiveStatus extends Model
{
    protected $table = 'engineer_live_status';

    protected $fillable = [
        'user_id', 'lat', 'lng', 'accuracy', 'speed', 'bearing', 'battery', 'is_charging', 'is_mocked',
        'recorded_at', 'last_sync_at', 'last_event_type', 'last_event_at', 'is_logged_out',
        'gps_enabled', 'app_version', 'device_model',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'accuracy' => 'float',
        'speed' => 'float',
        'bearing' => 'float',
        'is_charging' => 'boolean',
        'is_mocked' => 'boolean',
        'is_logged_out' => 'boolean',
        'gps_enabled' => 'boolean',
        'recorded_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
