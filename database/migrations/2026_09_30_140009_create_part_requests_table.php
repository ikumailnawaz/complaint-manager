<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('part_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('engineer_id');
            $table->unsignedBigInteger('machine_model_id')->nullable();
            $table->string('machine_serial_no')->nullable();
            $table->text('fault_description')->nullable();
            // status: pending_stock_check, stock_verified, pending_approval, approved, dispatched, rejected
            $table->string('status')->default('pending_stock_check');
            $table->string('fault_video_path')->nullable();
            // Stock verification
            $table->unsignedBigInteger('stock_verified_by_id')->nullable();
            $table->timestamp('stock_verified_at')->nullable();
            $table->text('stock_remarks')->nullable();
            // Approval
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            // Rejection
            $table->unsignedBigInteger('rejected_by_id')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            // Dispatch
            $table->unsignedBigInteger('dispatched_by_id')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->unsignedBigInteger('dispatch_location_id')->nullable();
            $table->string('dispatch_courier')->nullable();
            $table->string('dispatch_tracking_number')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('part_requests'); }
};
