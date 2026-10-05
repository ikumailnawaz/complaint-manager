<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('resolution_email_sent')->default(false)->after('email_reply_message_id');
            $table->timestamp('resolution_email_sent_at')->nullable()->after('resolution_email_sent');
            $table->foreignId('resolution_email_sent_by_id')->nullable()->constrained('users')->nullOnDelete()->after('resolution_email_sent_at');
            $table->string('resolution_email_message_id')->nullable()->after('resolution_email_sent_by_id');
            $table->string('resolution_email_to')->nullable()->after('resolution_email_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['resolution_email_sent_by_id']);
            $table->dropColumn([
                'resolution_email_sent',
                'resolution_email_sent_at',
                'resolution_email_sent_by_id',
                'resolution_email_message_id',
                'resolution_email_to',
            ]);
        });
    }
};
