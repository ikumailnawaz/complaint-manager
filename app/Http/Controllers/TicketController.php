<?php

namespace App\Http\Controllers;

use App\Models\InboxEmail;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\MailService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function showSimulateIngest()
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'AI Ingestion Simulator is reserved for Operations Administrators.');
        }
        return view('tickets.simulate-ingest');
    }

    public function processSimulateIngest(Request $request, \App\Services\GeminiService $gemini)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'AI Ingestion Simulator is reserved for Operations Administrators.');
        }

        $request->validate([
            'subject' => 'required|string',
            'body' => 'required|string',
            'from_email' => 'nullable|email',
        ]);

        $ingestController = app(\App\Http\Controllers\Api\TicketIngestController::class);
        $response = $ingestController->ingest($request);
        $data = $response->getData(true);

        if ($data['success'] ?? false) {
            if ($data['is_complaint'] ?? true) {
                return redirect()->route('tickets.show', $data['ticket_id'])
                    ->with('success', "AI Ingestion Succeeded! Ticket {$data['ticket_no']} created from bank email. Status: OPEN (Unassigned). Ready to pick engineer.");
            }
            return back()->with('warning', 'Email analyzed: Classified as non-complaint (' . ($data['type'] ?? 'other') . '). No ticket created.');
        }

        return back()->with('error', 'Ingestion failed: ' . ($data['error'] ?? 'Unknown error'));
    }

    public function openTickets(Request $request)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'The Open Tickets SLA Command Center is reserved for Operations Administrators.');
        }

        $query = Ticket::whereNotIn('status', ['closed', 'resolved'])
            ->with(['engineer', 'feedbacks.engineer', 'assignedBy'])
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('bank')) {
            $query->where('bank_name', 'like', "%{$request->bank}%");
        }

        if ($request->filled('location')) {
            $query->where('branch_location', 'like', "%{$request->location}%");
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('branch_location', 'like', "%{$search}%")
                  ->orWhere('machine_serial_no', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        if (Auth::user()->isEngineer()) {
            $query->where(function ($q) {
                $q->where('assigned_engineer_id', Auth::id())
                  ->orWhere('original_field_engineer_id', Auth::id());
            });
        }

        $rawPerPage = $request->input('per_page', '25');
        $perPage = ($rawPerPage === 'all') ? 1000 : (in_array((int)$rawPerPage, [10, 25, 50, 100]) ? (int)$rawPerPage : 25);
        $tickets = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total_open' => Ticket::whereNotIn('status', ['closed', 'resolved'])->count(),
            'high_priority' => Ticket::whereNotIn('status', ['closed', 'resolved'])->where('urgency', 'high')->count(),
            'needs_feedback' => Ticket::whereNotIn('status', ['closed', 'resolved'])
                ->whereDoesntHave('feedbacks', function($q) {
                    $q->whereDate('created_at', Carbon::today());
                })->count(),
            'escalated' => Ticket::where('status', 'escalated')->count(),
        ];

        return view('tickets.open', compact('tickets', 'stats'));
    }

    public function index(Request $request)
    {
        if (Auth::user()->isOfficeStaff()) {
            return redirect()->route('tickets.open');
        }

        $query = Ticket::with(['engineer', 'assignedBy'])->latest();

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('bank')) {
            $query->where('bank_name', 'like', "%{$request->bank}%");
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('location')) {
            $query->where('branch_location', 'like', "%{$request->location}%");
        }

        if ($request->filled('unassigned')) {
            $query->whereNull('assigned_engineer_id');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('branch_location', 'like', "%{$search}%")
                  ->orWhere('machine_serial_no', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        // If logged in as engineer, default to their own tickets unless filtering
        if (Auth::user()->isEngineer()) {
            $query->where(function ($q) {
                $q->where('assigned_engineer_id', Auth::id())
                  ->orWhere('original_field_engineer_id', Auth::id());
            });
        }

        $rawPerPage = $request->input('per_page', '25');
        if ($rawPerPage === 'all') {
            $perPage = 1000;
        } else {
            $perPage = in_array((int)$rawPerPage, [10, 25, 50, 100]) ? (int)$rawPerPage : 25;
        }
        $tickets = $query->paginate($perPage)->withQueryString();

        $engineers = User::where('role', 'engineer')->orderBy('base_city')->get();

        return view('tickets.index', compact('tickets', 'engineers'));
    }

    public function create()
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Registering new complaints is reserved for Operations Administrators.');
        }

        $engineers = User::where('role', 'engineer')->where('is_available', true)->get();
        return view('tickets.create', compact('engineers'));
    }

    public function store(Request $request)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Registering new complaints is reserved for Operations Administrators.');
        }

        $validated = $request->validate([
            'ticket_no_type' => 'required|in:auto,manual',
            'custom_ticket_no' => 'nullable|string|max:50',
            'bank_name' => 'required|string|max:100',
            'branch_name' => 'nullable|string|max:100',
            'branch_location' => 'required|string|max:100',
            'branch_address' => 'nullable|string',
            'customer_name' => 'nullable|string|max:100',
            'customer_mobile' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:100',
            'machine_type' => 'nullable|string|max:100',
            'machine_model' => 'nullable|string|max:100',
            'machine_serial_no' => 'nullable|string|max:100',
            'warranty_status' => 'required|in:in_warranty,out_of_warranty,unknown',
            'urgency' => 'required|in:high,medium,low',
            'sla_tat' => 'nullable|string',
            'issue_summary' => 'required|string|max:255',
            'issue_description' => 'nullable|string',
            'assigned_engineer_id' => 'nullable|exists:users,id',
        ]);

        // Generate Ticket No or use bank email reference
        if ($validated['ticket_no_type'] === 'manual' && !empty($validated['custom_ticket_no'])) {
            $rawTicketNo = strtoupper(trim($validated['custom_ticket_no']));
            $bankName = $validated['bank_name'];

            // Verify same ticket number and bank reference doesn't exist already
            $existing = Ticket::ticketExistsForBank($bankName, $rawTicketNo);
            if ($existing) {
                return back()->withInput()->withErrors([
                    'custom_ticket_no' => "A complaint ticket (#{$existing->ticket_no}) already exists for {$bankName} with reference {$rawTicketNo}. Duplicate registration was prevented."
                ])->with('error', "Complaint ticket already exists for {$bankName} with Ticket #{$rawTicketNo}!");
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

        // SLA deadline calculation (Supports 1 Day, 2 Days, 3 Days, 4 Days, 4h, 8h)
        $slaTat = $request->input('sla_tat');
        if ($slaTat === '1d' || $slaTat === '1 day') {
            $slaDeadline = Carbon::now()->addDays(1);
        } elseif ($slaTat === '2d' || $slaTat === '2 days') {
            $slaDeadline = Carbon::now()->addDays(2);
        } elseif ($slaTat === '3d' || $slaTat === '3 days') {
            $slaDeadline = Carbon::now()->addDays(3);
        } elseif ($slaTat === '4d' || $slaTat === '4 days') {
            $slaDeadline = Carbon::now()->addDays(4);
        } elseif ($slaTat === '4h' || $slaTat === '4 hours') {
            $slaDeadline = Carbon::now()->addHours(4);
        } elseif ($slaTat === '8h' || $slaTat === '8 hours') {
            $slaDeadline = Carbon::now()->addHours(8);
        } elseif ($slaTat && preg_match('/(\d+)\s*hour/i', $slaTat, $m)) {
            $slaDeadline = Carbon::now()->addHours((int)$m[1]);
        } elseif ($slaTat && preg_match('/(\d+)\s*day/i', $slaTat, $m)) {
            $slaDeadline = Carbon::now()->addDays((int)$m[1]);
        } else {
            $slaHours = match ($validated['urgency']) {
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
            'customer_ref_no' => $validated['custom_ticket_no'] ?? null,
            'bank_name' => $validated['bank_name'],
            'branch_name' => $validated['branch_name'] ?? null,
            'branch_location' => $validated['branch_location'],
            'branch_address' => $validated['branch_address'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_mobile' => $validated['customer_mobile'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'machine_type' => $validated['machine_type'] ?? null,
            'machine_model' => $validated['machine_model'] ?? null,
            'machine_serial_no' => $validated['machine_serial_no'] ?? null,
            'warranty_status' => $validated['warranty_status'],
            'urgency' => $validated['urgency'],
            'status' => !empty($validated['assigned_engineer_id']) ? 'assigned' : 'open',
            'issue_summary' => $validated['issue_summary'],
            'issue_description' => $validated['issue_description'] ?? null,
            'assigned_engineer_id' => $validated['assigned_engineer_id'] ?? null,
            'assigned_by_id' => !empty($validated['assigned_engineer_id']) ? Auth::id() : null,
            'assigned_at' => !empty($validated['assigned_engineer_id']) ? Carbon::now() : null,
            'sla_deadline' => $slaDeadline,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'created',
            'notes' => 'Ticket created manually by ' . Auth::user()->name . ($ticket->assigned_engineer_id ? " and assigned to engineer." : " (unassigned)."),
        ]);

        if ($ticket->assigned_engineer_id && $ticket->engineer) {
            \App\Services\NotificationService::notifyTicketAssigned($ticket, $ticket->engineer);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', "Ticket {$ticket->ticket_no} created successfully.");
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['incomingEmail', 'engineer', 'assignedBy', 'whatsappNotifiedBy', 'emailSentBy', 'escalatedTo', 'feedbacks.engineer', 'expenseClaims.engineer', 'logs.user']);

        // Available engineers in database for manual alignment
        $engineers = User::where('role', 'engineer')
            ->withCount(['assignedTickets' => function ($q) {
                $q->whereIn('status', ['assigned', 'in_progress', 'awaiting_workshop']);
            }])
            ->orderBy('is_available', 'desc')
            ->orderBy('base_city')
            ->get();

        // Superiors for escalation
        $superiors = User::whereIn('role', ['superior', 'super_admin'])->get();

        return view('tickets.show', compact('ticket', 'engineers', 'superiors'));
    }

    public function assignEngineer(Request $request, Ticket $ticket)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'This action is restricted to Operations Administrators.');
        }

        $request->validate([
            'engineer_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'sla_tat' => 'nullable|string',
            'custom_sla_deadline' => 'nullable|date',
        ]);

        $engineer = User::findOrFail($request->engineer_id);

        $updateData = [
            'assigned_engineer_id' => $engineer->id,
            'assigned_by_id' => Auth::id(),
            'assigned_at' => Carbon::now(),
            'status' => 'assigned',
            // Reset WhatsApp and email sent status for new alignment
            'whatsapp_notified' => false,
            'whatsapp_notified_at' => null,
            'email_assignment_sent' => false,
            'email_assignment_sent_at' => null,
        ];

        // Operator-defined TAT override if selected
        if (!empty($request->custom_sla_deadline)) {
            $updateData['sla_deadline'] = Carbon::parse($request->custom_sla_deadline);
        } elseif (!empty($request->sla_tat)) {
            if (preg_match('/(\d+)\s*day/i', $request->sla_tat, $m)) {
                $updateData['sla_deadline'] = Carbon::now()->addDays((int)$m[1]);
            } elseif (preg_match('/(\d+)\s*hour/i', $request->sla_tat, $m)) {
                $updateData['sla_deadline'] = Carbon::now()->addHours((int)$m[1]);
            }
        } elseif (empty($ticket->sla_deadline)) {
            $updateData['sla_deadline'] = Carbon::now()->addHours($ticket->expectedResponseHours());
        }

        $ticket->update($updateData);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'assigned',
            'notes' => "Manually assigned to Engineer {$engineer->name} ({$engineer->base_city}, {$engineer->phone_whatsapp}). " . ($request->sla_tat ? "Defined TAT: {$request->sla_tat}. " : "") . ($request->notes ? "Note: {$request->notes}" : ''),
        ]);

        \App\Services\NotificationService::notifyTicketAssigned($ticket, $engineer);

        return back()->with('success', "Engineer {$engineer->name} assigned successfully. Next step: Click 'Notify on WhatsApp' to dispatch screenshot & tag engineer.");
    }

    public function notifyWhatsApp(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'This action is restricted to Operations Administrators.');
        }

        if (empty($ticket->assigned_engineer_id)) {
            return back()->with('error', 'Cannot send WhatsApp notification: No engineer assigned yet.');
        }

        $engineer = $ticket->engineer;

        // Dispatch alert via WhatsAppService
        $dispatchResult = $whatsapp->sendAssignmentAlert($ticket, $engineer, $request->notes);
        $messageId = $dispatchResult['message_id'];

        $ticket->update([
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now(),
            'whatsapp_notified_by_id' => Auth::id(),
            'whatsapp_message_id' => $messageId,
            'status' => $ticket->status === 'open' ? 'assigned' : $ticket->status,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'whatsapp_notified',
            'notes' => "WhatsApp alignment alert dispatched to field group. Tagged engineer {$engineer->name} (@{$engineer->phone_whatsapp}). Message ID: {$messageId}. 'Send Assignment Email to Bank' button is now UNLOCKED.",
        ]);

        return back()->with('success', "WhatsApp notification sent to group! Tagged @{$engineer->phone_whatsapp}. The 'Send Assignment Email to Bank' button is now unlocked.");
    }

    /**
     * ONE-CLICK: Receive base64 screenshot + message from browser AJAX,
     * forward to wwebjs-service which sends image + text to WhatsApp group.
     */
    public function sendGroupWhatsApp(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'This action is restricted to Operations Administrators.');
        }

        if (empty($ticket->assigned_engineer_id)) {
            return response()->json(['success' => false, 'error' => 'No engineer assigned yet.'], 422);
        }

        $engineer  = $ticket->engineer;
        $message   = $whatsapp->getAssignmentMessageText($ticket, $engineer);
        $imageB64  = $request->input('image_base64');   // data:image/png;base64,...
        $groupId   = $request->input('group_id');        // optional override
        $filename  = 'complaint-' . $ticket->ticket_no . '.png';

        $serviceUrl = env('WWEBJS_SERVICE_URL', '');

        if (empty($serviceUrl)) {
            return response()->json([
                'success' => false,
                'error'   => 'wwebjs-service is not configured. Set WWEBJS_SERVICE_URL in .env.',
            ], 503);
        }

        try {
            $payload = [
                'group_id'       => $groupId ?: env('WHATSAPP_GROUP_ID', ''),
                'message'        => $message,
                'image_base64'   => $imageB64,
                'image_filename' => $filename,
            ];

            $httpReq = \Illuminate\Support\Facades\Http::timeout(30)->withoutVerifying();
            $token   = env('WWEBJS_SECRET', '');
            if (!empty($token)) {
                $httpReq = $httpReq->withToken($token);
            }

            $response = $httpReq->post($serviceUrl . '/send', $payload);

            if (!$response->successful()) {
                $errBody = $response->json();
                return response()->json([
                    'success' => false,
                    'error'   => $errBody['error'] ?? 'wwebjs-service returned error: ' . $response->status(),
                ], 502);
            }

            $resData   = $response->json();
            $messageId = $resData['message_id'] ?? ('wamid.' . strtoupper(substr(md5(uniqid()), 0, 16)));

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Cannot connect to wwebjs-service: ' . $e->getMessage()], 503);
        }

        // Mark ticket as WhatsApp notified
        $ticket->update([
            'whatsapp_notified'    => true,
            'whatsapp_notified_at' => Carbon::now(),
            'whatsapp_notified_by_id' => Auth::id(),
            'whatsapp_message_id'  => $messageId,
            'status'               => $ticket->status === 'open' ? 'assigned' : $ticket->status,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'whatsapp_group_sent',
            'notes'     => "One-click WhatsApp group dispatch: image + message sent to group. Engineer @{$engineer->phone_whatsapp} tagged. Msg ID: {$messageId}.",
        ]);

        return response()->json([
            'success'    => true,
            'message_id' => $messageId,
            'msg'        => "✅ Sent to WhatsApp group with email screenshot! Engineer {$engineer->name} tagged.",
        ]);
    }

    /**
     * Mark WhatsApp as notified via local copy/dispatch flow.
     * Unlocks Step 3 without requiring external wwebjs-service server.
     */
    public function markWhatsAppCopied(Request $request, Ticket $ticket)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'This action is restricted to Operations Administrators.');
        }

        if (empty($ticket->assigned_engineer_id)) {
            return response()->json(['success' => false, 'error' => 'No engineer assigned yet.'], 422);
        }

        $engineer  = $ticket->engineer;
        $messageId = 'local_copy_' . strtoupper(substr(md5(uniqid()), 0, 10));

        $ticket->update([
            'whatsapp_notified'       => true,
            'whatsapp_notified_at'    => Carbon::now(),
            'whatsapp_notified_by_id' => Auth::id(),
            'whatsapp_message_id'     => $messageId,
            'status'                  => $ticket->status === 'open' ? 'assigned' : $ticket->status,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'whatsapp_copied_dispatch',
            'notes'     => "Complaint mail copied to clipboard as Image & dispatched via local WhatsApp. Engineer {$engineer->name} notified. Step 3 unlocked.",
        ]);

        return response()->json([
            'success'              => true,
            'message_id'           => $messageId,
            'msg'                  => "✅ Email copied to clipboard as Image! Paste (Ctrl+V) into WhatsApp. Step 3 is now unlocked.",
            'ticket_status'        => $ticket->status,
            'whatsapp_notified_at' => $ticket->whatsapp_notified_at->format('h:i A'),
        ]);
    }

    public function sendAssignmentEmail(Request $request, Ticket $ticket, MailService $mailService)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'This action is restricted to Operations Administrators.');
        }

        // STRICT RULE ENFORCEMENT: WhatsApp notification MUST be done first
        if (!$ticket->whatsapp_notified) {
            return back()->with('error', 'STRICT SLA RULE: You must notify the engineer on WhatsApp before sending the assignment confirmation email to the bank.');
        }

        if (empty($ticket->customer_email)) {
            return back()->with('error', 'Bank email address is missing for this ticket.');
        }

        // Save updated CC addresses if edited in the form
        if ($request->has('customer_cc')) {
            $ticket->update(['customer_cc' => $request->customer_cc]);
        }

        $engineer = $ticket->engineer;

        // Dispatch real email via GoDaddy SMTP using PHPMailer
        $result = $mailService->sendBankAssignmentReply($ticket, $request->custom_notes);

        if (!$result['success']) {
            return back()->with('error', 'Failed to dispatch email to bank via GoDaddy SMTP: ' . ($result['error'] ?? 'Unknown mail server error'));
        }

        $messageId = $result['message_id'];

        $ticket->update([
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now(),
            'email_assignment_sent_by_id' => Auth::id(),
            'email_reply_message_id' => $messageId,
        ]);

        $slaNotice = $ticket->sla_deadline ? $ticket->sla_deadline->format('d M Y, h:i A') : 'Standard SLA';

        // Record in InboxEmail so it shows up in Webmail Sent Items
        InboxEmail::create([
            'message_id' => $messageId,
            'from_email' => env('MAIL_FROM_ADDRESS', 'support@cmscompany.biz'),
            'from_name' => env('MAIL_FROM_NAME', 'CMS Technical Operations Desk'),
            'to_email' => $ticket->customer_email,
            'cc_emails' => $ticket->customer_cc,
            'subject' => $mailService->buildThreadSubject($ticket->email_subject, "RE: Ticket #{$ticket->ticket_no}" . ($ticket->customer_ref_no ? " (Bank Ref: {$ticket->customer_ref_no})" : '') . " - Assigned to Field Engineer [{$ticket->bank_name}]"),
            'body_text' => "Official assignment email dispatched to bank for Ticket #{$ticket->ticket_no}.\nAssigned Field Engineer: {$engineer->name} ({$engineer->phone_whatsapp})\nSLA Deadline: {$slaNotice}\n" . ($request->custom_notes ? "Notes: {$request->custom_notes}" : ''),
            'email_date' => Carbon::now(),
            'is_read' => true,
            'is_sent' => true,
            'ticket_id' => $ticket->id,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'email_sent',
            'notes' => "Assignment confirmation email successfully sent via GoDaddy SMTP to bank ({$ticket->customer_email})" . ($ticket->customer_cc ? " with CC to ({$ticket->customer_cc})" : '') . ". Confirmed engineer {$engineer->name}, SLA resolution deadline: {$slaNotice}. Message ID: {$messageId}.",
        ]);

        return back()->with('success', "Assignment email successfully sent in reply to {$ticket->customer_email} via GoDaddy SMTP! Message ID: {$messageId}");
    }

    public function sendResolutionEmail(Request $request, Ticket $ticket, MailService $mailService)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'This action is restricted to Operations Administrators.');
        }

        if (!in_array($ticket->status, ['resolved', 'closed'])) {
            return back()->with('error', "Resolution email can only be dispatched for resolved or closed complaints. Ticket #{$ticket->ticket_no} is currently {$ticket->status}.");
        }

        if (empty($ticket->customer_email)) {
            return back()->with('error', 'Bank email address is missing for this ticket.');
        }

        if ($request->has('customer_cc')) {
            $ticket->update(['customer_cc' => $request->customer_cc]);
        }

        $result = $mailService->sendResolutionEmailReply($ticket, $request->custom_notes);

        if (!$result['success']) {
            return back()->with('error', 'Failed to dispatch resolution email to bank via GoDaddy SMTP: ' . ($result['error'] ?? 'Unknown mail server error'));
        }

        $messageId = $result['message_id'];
        $recipient = $result['recipient'] ?? $ticket->customer_email;

        $ticket->update([
            'resolution_email_sent' => true,
            'resolution_email_sent_at' => Carbon::now(),
            'resolution_email_sent_by_id' => Auth::id(),
            'resolution_email_message_id' => $messageId,
            'resolution_email_to' => $recipient,
        ]);

        $engineerName = $ticket->engineer ? $ticket->engineer->name : 'Assigned Field Engineer';

        // Record in InboxEmail so it shows up in Webmail Sent Items
        InboxEmail::create([
            'message_id' => $messageId,
            'from_email' => env('MAIL_FROM_ADDRESS', 'support@cmscompany.biz'),
            'from_name' => env('MAIL_FROM_NAME', 'CMS Technical Operations Desk'),
            'to_email' => $recipient,
            'cc_emails' => $ticket->customer_cc,
            'subject' => $mailService->buildThreadSubject($ticket->email_subject, "RE: Ticket #{$ticket->ticket_no} - RESOLVED - {$ticket->bank_name} {$ticket->branch_name}"),
            'body_text' => "Official service resolution email dispatched to bank for Ticket #{$ticket->ticket_no}.\nResolved by: {$engineerName}\nResolution Summary: {$ticket->resolution_summary}\n" . ($ticket->supporting_document ? "Supporting Document Attached: " . basename($ticket->supporting_document) . "\n" : '') . ($request->custom_notes ? "Notes: {$request->custom_notes}" : ''),
            'email_date' => Carbon::now(),
            'is_read' => true,
            'is_sent' => true,
            'ticket_id' => $ticket->id,
        ]);

        $docNote = $ticket->supporting_document ? " with attached evidence (" . basename($ticket->supporting_document) . ")" : "";
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'resolution_email_sent',
            'notes' => "Resolution email successfully sent via GoDaddy SMTP to bank ({$recipient})" . ($ticket->customer_cc ? " with CC to ({$ticket->customer_cc})" : '') . "{$docNote}. Message ID: {$messageId}.",
        ]);

        return back()->with('success', "Resolution confirmation email successfully dispatched in reply to {$recipient}" . ($ticket->customer_cc ? " (CC: {$ticket->customer_cc})" : '') . " via GoDaddy SMTP! Supporting proof attached.");
    }

    public function addFeedback(Request $request, Ticket $ticket)
    {
        $user = Auth::user();

        // Accessible by assigned engineer OR operations management (admin, super_admin, superior)
        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
            abort(403, 'Field engineers can only submit daily feedback for tickets assigned to them.');
        }

        $request->validate([
            'feedback_text' => 'required|string',
            'action_taken' => 'required|string',
            'parts_required' => 'nullable|string|max:255',
            'eta_completion' => 'nullable|date',
            'photo_evidence' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo_evidence')) {
            $photoPath = $request->file('photo_evidence')->store('feedbacks', 'public');
        }

        // Calendar date based day calculation
        $ticketStartDate = ($ticket->created_at ?? Carbon::now())->copy()->startOfDay();
        $todayDate = Carbon::today();
        $calendarDay = max(1, (int) $ticketStartDate->diffInDays($todayDate) + 1);

        // Check if a feedback was already logged today
        $existingTodayFeedback = TicketFeedback::where('ticket_id', $ticket->id)
            ->whereDate('submitted_at', $todayDate)
            ->first();

        if (!$existingTodayFeedback) {
            // Also fallback check on created_at in case submitted_at was null
            $existingTodayFeedback = TicketFeedback::where('ticket_id', $ticket->id)
                ->whereDate('created_at', $todayDate)
                ->first();
        }

        $submitterRole = $user->isEngineer() ? 'Field Engineer' : 'Operations Manager';

        if ($existingTodayFeedback) {
            // Update / edit today's feedback
            $updateData = [
                'action_taken'    => $request->action_taken,
                'feedback_text'   => $request->feedback_text,
                'parts_required'  => $request->parts_required,
                'submitted_by_id' => $user->id,
                'day_number'      => $calendarDay,
            ];
            if ($request->filled('eta_completion')) {
                $updateData['eta_completion'] = Carbon::parse($request->eta_completion);
            }
            if ($photoPath) {
                $updateData['photo_evidence'] = $photoPath;
            }

            $existingTodayFeedback->update($updateData);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'user_id'   => $user->id,
                'action'    => 'feedback_updated',
                'notes'     => "Day {$calendarDay} feedback updated by {$submitterRole} {$user->name}: {$request->action_taken}. Note: {$request->feedback_text}" . ($request->parts_required ? " [Parts: {$request->parts_required}]" : ''),
            ]);

            return back()->with('success', "Day {$calendarDay} daily progress feedback updated successfully for today.");
        }

        // Create new feedback for today's calendar day
        $feedback = TicketFeedback::create([
            'ticket_id'       => $ticket->id,
            'engineer_id'     => $ticket->assigned_engineer_id ?? $user->id,
            'submitted_by_id' => $user->id,
            'day_number'      => $calendarDay,
            'action_taken'    => $request->action_taken,
            'feedback_text'   => $request->feedback_text,
            'parts_required'  => $request->parts_required,
            'eta_completion'  => $request->eta_completion ? Carbon::parse($request->eta_completion) : null,
            'photo_evidence'  => $photoPath,
            'status'          => 'submitted',
            'submitted_at'    => Carbon::now(),
        ]);

        // Update ticket status to in_progress if assigned or open
        if (in_array($ticket->status, ['assigned', 'open', 'pending_feedback'])) {
            $ticket->update(['status' => 'in_progress']);
        }

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'feedback_added',
            'notes'     => "Day {$calendarDay} feedback logged by {$submitterRole} {$user->name}: {$request->action_taken}. Note: {$request->feedback_text}" . ($request->parts_required ? " [Parts: {$request->parts_required}]" : ''),
        ]);

        return back()->with('success', "Day {$calendarDay} daily progress feedback logged successfully.");
    }

    public function reassignWorkshop(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        if ($ticket->isWorkshopFlow() && !in_array($ticket->status, ['open', 'assigned', 'in_progress'])) {
            return back()->with('error', "This ticket is already in Central Workshop flow and cannot be dispatched again.");
        }

        if ($ticket->status === 'resolved' && $ticket->expenseClaims()->exists()) {
            return back()->with('error', "STRICT AUDIT POLICY: Ticket #{$ticket->ticket_no} is resolved and has claimed expenses. Its status cannot be altered.");
        }

        $request->validate([
            'workshop_location'          => 'required|string|max:100',
            'workshop_dispatch_courier'  => 'nullable|string|max:100',
            'workshop_dispatch_tracking' => 'nullable|string|max:100',
            'workshop_engineer_id'       => 'nullable|exists:users,id',
            'notes'                      => 'required|string',
        ]);

        $courier = $request->workshop_dispatch_courier ?: 'TCS Cargo / Courier';
        $tracking = $request->workshop_dispatch_tracking ?: ('TRK-' . date('Ymd') . '-' . rand(100, 999));

        // Preserve 1st / original field engineer who visited the machine
        $originalFieldEngineerId = $ticket->original_field_engineer_id ?? $ticket->assigned_engineer_id;

        $updateData = [
            'status'                      => 'awaiting_workshop',
            'workshop_location'           => $request->workshop_location,
            'original_field_engineer_id'  => $originalFieldEngineerId,
            // Ticket remains assigned to the field engineer while machine is in transit!
            'assigned_engineer_id'        => $ticket->assigned_engineer_id ?? $originalFieldEngineerId,
            'workshop_dispatch_courier'   => $courier,
            'workshop_dispatch_tracking'  => $tracking,
            'workshop_dispatched_at'      => Carbon::now(),
            'workshop_dispatch_notes'     => $request->notes,
        ];

        // Store recommended/designated workshop engineer without changing active assignment yet
        if ($request->filled('workshop_engineer_id')) {
            $updateData['workshop_engineer_id'] = (int) $request->workshop_engineer_id;
        }

        $ticket->update($updateData);

        $workshopEngineer = $ticket->workshop_engineer_id ? User::find($ticket->workshop_engineer_id) : null;
        // Dispatch WhatsApp workshop transit alert
        $whatsapp->sendWorkshopAlert($ticket, $request->workshop_location, $workshopEngineer);

        $fieldEngName = $ticket->originalFieldEngineer?->name ?? $ticket->engineer?->name ?? 'Field Engineer';
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'workshop_transfer',
            'notes'     => "Machine dispatched from branch to {$request->workshop_location} via {$courier} (Tracking #{$tracking}). Ticket remains under Field Engineer {$fieldEngName} during transit. Cargo Notes: {$request->notes}",
        ]);

        return back()->with('success', "Ticket updated: Machine routed to {$request->workshop_location} via {$courier} (Tracking #{$tracking}). Ticket remains assigned to {$fieldEngName} until physical intake at workshop.");
    }

    public function escalate(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        $request->validate([
            'escalated_to_id' => 'required|exists:users,id',
            'reason' => 'required|string',
        ]);

        $superior = User::findOrFail($request->escalated_to_id);

        $ticket->update([
            'status' => 'escalated',
            'escalated_at' => Carbon::now(),
            'escalated_to_id' => $superior->id,
        ]);

        // Dispatch WhatsApp escalation alert
        $whatsapp->sendEscalationAlert($ticket, $superior, $request->reason);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'escalated',
            'notes' => "Ticket manually escalated to Regional Superior {$superior->name} by " . Auth::user()->name . ". Reason: {$request->reason}",
        ]);

        \App\Services\NotificationService::notifyTicketEscalated($ticket);

        return back()->with('warning', "Ticket escalated to {$superior->name} for immediate intervention. WhatsApp alert dispatched.");
    }

    public function markResolved(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
            abort(403, 'Field engineers can only resolve tickets assigned to them.');
        }

        if ($ticket->status === 'awaiting_workshop') {
            return back()->with('error', "Machine is in transit to central workshop. It cannot be marked complete until bench repair is completed.");
        }

        $request->validate([
            'resolution_summary' => 'required|string',
            'supporting_document' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
            'uploaded_document_path' => 'nullable|string',
            'uploaded_document_name' => 'nullable|string',
        ]);

        $docPath = $ticket->supporting_document;
        $docName = $ticket->resolution_document_name;

        if ($request->hasFile('supporting_document')) {
            $file = $request->file('supporting_document');
            $docName = $file->getClientOriginalName();
            $docPath = $file->store('resolutions', 'public');
        } elseif ($request->filled('uploaded_document_path')) {
            $docPath = $request->uploaded_document_path;
            $docName = $request->uploaded_document_name ?: basename($docPath);
        }

        $isWorkshop = $ticket->isWorkshopFlow() || in_array($ticket->status, ['in_workshop_repair', 'awaiting_workshop']);

        if ($isWorkshop) {
            $ticket->update([
                'status' => 'workshop_repaired',
                'workshop_repaired_at' => Carbon::now(),
                'workshop_repair_summary' => $request->resolution_summary,
                'resolved_at' => Carbon::now(),
                'resolution_summary' => $request->resolution_summary,
                'supporting_document' => $docPath,
                'resolution_document_name' => $docName,
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'action' => 'workshop_repaired',
                'notes' => "Bench repair marked complete by {$user->name}. QA tested OK. Queued at Central Workshop ready for return delivery to bank. Summary: {$request->resolution_summary}" . ($docPath ? " (Supporting Document: {$docName})" : ''),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Bench repair marked complete! Repaired machine is now queued at Central Workshop ready for return dispatch.",
                    'redirect_url' => route('tickets.index'),
                ]);
            }

            return redirect()->route('tickets.index')
                ->with('success', "Bench repair marked complete! Repaired machine is now queued at Central Workshop ready for return dispatch.");
        }

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now(),
            'resolution_summary' => $request->resolution_summary,
            'supporting_document' => $docPath,
            'resolution_document_name' => $docName,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'resolved',
            'notes' => "Complaint marked RESOLVED by {$user->name}. Resolution summary: {$request->resolution_summary}" . ($docPath ? " (Supporting Document: {$docName})" : ''),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Ticket #{$ticket->ticket_no} marked resolved successfully!",
                'redirect_url' => route('tickets.index'),
            ]);
        }

        return redirect()->route('tickets.index')
            ->with('success', "Ticket #{$ticket->ticket_no} marked as RESOLVED!");
    }

    public function undoResolve(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
            abort(403, 'Field engineers can only undo resolution for tickets assigned to them.');
        }

        // STRICT POLICY: Once a ticket is marked resolved and expenses are claimed, it cannot be undone
        if ($ticket->expenseClaims()->exists()) {
            return back()->with('error', "STRICT SLA & AUDIT POLICY: Tour expenses have already been claimed for Ticket #{$ticket->ticket_no}. Once expenses are claimed, resolution is permanent and cannot be undone.");
        }

        if ($ticket->status === 'closed') {
            return back()->with('error', "Cannot undo: Ticket #{$ticket->ticket_no} has already been officially closed by Operations Administration.");
        }

        if ($ticket->status !== 'resolved') {
            return back()->with('warning', "Ticket #{$ticket->ticket_no} is currently {$ticket->status}, not resolved.");
        }

        $ticket->update([
            'status' => 'in_progress',
            'resolved_at' => null,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'resolution_undone',
            'notes' => "Resolution UNDONE by {$user->name}. Ticket reverted back to In Progress for further field service.",
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Resolution undone. Ticket #{$ticket->ticket_no} reverted to In Progress.",
                'redirect_url' => url()->previous(),
            ]);
        }

        return back()->with('warning', "Resolution undone. Ticket #{$ticket->ticket_no} has been restored to In Progress.");
    }

    public function uploadResolutionDocument(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only upload documents for tickets assigned to you.',
            ], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
        ]);

        $file = $request->file('document');
        $filename = $file->getClientOriginalName();
        $path = $file->store('resolutions', 'public');
        $url = asset('storage/' . $path);
        $ext = strtolower($file->getClientOriginalExtension());
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'path' => $path,
            'url' => $url,
            'filename' => $filename,
            'is_image' => $isImage,
            'filesize' => round($file->getSize() / 1024, 1) . ' KB',
        ]);
    }

    /**
     * View or stream ticket supporting document directly with proper MIME headers.
     */
    public function viewSupportingDocument(Ticket $ticket)
    {
        if (empty($ticket->supporting_document)) {
            abort(404, 'No supporting document attached to this ticket.');
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($ticket->supporting_document)) {
            $altPath = public_path('storage/' . $ticket->supporting_document);
            if (!file_exists($altPath)) {
                abort(404, 'Supporting document file not found on disk.');
            }
            $fullPath = $altPath;
        } else {
            $fullPath = $disk->path($ticket->supporting_document);
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        $filename = $ticket->resolution_document_name ?: basename($fullPath);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Edit / Replace supporting document for an existing ticket and update record.
     */
    public function updateSupportingDocument(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
            abort(403, 'Unauthorized to update document for this ticket.');
        }

        $request->validate([
            'supporting_document' => 'required|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
        ]);

        $file = $request->file('supporting_document');
        $docName = $file->getClientOriginalName();
        $docPath = $file->store('resolutions', 'public');

        // Sync to public/storage for Windows environments
        try {
            $publicTarget = public_path('storage/' . $docPath);
            if (!file_exists(dirname($publicTarget))) {
                @mkdir(dirname($publicTarget), 0777, true);
            }
            @copy(Storage::disk('public')->path($docPath), $publicTarget);
        } catch (\Throwable $e) {}

        // Remove old document if different
        if ($ticket->supporting_document && $ticket->supporting_document !== $docPath) {
            try {
                Storage::disk('public')->delete($ticket->supporting_document);
            } catch (\Throwable $e) {}
        }

        $ticket->update([
            'supporting_document' => $docPath,
            'resolution_document_name' => $docName,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'document_updated',
            'notes' => "Supporting document updated / replaced by {$user->name}: {$docName}.",
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Supporting document successfully updated and verified.',
                'path' => $docPath,
                'filename' => $docName,
                'url' => route('tickets.document', $ticket),
            ]);
        }

        return back()->with('success', "Supporting document successfully updated to '{$docName}'.");
    }

    public function closeTicket(Request $request, Ticket $ticket)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Only administrators can confirm ticket closure.');
        }

        $ticket->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'closed',
            'notes' => "Ticket closed and confirmed by Admin " . Auth::user()->name,
        ]);

        return back()->with('success', "Ticket {$ticket->ticket_no} is officially closed.");
    }

    public function edit(Ticket $ticket)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Full Standalone Ticket Editor is reserved for Operations Administrators.');
        }

        $engineers = User::where('role', 'engineer')
            ->orderBy('is_available', 'desc')
            ->orderBy('name')
            ->get();

        return view('tickets.edit', compact('ticket', 'engineers'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Full Standalone Ticket Editor is reserved for Operations Administrators.');
        }

        $validated = $request->validate([
            'ticket_no' => 'required|string|max:100',
            'customer_ref_no' => 'nullable|string|max:100',
            'bank_name' => 'required|string|max:150',
            'branch_name' => 'nullable|string|max:150',
            'branch_location' => 'required|string|max:100',
            'branch_address' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:100',
            'customer_mobile' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:150',
            'customer_cc' => 'nullable|string|max:500',
            'machine_type' => 'nullable|string|max:100',
            'machine_model' => 'nullable|string|max:100',
            'machine_serial_no' => 'nullable|string|max:100',
            'warranty_status' => 'required|in:in_warranty,out_of_warranty,unknown',
            'urgency' => 'required|in:high,medium,low',
            'status' => 'required|in:open,assigned,in_progress,awaiting_workshop,resolved,closed,escalated',
            'sla_tat' => 'nullable|string',
            'custom_sla_deadline' => 'nullable|date',
            'issue_summary' => 'required|string|max:255',
            'issue_description' => 'nullable|string',
            'assigned_engineer_id' => 'nullable|exists:users,id',
        ]);

        if ($ticket->ticket_no !== $validated['ticket_no']) {
            $existing = Ticket::ticketExistsForBank($validated['bank_name'], $validated['ticket_no'], $ticket->id);
            if ($existing) {
                return back()->withInput()->withErrors([
                    'ticket_no' => "A complaint ticket (#{$existing->ticket_no}) already exists for {$validated['bank_name']} with this reference."
                ]);
            }
            $validated['ticket_no'] = Ticket::formatTicketNo($validated['bank_name'], $validated['ticket_no']);
        }

        // STRICT POLICY: Once a ticket is resolved and expenses are claimed, resolution cannot be undone or status reverted
        if ($ticket->status === 'resolved' && $ticket->expenseClaims()->exists()) {
            if ($validated['status'] !== 'resolved' && $validated['status'] !== 'closed') {
                return back()->with('error', "STRICT AUDIT POLICY: Ticket #{$ticket->ticket_no} has associated tour expense claims. Resolution cannot be undone or reverted to {$validated['status']}.");
            }
        }

        // SLA deadline calculation if updated
        if (!empty($request->custom_sla_deadline)) {
            $validated['sla_deadline'] = Carbon::parse($request->custom_sla_deadline);
        } elseif (!empty($request->sla_tat)) {
            if (preg_match('/(\d+)\s*day/i', $request->sla_tat, $m)) {
                $validated['sla_deadline'] = Carbon::now()->addDays((int)$m[1]);
            } elseif (preg_match('/(\d+)\s*hour/i', $request->sla_tat, $m)) {
                $validated['sla_deadline'] = Carbon::now()->addHours((int)$m[1]);
            }
        } elseif (empty($ticket->sla_deadline) || $request->urgency !== $ticket->urgency) {
            $slaHours = match ($validated['urgency'] ?? 'medium') {
                'high' => 4,
                'medium' => 8,
                'low' => 24,
                default => 8,
            };
            $validated['sla_deadline'] = Carbon::now()->addHours($slaHours);
        }
        unset($validated['sla_tat'], $validated['custom_sla_deadline']);

        // Handle assignment timestamp if engineer changed or freshly assigned
        $engineerChanged = !empty($validated['assigned_engineer_id']) && $validated['assigned_engineer_id'] != $ticket->assigned_engineer_id;
        if ($engineerChanged) {
            $validated['assigned_by_id'] = Auth::id();
            $validated['assigned_at'] = Carbon::now();
            $validated['whatsapp_notified'] = false;
            $validated['whatsapp_notified_at'] = null;
            $validated['email_assignment_sent'] = false;
            $validated['email_assignment_sent_at'] = null;
            if ($validated['status'] === 'open') {
                $validated['status'] = 'assigned';
            }
        }

        $validated['is_human_verified'] = true;
        $validated['verified_by_id'] = Auth::id();
        $validated['verified_at'] = Carbon::now();

        $ticket->update($validated);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'updated',
            'notes' => 'Ticket details managed and finalized by ' . Auth::user()->name . '.',
        ]);

        if ($engineerChanged && $ticket->assigned_engineer_id && $ticket->engineer) {
            \App\Services\NotificationService::notifyTicketAssigned($ticket, $ticket->engineer);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', "Ticket {$ticket->ticket_no} updated and finalized successfully.");
    }

    public function finalizeDetails(Request $request, Ticket $ticket)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Ticket details can only be finalized by Operations Administrators.');
        }

        $validated = $request->validate([
            'machine_type' => 'nullable|string|max:100',
            'machine_model' => 'nullable|string|max:100',
            'machine_serial_no' => 'nullable|string|max:100',
            'warranty_status' => 'required|in:in_warranty,out_of_warranty,unknown',
            'branch_address' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:100',
            'customer_mobile' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:150',
            'customer_cc' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:150',
            'branch_name' => 'nullable|string|max:150',
            'branch_location' => 'nullable|string|max:100',
            'urgency' => 'nullable|in:high,medium,low',
            'issue_summary' => 'nullable|string|max:255',
        ]);

        $updateData = array_filter($validated, fn($v) => !is_null($v));
        $updateData['is_human_verified'] = true;
        $updateData['verified_by_id'] = Auth::id();
        $updateData['verified_at'] = Carbon::now();

        $ticket->update($updateData);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => 'human_verified',
            'notes' => 'Ticket details reviewed, edited, and finalized by operator ' . Auth::user()->name . '. Machine: ' . ($ticket->machine_type ?? 'N/A') . ', S/N: ' . ($ticket->machine_serial_no ?? 'N/A') . ', Address: ' . ($ticket->branch_address ?? 'N/A'),
        ]);

        return back()->with('success', 'Ticket hardware, location, and branch details finalized successfully by operator.');
    }

    /**
     * Delete a wrongly identified complaint ticket.
     * Operations Managers have the authority to delete complaints that are NOT yet human finalized.
     */
    public function destroy(Request $request, Ticket $ticket)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'Engineers do not have authority to delete complaints.');
        }

        // Strict Rule: Complaints that are already human finalized CANNOT be deleted
        if ($ticket->is_human_verified) {
            return back()->with('error', 'STRICT SLA RULE: This complaint ticket has already been human finalized and cannot be deleted.');
        }

        // Strict Rule: Tickets with submitted expense claims cannot be deleted
        if ($ticket->expenseClaims()->exists()) {
            return back()->with('error', "STRICT AUDIT POLICY: Ticket #{$ticket->ticket_no} has associated tour expense claims and cannot be deleted.");
        }

        $ticketNo = $ticket->ticket_no;

        // Unlink any InboxEmail associated with this ticket so it resets to "Needs Triage"
        InboxEmail::where('ticket_id', $ticket->id)->update(['ticket_id' => null]);

        // Delete dependent records
        $ticket->feedbacks()->delete();
        $ticket->logs()->delete();
        $ticket->delete();

        if ($request->input('source') === 'webmail') {
            return redirect()->route('settings.email')
                ->with('success', "Wrongly identified complaint {$ticketNo} deleted successfully. The email has been unlinked and reset to 'Needs Triage'.");
        }

        return redirect()->route('tickets.index')
            ->with('success', "Wrongly identified complaint {$ticketNo} has been deleted successfully (was not yet human finalized).");
    }

    /**
     * Regional Superior & Executive Escalation Command Center.
     */
    public function escalations(Request $request)
    {
        $user = Auth::user();
        if ($user->isEngineer()) {
            abort(403, 'Escalations Command Center is reserved for Regional Superiors and Operations Management.');
        }

        $query = Ticket::with(['assignedEngineer', 'feedbacks', 'logs'])
            ->where(function ($q) {
                $q->where('status', 'escalated')
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('sla_deadline')
                          ->where('sla_deadline', '<', Carbon::now())
                          ->whereNotIn('status', ['resolved', 'closed']);
                  });
            })
            ->latest('sla_deadline');

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('bank')) {
            $query->where('bank_name', $request->bank);
        }

        if ($request->filled('city')) {
            $query->where('branch_location', 'like', "%{$request->city}%");
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('ticket_no', 'like', "%{$term}%")
                  ->orWhere('customer_ref_no', 'like', "%{$term}%")
                  ->orWhere('bank_name', 'like', "%{$term}%")
                  ->orWhere('branch_name', 'like', "%{$term}%")
                  ->orWhere('branch_location', 'like', "%{$term}%")
                  ->orWhere('machine_serial_no', 'like', "%{$term}%")
                  ->orWhere('machine_model', 'like', "%{$term}%")
                  ->orWhere('issue_summary', 'like', "%{$term}%");
            });
        }

        $perPageParam = $request->input('per_page', '25');
        $perPage = ($perPageParam === 'all') ? 500 : max(5, min(200, (int) $perPageParam));

        $escalatedTickets = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total_escalated' => Ticket::where('status', 'escalated')->count(),
            'sla_breached' => Ticket::whereNotNull('sla_deadline')
                ->where('sla_deadline', '<', Carbon::now())
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),
            'high_urgency' => Ticket::where(function ($q) {
                $q->where('status', 'escalated')
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('sla_deadline')
                          ->where('sla_deadline', '<', Carbon::now())
                          ->whereNotIn('status', ['resolved', 'closed']);
                  });
            })->where('urgency', 'high')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'unassigned' => Ticket::where('status', 'open')->count(),
        ];

        $banks = Ticket::distinct()->whereNotNull('bank_name')->where('bank_name', '!=', '')->orderBy('bank_name')->pluck('bank_name');
        $engineers = User::where('role', 'engineer')->where('is_available', true)->get();

        return view('tickets.escalations', compact('escalatedTickets', 'stats', 'engineers', 'banks', 'perPageParam'));
    }

    /**
     * Issue high-priority Supervisor Directive on an escalated ticket.
     */
    public function addInstruction(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->isEngineer()) {
            abort(403, 'Only Supervisors and Operations Management can issue direct instructions.');
        }

        $request->validate([
            'instruction_text' => 'required|string',
            'action_type' => 'required|in:urgent_instruction,priority_boost,parts_approved,workshop_dispatch',
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'supervisor_instruction',
            'notes' => "SUPERVISOR DIRECTIVE (" . strtoupper(str_replace('_', ' ', $request->action_type)) . ") from {$user->name}: {$request->instruction_text}",
        ]);

        return back()->with('success', "Directive logged to ticket audit trail and flagged for engineer attention.");
    }
}
