<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Current stock balance held by each engineer in their advance float/envelope
        if (!Schema::hasTable('engineer_inventories')) {
            Schema::create('engineer_inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('engineer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
                $table->integer('qty_allocated')->default(0); // Cumulative total advance parts lent
                $table->integer('qty_used')->default(0);      // Cumulative parts consumed on complaints
                $table->integer('qty_on_hand')->default(0);   // Current remaining balance in engineer's envelope
                $table->timestamps();

                $table->unique(['engineer_id', 'part_id'], 'eng_inv_engineer_part_unique');
            });
        }

        // Audit log of all advance part allocations, complaint consumptions, and returns
        if (!Schema::hasTable('engineer_advance_transactions')) {
            Schema::create('engineer_advance_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('engineer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
                // type: advance_issue (lent to engineer), consumed_complaint (used on ticket), returned_to_warehouse (returned)
                $table->string('type', 40);
                $table->integer('qty');
                $table->foreignId('source_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
                $table->foreignId('part_request_id')->nullable()->constrained('part_requests')->nullOnDelete();
                $table->foreignId('machine_model_id')->nullable()->constrained('machine_models')->nullOnDelete();
                $table->string('machine_serial_no', 100)->nullable();
                $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // Add envelope tracking columns to part_request_items
        if (!Schema::hasColumn('part_request_items', 'qty_from_envelope')) {
            Schema::table('part_request_items', function (Blueprint $table) {
                $table->integer('qty_from_envelope')->default(0)->after('qty_requested');
            });
        }
    }

    public function down(): void
    {
        Schema::table('part_request_items', function (Blueprint $table) {
            $table->dropColumn('qty_from_envelope');
        });
        Schema::dropIfExists('engineer_advance_transactions');
        Schema::dropIfExists('engineer_inventories');
    }
};
