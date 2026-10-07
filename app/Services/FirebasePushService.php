<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    protected static ?string $credentialsPath = null;
    public static ?string $lastError = null;

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

            $raw = file_get_contents($path);
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            $json = json_decode($raw, true);
            if (!$json || empty($json['client_email']) || empty($json['private_key'])) {
                Log::warning("FirebasePushService: Invalid service account file format: " . json_last_error_msg());
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
            $key = openssl_pkey_get_private($json['private_key']);
            if (!$key) {
                $err = openssl_error_string() ?: 'Invalid private key format';
                self::$lastError = "OpenSSL private key error: " . $err;
                Log::error("FirebasePushService: " . self::$lastError);
                return null;
            }

            if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
                $err = openssl_error_string() ?: 'Unknown sign error';
                self::$lastError = "OpenSSL sign error: " . $err;
                Log::error("FirebasePushService: " . self::$lastError);
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

            $errBody = $response->body();
            self::$lastError = "Google OAuth2 endpoint rejected JWT (" . $response->status() . "): " . $errBody;
            Log::error("FirebasePushService: " . self::$lastError);
            return null;
        });
    }

    /**
     * Send native Android WhatsApp-style heads-up Push Notification to a user.
     * @return array{success: bool, error: ?string}
     */
    public static function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        if (empty($user->fcm_token)) {
            Log::info("FirebasePushService: User [{$user->id}] ({$user->name}) has no registered FCM token.");
            return ['success' => false, 'error' => "User {$user->name} has no registered FCM device token."];
        }

        return self::sendToToken($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send push notification to a specific FCM device token via HTTP v1 API.
     * @return array{success: bool, error: ?string}
     */
    public static function sendToToken(string $fcmToken, string $title, string $body, array $data = []): array
    {
        $path = self::getCredentialsPath();
        if (!file_exists($path)) {
            $msg = "Service account file missing at [{$path}]. Please upload firebase-service-account.json into storage/app/";
            Log::warning("FirebasePushService: " . $msg);
            return ['success' => false, 'error' => $msg];
        }

        $raw = file_get_contents($path);
        // Strip UTF-8 BOM if present
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $json = json_decode($raw, true);

        if (!$json || !is_array($json)) {
            $err = json_last_error_msg();
            $msg = "Invalid JSON in service account file at [{$path}]: {$err}. (File size: " . strlen($raw) . " bytes)";
            Log::warning("FirebasePushService: " . $msg);
            return ['success' => false, 'error' => $msg];
        }

        if (empty($json['client_email']) || empty($json['private_key'])) {
            $keysFound = implode(', ', array_keys($json));
            $msg = "Service account JSON is missing required fields (client_email or private_key). Keys found: [{$keysFound}].";
            Log::warning("FirebasePushService: " . $msg);
            return ['success' => false, 'error' => $msg];
        }

        $projectId = $json['project_id'] ?? 'cms-engineer-portal';

        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            $msg = "Failed to obtain Google OAuth2 token. Verify private key syntax and that OpenSSL PHP extension is enabled.";
            Log::warning("FirebasePushService: " . $msg);
            return ['success' => false, 'error' => $msg];
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
            return ['success' => true, 'error' => null];
        }

        $errMsg = "FCM Google API Error (" . $response->status() . "): " . $response->body();
        Log::warning("FirebasePushService: " . $errMsg);
        return ['success' => false, 'error' => $errMsg];
    }
}
