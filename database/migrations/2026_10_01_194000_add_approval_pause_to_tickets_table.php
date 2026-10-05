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
            $table->timestamp('approval_requested_at')->nullable()->after('sla_deadline');
            $table->foreignId('approval_requested_by_id')->nullable()->after('approval_requested_at')->constrained('users')->nullOnDelete();
            $table->string('approval_source')->nullable()->after('approval_requested_by_id');
            $table->text('approval_request_reason')->nullable()->after('approval_source');
            $table->timestamp('sla_paused_at')->nullable()->after('approval_request_reason');
            $table->unsignedInteger('approval_paused_seconds')->default(0)->after('sla_paused_at');
            $table->timestamp('approval_arrived_at')->nullable()->after('approval_paused_seconds');
            $table->foreignId('approval_arrived_by_id')->nullable()->after('approval_arrived_at')->constrained('users')->nullOnDelete();
            $table->text('approval_arrived_remarks')->nullable()->after('approval_arrived_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['approval_requested_by_id']);
            $table->dropForeign(['approval_arrived_by_id']);

            $table->dropColumn([
                'approval_requested_at',
                'approval_requested_by_id',
                'approval_source',
                'approval_request_reason',
                'sla_paused_at',
                'approval_paused_seconds',
                'approval_arrived_at',
                'approval_arrived_by_id',
                'approval_arrived_remarks',
            ]);
        });
    }
};
