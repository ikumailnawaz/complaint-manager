<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->cascadeOnDelete();
            $table->foreignId('pm_machine_id')->constrained('pm_machines')->cascadeOnDelete();
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('performed_at')->nullable();
            $table->date('due_date');
            $table->string('status')->default('completed'); // completed, missed
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->boolean('is_overdue')->default(false);  // true if completed after due_date
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_records');
    }
};
