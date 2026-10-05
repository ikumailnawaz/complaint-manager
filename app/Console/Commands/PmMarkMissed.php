<?php

namespace App\Console\Commands;

use App\Models\PmRecord;
use App\Models\PmSchedule;
use Illuminate\Console\Command;

class PmMarkMissed extends Command
{
    protected $signature   = 'pm:mark-missed';
    protected $description = 'Detect missed PM maintenance slots, log them, and advance the next_due_date.';

    public function handle(): int
    {
        $today = now()->startOfDay();

        // Active schedules whose next_due_date has passed and have no record for that due date
        $overdue = PmSchedule::with('machine')
            ->where('is_active', true)
            ->where('next_due_date', '<', $today->toDateString())
            ->get();

        $marked = 0;

        foreach ($overdue as $schedule) {
            // Check if a record already exists for this due date (avoids double-marking)
            $alreadyLogged = PmRecord::where('pm_schedule_id', $schedule->id)
                ->where('due_date', $schedule->next_due_date->toDateString())
                ->exists();

            if ($alreadyLogged) {
                continue;
            }

            // Create missed record
            PmRecord::create([
                'pm_schedule_id'  => $schedule->id,
                'pm_machine_id'   => $schedule->pm_machine_id,
                'performed_by_id' => null,
                'performed_at'    => null,
                'due_date'        => $schedule->next_due_date->toDateString(),
                'status'          => 'missed',
                'is_overdue'      => true,
            ]);

            // Advance next_due_date by one frequency cycle from the missed date
            $schedule->update([
                'next_due_date' => $schedule->next_due_date->addDays($schedule->frequency_days)->toDateString(),
            ]);

            $marked++;
            $this->line("  ✗ MISSED  [{$schedule->machine->serial_number}] {$schedule->title}");
        }

        $this->info("pm:mark-missed complete — {$marked} missed slot(s) logged.");
        return self::SUCCESS;
    }
}
