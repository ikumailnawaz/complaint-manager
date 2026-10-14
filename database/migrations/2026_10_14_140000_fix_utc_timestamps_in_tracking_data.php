<?php

use App\Models\Tracking\EngineerLiveStatus;
use App\Models\Tracking\LocationPoint;
use App\Models\Tracking\TrackingEvent;
use App\Models\User;
use App\Services\Tracking\TrackingEngine;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix points and events where recorded_at was stored in UTC (5 hours behind Asia/Karachi).
     */
    public function up(): void
    {
        // 1. Shift recorded_at by +5 hours if recorded_at was stored in UTC
        LocationPoint::chunk(200, function ($points) {
            foreach ($points as $p) {
                if ($p->recorded_at && $p->received_at) {
                    $diff = $p->recorded_at->diffInMinutes($p->received_at);
                    if ($diff >= 270 && $diff <= 330) {
                        $p->recorded_at = Carbon::parse($p->recorded_at)->addHours(5);
                        $p->save();
                    }
                }
            }
        });

        TrackingEvent::chunk(200, function ($events) {
            foreach ($events as $ev) {
                if ($ev->recorded_at && $ev->received_at) {
                    $diff = $ev->recorded_at->diffInMinutes($ev->received_at);
                    if ($diff >= 270 && $diff <= 330) {
                        $ev->recorded_at = Carbon::parse($ev->recorded_at)->addHours(5);
                        $ev->save();
                    }
                }
            }
        });

        // 2. Fix engineer live status recorded_at and last_event_at
        $statuses = EngineerLiveStatus::all();
        foreach ($statuses as $st) {
            $updated = false;
            if ($st->recorded_at && $st->last_sync_at) {
                $diff = Carbon::parse($st->recorded_at)->diffInMinutes(Carbon::parse($st->last_sync_at));
                if ($diff >= 270 && $diff <= 330) {
                    $st->recorded_at = Carbon::parse($st->recorded_at)->addHours(5);
                    $updated = true;
                }
            }
            if ($st->last_event_at && $st->last_sync_at) {
                $diff = Carbon::parse($st->last_event_at)->diffInMinutes(Carbon::parse($st->last_sync_at));
                if ($diff >= 270 && $diff <= 330) {
                    $st->last_event_at = Carbon::parse($st->last_event_at)->addHours(5);
                    $updated = true;
                }
            }
            if ($updated) {
                $st->save();
            }
        }

        // 3. Recalculate today's attendance & trips
        try {
            $engine = app(TrackingEngine::class);
            $today = Carbon::today()->toDateString();
            $engineers = User::whereIn('role', ['engineer', 'workshop_technician', 'admin'])->get();
            foreach ($engineers as $eng) {
                $engine->processAttendance($eng->id, $today);
                $engine->processUserDate($eng->id, $today);
            }
        } catch (\Throwable $e) {
            // Ignore during setup
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
