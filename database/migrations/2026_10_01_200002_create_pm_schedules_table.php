<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_machine_id')->constrained('pm_machines')->cascadeOnDelete();
            $table->string('title');                         // e.g. "Monthly Cleaning"
            $table->unsignedInteger('frequency_days');       // 30 = monthly, 90 = quarterly
            $table->foreignId('assigned_engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('last_performed_at')->nullable();
            $table->date('next_due_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_schedules');
    }
};
