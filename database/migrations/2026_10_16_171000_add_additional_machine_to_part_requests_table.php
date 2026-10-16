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
        Schema::table('part_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('part_requests', 'additional_machine_model_id')) {
                $table->foreignId('additional_machine_model_id')->nullable()->after('fault_video_path')->constrained('machine_models')->nullOnDelete();
            }
            if (!Schema::hasColumn('part_requests', 'additional_machine_model')) {
                $table->string('additional_machine_model', 100)->nullable()->after('additional_machine_model_id');
            }
            if (!Schema::hasColumn('part_requests', 'additional_machine_serial_no')) {
                $table->string('additional_machine_serial_no', 100)->nullable()->after('additional_machine_model');
            }
            if (!Schema::hasColumn('part_requests', 'additional_machine_type')) {
                $table->string('additional_machine_type', 100)->nullable()->after('additional_machine_serial_no');
            }
            if (!Schema::hasColumn('part_requests', 'additional_fault_description')) {
                $table->text('additional_fault_description')->nullable()->after('additional_machine_type');
            }
            if (!Schema::hasColumn('part_requests', 'additional_fault_video_path')) {
                $table->string('additional_fault_video_path')->nullable()->after('additional_fault_description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $cols = [
                'additional_fault_video_path',
                'additional_fault_description',
                'additional_machine_type',
                'additional_machine_serial_no',
                'additional_machine_model',
                'additional_machine_model_id',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('part_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
