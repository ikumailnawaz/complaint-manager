<?php

namespace App\Models\Tracking;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'uuid', 'lat', 'lng', 'accuracy', 'altitude', 'speed', 'bearing',
        'battery', 'is_charging', 'is_mocked', 'recorded_at', 'received_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'accuracy' => 'float',
        'altitude' => 'float',
        'speed' => 'float',
        'bearing' => 'float',
        'battery' => 'integer',
        'is_charging' => 'boolean',
        'is_mocked' => 'boolean',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
