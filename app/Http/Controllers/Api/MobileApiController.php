<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\EngineerInventory;
use App\Models\ExpenseClaim;
use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\PmRecord;
use App\Models\PmSchedule;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\NotificationService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MobileApiController extends Controller
{
    /**
     * 1. Authentication: Login and issue Sanctum token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide username/email and password.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $input = strtolower(trim($request->input('login')));
        $password = $request->input('password');

        $user = User::where('email', $input)
            ->orWhereRaw('LOWER(name) = ?', [$input])
            ->orWhere('email', $input . '@banksupport.com')
            ->first();

        if (!$user || (!Hash::check($password, $user->password) && !Hash::check(strtolower($password), $user->password))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid operational credentials.',
            ], 401);
        }

        // Revoke older mobile tokens if necessary, or create new
        $token = $user->createToken('android-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => "Welcome back, {$user->name}",
            'token'   => $token,
            'user'    => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'role'             => $user->role,
                'phone'            => $user->phone_whatsapp,
                'base_city'        => $user->base_city,
                'current_city'     => $user->current_city,
                'home_coordinates' => $user->home_coordinates,
                'home_address'     => $user->home_address,
                'specialization'   => $user->specialization,
                'is_available'     => (bool) $user->is_available,
            ],
        ]);
    }

    /**
     * 2. Revoke Current Token
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully signed out.',
        ]);
    }

    /**
     * 3. Engineer Profile & Quick Dashboard Summary
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        $activeTicketsCount = Ticket::where(function ($q) use ($user) {
            $q->forEngineer($user->id)
              ->orWhere('original_field_engineer_id', $user->id);
        })->whereNotIn('status', ['resolved', 'closed'])->count();

        $resolvedTicketsCount = Ticket::where(function ($q) use ($user) {
            $q->forEngineer($user->id)
              ->orWhere('original_field_engineer_id', $user->id);
        })->whereIn('status', ['resolved', 'closed'])->count();

        $pendingExpensesCount = ExpenseClaim::where('engineer_id', $user->id)
            ->where('status', 'submitted')->count();

        $envelopeCount = EngineerInventory::where('engineer_id', $user->id)
            ->where('qty_on_hand', '>', 0)->count();

        return response()->json([
            'success' => true,
            'user'    => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'role'             => $user->role,
                'phone'            => $user->phone_whatsapp,
                'base_city'        => $user->base_city,
                'current_city'     => $user->current_city,
                'home_coordinates' => $user->home_coordinates,
                'home_address'     => $user->home_address,
            ],
            'metrics' => [
                'active_tickets'   => $activeTicketsCount,
                'resolved_tickets' => $resolvedTicketsCount,
                'pending_expenses' => $pendingExpensesCount,
                'envelope_parts'   => $envelopeCount,
            ],
        ]);
    }

    /**
     * 4. List Active Assigned Tickets for Engineer
     */
    public function getTickets(Request $request)
    {
        $user = $request->user();
        $status = $request->input('status', 'active');
        $search = $request->input('search');

        $query = Ticket::where(function ($q) use ($user) {
            $q->forEngineer($user->id)
              ->orWhere('original_field_engineer_id', $user->id);
        });

        if ($status === 'active') {
            $query->whereNotIn('status', ['resolved', 'closed']);
        } elseif ($status === 'resolved') {
            $query->whereIn('status', ['resolved', 'closed']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('branch_name', 'like', "%{$search}%")
                  ->orWhere('branch_location', 'like', "%{$search}%")
                  ->orWhere('machine_serial_no', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'tickets' => $tickets->map(function ($t) {
                return [
                    'id'                 => $t->id,
                    'ticket_no'          => $t->ticket_no,
                    'customer_ref_no'    => $t->customer_ref_no,
                    'bank_name'          => $t->bank_name,
                    'branch_name'        => $t->branch_name,
                    'branch_location'    => $t->branch_location,
                    'branch_address'     => $t->branch_address,
                    'customer_name'      => $t->customer_name,
                    'customer_mobile'    => $t->customer_mobile,
                    'machine_type'       => $t->machine_type,
                    'machine_model'      => $t->machine_model,
                    'machine_serial_no'  => $t->machine_serial_no,
                    'warranty_status'    => $t->warranty_status,
                    'urgency'            => $t->urgency,
                    'status'             => $t->status,
                    'issue_summary'      => $t->issue_summary,
                    'sla_deadline'       => $t->sla_deadline?->format('d M Y, h:i A'),
                    'is_sla_breached'    => $t->sla_deadline ? $t->sla_deadline->isPast() && !in_array($t->status, ['resolved', 'closed']) : false,
                    'created_at'         => $t->created_at?->format('d M Y, h:i A'),
                ];
            }),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'total'        => $tickets->total(),
            ],
        ]);
    }

    /**
     * 5. Get Complete Ticket Details with Feedbacks and History
     */
    public function getTicketDetails($id, Request $request)
    {
        $user = $request->user();

        $ticket = Ticket::with(['feedbacks' => function ($q) {
            $q->latest('day_number');
        }, 'partRequests.items.part', 'expenseClaims', 'workshopIncharge', 'workshopEngineer'])->findOrFail($id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user) && $ticket->original_field_engineer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this complaint.'], 403);
        }

        return response()->json([
            'success' => true,
            'ticket'  => [
                'id'                 => $ticket->id,
                'ticket_no'          => $ticket->ticket_no,
                'customer_ref_no'    => $ticket->customer_ref_no,
                'bank_name'          => $ticket->bank_name,
                'branch_name'        => $ticket->branch_name,
                'branch_location'    => $ticket->branch_location,
                'branch_address'     => $ticket->branch_address,
                'customer_name'      => $ticket->customer_name,
                'customer_mobile'    => $ticket->customer_mobile,
                'customer_email'     => $ticket->customer_email,
                'machine_type'       => $ticket->machine_type,
                'machine_model'      => $ticket->machine_model,
                'machine_serial_no'  => $ticket->machine_serial_no,
                'warranty_status'    => $ticket->warranty_status,
                'workshop_incharge_id'   => $ticket->workshop_incharge_id,
                'workshop_incharge_name' => $ticket->workshopIncharge?->name,
                'workshop_engineer_id'   => $ticket->workshop_engineer_id,
                'workshop_engineer_name' => $ticket->workshopEngineer?->name,
                'workshop_tat_hours'     => $ticket->workshop_tat_hours,
                'workshop_tat_deadline'  => $ticket->workshop_tat_deadline?->format('d M Y, h:i A'),
                'workshop_instructions'  => $ticket->workshop_instructions,
                'urgency'            => $ticket->urgency,
                'status'             => $ticket->status,
                'issue_summary'      => $ticket->issue_summary,
                'issue_description'  => $ticket->issue_description,
                'sla_deadline'       => $ticket->sla_deadline?->format('d M Y, h:i A'),
                'is_sla_breached'    => $ticket->sla_deadline ? $ticket->sla_deadline->isPast() && !in_array($ticket->status, ['resolved', 'closed']) : false,
                'can_claim_expense'  => $ticket->canClaimExpense($user->id),
                'has_active_expense' => $ticket->hasActiveExpenseClaim($user->id),
                'supporting_doc_url' => $ticket->supporting_document ? asset('storage/' . $ticket->supporting_document) : null,
                'resolution_summary' => $ticket->resolution_summary,
                'current_tour'       => (int) ($ticket->current_cycle_no ?: 1),
                'reopen_count'       => (int) $ticket->reopen_count,
                'is_lead_engineer'   => $ticket->isLeadEngineer($user),
                'engineers'          => $ticket->activeTicketEngineers()->with('engineer:id,name,phone_whatsapp')->get()->map(fn ($te) => [
                    'id'   => $te->engineer_id,
                    'name' => $te->engineer?->name,
                    'role' => $te->role,
                ])->values(),
                'tours'              => $ticket->cycles()->get()->map(fn ($c) => [
                    'tour'          => $c->cycle_no,
                    'status'        => $c->status,
                    'opened_at'     => $c->opened_at?->format('d M Y, h:i A'),
                    'resolved_at'   => $c->resolved_at?->format('d M Y, h:i A'),
                    'closed_at'     => $c->closed_at?->format('d M Y, h:i A'),
                    'reopen_reason' => $c->reopen_reason,
                ])->values(),
                'documents'          => $ticket->documents()->get()->map(fn ($d) => [
                    'tour' => $d->cycle?->cycle_no,
                    'name' => $d->name,
                    'url'  => asset('storage/' . $d->path),
                    'uploaded_at' => $d->uploaded_at?->format('d M Y, h:i A'),
                ])->values(),
                'feedbacks'          => $ticket->feedbacks->map(function ($f) {
                    return [
                        'id'             => $f->id,
                        'day_number'     => $f->day_number,
                        'action_taken'   => $f->action_taken,
                        'feedback_text'  => $f->feedback_text,
                        'parts_required' => $f->parts_required,
                        'photo_url'      => $f->photo_evidence ? asset('storage/' . $f->photo_evidence) : null,
                        'submitted_at'   => $f->submitted_at?->format('d M Y, h:i A') ?? $f->created_at?->format('d M Y, h:i A'),
                    ];
                }),
                'part_requests'      => $ticket->partRequests->map(function ($pr) {
                    return [
                        'id'           => $pr->id,
                        'request_no'   => $pr->request_number,
                        'status'       => $pr->status,
                        'status_label' => $pr->status_label,
                        'items'      => $pr->items->map(fn($item) => [
                            'part_name'        => $item->part?->name,
                            'part_number'      => $item->part?->part_number,
                            'qty_requested'    => $item->qty_requested,
                            'from_envelope'    => $item->qty_from_envelope,
                        ]),
                    ];
                }),
                'expense_claims'     => $ticket->expenseClaims->map(function ($c) {
                    return [
                        'id'               => $c->id,
                        'claim_no'         => $c->claim_no ?? "EXP-{$c->id}",
                        'claimed_amount'   => (float) $c->claimed_amount,
                        'suggested_amount' => (float) $c->suggested_amount,
                        'approved_amount'  => (float) $c->approved_amount,
                        'status'           => $c->status,
                        'category'         => $c->category,
                        'from_city'        => $c->from_city,
                        'to_city'          => $c->to_city,
                        'trip_type'        => $c->trip_type,
                        'voucher_url'      => $c->voucher_file ? asset('storage/' . $c->voucher_file) : null,
                        'admin_notes'      => $c->admin_notes,
                        'created_at'       => $c->created_at?->format('d M Y, h:i A'),
                    ];
                }),
            ],
        ]);
    }

    /**
     * 6. Submit Daily Progress Feedback (Day 1, Day 2, etc.)
     */
    public function submitFeedback($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user)) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to this complaint.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action_taken'   => 'required|string|max:255',
            'feedback_text'  => 'required|string|max:2000',
            'parts_required' => 'nullable|string|max:500',
            'eta_completion' => 'nullable|date',
            'photo'          => 'nullable|image|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('feedbacks', 'public');
        }

        // Calendar date based day calculation
        $ticketStartDate = ($ticket->created_at ?? Carbon::now())->copy()->startOfDay();
        $todayDate = Carbon::today();
        $calendarDay = max(1, (int) $ticketStartDate->diffInDays($todayDate) + 1);

        $currentCycleId = $ticket->currentCycle()?->id;
        $existingTodayFeedback = TicketFeedback::where('ticket_id', $ticket->id)
            ->when($currentCycleId, fn ($q) => $q->where('cycle_id', $currentCycleId))
            ->where('submitted_by_id', $user->id)
            ->whereDate('submitted_at', $todayDate)
            ->first();

        if ($existingTodayFeedback) {
            $existingTodayFeedback->update([
                'action_taken'    => $request->action_taken,
                'feedback_text'   => $request->feedback_text,
                'parts_required'  => $request->parts_required,
                'submitted_by_id' => $user->id,
                'day_number'      => $calendarDay,
                'photo_evidence'  => $photoPath ?: $existingTodayFeedback->photo_evidence,
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'user_id'   => $user->id,
                'action'    => 'feedback_updated',
                'notes'     => "Day {$calendarDay} feedback updated via Android App by {$user->name}: {$request->action_taken}.",
            ]);

            return response()->json([
                'success'  => true,
                'message'  => "Day {$calendarDay} feedback updated successfully.",
                'feedback' => $existingTodayFeedback,
            ]);
        }

        $feedback = TicketFeedback::create([
            'ticket_id'       => $ticket->id,
            'engineer_id'     => $user->isEngineer() ? $user->id : ($ticket->assigned_engineer_id ?? $user->id),
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

        if (in_array($ticket->status, ['assigned', 'open', 'pending_feedback'])) {
            $ticket->update(['status' => 'in_progress']);
        }

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'feedback_added',
            'notes'     => "Day {$calendarDay} feedback logged via Android App by {$user->name}: {$request->action_taken}.",
        ]);

        return response()->json([
            'success'  => true,
            'message'  => "Day {$calendarDay} feedback logged successfully.",
            'feedback' => $feedback,
        ]);
    }

    /**
     * 7. Mark Complaint Resolved (Upload FSR / Proof)
     */
    public function resolveTicket($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user)) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to this complaint.'], 403);
        }

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return response()->json(['success' => false, 'message' => "Ticket #{$ticket->ticket_no} is already {$ticket->status}."], 422);
        }

        $validator = Validator::make($request->all(), [
            'resolution_summary'  => 'required|string|max:2000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $docPath = null;
        $docName = null;
        if ($request->hasFile('supporting_document')) {
            $file = $request->file('supporting_document');
            $docPath = $file->store('resolutions', 'public');
            $docName = $file->getClientOriginalName();
        }

        $ticket->update([
            'status'                   => 'resolved',
            'resolved_at'              => Carbon::now(),
            'resolution_summary'       => $request->resolution_summary,
            'supporting_document'      => $docPath ?: $ticket->supporting_document,
            'resolution_document_name' => $docName ?: $ticket->resolution_document_name,
        ]);

        app(\App\Services\TicketCycleService::class)
            ->recordResolution($ticket, $user, $request->resolution_summary, $docPath ?: $ticket->supporting_document, $docName ?: $ticket->resolution_document_name);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'resolved',
            'notes'     => "Complaint marked as Resolved via Android App by {$user->name}. Notes: {$request->resolution_summary}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ticket #{$ticket->ticket_no} marked as Resolved successfully.",
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 7b. Revert Resolved status back to assigned/in_progress (Mark Undone)
     */
    public function markUndone($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user)) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to this complaint.'], 403);
        }

        if ($ticket->status !== 'resolved') {
            return response()->json(['success' => false, 'message' => 'Only resolved complaints can be reopened.'], 422);
        }

        if ($ticket->hasActiveExpenseClaim()) {
            return response()->json([
                'success' => false,
                'message' => 'STRICT AUDIT POLICY: An expense claim has already been submitted against this resolved ticket. It cannot be reopened.',
            ], 422);
        }

        $reason = $request->input('reason', 'Reopened by Field Engineer for further troubleshooting.');

        $ticket->update([
            'status'             => 'assigned',
            'resolved_at'        => null,
            'resolution_summary' => null,
        ]);

        app(\App\Services\TicketCycleService::class)->undoResolution($ticket);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'reopened',
            'notes'     => "Ticket marked undone / reopened by {$user->name}. Reason: {$reason}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ticket #{$ticket->ticket_no} reopened and marked active.",
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 7c. Pause SLA Clock & Put Ticket on Hold (Waiting for Approval)
     */
    public function requestApproval($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user)) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to this complaint.'], 403);
        }

        if ($ticket->status === 'awaiting_approval') {
            return response()->json(['success' => false, 'message' => 'Complaint is already waiting for approval.'], 422);
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json(['success' => false, 'message' => 'Cannot pause SLA on a resolved ticket.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'approval_source' => 'nullable|string|max:150',
            'reason'          => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a reason for requesting approval.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $now = Carbon::now();
        $source = $request->input('approval_source') ?: 'Customer / Branch Manager Authorization';
        $reason = $request->input('reason');

        $ticket->update([
            'status'                   => 'awaiting_approval',
            'approval_requested_at'    => $now,
            'approval_requested_by_id' => $user->id,
            'approval_source'          => $source,
            'approval_request_reason'  => $reason,
            'sla_paused_at'            => $now,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'approval_requested',
            'notes'     => "SLA Clock Paused via Android App by {$user->name}. Waiting for Approval ({$source}). Reason: {$reason}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ticket #{$ticket->ticket_no} paused. Status set to 'Waiting for Approval'.",
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 7d. Dispatch Machine to Central Workshop
     */
    public function sendToWorkshop($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user) && $ticket->original_field_engineer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to this complaint.'], 403);
        }

        if ($ticket->isWorkshopFlow() && !in_array($ticket->status, ['open', 'assigned', 'in_progress', 'awaiting_approval'])) {
            return response()->json(['success' => false, 'message' => 'Machine has already been routed to central workshop.'], 422);
        }

        if ($ticket->status === 'resolved') {
            return response()->json(['success' => false, 'message' => 'Cannot dispatch resolved machine to workshop.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'workshop_location'          => 'required|string|max:100',
            'workshop_dispatch_courier'  => 'nullable|string|max:100',
            'workshop_dispatch_tracking' => 'nullable|string|max:100',
            'notes'                      => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide workshop destination and cargo notes.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $courier = $request->workshop_dispatch_courier ?: 'TCS Cargo / Leopard Courier';
        $tracking = $request->workshop_dispatch_tracking ?: ('TRK-' . date('Ymd') . '-' . rand(100, 999));
        $originalFieldEngineerId = $ticket->original_field_engineer_id ?? $ticket->assigned_engineer_id ?? $user->id;

        $ticket->update([
            'status'                     => 'awaiting_workshop',
            'workshop_location'          => $request->workshop_location,
            'original_field_engineer_id' => $originalFieldEngineerId,
            'assigned_engineer_id'       => $originalFieldEngineerId,
            'workshop_dispatch_courier'  => $courier,
            'workshop_dispatch_tracking' => $tracking,
            'workshop_dispatched_at'     => Carbon::now(),
            'workshop_dispatch_notes'    => $request->notes,
        ]);

        try {
            $whatsapp = app(\App\Services\WhatsAppService::class);
            $whatsapp->sendWorkshopAlert($ticket, $request->workshop_location, null);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WhatsApp workshop alert skipped: ' . $e->getMessage());
        }

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'workshop_transfer',
            'notes'     => "Machine sent to {$request->workshop_location} via {$courier} (Tracking #{$tracking}). Dispatched by {$user->name}. Notes: {$request->notes}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Machine dispatched to {$request->workshop_location} via {$courier} (Tracking #{$tracking}).",
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 8. List Engineer's Tour Expense Claims
     */
    public function getExpenses(Request $request)
    {
        $user = $request->user();

        $claims = ExpenseClaim::where('engineer_id', $user->id)
            ->with('ticket')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success'  => true,
            'expenses' => $claims->map(function ($c) {
                return [
                    'id'               => $c->id,
                    'claim_no'         => $c->claim_no ?? "EXP-{$c->id}",
                    'ticket_no'        => $c->ticket?->ticket_no,
                    'bank_name'        => $c->ticket?->bank_name,
                    'from_city'        => $c->from_city,
                    'to_city'          => $c->to_city,
                    'trip_type'        => $c->trip_type,
                    'category'         => $c->category,
                    'ai_distance_km'   => $c->ai_distance_km,
                    'claimed_amount'   => (float) $c->claimed_amount,
                    'suggested_amount' => (float) $c->suggested_amount,
                    'approved_amount'  => (float) $c->approved_amount,
                    'status'           => $c->status,
                    'voucher_url'      => $c->voucher_file ? asset('storage/' . $c->voucher_file) : null,
                    'admin_notes'      => $c->admin_notes,
                    'created_at'       => $c->created_at?->format('d M Y, h:i A'),
                ];
            }),
            'pagination' => [
                'current_page' => $claims->currentPage(),
                'last_page'    => $claims->lastPage(),
                'total'        => $claims->total(),
            ],
        ]);
    }

    /**
     * 9. Submit Tour Expense with AI Tentative Distance
     */
    public function submitExpense(Request $request, GeminiService $gemini)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'ticket_id'      => 'required|exists:tickets,id',
            'from_city'      => 'nullable|string|max:100',
            'to_city'        => 'nullable|string|max:100',
            'trip_type'      => 'required|in:round_trip,one_way',
            'category'       => 'nullable|in:travel,fuel,accommodation,parts,food,misc',
            'description'    => 'nullable|string|max:500',
            'claimed_amount' => 'required|numeric|min:1',
            'voucher_file'   => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ticket = Ticket::findOrFail($request->ticket_id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user) && $ticket->original_field_engineer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not assigned to this complaint.',
            ], 403);
        }

        if (!$ticket->canClaimExpense($user->id)) {
            if ($ticket->isWorkshopFlow() && !$ticket->workshop_received_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'WORKSHOP EXPENSE POLICY: Machine is in transit to central workshop. Tour expense can only be filed once the unit is physically received at workshop.',
                ], 422);
            }
            if ($ticket->isWorkshopFlow() && $ticket->original_field_engineer_id && (int)$user->id !== (int)$ticket->original_field_engineer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'WORKSHOP EXPENSE POLICY: Only the original field engineer who visited the bank branch can claim travel expenses.',
                ], 422);
            }
            if (!in_array($ticket->status, ['resolved', 'closed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'STRICT AUDIT POLICY: Tour expense CANNOT be claimed before the complaint is marked Resolved.',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'STRICT AUDIT POLICY: This ticket is currently not eligible for tour expense submission.',
            ], 422);
        }

        if ($ticket->hasActiveExpenseClaim($user->id)) {
            return response()->json([
                'success' => false,
                'message' => "STRICT AUDIT POLICY: An expense claim is already pending or approved for Ticket #{$ticket->ticket_no}.",
            ], 422);
        }

        $voucherPath = null;
        if ($request->hasFile('voucher_file')) {
            $voucherPath = $request->file('voucher_file')->store('vouchers', 'public');
        }

        // Origin: Home coordinates / Home address / Base city
        $origin = !empty($user->home_coordinates)
            ? $user->home_coordinates . (!empty($user->home_address) ? " ({$user->home_address})" : '')
            : ($request->from_city ?: ($user->base_city ?: 'Lahore'));

        // Destination: Branch street address
        $destination = !empty(trim($ticket->branch_address ?? ''))
            ? trim($ticket->branch_address) . ', ' . ($ticket->branch_location ?: '')
            : ($request->to_city ?: ($ticket->branch_location ?: 'Pakistan'));

        $aiResult = $gemini->estimateDistance($origin, $destination, $request->trip_type);
        $distance = (float) ($aiResult['distance_km'] ?? 0);
        $estimatedHours = (float) ($aiResult['estimated_hours'] ?? round($distance / 65, 1));

        $suggestedRatePerKm = 25.00;
        $category = $request->category ?: 'travel';
        $suggestedAmount = in_array($category, ['travel', 'fuel']) && $distance > 0
            ? round($distance * $suggestedRatePerKm, 2)
            : (float) $request->claimed_amount;

        // If an existing rejected claim exists for this ticket by this engineer, update it to avoid duplicate rows
        $existingRejected = $ticket->expenseClaims()
            ->where('engineer_id', $user->id)
            ->where('status', 'rejected')
            ->latest()
            ->first();

        if ($existingRejected) {
            $updateData = [
                'from_city'          => $request->from_city ?: ($user->base_city ?: 'Origin'),
                'to_city'            => $request->to_city ?: ($ticket->branch_location ?: 'Destination'),
                'trip_type'          => $request->trip_type,
                'category'           => $category,
                'description'        => $request->description ?: "Mobile claim for Ticket #{$ticket->ticket_no}",
                'ai_distance_km'     => $distance,
                'ai_estimated_hours' => $estimatedHours,
                'suggested_amount'   => $suggestedAmount,
                'claimed_amount'     => $request->claimed_amount,
                'status'             => 'submitted',
                'admin_notes'        => 'Re-submitted by engineer: ' . ($request->description ?: 'Updated claim details and amount'),
                'resubmission_count' => ((int)$existingRejected->resubmission_count) + 1,
            ];
            if ($voucherPath) {
                $updateData['voucher_file'] = $voucherPath;
            }
            $existingRejected->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Tour expense re-submitted successfully for audit.',
                'claim'   => $existingRejected->fresh(),
            ]);
        }

        $claim = ExpenseClaim::create([
            'ticket_id'          => $ticket->id,
            'engineer_id'        => $user->id,
            'from_city'          => $request->from_city ?: ($user->base_city ?: 'Origin'),
            'to_city'            => $request->to_city ?: ($ticket->branch_location ?: 'Destination'),
            'trip_type'          => $request->trip_type,
            'category'           => $category,
            'description'        => $request->description ?: "Mobile claim for Ticket #{$ticket->ticket_no}",
            'ai_distance_km'     => $distance,
            'ai_estimated_hours' => $estimatedHours,
            'suggested_amount'   => $suggestedAmount,
            'claimed_amount'     => $request->claimed_amount,
            'voucher_file'       => $voucherPath,
            'status'             => 'submitted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tour expense submitted successfully for audit.',
            'claim'   => $claim,
        ]);
    }

    /**
     * 9b. Re-submit a Rejected Tour Expense Claim
     */
    public function resubmitExpense($id, Request $request)
    {
        $user = $request->user();
        $claim = ExpenseClaim::findOrFail($id);

        if ($user->isEngineer() && (int)$claim->engineer_id !== (int)$user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only resubmit your own expense claims.',
            ], 403);
        }

        if ($claim->status !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Only rejected expense claims can be re-submitted for audit review.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'claimed_amount' => 'required|numeric|min:1',
            'voucher_file'   => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
            'notes'          => 'nullable|string|max:1000',
            'category'       => 'nullable|in:travel,fuel,accommodation,parts,food,misc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $note = $request->notes ? trim($request->notes) : 'Updated voucher and amount';
        $updateData = [
            'claimed_amount'     => (float) $request->claimed_amount,
            'status'             => 'submitted',
            'admin_notes'        => 'Re-submitted by engineer: ' . $note,
            'resubmission_count' => ((int)$claim->resubmission_count) + 1,
        ];

        if ($request->filled('category')) {
            $updateData['category'] = $request->category;
        }

        if ($request->hasFile('voucher_file')) {
            $updateData['voucher_file'] = $request->file('voucher_file')->store('vouchers', 'public');
        }

        $claim->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Expense claim #{$claim->id} re-submitted successfully for audit.",
            'claim'   => $claim->fresh(),
        ]);
    }

    /**
     * 10. Get Personal Advance Parts Float / Envelope
     */
    public function getEnvelope(Request $request)
    {
        $user = $request->user();

        $inventory = EngineerInventory::where('engineer_id', $user->id)
            ->with('part')
            ->get();

        return response()->json([
            'success'   => true,
            'inventory' => $inventory->map(function ($inv) {
                return [
                    'id'            => $inv->id,
                    'part_id'       => $inv->part_id,
                    'part_name'     => $inv->part?->name,
                    'part_number'   => $inv->part?->part_number,
                    'unit'          => $inv->part?->unit ?? 'PCS',
                    'qty_on_hand'   => (int) $inv->qty_on_hand,
                    'qty_allocated' => (int) $inv->qty_allocated,
                    'qty_used'      => (int) $inv->qty_used,
                ];
            }),
        ]);
    }

    /**
     * 11. Preventive Maintenance Tasks for Engineer
     */
    public function getPmTasks(Request $request)
    {
        $user = $request->user();

        $tasks = PmSchedule::where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where('assigned_engineer_id', $user->id)
                  ->orWhere(fn($q2) => $q2->whereNull('assigned_engineer_id')
                      ->whereHas('machine', fn($m) => $m->where('assigned_engineer_id', $user->id)));
            })
            ->with(['machine.machineModel'])
            ->orderBy('next_due_date')
            ->get();

        $today = Carbon::today();

        $mapped = $tasks->map(function ($s) use ($today) {
            $due = $s->next_due_date;
            $daysUntil = $due ? (int) $today->diffInDays($due, false) : null;
            return [
                'id'                => $s->id,
                'title'             => $s->title ?: 'Preventive Maintenance',
                'bank_name'         => $s->machine?->bank_name,
                'location'          => $s->machine?->location,
                'machine_model'     => $s->machine?->machineModel?->name,
                'machine_type'      => $s->machine?->machineModel?->machine_type,
                'serial_no'         => $s->machine?->serial_number,
                'asset_tag'         => $s->machine?->asset_tag,
                'frequency_days'    => $s->frequency_days,
                'frequency_label'   => $s->frequency_label,
                'last_performed_at' => $s->last_performed_at?->format('d M Y'),
                'next_due_date'     => $due?->format('d M Y'),
                'days_until_due'    => $daysUntil,
                'is_overdue'        => $daysUntil !== null && $daysUntil < 0,
                'is_due_soon'       => $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= 7,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total'    => $mapped->count(),
                'overdue'  => $mapped->where('is_overdue', true)->count(),
                'due_soon' => $mapped->where('is_due_soon', true)->count(),
            ],
            'tasks'   => $mapped,
        ]);
    }

    /**
     * 12. Complete a Preventive Maintenance Task
     */
    public function completePmTask($id, Request $request)
    {
        $user = $request->user();
        $schedule = PmSchedule::with('machine')->findOrFail($id);

        $isResponsible = $schedule->assigned_engineer_id === $user->id
            || ($schedule->assigned_engineer_id === null && $schedule->machine?->assigned_engineer_id === $user->id);

        if ($user->isEngineer() && !$isResponsible) {
            return response()->json(['success' => false, 'message' => 'This PM task is not assigned to you.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'notes'    => 'nullable|string|max:2000',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:15360',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $now = Carbon::now();
        $isOverdue = $schedule->next_due_date && $schedule->next_due_date->lt($now->copy()->startOfDay());

        $docPath = null;
        $docName = null;
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $docName = $file->getClientOriginalName();
            $docPath = $file->store('pm-documents', 'public');
        }

        PmRecord::create([
            'pm_schedule_id'         => $schedule->id,
            'pm_machine_id'          => $schedule->pm_machine_id,
            'performed_by_id'        => $user->id,
            'performed_at'           => $now,
            'due_date'               => $schedule->next_due_date?->toDateString() ?? $now->toDateString(),
            'status'                 => 'completed',
            'notes'                  => $request->input('notes'),
            'document_path'          => $docPath,
            'document_original_name' => $docName,
            'is_overdue'             => $isOverdue,
        ]);

        $nextDue = $now->copy()->addDays($schedule->frequency_days ?: 30);
        $schedule->update([
            'last_performed_at' => $now->toDateString(),
            'next_due_date'     => $nextDue->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance marked complete. Next due: ' . $nextDue->format('d M Y'),
        ]);
    }

    /**
     * 13. List Engineer's Spare Part Requests
     */
    public function getPartRequests(Request $request)
    {
        $user = $request->user();

        $requests = PartRequest::where('engineer_id', $user->id)
            ->with(['ticket', 'items.part', 'machineModel'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success'  => true,
            'requests' => $requests->map(function ($pr) {
                return [
                    'id'                => $pr->id,
                    'request_no'        => $pr->request_number,
                    'ticket_id'         => $pr->ticket_id,
                    'ticket_no'         => $pr->ticket?->ticket_no,
                    'bank_name'         => $pr->ticket?->bank_name,
                    'branch_name'       => $pr->ticket?->branch_name,
                    'machine_model'     => $pr->machineModel?->name,
                    'machine_serial_no' => $pr->machine_serial_no,
                    'fault_description' => $pr->fault_description,
                    'status'            => $pr->status,
                    'status_label'      => $pr->status_label,
                    'rejection_reason'  => $pr->rejection_reason,
                    'courier'           => $pr->dispatch_courier,
                    'tracking_no'       => $pr->dispatch_tracking_number,
                    'dispatched_at'     => $pr->dispatched_at?->format('d M Y, h:i A'),
                    'created_at'        => $pr->created_at?->format('d M Y, h:i A'),
                    'items'             => $pr->items->map(fn($item) => [
                        'part_name'      => $item->part?->name,
                        'part_number'    => $item->part?->part_number,
                        'qty_requested'  => (int) $item->qty_requested,
                        'qty_approved'   => (int) $item->qty_approved,
                        'qty_dispatched' => (int) $item->qty_dispatched,
                    ])->values(),
                ];
            }),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    /**
     * 14. Parts Catalog Search (for building a part request)
     */
    public function getPartsCatalog(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $parts = Part::where('is_active', true)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('part_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'parts'   => $parts->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'part_number' => $p->part_number,
                'unit'        => $p->unit ?? 'PCS',
            ]),
        ]);
    }

    /**
     * 14b. List Active Machine Models
     */
    public function getMachineModels()
    {
        $models = MachineModel::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'machine_type', 'manufacturer']);

        return response()->json([
            'success' => true,
            'models'  => $models->map(fn($m) => [
                'id'           => $m->id,
                'name'         => $m->name,
                'machine_type' => $m->machine_type,
                'manufacturer' => $m->manufacturer,
                'brand'        => $m->manufacturer,
            ]),
        ]);
    }

    /**
     * 14c. List Parts Mapped to a Specific Machine Model with Engineer Envelope Float
     */
    public function getModelParts($modelId, Request $request)
    {
        $user = $request->user();
        $model = MachineModel::findOrFail($modelId);
        $search = trim((string) $request->input('search', ''));

        $query = $model->parts()
            ->where('parts.is_active', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('parts.name', 'like', "%{$search}%")
                  ->orWhere('parts.part_number', 'like', "%{$search}%");
            });
        }

        $parts = $query->orderBy('parts.name')->get(['parts.id', 'parts.part_number', 'parts.name', 'parts.unit']);

        // Engineer's on-hand float envelope
        $envelope = EngineerInventory::where('engineer_id', $user->id)
            ->where('qty_on_hand', '>', 0)
            ->pluck('qty_on_hand', 'part_id');

        return response()->json([
            'success' => true,
            'parts'   => $parts->map(function ($p) use ($envelope) {
                return [
                    'id'          => $p->id,
                    'part_number' => $p->part_number,
                    'name'        => $p->name,
                    'unit'        => $p->unit ?? 'PCS',
                    'on_hand'     => (int) ($envelope[$p->id] ?? 0),
                ];
            }),
        ]);
    }

    /**
     * 15. Submit Spare Part Request against a Ticket
     */
    public function submitPartRequest(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'ticket_id'         => 'required|exists:tickets,id',
            'machine_model_id'  => 'nullable|exists:machine_models,id',
            'machine_serial_no' => 'nullable|string|max:100',
            'fault_description' => 'required|string|max:2000',
            'items'             => 'required|array|min:1',
            'items.*.part_id'   => 'required|exists:parts,id',
            'items.*.qty'       => 'required|integer|min:1',
            'items.*.note'      => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one part and describe the fault.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ticket = Ticket::findOrFail($request->ticket_id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user) && $ticket->original_field_engineer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to this complaint.'], 403);
        }

        if ($ticket->status === 'awaiting_workshop') {
            return response()->json([
                'success' => false,
                'message' => 'Machine is in transit to central workshop. Part requests are locked.',
            ], 422);
        }

        $pr = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $ticket) {
            $pr = PartRequest::create([
                'request_number'    => PartRequest::generateRequestNumber(),
                'ticket_id'         => $ticket->id,
                'engineer_id'       => $user->id,
                'machine_model_id'  => $request->machine_model_id ?: null,
                'machine_serial_no' => $request->machine_serial_no ?: $ticket->machine_serial_no,
                'fault_description' => $request->fault_description,
                'status'            => 'pending_stock_check',
            ]);

            foreach ($request->items as $item) {
                $qtyReq = (int) $item['qty'];
                $fromEnvelope = !empty($item['from_envelope']) ? min($qtyReq, (int) $item['from_envelope']) : 0;
                PartRequestItem::create([
                    'part_request_id'   => $pr->id,
                    'part_id'           => (int) $item['part_id'],
                    'qty_requested'     => $qtyReq,
                    'qty_from_envelope' => $fromEnvelope,
                    'note'              => $item['note'] ?? null,
                ]);
            }

            return $pr;
        });

        try {
            \App\Services\NotificationService::notifyPartRequestCreated($pr);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Part request notification failed: ' . $e->getMessage());
        }

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'part_requested',
            'notes'     => "Part request {$pr->request_number} raised via Android App by {$user->name}.",
        ]);

        return response()->json([
            'success'    => true,
            'message'    => "Part request {$pr->request_number} submitted for stock verification.",
            'request_no' => $pr->request_number,
        ]);
    }

    /**
     * 16. Get Engineer Notifications
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();

        $notifications = \App\Models\AppNotification::forUser($user)
            ->latest()
            ->take(30)
            ->get();

        $unreadCount = \App\Models\AppNotification::forUser($user)->unread()->count();

        return response()->json([
            'success'      => true,
            'unread_count' => $unreadCount,
            'notifications'=> $notifications->map(function ($n) {
                return [
                    'id'         => $n->id,
                    'type'       => $n->type,
                    'title'      => $n->title,
                    'message'    => $n->message,
                    'link'       => $n->link,
                    'icon'       => $n->icon,
                    'color'      => $n->color,
                    'is_read'    => (bool) $n->is_read,
                    'time_ago'   => $n->created_at?->diffForHumans(null, true, true),
                    'created_at' => $n->created_at?->toIso8601String(),
                    'data'       => $n->data,
                ];
            }),
        ]);
    }

    /**
     * 17. Mark Notification as Read
     */
    public function markNotificationRead($id, Request $request)
    {
        $user = $request->user();

        $notif = \App\Models\AppNotification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereNull('user_id');
        })->findOrFail($id);

        $notif->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * 7e. Update Machine / Hardware Details on Ticket
     */
    public function updateMachineDetails($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->isEngineer() && !$ticket->hasEngineer($user) && $ticket->original_field_engineer_id !== $user->id && $ticket->workshop_engineer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update machine details for this ticket.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'machine_type'      => 'nullable|string|max:100',
            'machine_model'     => 'nullable|string|max:100',
            'machine_serial_no' => 'nullable|string|max:100',
            'warranty_status'   => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $changes = [];
        if ($request->has('machine_type') && $request->machine_type !== $ticket->machine_type) {
            $changes[] = "Type: '{$ticket->machine_type}' -> '{$request->machine_type}'";
            $ticket->machine_type = $request->machine_type;
        }
        if ($request->has('machine_model') && $request->machine_model !== $ticket->machine_model) {
            $changes[] = "Model: '{$ticket->machine_model}' -> '{$request->machine_model}'";
            $ticket->machine_model = $request->machine_model;
        }
        if ($request->has('machine_serial_no') && $request->machine_serial_no !== $ticket->machine_serial_no) {
            $changes[] = "Serial: '{$ticket->machine_serial_no}' -> '{$request->machine_serial_no}'";
            $ticket->machine_serial_no = $request->machine_serial_no;
        }
        if ($request->has('warranty_status') && $request->warranty_status !== $ticket->warranty_status) {
            $changes[] = "Warranty: '{$ticket->warranty_status}' -> '{$request->warranty_status}'";
            $ticket->warranty_status = $request->warranty_status;
        }

        if (!empty($changes)) {
            $ticket->save();

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'user_id'   => $user->id,
                'action'    => 'machine_details_updated',
                'notes'     => "Hardware specs updated by {$user->name}: " . implode(', ', $changes),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Machine details updated successfully.',
            'ticket'  => [
                'machine_type'      => $ticket->machine_type,
                'machine_model'     => $ticket->machine_model,
                'machine_serial_no' => $ticket->machine_serial_no,
                'warranty_status'   => $ticket->warranty_status,
            ],
        ]);
    }

    /**
     * 19. Central Workshop Hub - List Tickets & Overview (Mobile)
     */
    public function getWorkshopTickets(Request $request)
    {
        $user = $request->user();
        $tab = $request->input('tab', 'all');
        $search = $request->input('search');

        // Base query for tickets involved in central workshop
        $query = Ticket::where(function ($q) {
            $q->whereIn('status', ['awaiting_workshop', 'in_workshop_repair', 'workshop_repaired', 'return_transit'])
              ->orWhere(function ($sq) {
                  $sq->whereIn('status', ['resolved', 'closed'])
                     ->whereNotNull('workshop_received_at');
              });
        });

        // Filter by tab
        if ($tab === 'awaiting_intake') {
            $query->where('status', 'awaiting_workshop');
        } elseif ($tab === 'in_repair') {
            $query->where('status', 'in_workshop_repair');
        } elseif ($tab === 'repaired') {
            $query->where('status', 'workshop_repaired');
        } elseif ($tab === 'return_transit') {
            $query->where('status', 'return_transit');
        } elseif ($tab === 'completed') {
            $query->whereIn('status', ['resolved', 'closed'])->whereNotNull('workshop_received_at');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('branch_name', 'like', "%{$search}%")
                  ->orWhere('machine_serial_no', 'like', "%{$search}%")
                  ->orWhere('workshop_dispatch_tracking', 'like', "%{$search}%")
                  ->orWhere('return_tracking_number', 'like', "%{$search}%");
            });
        }

        $tickets = $query->with(['workshopIncharge', 'workshopEngineer', 'activeTicketEngineers.engineer'])
            ->latest('updated_at')
            ->get();

        // Count metrics for 5 tabs
        $awaitingIntakeCount = Ticket::where('status', 'awaiting_workshop')->count();
        $inRepairCount       = Ticket::where('status', 'in_workshop_repair')->count();
        $repairedCount       = Ticket::where('status', 'workshop_repaired')->count();
        $returnTransitCount  = Ticket::where('status', 'return_transit')->count();
        $completedCount      = Ticket::whereIn('status', ['resolved', 'closed'])->whereNotNull('workshop_received_at')->count();

        // Available bench engineers & incharges for modals
        $engineers = User::where('role', 'engineer')
            ->select('id', 'name', 'base_city', 'current_city')
            ->orderBy('name')
            ->get();

        $incharges = User::whereIn('role', ['workshop_incharge', 'admin', 'coordinator'])
            ->select('id', 'name', 'role', 'base_city')
            ->orderByRaw("FIELD(role, 'workshop_incharge', 'admin', 'coordinator')")
            ->orderBy('name')
            ->get();

        $formattedTickets = $tickets->map(function ($t) {
            return [
                'id'                          => $t->id,
                'ticket_no'                   => $t->ticket_no,
                'customer_ref_no'             => $t->customer_ref_no,
                'bank_name'                   => $t->bank_name,
                'branch_name'                 => $t->branch_name,
                'branch_location'             => $t->branch_location,
                'machine_type'                => $t->machine_type,
                'machine_model'               => $t->machine_model,
                'machine_serial_no'           => $t->machine_serial_no,
                'warranty_status'             => $t->warranty_status,
                'status'                      => $t->status,
                'urgency'                     => $t->urgency,
                'issue_summary'               => $t->issue_summary,
                'workshop_location'           => $t->workshop_location ?? 'Lahore Central Workshop',
                'workshop_dispatched_at'      => $t->workshop_dispatched_at?->format('d M Y, h:i A'),
                'workshop_dispatch_courier'   => $t->workshop_dispatch_courier,
                'workshop_dispatch_tracking'  => $t->workshop_dispatch_tracking,
                'workshop_dispatch_notes'     => $t->workshop_dispatch_notes,
                'workshop_received_at'        => $t->workshop_received_at?->format('d M Y, h:i A'),
                'workshop_incharge_id'        => $t->workshop_incharge_id,
                'workshop_incharge_name'      => $t->workshopIncharge?->name,
                'workshop_engineer_id'        => $t->workshop_engineer_id,
                'workshop_engineer_name'      => $t->workshopEngineer?->name,
                'workshop_tat_hours'          => $t->workshop_tat_hours,
                'workshop_tat_deadline'       => $t->workshop_tat_deadline?->format('d M Y, h:i A'),
                'workshop_tat_remaining_hours'=> $t->workshop_tat_deadline ? round(Carbon::now()->diffInHours($t->workshop_tat_deadline, false), 1) : null,
                'workshop_instructions'       => $t->workshop_instructions,
                'workshop_intake_remarks'     => $t->workshop_intake_remarks,
                'workshop_repaired_at'        => $t->workshop_repaired_at?->format('d M Y, h:i A'),
                'workshop_repair_summary'     => $t->workshop_repair_summary,
                'return_dispatched_at'        => $t->return_dispatched_at?->format('d M Y, h:i A'),
                'return_courier'              => $t->return_courier,
                'return_tracking_number'      => $t->return_tracking_number,
                'return_dispatch_notes'       => $t->return_dispatch_notes,
                'bank_received_at'            => $t->bank_received_at?->format('d M Y, h:i A'),
                'inbound_transit_duration'    => $t->inbound_transit_duration_text,
                'workshop_repair_duration'    => $t->workshop_repair_duration_text,
                'return_transit_duration'     => $t->return_transit_duration_text,
                'total_resolution_duration'   => $t->total_resolution_duration_text,
            ];
        });

        return response()->json([
            'success' => true,
            'metrics' => [
                'awaiting_intake' => $awaitingIntakeCount,
                'in_repair'       => $inRepairCount,
                'repaired'        => $repairedCount,
                'return_transit'  => $returnTransitCount,
                'completed'       => $completedCount,
                'total'           => $awaitingIntakeCount + $inRepairCount + $repairedCount + $returnTransitCount + $completedCount,
            ],
            'tickets'    => $formattedTickets,
            'engineers'  => $engineers,
            'incharges'  => $incharges,
            'permissions'=> [
                'can_receive' => $user->isSuperior() || $user->isWorkshopIncharge(),
                'can_realign' => $user->isSuperior() || $user->isWorkshopIncharge(),
                'can_resolve' => true,
                'can_dispatch'=> $user->isSuperior() || $user->isWorkshopIncharge(),
                'can_close'   => $user->isSuperior(),
            ],
        ]);
    }

    /**
     * 20. Workshop Physical Intake & Handover to Incharge
     */
    public function workshopReceive($id, Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperior() && !$user->isWorkshopIncharge()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only staff/management can confirm physical workshop intake.'], 403);
        }

        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'workshop_incharge_id'    => 'nullable|exists:users,id',
            'workshop_location'       => 'nullable|string|max:150',
            'workshop_intake_remarks' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Physical inspection remarks are required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $incharge = null;
        if ($request->filled('workshop_incharge_id')) {
            $incharge = User::find($request->workshop_incharge_id);
        }
        if (!$incharge) {
            $incharge = User::where('role', 'workshop_incharge')->first()
                ?? User::where('name', 'like', '%ikram%')->first()
                ?? $user;
        }

        $location = $request->workshop_location ?: ($ticket->workshop_location ?: 'Lahore Central Workshop');
        $receivedAt = Carbon::now();
        $transitDuration = $ticket->inbound_transit_duration_text;

        $ticket->update([
            'status'                  => 'in_workshop_repair',
            'workshop_location'       => $location,
            'workshop_received_at'    => $receivedAt,
            'workshop_received_by_id' => $user->id,
            'workshop_incharge_id'    => $incharge->id,
            'assigned_engineer_id'    => $incharge->id,
            'workshop_intake_remarks' => $request->workshop_intake_remarks,
        ]);

        NotificationService::notifyWorkshopReceived($ticket, $incharge);

        try {
            $whatsapp = app(WhatsAppService::class);
            $whatsapp->sendWorkshopAlert($ticket, $location, $incharge);
        } catch (\Throwable $e) {}

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'workshop_received',
            'notes'     => "Machine physically received at {$location} by {$user->name}. Handover complete: Assigned to Workshop Incharge {$incharge->name}. (Inbound Cargo Transit Time: {$transitDuration}). Inspection Remarks: {$request->workshop_intake_remarks}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Machine received at workshop and handed over to Workshop Incharge {$incharge->name}.",
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 21. Workshop Incharge Re-aligns Machine to Bench Engineer with TAT
     */
    public function workshopRealign($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if (!$user->isSuperior() && !$user->isWorkshopIncharge() && $user->id !== $ticket->workshop_incharge_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only Workshop Incharge or management can re-align bench tasks.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'workshop_engineer_id'  => 'required|exists:users,id',
            'workshop_tat_hours'    => 'required|integer|min:1|max:720',
            'workshop_instructions' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please select bench engineer and specify TAT hours.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $engineer = User::findOrFail($request->workshop_engineer_id);
        $tatHours = (int) $request->workshop_tat_hours;
        $tatDeadline = Carbon::now()->addHours($tatHours);

        $ticket->update([
            'workshop_engineer_id'   => $engineer->id,
            'assigned_engineer_id'   => $engineer->id,
            'workshop_tat_hours'     => $tatHours,
            'workshop_tat_deadline'  => $tatDeadline,
            'workshop_instructions'  => $request->workshop_instructions,
            'workshop_realigned_at'  => Carbon::now(),
        ]);

        if (!$ticket->activeTicketEngineers()->where('engineer_id', $engineer->id)->exists()) {
            $ticket->ticketEngineers()->create([
                'engineer_id' => $engineer->id,
                'role'        => 'support',
                'assigned_at' => Carbon::now(),
            ]);
        }

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'workshop_realigned',
            'notes'     => "Realigned to Bench Engineer {$engineer->name} with TAT of {$tatHours} Hours (Deadline: {$tatDeadline->format('d M Y, h:i A')}). Instructions: {$request->workshop_instructions}",
        ]);

        NotificationService::notifyWorkshopRealigned($ticket, $engineer, $tatHours, $request->workshop_instructions);

        try {
            $whatsapp = app(WhatsAppService::class);
            $whatsapp->sendTicketAssignmentAlert($ticket, $engineer);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => "Machine assigned to Bench Engineer {$engineer->name} with TAT of {$tatHours} hours.",
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 22. Workshop Bench Repair Completed
     */
    public function workshopResolve($id, Request $request)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if (!$user->isSuperior() && !$user->isWorkshopIncharge() && $user->id !== $ticket->assigned_engineer_id && $user->id !== $ticket->workshop_engineer_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only assigned bench tech or incharge can complete repair.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'workshop_repair_summary' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Repair summary and testing notes are required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $now = Carbon::now();
        $ticket->update([
            'status'                  => 'workshop_repaired',
            'workshop_repaired_at'    => $now,
            'workshop_repair_summary' => $request->workshop_repair_summary,
            'resolution_summary'      => $request->workshop_repair_summary,
        ]);

        NotificationService::notifyWorkshopRepaired($ticket, $user);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'workshop_repaired',
            'notes'     => "Bench repair completed by {$user->name}. Unit QA tested OK. (Bench Repair Duration: {$ticket->workshop_repair_duration_text}). Summary: {$request->workshop_repair_summary}",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bench repair marked complete. Machine tested OK and ready for dispatch back to bank.',
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 23. Dispatch Repaired Machine Back to Bank Branch
     */
    public function workshopReturnDispatch($id, Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperior() && !$user->isWorkshopIncharge()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only staff/incharge can dispatch returned cargo.'], 403);
        }

        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'return_courier'         => 'required|string|max:150',
            'return_tracking_number' => 'required|string|max:150',
            'return_dispatch_notes'  => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Courier service and tracking/bilty number are required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $now = Carbon::now();
        $ticket->update([
            'status'                 => 'return_transit',
            'return_courier'         => $request->return_courier,
            'return_tracking_number' => $request->return_tracking_number,
            'return_dispatched_at'   => $now,
            'return_dispatch_notes'  => $request->return_dispatch_notes,
        ]);

        NotificationService::notifyWorkshopReturnDispatched($ticket);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'workshop_return_dispatched',
            'notes'     => "Repaired machine dispatched back to bank branch via {$request->return_courier} (Tracking #{$request->return_tracking_number}) by {$user->name}. Notes: {$request->return_dispatch_notes}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Machine dispatched back to bank via {$request->return_courier} (Tracking #{$request->return_tracking_number}).",
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 24. Confirm Delivery at Bank Branch & Final Close
     */
    public function workshopConfirmDelivery($id, Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperior()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only office staff or management can confirm final bank delivery.'], 403);
        }

        $ticket = Ticket::findOrFail($id);
        $now = Carbon::now();

        $ticket->update([
            'status'           => 'closed',
            'bank_received_at' => $now,
            'resolved_at'      => $ticket->resolved_at ?? $now,
            'closed_at'        => $now,
        ]);

        $activeCycle = $ticket->activeCycle();
        if ($activeCycle) {
            $activeCycle->update([
                'status'      => 'closed',
                'resolved_at' => $activeCycle->resolved_at ?? $now,
                'closed_at'   => $now,
            ]);
        }

        NotificationService::notifyTicketClosed($ticket, $user);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'workshop_bank_delivery_confirmed',
            'notes'     => "Bank receipt confirmed by {$user->name}. Ticket permanently closed. Total end-to-end resolution turnaround: {$ticket->total_resolution_duration_text}.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Machine delivery at bank confirmed! Ticket closed successfully.',
            'ticket'  => $ticket,
        ]);
    }

    /**
     * 18. Register or update engineer's device FCM Token for mobile push notifications
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'FCM device token registered successfully.',
        ]);
    }
}

