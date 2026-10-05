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
            // Stage 1 & 2: Original Field Engineer & Inbound Transit
            $table->foreignId('original_field_engineer_id')->nullable()->after('assigned_engineer_id')->constrained('users')->nullOnDelete();
            $table->string('workshop_dispatch_courier')->nullable()->after('workshop_location');
            $table->string('workshop_dispatch_tracking')->nullable()->after('workshop_dispatch_courier');
            $table->timestamp('workshop_dispatched_at')->nullable()->after('workshop_dispatch_tracking');
            $table->text('workshop_dispatch_notes')->nullable()->after('workshop_dispatched_at');

            // Stage 3: Workshop Intake & Assignment
            $table->foreignId('workshop_engineer_id')->nullable()->after('original_field_engineer_id')->constrained('users')->nullOnDelete();
            $table->timestamp('workshop_received_at')->nullable()->after('workshop_dispatch_notes');
            $table->foreignId('workshop_received_by_id')->nullable()->after('workshop_received_at')->constrained('users')->nullOnDelete();
            $table->text('workshop_intake_remarks')->nullable()->after('workshop_received_by_id');
            $table->timestamp('workshop_repaired_at')->nullable()->after('workshop_intake_remarks');
            $table->text('workshop_repair_summary')->nullable()->after('workshop_repaired_at');

            // Stage 4: Return Dispatch to Bank Branch & Closure
            $table->string('return_courier')->nullable()->after('workshop_repair_summary');
            $table->string('return_tracking_number')->nullable()->after('return_courier');
            $table->timestamp('return_dispatched_at')->nullable()->after('return_tracking_number');
            $table->foreignId('return_dispatched_by_id')->nullable()->after('return_dispatched_at')->constrained('users')->nullOnDelete();
            $table->text('return_dispatch_notes')->nullable()->after('return_dispatched_by_id');
            $table->timestamp('bank_received_at')->nullable()->after('return_dispatch_notes');
            $table->foreignId('bank_received_confirmed_by_id')->nullable()->after('bank_received_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['original_field_engineer_id']);
            $table->dropForeign(['workshop_engineer_id']);
            $table->dropForeign(['workshop_received_by_id']);
            $table->dropForeign(['return_dispatched_by_id']);
            $table->dropForeign(['bank_received_confirmed_by_id']);

            $table->dropColumn([
                'original_field_engineer_id',
                'workshop_engineer_id',
                'workshop_dispatch_courier',
                'workshop_dispatch_tracking',
                'workshop_dispatched_at',
                'workshop_dispatch_notes',
                'workshop_received_at',
                'workshop_received_by_id',
                'workshop_intake_remarks',
                'workshop_repaired_at',
                'workshop_repair_summary',
                'return_courier',
                'return_tracking_number',
                'return_dispatched_at',
                'return_dispatched_by_id',
                'return_dispatch_notes',
                'bank_received_at',
                'bank_received_confirmed_by_id',
            ]);
        });
    }
};
