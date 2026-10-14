<?php

namespace App\Models\Tracking;

use Illuminate\Database\Eloquent\Model;

class GeoCache extends Model
{
    protected $table = 'geo_cache';

    protected $fillable = ['lat_r', 'lng_r', 'address'];

    public static function lookup(float $lat, float $lng): ?string
    {
        $latR = round($lat, 4);
        $lngR = round($lng, 4);
        $entry = static::where('lat_r', $latR)->where('lng_r', $lngR)->first();
        return $entry?->address;
    }

    public static function store(float $lat, float $lng, string $address): void
    {
        $latR = round($lat, 4);
        $lngR = round($lng, 4);
        static::updateOrCreate(
            ['lat_r' => $latR, 'lng_r' => $lngR],
            ['address' => $address]
        );
    }
}
