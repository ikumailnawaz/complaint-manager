<?php

namespace App\Models\Tracking;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingDevice extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'key_hash', 'device_model', 'os_version', 'app_version',
        'last_seen_at', 'revoked_at', 'revoked_reason',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
