<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('incoming_message_id', 255)->nullable()->after('customer_email')->comment('Original Message-ID from bank email header for threading');
            $table->string('email_subject', 500)->nullable()->after('incoming_message_id')->comment('Original email subject for threading');
            $table->boolean('is_human_verified')->default(false)->after('ai_classified')->comment('Whether an operator reviewed and finalized extracted details');
            $table->unsignedBigInteger('verified_by_id')->nullable()->after('is_human_verified');
            $table->timestamp('verified_at')->nullable()->after('verified_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'incoming_message_id',
                'email_subject',
                'is_human_verified',
                'verified_by_id',
                'verified_at',
            ]);
        });
    }
};
