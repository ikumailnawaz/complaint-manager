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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique(); // e.g. CMP-2026-00001 or bank ref
            $table->string('ticket_no_source')->default('auto_generated'); // auto_generated, from_email
            $table->string('customer_ref_no')->nullable(); // bank/customer given reference
            $table->string('bank_name'); // e.g. MCB, HBL, UBL, Meezan
            $table->string('branch_name')->nullable(); // e.g. Gulberg Branch
            $table->string('branch_location'); // City e.g. Lahore, Karachi, Islamabad
            $table->text('branch_address')->nullable();
            
            // Customer contact
            $table->string('customer_name')->nullable();
            $table->string('customer_mobile')->nullable();
            $table->string('customer_email')->nullable();
            
            // Machine / Hardware details
            $table->string('machine_type')->nullable(); // ATM, CDM, POS, Kiosk, Server
            $table->string('machine_model')->nullable();
            $table->string('machine_serial_no')->nullable();
            $table->string('warranty_status')->default('unknown'); // in_warranty, out_of_warranty, unknown
            $table->date('warranty_expiry')->nullable();
            
            // Issue details
            $table->string('urgency')->default('medium'); // high, medium, low
            $table->string('status')->default('open'); // open, assigned, in_progress, pending_feedback, awaiting_workshop, escalated, resolved, closed
            $table->text('issue_summary')->nullable();
            $table->text('issue_description')->nullable();
            
            // Manual Alignment fields
            $table->foreignId('assigned_engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            
            // WhatsApp Alignment Notification
            $table->boolean('whatsapp_notified')->default(false);
            $table->timestamp('whatsapp_notified_at')->nullable();
            $table->foreignId('whatsapp_notified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('whatsapp_message_id')->nullable();
            
            // Bank Assignment Email
            $table->boolean('email_assignment_sent')->default(false);
            $table->timestamp('email_assignment_sent_at')->nullable();
            $table->foreignId('email_assignment_sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email_reply_message_id')->nullable();
            
            // SLA & Escalation
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('workshop_location')->nullable(); // Lahore, Karachi, Islamabad workshop
            $table->boolean('ai_classified')->default(false);
            
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
