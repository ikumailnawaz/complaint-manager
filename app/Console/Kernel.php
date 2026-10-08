<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Monitor active complaints for SLA breaches and auto-escalate
        $schedule->command('tickets:check-sla')->everyFifteenMinutes()->withoutOverlapping();

        // Detect missed Preventive Maintenance slots and advance schedule
        $schedule->command('pm:mark-missed')->dailyAt('00:05')->withoutOverlapping();

        // Background sync incoming complaint emails from GoDaddy IMAP
        $schedule->command('tickets:fetch-emails --limit=30')->everyTenMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
