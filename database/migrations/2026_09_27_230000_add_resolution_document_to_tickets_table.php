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
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('resolution_summary')->nullable()->after('issue_description');
            $table->string('supporting_document')->nullable()->after('resolution_summary');
            $table->string('resolution_document_name')->nullable()->after('supporting_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['resolution_summary', 'supporting_document', 'resolution_document_name']);
        });
    }
};
