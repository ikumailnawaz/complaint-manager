<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_model_id')->constrained('machine_models')->cascadeOnDelete();
            $table->string('serial_number')->unique();
            $table->string('asset_tag')->nullable();
            $table->string('location');                // Branch / site description
            $table->string('bank_name')->nullable();   // Owning bank
            $table->date('installed_at')->nullable();
            $table->foreignId('assigned_engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_machines');
    }
};
