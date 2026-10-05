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
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('engineer_id')->constrained('users')->cascadeOnDelete();
            $table->string('from_city'); // Base city of engineer
            $table->string('to_city'); // Branch city
            $table->string('trip_type')->default('round_trip'); // round_trip, one_way
            $table->decimal('ai_distance_km', 8, 2)->nullable(); // Gemini/AI tentative round-trip distance
            $table->decimal('ai_estimated_hours', 5, 2)->nullable();
            $table->decimal('claimed_amount', 10, 2);
            $table->decimal('suggested_amount', 10, 2)->nullable(); // Distance * per_km_rate
            $table->string('status')->default('submitted'); // submitted, approved, rejected, paid
            $table->text('admin_notes')->nullable(); // Rejection reason or approval remarks
            $table->integer('resubmission_count')->default(0);
            
            // Uploaded Documents
            $table->string('voucher_file')->nullable(); // Voucher PDF or image path
            $table->string('supporting_doc')->nullable(); // Finalized complaint / service report proof
            
            // Payment info
            $table->string('payment_method')->nullable(); // bank_transfer, cash, cheque
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
    }
};
