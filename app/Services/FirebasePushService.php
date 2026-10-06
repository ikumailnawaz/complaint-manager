<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    protected static ?string $credentialsPath = null;

    /**
     * Get path to the Firebase Service Account JSON file.
     */
    protected static function getCredentialsPath(): string
    {
        if (self::$credentialsPath) {
            return self::$credentialsPath;
        }

        $defaultPath = storage_path('app/firebase-service-account.json');
        if (file_exists($defaultPath)) {
            return self::$credentialsPath = $defaultPath;
        }

        $envPath = env('FIREBASE_CREDENTIALS_PATH');
        if ($envPath && file_exists($envPath)) {
            return self::$credentialsPath = $envPath;
        }

        return self::$credentialsPath = $defaultPath;
    }

    /**
     * Obtain a Google OAuth2 Bearer Access Token using Service Account JWT.
     */
    public static function getAccessToken(): ?string
    {
        return Cache::remember('firebase_fcm_oauth_token', 3300, function () {
            $path = self::getCredentialsPath();
            if (!file_exists($path)) {
                Log::warning("FirebasePushService: Service account file not found at [{$path}].");
                return null;
            }

            $json = json_decode(file_get_contents($path), true);
            if (empty($json['client_email']) || empty($json['private_key'])) {
                Log::warning("FirebasePushService: Invalid service account file format.");
                return null;
            }

            $now = time();
            $base64Url = fn($data) => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));

            $header = $base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $base64Url(json_encode([
                'iss'   => $json['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'exp'   => $now + 3600,
                'iat'   => $now,
            ]));

            $data = $header . '.' . $claim;
            $signature = '';
            if (!openssl_sign($data, $signature, $json['private_key'], 'sha256')) {
                Log::error("FirebasePushService: OpenSSL failed to sign JWT assertion.");
                return null;
            }

            $jwt = $data . '.' . $base64Url($signature);

            $response = Http::withoutVerifying()
                ->asForm()
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error("FirebasePushService: OAuth2 token exchange failed: " . $response->body());
            return null;
        });
    }

    /**
     * Send native Android WhatsApp-style heads-up Push Notification to a user.
     */
    public static function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (empty($user->fcm_token)) {
            Log::info("FirebasePushService: User [{$user->id}] ({$user->name}) has no registered FCM token.");
            return false;
        }

        return self::sendToToken($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send push notification to a specific FCM device token via HTTP v1 API.
     */
    public static function sendToToken(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        $path = self::getCredentialsPath();
        if (!file_exists($path)) {
            return false;
        }

        $json = json_decode(file_get_contents($path), true);
        $projectId = $json['project_id'] ?? 'cms-engineer-portal';

        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            Log::warning("FirebasePushService: Cannot send push, access token unavailable.");
            return false;
        }

        // Convert all data values to strings (FCM requirement)
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_array($v) ? json_encode($v) : (string) $v;
        }
        $stringData['title'] = $title;
        $stringData['body'] = $body;
        $stringData['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id'             => 'ticket_alerts_channel',
                        'default_sound'          => true,
                        'default_vibrate_timings'=> true,
                        'priority'               => 'MAX',
                        'visibility'             => 'PUBLIC',
                        'notification_priority'  => 'PRIORITY_MAX',
                    ],
                ],
                'data' => $stringData,
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $response = Http::withoutVerifying()
            ->withToken($accessToken)
            ->post($url, $payload);

        if ($response->successful()) {
            Log::info("FirebasePushService: Push notification delivered successfully to token [" . substr($fcmToken, 0, 15) . "...].");
            return true;
        }

        Log::warning("FirebasePushService: FCM delivery failed: " . $response->body());
        return false;
    }
}
