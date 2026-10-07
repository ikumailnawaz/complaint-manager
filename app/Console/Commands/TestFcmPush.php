<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestFcmPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test {user_id?}';
    protected $description = 'Test Firebase Cloud Messaging push dispatch and diagnose credentials';

    public function handle()
    {
        $this->info("=== FCM DIAGNOSTIC TOOL ===");
        
        $credPath = storage_path('app/firebase-service-account.json');
        $this->line("1. Checking service account file at: " . $credPath);
        if (!file_exists($credPath)) {
            $this->error("❌ Service account file NOT found!");
            return 1;
        }
        $this->info("✅ File exists (" . filesize($credPath) . " bytes).");

        $this->line("2. Testing Google OAuth2 token generation...");
        \Illuminate\Support\Facades\Cache::forget('firebase_fcm_oauth_token');
        
        $raw = file_get_contents($credPath);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $json = json_decode($raw, true);

        if (!$json || !is_array($json)) {
            $this->error("❌ JSON parse error: " . json_last_error_msg());
            return 1;
        }

        if (empty($json['client_email']) || empty($json['private_key'])) {
            $this->error("❌ JSON missing client_email or private_key. Keys found: " . implode(', ', array_keys($json)));
            return 1;
        }
        $this->info("✅ JSON valid. Service Account: " . $json['client_email']);

        $key = openssl_pkey_get_private($json['private_key']);
        if (!$key) {
            $this->error("❌ OpenSSL failed to read private_key: " . (openssl_error_string() ?: 'Invalid private key format'));
            return 1;
        }
        $this->info("✅ OpenSSL successfully loaded RSA private key.");

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

        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            $this->error("❌ OpenSSL signing failed: " . (openssl_error_string() ?: 'unknown error'));
            return 1;
        }
        $this->info("✅ OpenSSL successfully signed JWT assertion.");

        $jwt = $data . '.' . $base64Url($signature);
        $response = \Illuminate\Support\Facades\Http::withoutVerifying()
            ->asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

        if (!$response->successful()) {
            $this->error("❌ Google OAuth2 HTTP Error (" . $response->status() . "): " . $response->body());
            return 1;
        }

        $token = $response->json('access_token');
        $this->info("✅ Google OAuth2 token obtained successfully: " . substr($token, 0, 20) . "...");

        $this->line("3. Checking registered engineers with FCM tokens in database...");
        $users = \App\Models\User::whereNotNull('fcm_token')->get();
        $this->info("Total users with registered FCM token: " . $users->count());
        foreach ($users as $u) {
            $this->line(" - User #{$u->id}: {$u->name} ({$u->email}) | Token: " . substr($u->fcm_token, 0, 25) . "...");
        }

        if ($users->isEmpty()) {
            $this->warn("⚠️ No user in the database has an FCM token registered yet!");
            return 0;
        }

        $userId = $this->argument('user_id') ?: $users->first()->id;
        $targetUser = \App\Models\User::find($userId);

        if (!$targetUser || empty($targetUser->fcm_token)) {
            $this->error("❌ User #{$userId} not found or has no FCM token!");
            return 1;
        }

        $this->line("4. Sending test push notification to User #{$targetUser->id} ({$targetUser->name})...");
        $result = \App\Services\FirebasePushService::sendToUser(
            $targetUser,
            "⚡ Test Assignment Push Notification",
            "This is a live test notification from CMS Engineer Portal at " . date('h:i:s A'),
            ['type' => 'test_push', 'timestamp' => time()]
        );

        if ($result['success']) {
            $this->info("🎉 SUCCESS: Push notification successfully accepted by Firebase and sent to device!");
        } else {
            $this->error("❌ FAILED: " . ($result['error'] ?? 'Unknown error'));
        }

        return 0;
    }
}
