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
        if (!Schema::hasColumn('tickets', 'sla_tat')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('sla_tat', 50)->nullable()->after('urgency');
            });
        }

        // Clean up any historical tickets whose SLA deadline fell on Saturday or Sunday
        try {
            $tickets = Ticket::whereNotNull('sla_deadline')->get();
            foreach ($tickets as $ticket) {
                if ($ticket->sla_deadline && $ticket->sla_deadline->isWeekend()) {
                    $start = $ticket->created_at ?? now();
                    $ticket->sla_deadline = Ticket::calculateBusinessDeadline($start, $ticket->sla_tat, $ticket->urgency);
                    $ticket->saveQuietly();
                }
            }
        } catch (\Throwable $e) {
            // Ignore during silent installation
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'sla_tat')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('sla_tat');
            });
        }
    }
};
