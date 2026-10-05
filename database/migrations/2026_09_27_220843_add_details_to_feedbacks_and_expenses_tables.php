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
        Schema::table('ticket_feedbacks', function (Blueprint $table) {
            $table->string('parts_required')->nullable()->after('action_taken');
            $table->timestamp('eta_completion')->nullable()->after('parts_required');
            $table->string('photo_evidence')->nullable()->after('eta_completion');
            $table->foreignId('submitted_by_id')->nullable()->after('engineer_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('expense_claims', function (Blueprint $table) {
            $table->string('category')->default('travel')->after('trip_type'); // travel, fuel, accommodation, parts, food, misc
            $table->text('description')->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_feedbacks', function (Blueprint $table) {
            $table->dropForeign(['submitted_by_id']);
            $table->dropColumn(['parts_required', 'eta_completion', 'photo_evidence', 'submitted_by_id']);
        });

        Schema::table('expense_claims', function (Blueprint $table) {
            $table->dropColumn(['category', 'description']);
        });
    }
};
