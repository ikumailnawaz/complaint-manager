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
            if (!Schema::hasColumn('part_requests', 'additional_machines')) {
                $table->json('additional_machines')->nullable()->after('additional_fault_video_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            if (Schema::hasColumn('part_requests', 'additional_machines')) {
                $table->dropColumn('additional_machines');
            }
        });
    }
};
