<?php

namespace App\Models\Tracking;

use Illuminate\Database\Eloquent\Model;

class TrackingSetting extends Model
{
    protected $table = 'tracking_settings';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        $decoded = json_decode($setting->value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $setting->value;
    }

    public static function set(string $key, $value): void
    {
        $serialized = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;
        static::updateOrCreate(['key' => $key], ['value' => $serialized]);
    }

    public static function allSettings(): array
    {
        $defaults = [
            'tracking_enabled'        => true,
            'timezone'                => 'Asia/Karachi',
            'duty_start'              => '08:00',
            'duty_end'                => '20:00',
            'work_days'               => [1, 2, 3, 4, 5, 6], // Mon-Sat
            'attendance_late_time'    => '09:30', // 09:00 + 30 min grace
            'moving_interval_sec'     => 10,
            'moving_distance_m'       => 20,
            'stationary_interval_sec' => 120,
            'max_accuracy_m'          => 50,
            'upload_interval_sec'     => 120,
            'heartbeat_interval_sec'  => 900,
            'attendance_dwell_sec'    => 120,
            'stop_radius_m'           => 80,
            'stop_min_minutes'        => 5,
            'stop_exit_radius_m'      => 150,
            'lock_password'           => 'admin',
        ];

        $stored = static::pluck('value', 'key')->toArray();
        $merged = [];
        foreach ($defaults as $k => $v) {
            if (array_key_exists($k, $stored)) {
                $dec = json_decode($stored[$k], true);
                $merged[$k] = (json_last_error() === JSON_ERROR_NONE) ? $dec : $stored[$k];
            } else {
                $merged[$k] = $v;
            }
        }

        return $merged;
    }
}
