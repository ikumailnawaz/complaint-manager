<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('tickets', 'workshop_incharge_id')) {
                $table->foreignId('workshop_incharge_id')->nullable()->constrained('users')->nullOnDelete()->after('workshop_engineer_id');
            }
            if (!Schema::hasColumn('tickets', 'workshop_tat_hours')) {
                $table->integer('workshop_tat_hours')->nullable()->after('workshop_intake_remarks');
            }
            if (!Schema::hasColumn('tickets', 'workshop_tat_deadline')) {
                $table->timestamp('workshop_tat_deadline')->nullable()->after('workshop_tat_hours');
            }
            if (!Schema::hasColumn('tickets', 'workshop_instructions')) {
                $table->text('workshop_instructions')->nullable()->after('workshop_tat_deadline');
            }
            if (!Schema::hasColumn('tickets', 'workshop_realigned_at')) {
                $table->timestamp('workshop_realigned_at')->nullable()->after('workshop_instructions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workshop_incharge_id');
            $table->dropColumn([
                'workshop_tat_hours',
                'workshop_tat_deadline',
                'workshop_instructions',
                'workshop_realigned_at',
            ]);
        });
    }
};
