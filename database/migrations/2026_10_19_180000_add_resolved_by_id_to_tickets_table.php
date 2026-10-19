<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('resolved_by_id')->nullable()->after('resolved_at');
            $table->foreign('resolved_by_id')->references('id')->on('users')->onDelete('set null');
        });

        // Backfill existing resolved workshop tickets: credit to workshop_engineer_id
        DB::table('tickets')
            ->whereNotNull('workshop_engineer_id')
            ->where(function ($q) {
                $q->whereIn('status', ['resolved', 'closed', 'workshop_repaired', 'return_transit'])
                  ->orWhereNotNull('workshop_repaired_at');
            })
            ->update([
                'resolved_by_id' => DB::raw('workshop_engineer_id')
            ]);

        // Backfill existing resolved on-site tickets: credit to assigned_engineer_id
        DB::table('tickets')
            ->whereNull('resolved_by_id')
            ->whereNull('workshop_engineer_id')
            ->whereIn('status', ['resolved', 'closed'])
            ->whereNotNull('assigned_engineer_id')
            ->update([
                'resolved_by_id' => DB::raw('assigned_engineer_id')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['resolved_by_id']);
            $table->dropColumn('resolved_by_id');
        });
    }
};
