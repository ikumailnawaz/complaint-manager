<?php

namespace App\Http\Controllers;

use App\Models\ExpenseClaim;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\GeminiService;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->check() && auth()->user()->isOfficeStaff()) {
                abort(403, 'Office staff does not have access to financial expenses.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $settlementStatus = $request->input('settlement_status'); // 'paid', 'unpaid', ''
        $claimStatus = $request->input('claim_status', $request->input('status')); // 'submitted', 'approved', 'rejected', 'not_submitted', ''
        $engineerId = $request->input('engineer_id');
        $search = $request->input('search');
        $fromDate = $request->input('from_date', $request->input('date_from'));
        $toDate = $request->input('to_date', $request->input('date_to'));
        $rawPerPage = $request->input('per_page', '25');
        $perPage = ($rawPerPage === 'all') ? 1000 : (in_array((int)$rawPerPage, [10, 25, 50, 100]) ? (int)$rawPerPage : 25);

        $isNotSubmitted = ($claimStatus === 'not_submitted');
        $notSubmittedTickets = collect();

        if ($isNotSubmitted) {
            $notSubmittedQuery = Ticket::with(['assignedEngineer'])
                ->doesntHave('expenseClaims')
                ->when($user->isEngineer(), function ($q) use ($user) {
                    $q->forEngineer($user->id);
                })
                ->when($engineerId, function ($q, $eid) {
                    $q->where('assigned_engineer_id', $eid);
                })
                ->when($fromDate, function ($q, $from) {
                    $q->whereDate('created_at', '>=', $from);
                })
                ->when($toDate, function ($q, $to) {
                    $q->whereDate('created_at', '<=', $to);
                })
                ->when($search, function ($q, $s) {
                    $q->where(function ($sub) use ($s) {
                        $sub->where('ticket_no', 'like', "%{$s}%")
                            ->orWhere('bank_name', 'like', "%{$s}%")
                            ->orWhere('branch_name', 'like', "%{$s}%")
                            ->orWhere('branch_location', 'like', "%{$s}%")
                            ->orWhereHas('assignedEngineer', function ($eq) use ($s) {
                                $eq->where('name', 'like', "%{$s}%");
                            });
                    });
                })
                ->latest();

            // Unsubmitted tickets can never be paid
            if ($settlementStatus === 'paid') {
                $notSubmittedQuery->whereRaw('1 = 0');
            }

            $notSubmittedTickets = $notSubmittedQuery->paginate($perPage)->withQueryString();
            $claims = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        } else {
            $query = ExpenseClaim::with(['ticket', 'engineer', 'paidBy'])->latest();

            if ($user->isEngineer()) {
                $query->where('engineer_id', $user->id);
            }

            // Settlement Filter (paid vs unpaid)
            if ($settlementStatus === 'paid') {
                $query->where('status', 'paid');
            } elseif ($settlementStatus === 'unpaid') {
                $query->where('status', '!=', 'paid');
            }

            // Claim Lifecycle Status Filter (submitted, approved, rejected, paid)
            if ($claimStatus && $claimStatus !== 'all') {
                $query->where('status', $claimStatus);
            }

            // Engineer Filter
            if ($engineerId) {
                $query->where('engineer_id', $engineerId);
            }

            // Date Range Filters
            if ($fromDate) {
                $query->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('created_at', '<=', $toDate);
            }

            // Search Filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('from_city', 'like', "%{$search}%")
                      ->orWhere('to_city', 'like', "%{$search}%")
                      ->orWhere('payment_reference', 'like', "%{$search}%")
                      ->orWhereHas('ticket', function ($tq) use ($search) {
                          $tq->where('ticket_no', 'like', "%{$search}%")
                             ->orWhere('bank_name', 'like', "%{$search}%")
                             ->orWhere('branch_name', 'like', "%{$search}%")
                             ->orWhere('branch_location', 'like', "%{$search}%");
                      })
                      ->orWhereHas('engineer', function ($eq) use ($search) {
                          $eq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $claims = $query->paginate($perPage)->withQueryString();
        }

        // Summary metrics
        $baseClaimsQuery = ExpenseClaim::when($user->isEngineer(), fn($q) => $q->where('engineer_id', $user->id));
        $notSubmittedCount = Ticket::doesntHave('expenseClaims')
            ->when($user->isEngineer(), fn($q) => $q->forEngineer($user->id))
            ->count();

        $stats = [
            'total_claims' => (clone $baseClaimsQuery)->count(),
            'pending' => (clone $baseClaimsQuery)->where('status', 'submitted')->count(),
            'approved' => (clone $baseClaimsQuery)->where('status', 'approved')->count(),
            'paid' => (clone $baseClaimsQuery)->where('status', 'paid')->count(),
            'unpaid' => (clone $baseClaimsQuery)->where('status', '!=', 'paid')->count(),
            'not_submitted' => $notSubmittedCount,
            'total_pending_amount' => (clone $baseClaimsQuery)->where('status', 'submitted')->sum('claimed_amount'),
            'total_paid_amount' => (clone $baseClaimsQuery)->where('status', 'paid')->sum('claimed_amount'),
            'total_unpaid_amount' => (clone $baseClaimsQuery)->where('status', '!=', 'paid')->sum('claimed_amount'),
        ];

        $engineers = \App\Models\User::where('role', 'engineer')->orderBy('name')->get();

        $selectedTicket = null;
        if ($request->filled('ticket_id')) {
            $selectedTicket = Ticket::with('assignedEngineer')->find($request->ticket_id);
        }

        $claimableTickets = Ticket::when($user->isEngineer(), function ($q) use ($user) {
                $q->where(function ($sq) use ($user) {
                    $sq->forEngineer($user->id)
                       ->orWhere('original_field_engineer_id', $user->id);
                });
            })
            ->whereIn('status', ['in_progress', 'resolved', 'closed', 'in_workshop_repair', 'workshop_repaired', 'return_transit'])
            ->latest()
            ->get()
            ->filter(fn($t) => $t->canClaimExpense($user->id));

        return view('expenses.index', compact(
            'claims',
            'stats',
            'engineers',
            'selectedTicket',
            'claimableTickets',
            'isNotSubmitted',
            'notSubmittedTickets'
        ));
    }

    public function create(Request $request)
    {
        $ticketId = $request->query('ticket_id');
        $ticket = null;

        if ($ticketId) {
            $ticket = Ticket::findOrFail($ticketId);
        }

        // Resolved or assigned tickets eligible for expense
        $user = Auth::user();
        $tickets = Ticket::when($user->isEngineer(), function ($q) use ($user) {
            $q->where(function ($sq) use ($user) {
                $sq->forEngineer($user->id)
                   ->orWhere('original_field_engineer_id', $user->id);
            });
        })
        ->whereIn('status', ['in_progress', 'resolved', 'closed', 'in_workshop_repair', 'workshop_repaired', 'return_transit'])
        ->latest()
        ->get()
        ->filter(fn($t) => $t->canClaimExpense($user->id));

        return view('expenses.create', compact('ticket', 'tickets'));
    }

    public function store(Request $request, GeminiService $gemini)
    {
        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'from_city' => 'required|string|max:100',
            'to_city' => 'required|string|max:100',
            'trip_type' => 'required|in:round_trip,one_way',
            'category' => 'nullable|in:travel,fuel,accommodation,parts,food,misc',
            'description' => 'nullable|string|max:500',
            'claimed_amount' => 'required|numeric|min:1',
            'voucher_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
            'uploaded_voucher_path' => 'nullable|string',
            'uploaded_voucher_name' => 'nullable|string',
            'supporting_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
        ]);

        $ticket = Ticket::findOrFail($validated['ticket_id']);

        // Engineers can only claim on tickets they are aligned to (lead or support).
        if (Auth::user()->isEngineer() && !$ticket->hasEngineer(Auth::user()) && (int) $ticket->original_field_engineer_id !== (int) Auth::id()) {
            abort(403, 'You are not aligned to this ticket, so you cannot claim expense against it.');
        }

        // STRICT CLAIM AUDIT POLICY: Once this engineer has an active claim for the current tour, another cannot be created until it is rejected.
        // Each aligned engineer claims separately; a reopened ticket starts a new tour with a fresh claim allowance.
        if ($ticket->hasActiveExpenseClaim(Auth::id())) {
            return back()->with('error', "STRICT CLAIM POLICY: You already have an active expense claim pending or approved for Ticket #{$ticket->ticket_no} (Tour {$ticket->current_cycle_no}). A new claim cannot be submitted unless Operations Management rejects the existing claim.");
        }

        // Workshop Policy: Check eligibility
        if (!$ticket->canClaimExpense(Auth::id())) {
            if ($ticket->isWorkshopFlow() && !$ticket->workshop_received_at) {
                return back()->with('error', "WORKSHOP EXPENSE POLICY: Machine is in transit to central workshop. Tour expense can only be filed once the unit is physically received at the workshop.");
            }
            if ($ticket->isWorkshopFlow() && $ticket->original_field_engineer_id && Auth::id() !== (int)$ticket->original_field_engineer_id) {
                return back()->with('error', "WORKSHOP EXPENSE POLICY: Only the original field engineer who visited the bank branch can claim travel expenses. Workshop bench technicians cannot claim tour expenses.");
            }
            if (!in_array($ticket->status, ['resolved', 'closed'])) {
                return back()->with('error', "POLICY: Tour expense cannot be claimed before the complaint is marked complete/done.");
            }
            return back()->with('error', "POLICY: This ticket is currently not eligible for tour expense submission.");
        }

        $ticket = Ticket::findOrFail($validated['ticket_id']);
        $engineer = Auth::user()->isEngineer() ? Auth::user() : ($ticket->assignedEngineer ?: Auth::user());

        // Origin: Home coordinates / Home address / Base city
        $origin = !empty($engineer->home_coordinates)
            ? $engineer->home_coordinates . (!empty($engineer->home_address) ? " ({$engineer->home_address})" : '')
            : (!empty($engineer->home_address)
                ? "{$engineer->home_address}, {$engineer->base_city}"
                : ($validated['from_city'] ?: ($engineer->base_city ?: 'Lahore')));

        // Destination: Branch Physical Street Address, fallback to branch location (City / Region)
        $destination = !empty(trim($ticket->branch_address ?? ''))
            ? trim($ticket->branch_address) . ', ' . ($ticket->branch_location ?: '')
            : ($validated['to_city'] ?: ($ticket->branch_location ?: 'Pakistan'));

        // Gemini AI Highway Distance Estimation with local matrix / GPS fallback
        $aiResult = $gemini->estimateDistance($origin, $destination, $validated['trip_type']);
        $distance = (float) ($aiResult['distance_km'] ?? 0);
        $estimatedHours = (float) ($aiResult['estimated_hours'] ?? round($distance / 65, 1));

        $suggestedRatePerKm = 25.00; // PKR 25/km standard benchmark
        $category = $validated['category'] ?? 'travel';
        $suggestedAmount = in_array($category, ['travel', 'fuel']) && $distance > 0 
            ? round($distance * $suggestedRatePerKm, 2) 
            : $validated['claimed_amount'];

        $voucherPath = null;
        if ($request->hasFile('voucher_file')) {
            $voucherPath = $request->file('voucher_file')->store('vouchers', 'public');
        } elseif ($request->filled('uploaded_voucher_path')) {
            $voucherPath = $request->input('uploaded_voucher_path');
        }

        $supportingPath = null;
        if ($request->hasFile('supporting_doc')) {
            $supportingPath = $request->file('supporting_doc')->store('reports', 'public');
        }

        $claim = ExpenseClaim::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => Auth::id(),
            'from_city' => $validated['from_city'],
            'to_city' => $validated['to_city'],
            'trip_type' => $validated['trip_type'],
            'category' => $category,
            'description' => $validated['description'] ?? null,
            'ai_distance_km' => $distance,
            'ai_estimated_hours' => $estimatedHours,
            'claimed_amount' => $validated['claimed_amount'],
            'suggested_amount' => $suggestedAmount,
            'status' => 'submitted',
            'voucher_file' => $voucherPath,
            'supporting_doc' => $supportingPath,
        ]);

        $redirectUrl = $request->input('redirect_to') === 'tickets' ? route('tickets.index') : route('expenses.index');
        return redirect($redirectUrl)->with('success', "Expense claim #{$claim->id} submitted for Ticket #{$ticket->ticket_no}. Estimated round-trip distance: {$distance} km (Suggested benchmark: PKR " . number_format($suggestedAmount, 2) . ").");
    }

    /**
     * Live AJAX Upload for Receipt Vouchers & Supporting Docs with progress tracking.
     */
    public function uploadVoucher(Request $request)
    {
        $request->validate([
            'voucher' => 'required|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
        ]);

        $file = $request->file('voucher');
        $filename = $file->getClientOriginalName();
        $path = $file->store('vouchers', 'public');
        $url = asset('storage/' . $path);
        $ext = strtolower($file->getClientOriginalExtension());
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        return response()->json([
            'success' => true,
            'message' => 'Receipt voucher uploaded successfully.',
            'path' => $path,
            'filename' => $filename,
            'url' => $url,
            'is_image' => $isImage,
            'filesize' => round($file->getSize() / 1024, 1) . ' KB',
        ]);
    }

    public function show(ExpenseClaim $claim)
    {
        $claim->load(['ticket', 'engineer', 'paidBy']);
        return view('expenses.show', compact('claim'));
    }

    /**
     * View or stream receipt voucher directly with proper MIME headers.
     */
    public function viewVoucher(ExpenseClaim $claim)
    {
        if (empty($claim->voucher_file)) {
            abort(404, 'No voucher file attached to this expense claim.');
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($claim->voucher_file)) {
            $altPath = public_path('storage/' . $claim->voucher_file);
            if (!file_exists($altPath)) {
                abort(404, 'Voucher file not found on disk.');
            }
            $fullPath = $altPath;
        } else {
            $fullPath = $disk->path($claim->voucher_file);
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        $filename = basename($fullPath);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Edit / Replace voucher for an existing expense claim and update record.
     */
    public function updateVoucher(Request $request, ExpenseClaim $claim)
    {
        $user = Auth::user();
        if ($user->isEngineer() && $claim->engineer_id !== $user->id) {
            abort(403, 'Unauthorized to update voucher for this expense claim.');
        }

        if (in_array($claim->status, ['approved', 'paid']) && !($user->isAdmin() || $user->isSuperAdmin())) {
            return back()->with('error', 'Approved or paid vouchers cannot be modified by engineers.');
        }

        $request->validate([
            'voucher_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
        ]);

        $file = $request->file('voucher_file');
        $voucherPath = $file->store('vouchers', 'public');

        try {
            $publicTarget = public_path('storage/' . $voucherPath);
            if (!file_exists(dirname($publicTarget))) {
                @mkdir(dirname($publicTarget), 0777, true);
            }
            @copy(Storage::disk('public')->path($voucherPath), $publicTarget);
        } catch (\Throwable $e) {}

        // Delete old voucher if different
        if ($claim->voucher_file && $claim->voucher_file !== $voucherPath) {
            try {
                Storage::disk('public')->delete($claim->voucher_file);
            } catch (\Throwable $e) {}
        }

        $updateFields = [
            'voucher_file' => $voucherPath,
        ];

        // If claim was rejected, uploading/replacing a voucher re-submits it for Admin verification
        if ($claim->status === 'rejected') {
            $updateFields['status'] = 'submitted';
            $updateFields['admin_notes'] = 'Voucher attached/updated by engineer. Re-submitted for Admin verification.';
        }

        $claim->update($updateFields);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Voucher receipt updated and verified successfully.',
                'path' => $voucherPath,
                'url' => route('expenses.voucher', $claim),
            ]);
        }

        return back()->with('success', "Expense voucher updated successfully.");
    }

    public function approve(Request $request, ExpenseClaim $claim)
    {
        $claim->update([
            'status' => 'approved',
            'admin_notes' => $request->admin_notes ?? 'Verified and approved by ' . Auth::user()->name,
        ]);

        return back()->with('success', "Expense claim #{$claim->id} approved.");
    }

    public function reject(Request $request, ExpenseClaim $claim)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $claim->update([
            'status' => 'rejected',
            'admin_notes' => $request->rejection_reason,
            'resubmission_count' => $claim->resubmission_count + 1,
        ]);

        return back()->with('warning', "Expense claim #{$claim->id} rejected. The engineer has been notified to re-submit with corrections.");
    }

    public function resubmit(Request $request, ExpenseClaim $claim)
    {
        $validated = $request->validate([
            'claimed_amount' => 'required|numeric|min:1',
            'voucher_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
            'supporting_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
            'notes' => 'nullable|string',
        ]);

        $updateData = [
            'claimed_amount' => $validated['claimed_amount'],
            'status' => 'submitted',
            'admin_notes' => 'Re-submitted by engineer: ' . ($validated['notes'] ?? 'Updated voucher/documents'),
        ];

        if ($request->hasFile('voucher_file')) {
            $updateData['voucher_file'] = $request->file('voucher_file')->store('vouchers', 'public');
        }

        if ($request->hasFile('supporting_doc')) {
            $updateData['supporting_doc'] = $request->file('supporting_doc')->store('reports', 'public');
        }

        $claim->update($updateData);

        return back()->with('success', "Expense claim #{$claim->id} re-submitted for admin verification.");
    }

    public function pay(Request $request, ExpenseClaim $claim)
    {
        $request->validate([
            'payment_method' => 'required|in:bank_transfer,cash,cheque',
            'payment_reference' => 'required|string|max:100',
        ]);

        $claim->update([
            'status' => 'paid',
            'payment_method' => $request->payment_method,
            'payment_reference' => $request->payment_reference,
            'paid_at' => Carbon::now(),
            'paid_by_id' => Auth::id(),
        ]);

        return back()->with('success', "Expense claim #{$claim->id} marked as PAID. Reference: {$request->payment_reference}");
    }

    public function bulkPay(Request $request)
    {
        $request->validate([
            'claim_ids' => 'required|array',
            'claim_ids.*' => 'exists:expense_claims,id',
            'payment_method' => 'required|in:bank_transfer,cash,cheque',
            'batch_reference' => 'required|string|max:100',
        ]);

        $count = ExpenseClaim::whereIn('id', $request->claim_ids)
            ->where('status', 'approved')
            ->update([
                'status' => 'paid',
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->batch_reference,
                'paid_at' => Carbon::now(),
                'paid_by_id' => Auth::id(),
            ]);

        return back()->with('success', "Bulk payment processed for {$count} approved expense claims! Batch Ref: {$request->batch_reference}");
    }

    private function calculateTentativeDistance(string $fromCity, string $toCity, string $tripType): float
    {
        $from = strtolower(trim($fromCity));
        $to = strtolower(trim($toCity));

        if ($from === $to) {
            return $tripType === 'round_trip' ? 40.00 : 20.00; // local branch visit
        }

        // Pakistani highway matrix (one-way km)
        $distances = [
            'lahore-vehari'      => 331,
            'lahore-karachi'     => 1210,
            'lahore-islamabad'   => 375,
            'lahore-multan'      => 345,
            'lahore-faisalabad'  => 135,
            'lahore-gujranwala'  => 70,
            'lahore-sahiwal'     => 180,
            'lahore-sialkot'     => 130,
            'karachi-islamabad'  => 1410,
            'karachi-multan'     => 930,
            'karachi-hyderabad'  => 165,
            'islamabad-peshawar' => 185,
            'islamabad-multan'   => 540,
            'multan-faisalabad'  => 240,
            'multan-vehari'      => 105,
        ];

        $key1 = "{$from}-{$to}";
        $key2 = "{$to}-{$from}";

        $oneWay = $distances[$key1] ?? $distances[$key2] ?? 150.00;

        return $tripType === 'round_trip' ? $oneWay * 2 : $oneWay;
    }

    /**
     * Export expense claims (Master Sheet + Engineer Summary) in Excel XML (multi-sheet) or CSV format.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        if ($user->isEngineer()) {
            abort(403, 'Expense export is restricted to Operations Managers.');
        }

        $settlementStatus = $request->input('settlement_status');
        $claimStatus = $request->input('claim_status', $request->input('status'));
        $engineerId = $request->input('engineer_id');
        $search = $request->input('search');
        $fromDate = $request->input('from_date', $request->input('date_from'));
        $toDate = $request->input('to_date', $request->input('date_to'));
        $format = strtolower($request->input('format', 'csv')); // 'excel' or 'csv'

        if ($claimStatus === 'not_submitted') {
            return $this->exportNotSubmittedReport($request, $format);
        }

        $query = ExpenseClaim::with(['ticket', 'engineer', 'paidBy'])->latest();

        // Settlement Status
        if ($settlementStatus === 'paid') {
            $query->where('status', 'paid');
        } elseif ($settlementStatus === 'unpaid') {
            $query->where('status', '!=', 'paid');
        }

        // Claim Lifecycle Status
        if ($claimStatus && $claimStatus !== 'all') {
            $query->where('status', $claimStatus);
        }

        // Engineer
        if ($engineerId) {
            $query->where('engineer_id', $engineerId);
        }

        // Date Range
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('from_city', 'like', "%{$search}%")
                  ->orWhere('to_city', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%")
                  ->orWhereHas('ticket', function ($tq) use ($search) {
                      $tq->where('ticket_no', 'like', "%{$search}%")
                         ->orWhere('bank_name', 'like', "%{$search}%")
                         ->orWhere('branch_name', 'like', "%{$search}%")
                         ->orWhere('branch_location', 'like', "%{$search}%");
                  })
                  ->orWhereHas('engineer', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $claims = $query->get();

        // Calculate Engineer Summary
        $engineerSummaries = $claims->groupBy('engineer_id')->map(function ($engClaims) {
            $engineer = $engClaims->first()->engineer;
            $count = $engClaims->count();
            $totalDistance = round((float) $engClaims->sum('ai_distance_km'), 2);
            $totalCost = round((float) $engClaims->sum('claimed_amount'), 2);
            $avgCost = $count > 0 ? round($totalCost / $count, 2) : 0.00;
            $avgPerKm = $totalDistance > 0 ? round($totalCost / $totalDistance, 2) : 0.00;

            return [
                'engineer_name' => $engineer ? $engineer->name : 'Unassigned / Other',
                'exp_count' => $count,
                'total_ai_distance' => $totalDistance,
                'avg_tour_cost' => $avgCost,
                'total_tour_cost' => $totalCost,
                'avg_per_km_cost' => $avgPerKm,
            ];
        })->values();

        if ($format === 'csv') {
            return $this->streamCsvExport($claims, $engineerSummaries);
        }

        return $this->downloadExcelMultiSheet($claims, $engineerSummaries);
    }

    /**
     * Download true multi-sheet Excel file (.xls XML format).
     */
    private function downloadExcelMultiSheet($claims, $engineerSummaries)
    {
        $filename = 'expense_report_' . date('Y-m-d') . '.xls';

        $totalCount = 0;
        $totalDistance = 0.0;
        $grandTotalCost = 0.0;
        foreach ($engineerSummaries as $s) {
            $totalCount += $s['exp_count'];
            $totalDistance += $s['total_ai_distance'];
            $grandTotalCost += $s['total_tour_cost'];
        }
        $overallAvg = $totalCount > 0 ? round($grandTotalCost / $totalCount, 2) : 0.00;
        $overallAvgPerKm = $totalDistance > 0 ? round($grandTotalCost / $totalDistance, 2) : 0.00;

        $xml = view('expenses.exports.excel_multisheet', compact(
            'claims',
            'engineerSummaries',
            'totalCount',
            'totalDistance',
            'grandTotalCost',
            'overallAvg',
            'overallAvgPerKm'
        ))->render();

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Stream CSV export with Master Sheet and Engineer Summary sections.
     */
    private function streamCsvExport($claims, $engineerSummaries)
    {
        $filename = 'expense_claims_report_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($claims, $engineerSummaries) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // SHEET 1: MASTER CLAIMS
            fputcsv($handle, ['=== SHEET 1: MASTER TOUR EXPENSE CLAIMS ===']);
            fputcsv($handle, [
                'Claim ID',
                'Ticket No',
                'Bank Name',
                'Branch / City',
                'Branch Address',
                'Engineer Name',
                'Engineer Location',
                'Category',
                'Description',
                'From City',
                'To City',
                'Trip Type',
                'AI Distance (KM)',
                'Claimed Amount (PKR)',
                'Applied Rate (PKR/KM)',
                'Lifecycle Status',
                'Settlement Status',
                'Payment Method',
                'Payment Ref',
                'Paid At',
                'Submission Date'
            ]);

            foreach ($claims as $c) {
                fputcsv($handle, [
                    $c->id,
                    $c->ticket?->ticket_no ?? 'N/A',
                    $c->ticket?->bank_name ?? 'N/A',
                    $c->ticket?->branch_location ?? $c->to_city,
                    $c->ticket?->branch_address ?? 'N/A',
                    $c->engineer?->name ?? 'N/A',
                    $c->engineer?->base_city ?? ($c->engineer?->home_address ?? 'N/A'),
                    strtoupper($c->category ?? 'TRAVEL'),
                    $c->description ?? 'N/A',
                    $c->from_city,
                    $c->to_city,
                    ucwords(str_replace('_', ' ', $c->trip_type)),
                    number_format($c->ai_distance_km ?? 0, 2, '.', ''),
                    number_format($c->claimed_amount, 2, '.', ''),
                    $c->applied_rate !== null ? number_format($c->applied_rate, 2, '.', '') : 'N/A',
                    strtoupper($c->status),
                    $c->status === 'paid' ? 'PAID & SETTLED' : 'UNPAID & UNSETTLED',
                    strtoupper(str_replace('_', ' ', $c->payment_method ?? 'N/A')),
                    $c->payment_reference ?? 'N/A',
                    $c->paid_at ? $c->paid_at->format('Y-m-d H:i') : 'N/A',
                    $c->created_at->format('Y-m-d H:i')
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, []);

            // SHEET 2: ENGINEER SUMMARY
            fputcsv($handle, ['=== SHEET 2: ENGINEER EXPENSE SUMMARY ===']);
            fputcsv($handle, [
                'Engineer Name',
                "Exp No's",
                'Total AI Estimated Distance (KM)',
                'Average Tour Cost (PKR)',
                'Total Tour Cost (PKR)',
                'Average Per KM Cost (PKR)'
            ]);

            $totalCount = 0;
            $totalDistance = 0.0;
            $grandTotalCost = 0.0;

            foreach ($engineerSummaries as $s) {
                $totalCount += $s['exp_count'];
                $totalDistance += $s['total_ai_distance'];
                $grandTotalCost += $s['total_tour_cost'];

                fputcsv($handle, [
                    $s['engineer_name'],
                    $s['exp_count'],
                    number_format($s['total_ai_distance'], 2, '.', ''),
                    number_format($s['avg_tour_cost'], 2, '.', ''),
                    number_format($s['total_tour_cost'], 2, '.', ''),
                    number_format($s['avg_per_km_cost'] ?? 0, 2, '.', '')
                ]);
            }

            $overallAvg = $totalCount > 0 ? ($grandTotalCost / $totalCount) : 0;
            $overallAvgPerKm = $totalDistance > 0 ? ($grandTotalCost / $totalDistance) : 0;
            fputcsv($handle, [
                'TOTAL / ALL ENGINEERS',
                $totalCount,
                number_format($totalDistance, 2, '.', ''),
                number_format($overallAvg, 2, '.', ''),
                number_format($grandTotalCost, 2, '.', ''),
                number_format($overallAvgPerKm, 2, '.', '')
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export unsubmitted tickets report.
     */
    private function exportNotSubmittedReport(Request $request, string $format)
    {
        $engineerId = $request->input('engineer_id');
        $search = $request->input('search');
        $fromDate = $request->input('from_date', $request->input('date_from'));
        $toDate = $request->input('to_date', $request->input('date_to'));

        $query = Ticket::with(['assignedEngineer'])
            ->doesntHave('expenseClaims')
            ->when($engineerId, fn($q, $eid) => $q->where('assigned_engineer_id', $eid))
            ->when($fromDate, fn($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($toDate, fn($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($search, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('ticket_no', 'like', "%{$s}%")
                        ->orWhere('bank_name', 'like', "%{$s}%")
                        ->orWhere('branch_name', 'like', "%{$s}%")
                        ->orWhere('branch_location', 'like', "%{$s}%")
                        ->orWhereHas('assignedEngineer', fn($eq) => $eq->where('name', 'like', "%{$s}%"));
                });
            })
            ->latest();

        $tickets = $query->get();

        $engineerSummaries = $tickets->groupBy('assigned_engineer_id')->map(function ($engTickets) {
            $engineer = $engTickets->first()->assignedEngineer;
            return [
                'engineer_name' => $engineer ? $engineer->name : 'Unassigned',
                'exp_count' => $engTickets->count(),
                'total_ai_distance' => 0.00,
                'avg_tour_cost' => 0.00,
                'total_tour_cost' => 0.00,
            ];
        })->values();

        $totalCount = $tickets->count();

        if ($format === 'csv') {
            $filename = 'unsubmitted_expense_tickets_' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($tickets, $engineerSummaries, $totalCount) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

                fputcsv($handle, ['=== SHEET 1: TICKETS PENDING TOUR EXPENSE CLAIM SUBMISSION ===']);
                fputcsv($handle, [
                    'Ticket No',
                    'Bank Name',
                    'Branch / Location',
                    'Assigned Engineer',
                    'Machine Type',
                    'Urgency',
                    'Ticket Status',
                    'Claim Status',
                    'Settlement Status',
                    'Created Date'
                ]);

                foreach ($tickets as $t) {
                    fputcsv($handle, [
                        $t->ticket_no,
                        $t->bank_name,
                        $t->branch_location ?? $t->branch_name ?? 'N/A',
                        $t->assignedEngineer?->name ?? 'Unassigned',
                        $t->machine_type ?? 'N/A',
                        strtoupper($t->urgency ?? 'NORMAL'),
                        strtoupper(str_replace('_', ' ', $t->status)),
                        'NOT SUBMITTED',
                        'UNPAID & UNSETTLED',
                        $t->created_at->format('Y-m-d H:i')
                    ]);
                }

                fputcsv($handle, []);
                fputcsv($handle, []);

                fputcsv($handle, ['=== SHEET 2: ENGINEER PENDING EXPENSE SUMMARY ===']);
                fputcsv($handle, [
                    'Engineer Name',
                    "Pending Claims (Exp No's)",
                    'Total AI Estimated Distance (KM)',
                    'Average Tour Cost (PKR)',
                    'Total Tour Cost (PKR)'
                ]);

                foreach ($engineerSummaries as $s) {
                    fputcsv($handle, [
                        $s['engineer_name'],
                        $s['exp_count'],
                        'Pending Claim',
                        'Pending Claim',
                        'Pending Claim'
                    ]);
                }

                fputcsv($handle, [
                    'TOTAL / ALL ENGINEERS',
                    $totalCount,
                    '-',
                    '-',
                    '-'
                ]);

                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        }

        $xml = view('expenses.exports.excel_not_submitted', compact(
            'tickets',
            'engineerSummaries',
            'totalCount'
        ))->render();

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"unsubmitted_expense_tickets_" . date('Y-m-d') . ".xls\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
