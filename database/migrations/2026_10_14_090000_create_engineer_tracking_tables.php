<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Engineer GPS tracking, trip/stop analytics and automated geofence attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('radius_m')->default(75);
            $table->boolean('is_attendance_point')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tracking_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id', 100)->index();
            $table->string('key_hash', 64)->unique();
            $table->string('device_model', 150)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('location_points', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('uuid', 40)->unique();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->float('accuracy')->nullable();
            $table->float('altitude')->nullable();
            $table->float('speed')->nullable();
            $table->float('bearing')->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->boolean('is_charging')->default(false);
            $table->boolean('is_mocked')->default(false);
            $table->dateTime('recorded_at');
            $table->dateTime('received_at');
            $table->index(['user_id', 'recorded_at']);
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('uuid', 40)->unique();
            $table->string('type', 40);
            $table->unsignedBigInteger('geofence_id')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('recorded_at');
            $table->dateTime('received_at');
            $table->text('meta')->nullable();
            $table->index(['user_id', 'recorded_at']);
        });

        Schema::create('trip_segments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('seq');
            $table->string('type', 10); // stop | drive | gap
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('duration_sec')->default(0);
            $table->unsignedInteger('distance_m')->default(0);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();
            $table->string('kind', 20)->nullable(); // office | ticket_site | unknown
            $table->string('label', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('geofence_id')->nullable();
            $table->float('avg_speed_kmh')->nullable();
            $table->float('max_speed_kmh')->nullable();
            $table->unsignedInteger('point_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        Schema::create('tracking_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('distance_m')->default(0);
            $table->unsignedInteger('drive_sec')->default(0);
            $table->unsignedInteger('stop_sec')->default(0);
            $table->unsignedInteger('office_sec')->default(0);
            $table->unsignedInteger('site_sec')->default(0);
            $table->unsignedInteger('unknown_stop_sec')->default(0);
            $table->unsignedInteger('long_unknown_stop_sec')->default(0);
            $table->unsignedInteger('gap_sec')->default(0);
            $table->unsignedSmallInteger('stops_count')->default(0);
            $table->unsignedInteger('points_count')->default(0);
            $table->unsignedSmallInteger('mocked_points')->default(0);
            $table->dateTime('first_point_at')->nullable();
            $table->dateTime('last_point_at')->nullable();
            $table->float('max_speed_kmh')->nullable();
            $table->unsignedTinyInteger('efficiency_score')->nullable();
            $table->boolean('needs_processing')->default(true);
            $table->dateTime('processed_at')->nullable();
            $table->text('ai_summary')->nullable();
            $table->dateTime('ai_generated_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
            $table->index(['needs_processing']);
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('geofence_id')->nullable();
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->boolean('still_inside')->default(false);
            $table->unsignedSmallInteger('office_visits')->default(0);
            $table->unsignedInteger('office_minutes')->default(0);
            $table->string('status', 20)->default('absent'); // present | late | field | absent | off_day
            $table->unsignedSmallInteger('late_minutes')->default(0);
            // Manual admin override (audited)
            $table->dateTime('manual_first_in')->nullable();
            $table->dateTime('manual_last_out')->nullable();
            $table->string('manual_status', 20)->nullable();
            $table->string('manual_note', 255)->nullable();
            $table->unsignedBigInteger('overridden_by_id')->nullable();
            $table->dateTime('overridden_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::create('engineer_live_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->float('accuracy')->nullable();
            $table->float('speed')->nullable();
            $table->float('bearing')->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->boolean('is_charging')->default(false);
            $table->boolean('is_mocked')->default(false);
            $table->dateTime('recorded_at')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->string('last_event_type', 40)->nullable();
            $table->dateTime('last_event_at')->nullable();
            $table->boolean('is_logged_out')->default(false);
            $table->boolean('gps_enabled')->default(true);
            $table->string('app_version', 40)->nullable();
            $table->string('device_model', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('tracking_ai_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('kind', 20); // summary | tuning
            $table->text('response')->nullable();
            $table->boolean('applied')->default(false);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
        });

        Schema::create('geo_cache', function (Blueprint $table) {
            $table->id();
            $table->decimal('lat_r', 8, 4);
            $table->decimal('lng_r', 8, 4);
            $table->string('address', 255)->nullable();
            $table->timestamps();
            $table->unique(['lat_r', 'lng_r']);
        });

        // Seed the Lahore office used for testing (editable later in Tracking Settings)
        DB::table('geofences')->insert([
            'name' => 'Lahore Office',
            'lat' => 31.5397357,
            'lng' => 74.3306892,
            'radius_m' => 75,
            'is_attendance_point' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach ([
            'geo_cache', 'tracking_ai_reviews', 'engineer_live_status', 'attendance_days',
            'tracking_daily_summaries', 'trip_segments', 'tracking_events', 'location_points',
            'tracking_devices', 'geofences', 'tracking_settings',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
