<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->integer('qty_received');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->string('condition')->default('new'); // new, refurbished, used
            $table->string('batch_number')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('grn_items'); }
};
