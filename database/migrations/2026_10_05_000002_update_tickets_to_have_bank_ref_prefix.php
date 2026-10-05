<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Ticket;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update existing tickets where ticket_no is purely a numeric or bank-provided ref without bank prefix
        $tickets = Ticket::all();
        foreach ($tickets as $ticket) {
            if (!empty($ticket->ticket_no) && !str_starts_with($ticket->ticket_no, 'CMP-')) {
                $bankCode = Ticket::getBankCode($ticket->bank_name);
                if (!str_starts_with($ticket->ticket_no, $bankCode . '-') && !str_starts_with($ticket->ticket_no, $bankCode . '/')) {
                    $newTicketNo = Ticket::formatTicketNo($ticket->bank_name, $ticket->ticket_no);
                    $ticket->customer_ref_no = $ticket->customer_ref_no ?: $ticket->ticket_no;
                    $ticket->ticket_no = $newTicketNo;
                    $ticket->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
