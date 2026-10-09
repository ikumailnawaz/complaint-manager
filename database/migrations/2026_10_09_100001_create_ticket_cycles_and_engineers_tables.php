<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cycles: every "tour" on a ticket. Cycle 1 = original complaint, 2+ = reopens.
        Schema::create('ticket_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle_no');
            $table->string('status')->default('open'); // open | resolved | closed
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('opened_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_summary')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'cycle_no']);
        });

        // 2. Engineers aligned to a ticket (lead + support).
        Schema::create('ticket_engineers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engineer_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('support'); // lead | support
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('cycle_id')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'released_at']);
            $table->index(['engineer_id', 'released_at']);
        });

        // 3. Documents per cycle (resolution proofs).
        Schema::create('ticket_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cycle_id')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('resolution');
            $table->string('path');
            $table->string('name')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        // 4. Tag existing child records with a cycle.
        Schema::table('ticket_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id')->nullable();
        });
        Schema::table('expense_claims', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id')->nullable();
            $table->unsignedInteger('tour_no')->default(1);
        });
        Schema::table('ticket_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id')->nullable();
        });
        Schema::table('part_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id')->nullable();
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('current_cycle_no')->default(1);
            $table->unsignedInteger('reopen_count')->default(0);
        });

        // 5. Backfill: Cycle 1 for every existing ticket.
        $now = now();
        DB::table('tickets')->orderBy('id')->chunkById(200, function ($tickets) use ($now) {
            foreach ($tickets as $t) {
                $closed = $t->closed_at ?: null;
                $resolved = $t->resolved_at ?: null;
                $status = $closed || $t->status === 'closed' ? 'closed' : ($t->status === 'resolved' ? 'resolved' : 'open');

                $cycleId = DB::table('ticket_cycles')->insertGetId([
                    'ticket_id' => $t->id,
                    'cycle_no' => 1,
                    'status' => $status,
                    'opened_at' => $t->created_at ?: $now,
                    'resolved_at' => $resolved,
                    'resolution_summary' => $t->resolution_summary,
                    'closed_at' => $closed,
                    'sla_deadline' => $t->sla_deadline,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('ticket_feedbacks')->where('ticket_id', $t->id)->update(['cycle_id' => $cycleId]);
                DB::table('expense_claims')->where('ticket_id', $t->id)->update(['cycle_id' => $cycleId, 'tour_no' => 1]);
                DB::table('ticket_logs')->where('ticket_id', $t->id)->update(['cycle_id' => $cycleId]);
                DB::table('part_requests')->where('ticket_id', $t->id)->update(['cycle_id' => $cycleId]);

                if ($t->assigned_engineer_id) {
                    DB::table('ticket_engineers')->insert([
                        'ticket_id' => $t->id,
                        'engineer_id' => $t->assigned_engineer_id,
                        'role' => 'lead',
                        'assigned_by_id' => $t->assigned_by_id,
                        'assigned_at' => $t->assigned_at ?: $now,
                        'cycle_id' => $cycleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (!empty($t->supporting_document)) {
                    DB::table('ticket_documents')->insert([
                        'ticket_id' => $t->id,
                        'cycle_id' => $cycleId,
                        'type' => 'resolution',
                        'path' => $t->supporting_document,
                        'name' => $t->resolution_document_name,
                        'uploaded_at' => $resolved ?: $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['current_cycle_no', 'reopen_count']);
        });
        Schema::table('part_requests', fn (Blueprint $t) => $t->dropColumn('cycle_id'));
        Schema::table('ticket_logs', fn (Blueprint $t) => $t->dropColumn('cycle_id'));
        Schema::table('expense_claims', fn (Blueprint $t) => $t->dropColumn(['cycle_id', 'tour_no']));
        Schema::table('ticket_feedbacks', fn (Blueprint $t) => $t->dropColumn('cycle_id'));
        Schema::dropIfExists('ticket_documents');
        Schema::dropIfExists('ticket_engineers');
        Schema::dropIfExists('ticket_cycles');
    }
};
