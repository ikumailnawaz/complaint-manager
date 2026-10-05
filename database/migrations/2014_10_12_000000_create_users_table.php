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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('engineer'); // super_admin, admin, superior, engineer
            $table->string('phone_whatsapp')->nullable();
            $table->string('base_city')->nullable(); // Lahore, Karachi, Islamabad, etc.
            $table->string('current_city')->nullable();
            $table->string('specialization')->nullable(); // ATM, CDM, POS, Hardware, etc.
            $table->boolean('is_available')->default(true);
            $table->boolean('is_on_leave')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
