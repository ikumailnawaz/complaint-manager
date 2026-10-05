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
            $q->where('assigned_engineer_id', $user->id)
              ->orWhere('original_field_engineer_id', $user->id);
        })->whereNotIn('status', ['resolved', 'closed'])->count();

        $resolvedTicketsCount = Ticket::where(function ($q) use ($user) {
            $q->where('assigned_engineer_id', $user->id)
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
            $q->where('assigned_engineer_id', $user->id)
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
        }, 'partRequests.items.part'])->findOrFail($id);

        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id && $ticket->original_field_engineer_id !== $user->id) {
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
                'urgency'            => $ticket->urgency,
                'status'             => $ticket->status,
                'issue_summary'      => $ticket->issue_summary,
                'issue_description'  => $ticket->issue_description,
                'sla_deadline'       => $ticket->sla_deadline?->format('d M Y, h:i A'),
                'is_sla_breached'    => $ticket->sla_deadline ? $ticket->sla_deadline->isPast() && !in_array($ticket->status, ['resolved', 'closed']) : false,
                'can_claim_expense'  => $ticket->canClaimExpense($user->id),
                'has_active_expense' => $ticket->hasActiveExpenseClaim(),
                'supporting_doc_url' => $ticket->supporting_document ? asset('storage/' . $ticket->supporting_document) : null,
                'resolution_summary' => $ticket->resolution_summary,
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

        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
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

        $existingTodayFeedback = TicketFeedback::where('ticket_id', $ticket->id)
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

        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to this complaint.'], 403);
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

        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
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

        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
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

        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id && $ticket->original_field_engineer_id !== $user->id) {
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

        if ($ticket->hasActiveExpenseClaim()) {
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
            ->get(['id', 'name', 'machine_type', 'brand']);

        return response()->json([
            'success' => true,
            'models'  => $models->map(fn($m) => [
                'id'           => $m->id,
                'name'         => $m->name,
                'machine_type' => $m->machine_type,
                'brand'        => $m->brand,
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

        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id && $ticket->original_field_engineer_id !== $user->id) {
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
}

