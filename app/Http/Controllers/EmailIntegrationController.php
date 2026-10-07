<?php

namespace App\Http\Controllers;

use App\Models\InboxEmail;
use App\Models\Ticket;
use App\Models\TicketLog;
use App\Services\GeminiService;
use App\Services\ImapMailboxService;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Console\Output\BufferedOutput;

class EmailIntegrationController extends Controller
{
    public function index(Request $request, ImapMailboxService $imapService)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Email mailbox and triage command center is reserved for Operations Administrators.');
        }

        $folder = $request->input('folder', 'inbox');
        $search = $request->input('search');

        $query = InboxEmail::with(['ticket.assignedEngineer'])->latest('email_date');

        // Folder filtering
        if ($folder === 'sent') {
            $query->where('is_sent', true);
        } elseif ($folder === 'complaints') {
            $query->has('ticket');
        } elseif ($folder === 'unassigned') {
            $query->doesntHave('ticket')->where('is_sent', false);
        } elseif ($folder === 'unread') {
            $query->where('is_read', false)->where('is_sent', false);
        } elseif ($folder === 'all') {
            // all emails
        } else {
            // default 'inbox': inbound only
            $folder = 'inbox';
            $query->where('is_sent', false);
        }

        // Search query
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('from_email', 'like', "%{$search}%")
                  ->orWhere('to_email', 'like', "%{$search}%")
                  ->orWhere('from_name', 'like', "%{$search}%")
                  ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        $emails = $query->paginate(40)->withQueryString();

        // Selected email for right pane
        $selectedEmail = null;
        if ($request->filled('selected')) {
            $selectedEmail = InboxEmail::with(['ticket.assignedEngineer'])->find($request->selected);
        }
        if (!$selectedEmail && $emails->isNotEmpty()) {
            $selectedEmail = $emails->first();
        }

        // Mark selected email as read
        if ($selectedEmail && !$selectedEmail->is_read) {
            $selectedEmail->update(['is_read' => true]);
        }

        $counts = [
            'inbox' => InboxEmail::where('is_sent', false)->count(),
            'sent' => InboxEmail::where('is_sent', true)->count(),
            'complaints' => InboxEmail::has('ticket')->count(),
            'unassigned' => InboxEmail::doesntHave('ticket')->where('is_sent', false)->count(),
            'unread' => InboxEmail::where('is_read', false)->where('is_sent', false)->count(),
            'all' => InboxEmail::count(),
        ];

        $currentConfig = [
            'host' => env('IMAP_HOST', 'mail.cmscompany.biz'),
            'port' => env('IMAP_PORT', 993),
            'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
            'username' => env('IMAP_USERNAME', 'support@cmscompany.biz'),
            'has_password' => !empty(env('IMAP_PASSWORD')),
            'gemini_key' => env('GEMINI_API_KEY', ''),
            'smtp_host' => env('MAIL_HOST', 'mail.cmscompany.biz'),
            'smtp_port' => env('MAIL_PORT', 465),
        ];

        return view('settings.email-setup', compact('emails', 'selectedEmail', 'counts', 'folder', 'search', 'currentConfig'));
    }

    public function syncNow(Request $request, ImapMailboxService $imapService)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Email mailbox and triage command center is reserved for Operations Administrators.');
        }

        $result = $imapService->syncMailbox(40);

        if ($result['success'] ?? false) {
            return back()->with('success', "Mailbox synced successfully! Fetched {$result['new_count']} new emails from GoDaddy (Total in mailbox: {$result['total']}).");
        }

        return back()->with('error', "Mailbox sync failed: " . ($result['error'] ?? 'Unknown error connecting to GoDaddy IMAP.'));
    }

    /**
     * Empty current mailbox database records (inbox_emails) and un-link tickets.
     */
    public function emptyMailbox(Request $request)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Mailbox maintenance is reserved for Operations Administrators.');
        }

        $count = InboxEmail::count();

        // Unlink foreign keys before deleting
        InboxEmail::query()->update(['ticket_id' => null]);
        InboxEmail::truncate();

        return back()->with('success', "Mailbox has been emptied successfully! Cleared {$count} stored email records from the system.");
    }

    /**
     * Mark an email as Complaint, invoke Gemini AI to extract fields, and create an unassigned ticket.
     * Strictly blocks duplicate marking if the email already has a ticket.
     */
    public function convertToComplaint(Request $request, InboxEmail $email, GeminiService $gemini)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Complaint conversion is reserved for Operations Administrators.');
        }

        // 1. STRICT DUPLICATE PREVENTION:
        if ($email->ticket_id || $email->ticket()->exists()) {
            $existingTicket = $email->ticket;
            return redirect()->route('tickets.show', $existingTicket->id)
                ->with('warning', "Complaint already exists for this email! Linked to Ticket #{$existingTicket->ticket_no}. Duplicate registration was blocked.");
        }

        // Also check if any ticket already matches this incoming_message_id
        if (!empty($email->message_id)) {
            $existingTicket = Ticket::where('incoming_message_id', 'like', "%{$email->message_id}%")->first();
            if ($existingTicket) {
                $email->update(['ticket_id' => $existingTicket->id]);
                return redirect()->route('tickets.show', $existingTicket->id)
                    ->with('warning', "Complaint already exists for this email! Linked to Ticket #{$existingTicket->ticket_no}. Duplicate registration was blocked.");
            }
        }

        // 2. Invoke Gemini AI to extract structured complaint data
        $bodyContent = !empty($email->body_text) ? $email->body_text : strip_tags($email->body_html ?? '');
        $aiData = $gemini->classifyAndExtractEmail(
            $email->subject ?? '',
            $bodyContent,
            $email->from_email ?? ''
        );

        // 3. Determine Ticket Number (from email reference or auto-generated)
        $refNo = $aiData['customer_ref_no'] ?? null;
        $bankName = $aiData['bank_name'] ?? 'Bank Branch';
        if (!empty($refNo)) {
            $rawTicketNo = strtoupper(trim($refNo));
            $ticketSource = 'from_email';

            // Check if ticket already exists with this ticket number FOR THIS BANK
            $existingTicket = Ticket::ticketExistsForBank($bankName, $rawTicketNo);
            if ($existingTicket) {
                $email->update(['ticket_id' => $existingTicket->id]);
                return redirect()->route('tickets.show', $existingTicket->id)
                    ->with('warning', "Complaint already exists for {$bankName} with Ticket #{$rawTicketNo} (Linked to #{$existingTicket->ticket_no})! Duplicate registration was blocked.");
            }

            $ticketNo = Ticket::formatTicketNo($bankName, $rawTicketNo);
        } else {
            $year = date('Y');
            $nextId = (Ticket::max('id') ?? 0) + 1;
            do {
                $ticketNo = 'CMP-' . $year . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
                $nextId++;
            } while (Ticket::where('ticket_no', $ticketNo)->exists());
            $ticketSource = 'auto_generated';
        }

        // Urgency & SLA Deadline
        $urgency = strtolower($aiData['urgency'] ?? 'high');
        if (!in_array($urgency, ['high', 'medium', 'low'])) {
            $urgency = 'high';
        }

        $slaTat = $aiData['sla_tat'] ?? null;
        if ($slaTat && preg_match('/(\d+)\s*day/i', $slaTat, $m)) {
            $slaDeadline = Carbon::now()->addDays((int)$m[1]);
        } elseif ($slaTat && preg_match('/(\d+)\s*hour/i', $slaTat, $m)) {
            $slaDeadline = Carbon::now()->addHours((int)$m[1]);
        } else {
            $slaHours = match ($urgency) {
                'high' => 4,
                'medium' => 8,
                'low' => 24,
                default => 8,
            };
            $slaDeadline = Carbon::now()->addHours($slaHours);
        }

        // 4. Create Ticket (Status: 'open' / Unassigned matching system standard)
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
            'customer_email' => $email->from_email,
            'customer_cc' => $email->cc_emails,
            'machine_type' => $aiData['machine_type'] ?? null,
            'machine_model' => $aiData['machine_model'] ?? null,
            'machine_serial_no' => $aiData['machine_serial_no'] ?? null,
            'warranty_status' => in_array($aiData['warranty_hint'] ?? '', ['in_warranty', 'out_of_warranty']) ? $aiData['warranty_hint'] : 'unknown',
            'urgency' => $urgency,
            'status' => 'open', // OPEN = Unassigned
            'issue_summary' => $aiData['issue_summary'] ?? substr($email->subject, 0, 150),
            'issue_description' => $bodyContent,
            'assigned_engineer_id' => null, // Admin will pick engineer
            'sla_deadline' => $slaDeadline,
            'ai_classified' => true,
            'incoming_message_id' => $email->message_id,
            'email_subject' => $email->subject,
        ]);

        // Link email to ticket
        $email->update(['ticket_id' => $ticket->id]);

        // Log the action in ticket audit log
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'created',
            'notes' => "Complaint ticket registered from GoDaddy Webmail via Gemini AI extraction by Operator " . Auth::user()->name . ". Status: OPEN (Unassigned). Machine: " . ($ticket->machine_type ?? 'N/A') . " (S/N: " . ($ticket->machine_serial_no ?? 'N/A') . ")" . ($ticket->customer_cc ? " [CC: {$ticket->customer_cc}]" : '') . ".",
        ]);

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', "Complaint Ticket #{$ticket->ticket_no} created successfully from email! Gemini AI extracted machine and branch details. Now assign a field engineer.");
    }

    /**
     * Batch Auto-Triage: Automatically processes untriaged inbox emails using Gemini AI.
     * Real complaints become unassigned tickets; bounce/spam messages are skipped.
     */
    public function autoTriageAll(Request $request, GeminiService $gemini)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Batch AI Triage is reserved for Operations Administrators.');
        }

        $unassignedEmails = InboxEmail::whereNull('ticket_id')
            ->where('is_sent', false)
            ->latest('id')
            ->take(50)
            ->get();

        if ($unassignedEmails->isEmpty()) {
            return back()->with('info', 'No untriaged inbox emails found. All emails have already been processed.');
        }

        $createdTickets = 0;
        $skippedCount = 0;
        $alreadyLinked = 0;

        set_time_limit(300);

        foreach ($unassignedEmails as $email) {
            try {
                // Check if ticket already exists matching message_id
                if (!empty($email->message_id)) {
                    $existing = Ticket::where('incoming_message_id', 'like', "%{$email->message_id}%")->first();
                    if ($existing) {
                        $email->update(['ticket_id' => $existing->id]);
                        $alreadyLinked++;
                        continue;
                    }
                }

                $bodyContent = !empty($email->body_text) ? $email->body_text : strip_tags($email->body_html ?? '');
                $aiData = $gemini->classifyAndExtractEmail($email->subject ?? '', $bodyContent, $email->from_email ?? '');

                // Skip non-complaints (e.g. bounce notices, system delivery errors, spam, inquiries, greetings)
                if (
                    (!($aiData['is_complaint'] ?? false) && ($aiData['type'] ?? '') !== 'complaint') ||
                    in_array(strtolower($aiData['type'] ?? ''), ['spam', 'inquiry', 'conversation', 'other'])
                ) {
                    $skippedCount++;
                    continue;
                }

                // Determine ticket number & prevent duplicates
                $refNo = $aiData['customer_ref_no'] ?? null;
                $bankName = !empty($aiData['bank_name']) ? $aiData['bank_name'] : 'Bank Branch';
                if (!empty($refNo)) {
                    $rawTicketNo = strtoupper(trim($refNo));
                    $ticketSource = 'from_email';

                    // Check if a ticket with this bank reference number already exists for this bank!
                    $existing = Ticket::ticketExistsForBank($bankName, $rawTicketNo);
                    if ($existing) {
                        $email->update(['ticket_id' => $existing->id]);
                        $alreadyLinked++;
                        continue;
                    }

                    $ticketNo = Ticket::formatTicketNo($bankName, $rawTicketNo);
                } else {
                    $year = date('Y');
                    $nextId = (Ticket::max('id') ?? 0) + 1;
                    do {
                        $ticketNo = 'CMP-' . $year . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
                        $nextId++;
                    } while (Ticket::where('ticket_no', $ticketNo)->exists());
                    $ticketSource = 'auto_generated';
                }

                // Urgency & TAT
                $urgency = strtolower($aiData['urgency'] ?? 'high');
                if (!in_array($urgency, ['high', 'medium', 'low'])) {
                    $urgency = 'high';
                }

                $slaTat = $aiData['sla_tat'] ?? null;
                if ($slaTat && preg_match('/(\d+)\s*day/i', $slaTat, $m)) {
                    $slaDeadline = Carbon::now()->addDays((int)$m[1]);
                } elseif ($slaTat && preg_match('/(\d+)\s*hour/i', $slaTat, $m)) {
                    $slaDeadline = Carbon::now()->addHours((int)$m[1]);
                } else {
                    $slaHours = match ($urgency) {
                        'high' => 4,
                        'medium' => 8,
                        'low' => 24,
                        default => 8,
                    };
                    $slaDeadline = Carbon::now()->addHours($slaHours);
                }

                $ticket = Ticket::create([
                    'ticket_no' => $ticketNo,
                    'ticket_no_source' => $ticketSource,
                    'customer_ref_no' => $refNo,
                    'bank_name' => !empty($aiData['bank_name']) ? $aiData['bank_name'] : 'Bank Branch',
                    'branch_name' => $aiData['branch_name'] ?? null,
                    'branch_location' => !empty($aiData['branch_location']) ? $aiData['branch_location'] : 'Lahore',
                    'branch_address' => $aiData['branch_address'] ?? null,
                    'customer_name' => $aiData['customer_name'] ?? null,
                    'customer_mobile' => $aiData['customer_mobile'] ?? null,
                    'customer_email' => $email->from_email,
                    'customer_cc' => $email->cc_emails,
                    'machine_type' => $aiData['machine_type'] ?? null,
                    'machine_model' => $aiData['machine_model'] ?? null,
                    'machine_serial_no' => $aiData['machine_serial_no'] ?? null,
                    'warranty_status' => in_array($aiData['warranty_hint'] ?? '', ['in_warranty', 'out_of_warranty']) ? $aiData['warranty_hint'] : 'unknown',
                    'urgency' => $urgency,
                    'status' => 'open',
                    'issue_summary' => $aiData['issue_summary'] ?? substr($email->subject, 0, 150),
                    'issue_description' => $bodyContent,
                    'assigned_engineer_id' => null,
                    'sla_deadline' => $slaDeadline,
                    'ai_classified' => true,
                    'incoming_message_id' => $email->message_id,
                    'email_subject' => $email->subject,
                ]);

                $email->update(['ticket_id' => $ticket->id]);

                TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'notes' => "Auto-triaged by Gemini AI from Mailbox. Machine: " . ($ticket->machine_type ?? 'N/A') . " (S/N: " . ($ticket->machine_serial_no ?? 'N/A') . ")" . ($ticket->customer_cc ? " [CC: {$ticket->customer_cc}]" : '') . ".",
                ]);

                $createdTickets++;
            } catch (\Throwable $e) {
                Log::error("Error auto-triaging email ID {$email->id}: " . $e->getMessage());
                $skippedCount++;
            }
        }

        return back()->with('success', "Batch AI Triage complete! Processed {$unassignedEmails->count()} emails: Created {$createdTickets} new complaints, Skipped {$skippedCount} non-complaint/bounces, Linked {$alreadyLinked} existing tickets.");
    }

    /**
     * Send in-app reply to email with RFC threading headers.
     */
    public function replyEmail(Request $request, InboxEmail $email, MailService $mailService)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Email replies are reserved for Operations Administrators.');
        }

        $request->validate([
            'reply_body' => 'required|string',
        ]);

        $rawSubject = $email->subject ?: 'Bank Hardware Complaint Follow-up';
        $replySubject = preg_match('/^re:\s*/i', $rawSubject) ? $rawSubject : 'RE: ' . $rawSubject;

        $result = $mailService->sendDirectMail(
            $email->from_email,
            $replySubject,
            $request->reply_body,
            $email->message_id,
            $email->message_id
        );

        if ($result['success'] ?? false) {
            // Record sent message in InboxEmail with is_sent = true
            InboxEmail::create([
                'message_id' => $result['message_id'],
                'from_email' => env('MAIL_FROM_ADDRESS', 'support@cmscompany.biz'),
                'from_name' => env('MAIL_FROM_NAME', 'CMS Technical Operations Desk'),
                'to_email' => $email->from_email,
                'cc_emails' => null,
                'subject' => $replySubject,
                'body_text' => $request->reply_body,
                'email_date' => Carbon::now(),
                'is_read' => true,
                'is_sent' => true,
                'ticket_id' => $email->ticket_id,
            ]);

            if ($email->ticket_id) {
                TicketLog::create([
                    'ticket_id' => $email->ticket_id,
                    'user_id' => Auth::id(),
                    'action' => 'email_reply',
                    'notes' => "Direct email reply sent to {$email->from_email} by " . Auth::user()->name . ". Message ID: {$result['message_id']}",
                ]);
            }

            return back()->with('success', "Reply successfully dispatched to {$email->from_email} via GoDaddy SMTP!");
        }

        return back()->with('error', "Failed to dispatch reply: " . ($result['error'] ?? 'Unknown SMTP error'));
    }

    /**
     * Compose and dispatch a new email via GoDaddy SMTP.
     */
    public function composeEmail(Request $request, MailService $mailService)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Email dispatch is reserved for Operations Administrators.');
        }

        $request->validate([
            'to_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $result = $mailService->sendDirectMail(
            $request->to_email,
            $request->subject,
            $request->body
        );

        if ($result['success'] ?? false) {
            InboxEmail::create([
                'message_id' => $result['message_id'],
                'from_email' => env('MAIL_FROM_ADDRESS', 'support@cmscompany.biz'),
                'from_name' => env('MAIL_FROM_NAME', 'CMS Technical Operations Desk'),
                'to_email' => $request->to_email,
                'cc_emails' => null,
                'subject' => $request->subject,
                'body_text' => $request->body,
                'email_date' => Carbon::now(),
                'is_read' => true,
                'is_sent' => true,
            ]);

            return back()->with('success', "New message dispatched successfully to {$request->to_email} via GoDaddy SMTP!");
        }

        return back()->with('error', "Failed to dispatch email: " . ($result['error'] ?? 'Unknown SMTP error'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'imap_host' => 'required|string',
            'imap_port' => 'required|numeric',
            'imap_encryption' => 'required|in:ssl,tls',
            'imap_username' => 'required|email',
            'imap_password' => 'nullable|string',
            'gemini_api_key' => 'nullable|string',
        ]);

        $port = (int)$validated['imap_port'];
        $encryption = $validated['imap_encryption'];
        // Guard: If an SMTP port was accidentally submitted for IMAP, auto-correct to 993 SSL
        if (in_array($port, [25, 465, 587])) {
            $port = 993;
            $encryption = 'ssl';
        }

        $this->updateEnv([
            'IMAP_HOST' => $validated['imap_host'],
            'IMAP_PORT' => $port,
            'IMAP_ENCRYPTION' => $encryption,
            'IMAP_USERNAME' => $validated['imap_username'],
            'IMAP_PASSWORD' => $validated['imap_password'] ?: env('IMAP_PASSWORD', ''),
            'GEMINI_API_KEY' => $validated['gemini_api_key'] ?: env('GEMINI_API_KEY', ''),
        ]);

        return back()->with('success', 'GoDaddy email integration settings saved to .env successfully!');
    }

    private function updateEnv(array $data): void
    {
        $path = base_path('.env');
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        foreach ($data as $key => $value) {
            if (empty($value)) continue;

            if (preg_match("/^{$key}=/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}=\"{$value}\"", $content);
            } else {
                $content .= "\n{$key}=\"{$value}\"";
            }
        }

        file_put_contents($path, $content);
    }
}
