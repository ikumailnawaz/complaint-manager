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
            $table->string('approval_document')->nullable()->after('approval_request_reason');
            $table->string('approval_document_name')->nullable()->after('approval_document');
            $table->string('workshop_dispatch_document')->nullable()->after('workshop_dispatch_notes');
            $table->string('workshop_dispatch_document_name')->nullable()->after('workshop_dispatch_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'approval_document',
                'approval_document_name',
                'workshop_dispatch_document',
                'workshop_dispatch_document_name',
            ]);
        });
    }
};
