<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->string('type'); // grn_in, dispatch_out, transfer_in, transfer_out, adjustment
            $table->string('reference_type')->nullable(); // grn, part_request, part_transfer
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('qty');
            $table->integer('qty_after');
            $table->text('note')->nullable();
            $table->foreignId('created_by_id')->nullable()->references('id')->on('users');
            $table->timestamp('created_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('stock_movements'); }
};
