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
        Schema::create('ticket_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('engineer_id')->constrained('users')->cascadeOnDelete();
            $table->integer('day_number')->default(1); // Day 1, Day 2, Day 3...
            $table->text('feedback_text');
            $table->string('status')->default('submitted'); // submitted, pending, missed
            $table->string('action_taken')->nullable(); // On Site, Waiting Parts, Machine Sent to Workshop, etc.
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_feedbacks');
    }
};
