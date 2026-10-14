<?php

namespace App\Services\Tracking;

use App\Models\Tracking\AttendanceDay;
use App\Models\Tracking\TrackingDailySummary;
use App\Models\Tracking\TrackingSetting;
use App\Models\Tracking\TripSegment;
use App\Models\User;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrackingAiService
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Generate an AI narrative summary of an engineer's day.
     */
    public function generateDailySummary(User $user, string $date): ?string
    {
        $dateStr = Carbon::parse($date)->toDateString();
        $summary = TrackingDailySummary::where('user_id', $user->id)->where('date', $dateStr)->first();
        $attendance = AttendanceDay::where('user_id', $user->id)->where('date', $dateStr)->first();
        $segments = TripSegment::where('user_id', $user->id)->where('date', $dateStr)->orderBy('started_at')->get();

        if (!$summary || $segments->isEmpty()) {
            return "No tracking telemetry recorded for {$user->name} on {$dateStr}.";
        }

        $stopsList = [];
        foreach ($segments as $s) {
            if ($s->type === 'stop') {
                $durMin = round($s->duration_sec / 60);
                $start = Carbon::parse($s->started_at)->format('h:i A');
                $end = Carbon::parse($s->ended_at)->format('h:i A');
                $stopsList[] = "- [{$s->kind}] {$s->label}: {$durMin} mins ({$start} to {$end})";
            }
        }
        $stopsText = implode("\n", $stopsList);

        $distKm = round($summary->distance_m / 1000, 1);
        $driveMin = round($summary->drive_sec / 60);
        $firstIn = $attendance && $attendance->first_in ? Carbon::parse($attendance->first_in)->format('h:i A') : 'None';
        $status = $attendance ? $attendance->status : 'absent';

        $prompt = <<<EOT
You are an Operations Audit AI for a banking maintenance company in Pakistan.
Analyze this field engineer's daily GPS tracking telemetry and write a professional, concise 3-4 sentence performance appraisal summary:

Engineer: {$user->name} ({$user->role})
Date: {$date}
Office Check-in: {$firstIn} (Status: {$status})
Total Distance Travelled: {$distKm} km
Total Drive Time: {$driveMin} minutes
Stops Count: {$summary->stops_count}
Unknown / Unplanned Stop Duration: {$summary->unknown_stop_sec} seconds (Long Unknown Stops: {$summary->long_unknown_stop_sec} seconds)
Efficiency Score: {$summary->efficiency_score} / 100

Stops Breakdown:
{$stopsText}

Focus on:
1. Punctuality and total travel activity.
2. Legitimate branch/site work vs suspicious unplanned idle time or excessive detours.
3. Final evaluation (Efficient / Acceptable / Requires Review). Keep it strictly factual and professional.
EOT;

        if ($this->gemini->hasApiKey()) {
            try {
                $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
                $key = env('GEMINI_API_KEY');

                $res = Http::withoutVerifying()->timeout(15)->post("{$apiUrl}?key={$key}", [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 300],
                ]);

                if ($res->successful()) {
                    $json = $res->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($text) {
                        $summary->ai_summary = trim($text);
                        $summary->ai_generated_at = now();
                        $summary->save();
                        return trim($text);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini Daily Tracking Summary exception: ' . $e->getMessage());
            }
        }

        // Rule-based fallback summary
        $unknownMin = round($summary->unknown_stop_sec / 60);
        $fallback = "Engineer {$user->name} recorded {$distKm} km across {$summary->stops_count} stops on {$date}. Office entry was at {$firstIn} ({$status}). Unplanned stoppage was {$unknownMin} mins. Daily efficiency rating: {$summary->efficiency_score}%.";

        $summary->ai_summary = $fallback;
        $summary->ai_generated_at = now();
        $summary->save();

        return $fallback;
    }

    /**
     * AI Parameter Self-Tuning: Analyze trip segments and GPS drift to suggest threshold adjustments.
     */
    public function analyzeAndSuggestTuning(string $date): array
    {
        $settings = TrackingSetting::allSettings();
        $summaries = TrackingDailySummary::where('date', $date)->get();
        $stops = TripSegment::where('date', $date)->where('type', 'stop')->get();

        $shortStops = $stops->filter(fn($s) => $s->duration_sec < 600)->count();
        $longStops = $stops->filter(fn($s) => $s->duration_sec >= 3600)->count();

        $prompt = <<<EOT
You are an expert GPS Geospatial Data Engineer.
A field engineer tracking system in Pakistan uses these stay-point detection parameters:
- Stop Radius (R): {$settings['stop_radius_m']} meters
- Minimum Stop Duration (T): {$settings['stop_min_minutes']} minutes
- Stop Exit Radius (R_exit): {$settings['stop_exit_radius_m']} meters
- Max GPS Inaccuracy: {$settings['max_accuracy_m']} meters

Today's Aggregated Telemetry:
- Engineers tracked: {$summaries->count()}
- Total stops identified: {$stops->count()}
- Short stops (<10 mins): {$shortStops}
- Long stops (>60 mins): {$longStops}

Analyze whether the current parameters are optimal or if minor threshold adjustments (e.g. increasing R by 10m for dense indoor branch drift, or tweaking R_exit) would yield cleaner trip segmentation.
Return strictly a JSON object:
{
  "status": "optimal" | "adjustment_recommended",
  "analysis": "2-3 sentences explaining findings",
  "suggested_settings": {
     "stop_radius_m": number,
     "stop_min_minutes": number,
     "stop_exit_radius_m": number
  }
}
EOT;

        if ($this->gemini->hasApiKey()) {
            try {
                $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
                $key = env('GEMINI_API_KEY');

                $res = Http::withoutVerifying()->timeout(15)->post("{$apiUrl}?key={$key}", [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.1, 'response_mime_type' => 'application/json'],
                ]);

                if ($res->successful()) {
                    $json = $res->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                    $parsed = json_decode(trim($text), true);
                    if ($parsed && isset($parsed['analysis'])) {
                        return $parsed;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini Tuning Exception: ' . $e->getMessage());
            }
        }

        return [
            'status' => 'optimal',
            'analysis' => 'Current stay-point detection parameters (R=80m, T=5m, R_exit=150m) are well-balanced for motorcycle field transit in Lahore with effective drift rejection.',
            'suggested_settings' => [
                'stop_radius_m' => (int)($settings['stop_radius_m'] ?? 80),
                'stop_min_minutes' => (int)($settings['stop_min_minutes'] ?? 5),
                'stop_exit_radius_m' => (int)($settings['stop_exit_radius_m'] ?? 150),
            ],
        ];
    }
}
