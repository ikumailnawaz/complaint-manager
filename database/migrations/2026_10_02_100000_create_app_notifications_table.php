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
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('role_target')->nullable(); // e.g. 'office_staff', 'admin', 'engineer', or null for individual user
            $table->string('type'); // 'ticket_assigned', 'part_request_created', 'part_request_verified', 'part_request_approved', 'part_request_dispatched', 'pm_due', 'ticket_escalated'
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->string('icon')->default('fa-solid fa-bell');
            $table->string('color')->default('sky');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index(['role_target', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
