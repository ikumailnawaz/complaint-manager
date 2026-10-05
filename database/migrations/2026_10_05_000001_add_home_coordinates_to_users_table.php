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
        Schema::table('users', function (Blueprint $table) {
            $table->string('home_coordinates')->nullable()->after('current_city'); // e.g. "31.5204, 74.3587"
            $table->text('home_address')->nullable()->after('home_coordinates'); // e.g. "House 12, Street 4, Johar Town, Lahore"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['home_coordinates', 'home_address']);
        });
    }
};
