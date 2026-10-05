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
        Schema::create('inbox_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255)->nullable()->index();
            $table->string('uid', 50)->nullable()->index();
            $table->string('from_email', 255);
            $table->string('from_name', 255)->nullable();
            $table->string('to_email', 255)->nullable();
            $table->string('subject', 500)->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('email_date')->nullable()->index();
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbox_emails');
    }
};
