<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('part_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->unsignedBigInteger('from_location_id');
            $table->unsignedBigInteger('to_location_id');
            $table->string('status')->default('pending'); // pending, in_transit, received, cancelled
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->unsignedBigInteger('dispatched_by_id')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->unsignedBigInteger('received_by_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('part_transfers'); }
};
