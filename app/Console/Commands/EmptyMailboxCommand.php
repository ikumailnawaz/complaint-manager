<?php

namespace App\Console\Commands;

use App\Models\InboxEmail;
use Illuminate\Console\Command;

class EmptyMailboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailbox:empty {--server : Also purge emails on the remote IMAP mailbox}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears all stored emails from the local inbox_emails database table and unlinks them from tickets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = InboxEmail::count();

        if ($count === 0) {
            $this->info('Local mailbox is already empty.');
        } else {
            // Unlink from tickets
            InboxEmail::query()->update(['ticket_id' => null]);
            InboxEmail::truncate();
            $this->info("Successfully cleared {$count} stored emails from the database.");
        }

        if ($this->option('server')) {
            $this->purgeRemoteImap();
        }

        return Command::SUCCESS;
    }

    /**
     * Optional remote IMAP purge
     */
    protected function purgeRemoteImap(): void
    {
        $host = config('mail.imap.host') ?: env('IMAP_HOST', config('mail.mailers.smtp.host', 'mail.cmscompany.biz'));
        $port = (int) (config('mail.imap.port') ?: env('IMAP_PORT', 993));
        $encryption = strtolower(config('mail.imap.encryption') ?: env('IMAP_ENCRYPTION', 'ssl'));
        $username = config('mail.imap.username') ?: env('IMAP_USERNAME', config('mail.mailers.smtp.username', 'support@cmscompany.biz'));
        $password = config('mail.imap.password') ?: env('IMAP_PASSWORD', config('mail.mailers.smtp.password', ''));

        if (empty($username) || empty($password)) {
            $this->warn('Remote IMAP purge skipped: IMAP credentials not set in .env.');
            return;
        }

        if (!extension_loaded('imap')) {
            $this->warn('Remote IMAP purge skipped: PHP imap extension is not loaded.');
            return;
        }

        $mailbox = "{" . "{$host}:{$port}/imap/{$encryption}/novalidate-cert}INBOX";
        $inbox = @imap_open($mailbox, $username, $password);

        if (!$inbox) {
            $this->error('Failed to connect to remote IMAP server: ' . imap_last_error());
            return;
        }

        $total = imap_num_msg($inbox);
        if ($total === 0) {
            $this->info('Remote IMAP INBOX is already empty.');
            imap_close($inbox);
            return;
        }

        $this->warn("Marking {$total} messages for deletion on remote IMAP server...");
        for ($i = 1; $i <= $total; $i++) {
            @imap_delete($inbox, $i);
        }

        @imap_expunge($inbox);
        imap_close($inbox);
        $this->info("Remote IMAP INBOX expunged successfully ({$total} messages deleted).");
    }
}
