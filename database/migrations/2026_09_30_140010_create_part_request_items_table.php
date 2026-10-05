<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('part_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained();
            $table->integer('qty_requested');
            $table->integer('qty_approved')->nullable();
            $table->integer('qty_dispatched')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->text('note')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('part_request_items'); }
};
