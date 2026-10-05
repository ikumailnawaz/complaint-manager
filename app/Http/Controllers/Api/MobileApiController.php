<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\EngineerInventory;
use App\Models\ExpenseClaim;
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
                        'id'         => $pr->id,
                        'request_no' => $pr->request_no,
                        'status'     => $pr->status,
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
            ->with(['machine'])
            ->orderBy('next_due_date')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'tasks'   => $tasks->map(function ($s) {
                return [
                    'id'            => $s->id,
                    'machine_name'  => $s->machine?->bank_name . ' - ' . $s->machine?->model_name,
                    'serial_no'     => $s->machine?->serial_no,
                    'frequency'     => $s->frequency,
                    'next_due_date' => $s->next_due_date?->format('d M Y'),
                    'is_overdue'    => $s->next_due_date?->isPast() ?? false,
                ];
            }),
        ]);
    }
}
