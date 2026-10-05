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
        Schema::table('inbox_emails', function (Blueprint $table) {
            $table->text('cc_emails')->nullable()->after('to_email');
            $table->boolean('is_sent')->default(false)->after('is_read')->index();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->text('customer_cc')->nullable()->after('customer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbox_emails', function (Blueprint $table) {
            $table->dropColumn(['cc_emails', 'is_sent']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('customer_cc');
        });
    }
};
