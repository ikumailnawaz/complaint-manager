<?php

namespace App\Models\Tracking;

use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    protected $table = 'geofences';

    protected $fillable = ['name', 'lat', 'lng', 'radius_m', 'is_attendance_point', 'is_active'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'radius_m' => 'integer',
        'is_attendance_point' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
