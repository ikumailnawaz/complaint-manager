<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketLog;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchBankEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:fetch-emails {--limit=50 : Max number of emails to process} {--all : Fetch without unseen filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connects to IMAP inbox, extracts complaints with Gemini AI, and auto-creates unassigned tickets';

    /**
     * Execute the console command.
     */
    public function handle(GeminiService $gemini): int
    {
        $host = config('mail.imap.host') ?: env('IMAP_HOST', 'mail.cmscompany.biz');
        $port = (int) (config('mail.imap.port') ?: env('IMAP_PORT', 993));
        $encryption = strtolower(config('mail.imap.encryption') ?: env('IMAP_ENCRYPTION', 'ssl'));
        $username = config('mail.imap.username') ?: env('IMAP_USERNAME', 'support@cmscompany.biz');
        $password = config('mail.imap.password') ?: env('IMAP_PASSWORD', '');

        if (empty($username) || empty($password)) {
            $this->error('IMAP credentials not configured. Please set in config/mail.php or .env.');
            return Command::FAILURE;
        }

        $mailbox = "{" . "{$host}:{$port}/imap/{$encryption}/novalidate-cert}INBOX";

        $this->info("Connecting to GoDaddy IMAP inbox ({$username} on {$host}:{$port})...");

        // Open IMAP stream
        $inbox = @imap_open($mailbox, $username, $password);

        if (!$inbox) {
            $err = imap_last_error();
            $this->error("Failed to connect to GoDaddy IMAP server: {$err}");
            Log::error("GoDaddy IMAP connection failure: {$err}");
            return Command::FAILURE;
        }

        $this->info("Successfully connected to mailbox!");

        // Search for emails
        $searchCriteria = $this->option('all') ? 'ALL' : 'UNSEEN';
        $emails = imap_search($inbox, $searchCriteria);

        if (!$emails) {
            $this->info("No {$searchCriteria} emails found in INBOX. All caught up!");
            imap_close($inbox);
            return Command::SUCCESS;
        }

        rsort($emails);
        $limit = (int) $this->option('limit');
        $count = count($emails);
        $this->info("Found {$count} {$searchCriteria} email(s). Processing top {$limit}...");

        $processed = 0;
        foreach (array_slice($emails, 0, $limit) as $mailId) {
            $header = imap_headerinfo($inbox, $mailId);
            $subject = isset($header->subject) ? mb_decode_mimeheader($header->subject) : '(No Subject)';
            $subject = $this->toUtf8($subject);
            $from = isset($header->from[0]) ? ($header->from[0]->mailbox . '@' . $header->from[0]->host) : 'unknown@bank.com';

            $this->line("--------------------------------------------------");
            $this->info("Reading Email #{$mailId}: '{$subject}' from [{$from}]");

            // Fetch body
            $body = $this->getMailBody($inbox, $mailId);

            // Pass to Gemini AI Service
            if ($gemini->hasApiKey()) {
                $this->info("Invoking Google Gemini 1.5 Flash AI in real-time...");
            } else {
                $this->line("Invoking NLP Heuristics Engine (Offline Fallback - Note: add GEMINI_API_KEY in .env for real-time cloud LLM)...");
            }
            $aiData = $gemini->classifyAndExtractEmail($subject, $body, $from);

            // Strict Complaint Check: Only create ticket if type === 'complaint' AND is_complaint === true
            $isComplaint = (isset($aiData['type']) && strtolower($aiData['type']) === 'complaint') 
                           && ($aiData['is_complaint'] ?? true) === true;

            if (!$isComplaint) {
                $type = $aiData['type'] ?? 'non-complaint';
                $this->warn("Classified as: {$type} (Not a hardware complaint. Skipping ticket creation).");
                // Mark as seen
                imap_setflag_full($inbox, (string)$mailId, "\\Seen");
                continue;
            }

            // Ticket Number formatting
            $refNo = $aiData['customer_ref_no'] ?? null;
            $bankName = $aiData['bank_name'] ?? 'Bank Branch';
            if (!empty($refNo)) {
                $rawTicketNo = strtoupper(trim($refNo));
                $existingTicket = Ticket::ticketExistsForBank($bankName, $rawTicketNo);
                if ($existingTicket) {
                    $this->info("Duplicate complaint detected for {$bankName} Ref #{$rawTicketNo}. Skipping ticket creation.");
                    continue;
                }
                $ticketNo = Ticket::formatTicketNo($bankName, $rawTicketNo);
                $ticketSource = 'from_email';
            } else {
                $year = date('Y');
                $nextId = (Ticket::max('id') ?? 0) + 1;
                do {
                    $ticketNo = 'CMP-' . $year . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
                    $nextId++;
                } while (Ticket::where('ticket_no', $ticketNo)->exists());
                $ticketSource = 'auto_generated';
            }

            $urgency = strtolower($aiData['urgency'] ?? 'medium');
            if (!in_array($urgency, ['high', 'medium', 'low'])) {
                $urgency = 'medium';
            }

            // Calculate SLA / TAT (Supports 1 Day, 2 Days, 3 Days, 4 Days, or Hours)
            $slaTat = $aiData['sla_tat'] ?? null;
            if ($slaTat && preg_match('/(\d+)\s*day/i', $slaTat, $m)) {
                $slaDeadline = Carbon::now()->addDays((int)$m[1]);
            } elseif ($slaTat && preg_match('/(\d+)\s*hour/i', $slaTat, $m)) {
                $slaDeadline = Carbon::now()->addHours((int)$m[1]);
            } else {
                // Default SLA: High = 1 Day, Medium = 2 Days, Low = 3 Days
                $slaDays = match ($urgency) {
                    'high' => 1,
                    'medium' => 2,
                    'low' => 3,
                    default => 2,
                };
                $slaDeadline = Carbon::now()->addDays($slaDays);
            }

            $incomingMessageId = $header->message_id ?? null;

            // Create Ticket in Laravel (Unassigned by design!)
            $ticket = Ticket::create([
                'ticket_no' => $ticketNo,
                'ticket_no_source' => $ticketSource,
                'customer_ref_no' => $refNo,
                'bank_name' => $aiData['bank_name'] ?? 'Bank Branch',
                'branch_name' => $aiData['branch_name'] ?? null,
                'branch_location' => $aiData['branch_location'] ?? 'Lahore',
                'branch_address' => $aiData['branch_address'] ?? null,
                'customer_name' => $aiData['customer_name'] ?? null,
                'customer_mobile' => $aiData['customer_mobile'] ?? null,
                'customer_email' => $from,
                'incoming_message_id' => $incomingMessageId,
                'email_subject' => $subject,
                'machine_type' => $aiData['machine_type'] ?? null,
                'machine_model' => $aiData['machine_model'] ?? null,
                'machine_serial_no' => $aiData['machine_serial_no'] ?? null,
                'warranty_status' => in_array($aiData['warranty_hint'] ?? '', ['in_warranty', 'out_of_warranty']) ? $aiData['warranty_hint'] : 'unknown',
                'urgency' => $urgency,
                'status' => 'open', // OPEN = Unassigned
                'issue_summary' => $aiData['issue_summary'] ?? substr($subject, 0, 150),
                'issue_description' => $body,
                'assigned_engineer_id' => null,
                'sla_deadline' => $slaDeadline,
                'ai_classified' => true,
                'is_human_verified' => false,
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'action' => 'created',
                'notes' => "Ticket automatically ingested via GoDaddy IMAP & Gemini AI. Extracted Bank: {$ticket->bank_name}, Location: {$ticket->branch_location}, Machine: {$ticket->machine_type}. Awaiting manual engineer assignment.",
            ]);

            $this->info("Ticket created successfully: {$ticket->ticket_no} (Status: OPEN - Unassigned)");

            // Mark email as read in GoDaddy webmail
            imap_setflag_full($inbox, (string)$mailId, "\\Seen");
            $processed++;
        }

        imap_close($inbox);
        $this->info("Completed! Processed {$processed} complaint ticket(s).");
        return Command::SUCCESS;
    }

    private function getMailBody($inbox, $mailId): string
    {
        $structure = imap_fetchstructure($inbox, $mailId);
        $body = $this->extractBodyPart($inbox, $mailId, $structure, '');

        if (empty($body)) {
            $body = imap_body($inbox, $mailId);
        }

        // Clean up quoted-printable leftovers if present
        if (strpos($body, '=0A') !== false || strpos($body, '=20') !== false || strpos($body, '=E2=80=93') !== false || strpos($body, "=\r\n") !== false || strpos($body, "=\n") !== false) {
            $body = quoted_printable_decode($body);
        }

        // Strip MIME multipart boundary lines and headers
        $body = preg_replace('/--[a-f0-9]{12,}[^\r\n]*/i', '', $body);
        $body = preg_replace('/Content-Type:[^\r\n]*/i', '', $body);
        $body = preg_replace('/Content-Transfer-Encoding:[^\r\n]*/i', '', $body);

        return $this->toUtf8(trim(strip_tags($body)));
    }

    private function extractBodyPart($inbox, $mailId, $structure, string $partNumber): string
    {
        if (empty($structure)) {
            return '';
        }

        if (isset($structure->parts) && count($structure->parts)) {
            // First look for direct PLAIN part
            foreach ($structure->parts as $index => $subPart) {
                $prefix = $partNumber ? ($partNumber . '.' . ($index + 1)) : (string)($index + 1);
                if (isset($subPart->subtype) && strtoupper($subPart->subtype) === 'PLAIN') {
                    $data = imap_fetchbody($inbox, $mailId, $prefix);
                    return $this->decodeBodyData($data, $subPart->encoding ?? 0);
                }
            }
            // Recurse into nested parts (e.g. multipart/alternative inside multipart/mixed)
            foreach ($structure->parts as $index => $subPart) {
                $prefix = $partNumber ? ($partNumber . '.' . ($index + 1)) : (string)($index + 1);
                $res = $this->extractBodyPart($inbox, $mailId, $subPart, $prefix);
                if (!empty($res)) {
                    return $res;
                }
            }
        }

        // Single part
        $section = $partNumber ?: '1';
        $data = imap_fetchbody($inbox, $mailId, $section);
        return $this->decodeBodyData($data, $structure->encoding ?? 0);
    }

    private function decodeBodyData(string $data, int $encoding): string
    {
        if ($encoding === 3) {
            return base64_decode($data);
        } elseif ($encoding === 4) {
            return quoted_printable_decode($data);
        }
        return $data;
    }

    private function toUtf8(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }

        if (!mb_check_encoding($str, 'UTF-8')) {
            $converted = @mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $str = $converted;
            } else {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
                $str = $converted !== false ? $converted : @mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            }
        }

        if (function_exists('mb_scrub')) {
            $str = mb_scrub($str, 'UTF-8');
        }

        return $str;
    }
}
