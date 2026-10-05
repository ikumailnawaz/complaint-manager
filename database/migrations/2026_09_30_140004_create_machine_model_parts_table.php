<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('machine_model_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_common')->default(false);
            $table->unique(['machine_model_id', 'part_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('machine_model_parts'); }
};
