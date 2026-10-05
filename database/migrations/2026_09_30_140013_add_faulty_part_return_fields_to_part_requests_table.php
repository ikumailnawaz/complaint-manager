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
        if (!Schema::hasColumn('part_requests', 'faulty_return_status')) {
            Schema::table('part_requests', function (Blueprint $table) {
                // faulty_return_status: not_applicable, pending_return, returned, waived
                $table->string('faulty_return_status')->default('not_applicable')->after('dispatch_tracking_number');
                $table->timestamp('faulty_returned_at')->nullable()->after('faulty_return_status');
                $table->unsignedBigInteger('faulty_return_received_by_id')->nullable()->after('faulty_returned_at');
                $table->unsignedBigInteger('faulty_return_location_id')->nullable()->after('faulty_return_received_by_id');
                $table->string('faulty_return_courier_tracking')->nullable()->after('faulty_return_location_id');
                $table->text('faulty_return_remarks')->nullable()->after('faulty_return_courier_tracking');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropColumn([
                'faulty_return_status',
                'faulty_returned_at',
                'faulty_return_received_by_id',
                'faulty_return_location_id',
                'faulty_return_courier_tracking',
                'faulty_return_remarks',
            ]);
        });
    }
};
