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
        $token = \App\Services\FirebasePushService::getAccessToken();
        if (!$token) {
            $this->error("❌ Failed to obtain OAuth2 token from Google!");
            return 1;
        }
        $this->info("✅ OAuth2 token obtained successfully: " . substr($token, 0, 20) . "...");

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
        $sent = \App\Services\FirebasePushService::sendToUser(
            $targetUser,
            "⚡ Test Assignment Push Notification",
            "This is a live test notification from CMS Engineer Portal at " . date('h:i:s A'),
            ['type' => 'test_push', 'timestamp' => time()]
        );

        if ($sent) {
            $this->info("🎉 SUCCESS: Push notification successfully accepted by Firebase and sent to device!");
        } else {
            $this->error("❌ FAILED: Firebase rejected or failed to deliver push notification. Check storage/logs/laravel.log.");
        }

        return 0;
    }
}
