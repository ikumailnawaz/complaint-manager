<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\Ticket;
use App\Services\EngineerEnvelopeService;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PartRequestController extends Controller
{
    public function __construct(
        private StockLedgerService $stock,
        private EngineerEnvelopeService $envelopeService
    ) {}

    public function index(Request $request)
    {
        $baseQuery = PartRequest::query();
        if (auth()->user()->isEngineer()) {
            $baseQuery->where('engineer_id', auth()->id());
        }

        // Status counts for top tabs
        $statusCounts = [
            'all'                 => (clone $baseQuery)->count(),
            'pending_stock_check' => (clone $baseQuery)->where('status', 'pending_stock_check')->count(),
            'pending_approval'    => (clone $baseQuery)->where('status', 'pending_approval')->count(),
            'approved'            => (clone $baseQuery)->where('status', 'approved')->count(),
            'dispatched'          => (clone $baseQuery)->where('status', 'dispatched')->count(),
            'rejected'            => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        // Faulty return tracking counts
        $returnCounts = [
            'pending_return' => (clone $baseQuery)->where('faulty_return_status', 'pending_return')->count(),
            'returned'       => (clone $baseQuery)->where('faulty_return_status', 'returned')->count(),
            'waived'         => (clone $baseQuery)->where('faulty_return_status', 'waived')->count(),
        ];

        $query = (clone $baseQuery)
            ->with([
                'ticket', 'engineer', 'machineModel', 'items.part',
                'dispatchLocation', 'faultyReturnReceivedBy', 'faultyReturnLocation', 'approvedBy',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('faulty_return_status')) {
            $query->where('faulty_return_status', $request->faulty_return_status);
        }

        if ($request->filled('engineer_id') && !auth()->user()->isEngineer()) {
            $query->where('engineer_id', $request->engineer_id);
        }

        // Date Range Filters
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search Filter (request #, ticket #, serial, tracking, part name/number)
        if ($request->filled('search')) {
            $s = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('request_number', 'like', $s)
                  ->orWhere('machine_serial_no', 'like', $s)
                  ->orWhere('dispatch_tracking_number', 'like', $s)
                  ->orWhere('dispatch_courier', 'like', $s)
                  ->orWhereHas('ticket', fn($t) => $t->where('ticket_no', 'like', $s)->orWhere('bank_name', 'like', $s))
                  ->orWhereHas('items.part', fn($p) => $p->where('name', 'like', $s)->orWhere('part_number', 'like', $s));
            });
        }

        $requests = $query->paginate(20)->withQueryString();
        $pendingCount = $statusCounts['pending_stock_check'] + $statusCounts['pending_approval'];
        $engineers = \App\Models\User::where('role', 'engineer')->orderBy('name')->get(['id', 'name']);

        return view('parts.requests.index', compact('requests', 'pendingCount', 'engineers', 'statusCounts', 'returnCounts'));
    }

    public function create(Request $request)
    {
        $tickets = auth()->user()->isEngineer()
            ? Ticket::where('assigned_engineer_id', auth()->id())
                ->whereIn('status', ['in_progress', 'resolved', 'closed'])
                ->orderByDesc('created_at')->get(['id', 'ticket_no', 'bank_name', 'branch_location', 'machine_model', 'machine_serial_no'])
            : Ticket::whereIn('status', ['in_progress', 'resolved', 'closed'])
                ->orderByDesc('created_at')->get(['id', 'ticket_no', 'bank_name', 'branch_location', 'machine_model', 'machine_serial_no']);
        $models = MachineModel::where('is_active', true)->orderBy('name')->get();
        $selectedTicket = $request->ticket_id ? Ticket::find($request->ticket_id) : null;

        if ($selectedTicket && $selectedTicket->status === 'awaiting_workshop') {
            return redirect()->route('tickets.show', $selectedTicket)
                ->with('error', "Machine is in transit to central workshop. Requesting spare parts is locked while cargo is in transit.");
        }

        // Current engineer's advance float envelope stock
        $engineerEnvelopes = \App\Models\EngineerInventory::where('engineer_id', auth()->id())
            ->where('qty_on_hand', '>', 0)
            ->with('part')
            ->get()
            ->keyBy('part_id');

        return view('parts.requests.create', compact('tickets', 'models', 'selectedTicket', 'engineerEnvelopes'));
    }

    public function store(Request $request)
    {
        // Sanitize & filter items array: keep only rows where a part was checked/selected
        $rawItems = $request->input('items', []);
        if (is_array($rawItems)) {
            $filteredItems = [];
            foreach ($rawItems as $item) {
                if (is_array($item) && !empty($item['part_id'])) {
                    $filteredItems[] = [
                        'part_id' => (int) $item['part_id'],
                        'qty'     => isset($item['qty']) ? max(1, (int) $item['qty']) : 1,
                        'note'    => !empty($item['note']) ? (string) $item['note'] : null,
                    ];
                }
            }
            $request->merge(['items' => $filteredItems]);
        }

        $data = $request->validate([
            'ticket_id'         => 'required|exists:tickets,id',
            'machine_model_id'  => 'nullable|exists:machine_models,id',
            'machine_serial_no' => 'nullable|string|max:100',
            'fault_description' => 'required|string',
            'items'             => 'required|array|min:1',
            'items.*.part_id'   => 'required|exists:parts,id',
            'items.*.qty'       => 'required|integer|min:1',
            'items.*.note'      => 'nullable|string|max:255',
        ], [
            'items.required'           => 'Please select at least one spare part from the list.',
            'items.min'                => 'Please select at least one spare part from the list.',
            'items.*.part_id.required' => 'Please select a valid spare part.',
            'items.*.qty.min'          => 'Quantity must be at least 1.',
        ]);

        $targetTicket = Ticket::findOrFail($data['ticket_id']);
        if ($targetTicket->status === 'awaiting_workshop') {
            return back()->with('error', "Machine is in transit to central workshop. Requesting spare parts is locked while cargo is in transit.");
        }

        $pr = DB::transaction(function () use ($data) {
            $engineerId = auth()->id();
            $ticketId = (int) $data['ticket_id'];
            $machineModelId = !empty($data['machine_model_id']) ? (int) $data['machine_model_id'] : null;
            $machineSerialNo = $data['machine_serial_no'] ?? null;

            $pr = PartRequest::create([
                'request_number'    => PartRequest::generateRequestNumber(),
                'ticket_id'         => $ticketId,
                'engineer_id'       => $engineerId,
                'machine_model_id'  => $machineModelId,
                'machine_serial_no' => $machineSerialNo,
                'fault_description' => $data['fault_description'],
                'status'            => 'pending_stock_check',
            ]);

            foreach ($data['items'] as $item) {
                PartRequestItem::create([
                    'part_request_id'   => $pr->id,
                    'part_id'           => (int) $item['part_id'],
                    'qty_requested'     => (int) $item['qty'],
                    'qty_from_envelope' => 0,
                    'note'              => $item['note'] ?? null,
                ]);
            }

            return $pr;
        });

        \App\Services\NotificationService::notifyPartRequestCreated($pr);

        return redirect()->route('parts.requests.show', $pr)->with('success', "Part request {$pr->request_number} submitted successfully and forwarded for stock verification.");
    }

    public function show(PartRequest $partRequest)
    {
        $partRequest->load([
            'ticket', 'engineer', 'machineModel', 'items.part',
            'stockVerifiedBy', 'approvedBy', 'rejectedBy', 'dispatchedBy', 'dispatchLocation',
            'faultyReturnReceivedBy', 'faultyReturnLocation',
        ]);
        $locations = Location::where('is_active', true)->orderBy('name')->get();
        $partIds = $partRequest->items->pluck('part_id')->filter()->unique();
        $itemStocks = \App\Models\PartStockLedger::whereIn('part_id', $partIds)->get()
            ->groupBy(['location_id', 'part_id']);

        // Engineer envelope stock for these parts
        $engineerEnvelopes = \App\Models\EngineerInventory::where('engineer_id', $partRequest->engineer_id)
            ->whereIn('part_id', $partIds)
            ->get()
            ->keyBy('part_id');

        return view('parts.requests.show', compact('partRequest', 'locations', 'itemStocks', 'engineerEnvelopes'));
    }

    public function edit(PartRequest $partRequest)
    {
        if (!$partRequest->isEditable()) {
            return redirect()->route('parts.requests.show', $partRequest)
                ->with('error', 'Cannot edit a part request that has already been approved or dispatched.');
        }

        if (auth()->user()->isEngineer() && $partRequest->engineer_id !== auth()->id()) {
            abort(403, 'You are not authorized to edit this part request.');
        }

        $tickets = auth()->user()->isEngineer()
            ? Ticket::where('assigned_engineer_id', auth()->id())
                ->whereIn('status', ['in_progress', 'resolved', 'closed'])
                ->orderByDesc('created_at')->get(['id', 'ticket_no', 'bank_name', 'branch_location', 'machine_model', 'machine_serial_no'])
            : Ticket::whereIn('status', ['in_progress', 'resolved', 'closed'])
                ->orderByDesc('created_at')->get(['id', 'ticket_no', 'bank_name', 'branch_location', 'machine_model', 'machine_serial_no']);

        $models = MachineModel::where('is_active', true)->orderBy('name')->get();
        $partRequest->load(['items.part', 'machineModel', 'ticket']);

        $modelParts = [];
        if ($partRequest->machine_model_id) {
            $modelParts = Part::whereHas('machineModels', function ($q) use ($partRequest) {
                $q->where('machine_models.id', $partRequest->machine_model_id);
            })->where('is_active', true)->orderBy('name')->get();
        }

        // Current engineer's advance float envelope stock
        $engineerEnvelopes = \App\Models\EngineerInventory::where('engineer_id', $partRequest->engineer_id)
            ->where('qty_on_hand', '>', 0)
            ->with('part')
            ->get()
            ->keyBy('part_id');

        $initialSelectedItems = $partRequest->items->mapWithKeys(function ($item) {
            return [$item->part_id => [
                'qty'               => (int) ($item->qty_requested + $item->qty_from_envelope),
                'qty_requested'     => (int) $item->qty_requested,
                'qty_from_envelope' => (int) $item->qty_from_envelope,
                'use_envelope'      => $item->qty_from_envelope > 0 || !str_contains($item->note ?? '', 'Emergency Float Preserved'),
                'note'              => $item->note ?? '',
            ]];
        })->all();

        $envelopeStockMap = $engineerEnvelopes->mapWithKeys(fn ($item, $key) => [$key => $item->qty_on_hand])->all();

        return view('parts.requests.edit', compact('partRequest', 'tickets', 'models', 'modelParts', 'engineerEnvelopes', 'initialSelectedItems', 'envelopeStockMap'));
    }

    public function update(Request $request, PartRequest $partRequest)
    {
        if (!$partRequest->isEditable()) {
            return redirect()->route('parts.requests.show', $partRequest)
                ->with('error', 'Cannot edit a part request that has already been approved or dispatched.');
        }

        if (auth()->user()->isEngineer() && $partRequest->engineer_id !== auth()->id()) {
            abort(403, 'You are not authorized to edit this part request.');
        }

        // Sanitize & filter items array: keep only rows where a part was checked/selected
        $rawItems = $request->input('items', []);
        if (is_array($rawItems)) {
            $filteredItems = [];
            foreach ($rawItems as $item) {
                if (is_array($item) && !empty($item['part_id'])) {
                    $filteredItems[] = [
                        'part_id' => (int) $item['part_id'],
                        'qty'     => isset($item['qty']) ? max(1, (int) $item['qty']) : 1,
                        'note'    => !empty($item['note']) ? (string) $item['note'] : null,
                    ];
                }
            }
            $request->merge(['items' => $filteredItems]);
        }

        $data = $request->validate([
            'ticket_id'          => 'required|exists:tickets,id',
            'machine_model_id'   => 'nullable|exists:machine_models,id',
            'machine_serial_no'  => 'nullable|string|max:100',
            'fault_description'  => 'required|string',
            'items'              => 'required|array|min:1',
            'items.*.part_id'    => 'required|exists:parts,id',
            'items.*.qty'        => 'required|integer|min:1',
            'items.*.note'       => 'nullable|string|max:255',
        ], [
            'items.required'           => 'Please select at least one spare part from the list.',
            'items.min'                => 'Please select at least one spare part from the list.',
            'items.*.part_id.required' => 'Please select a valid spare part.',
            'items.*.qty.min'          => 'Quantity must be at least 1.',
        ]);

        DB::transaction(function () use ($partRequest, $data) {
            $partRequest->update([
                'ticket_id'         => $data['ticket_id'],
                'machine_model_id'  => $data['machine_model_id'] ?? null,
                'machine_serial_no' => $data['machine_serial_no'] ?? null,
                'fault_description' => $data['fault_description'],
            ]);

            $partRequest->items()->delete();
            foreach ($data['items'] as $item) {
                PartRequestItem::create([
                    'part_request_id' => $partRequest->id,
                    'part_id'         => $item['part_id'],
                    'qty_requested'   => $item['qty'],
                    'note'            => $item['note'] ?? null,
                ]);
            }
        });

        return redirect()->route('parts.requests.show', $partRequest)->with('success', 'Part request ' . $partRequest->request_number . ' updated successfully.');
    }

    public function verifyStock(Request $request, PartRequest $partRequest)
    {
        abort_unless(auth()->user()->isOfficeStaff() || auth()->user()->isSuperior(), 403, 'Stage 1 Stock Verification requires Office Staff or Administrator access.');
        $data = $request->validate([
            'stock_remarks' => 'nullable|string',
            'fault_video'   => 'nullable|file|mimes:mp4,mov,avi,wmv,webm,mkv|max:102400',
        ]);

        $videoPath = null;
        if ($request->hasFile('fault_video')) {
            $videoPath = $request->file('fault_video')->store('part-requests/videos', 'public');
            // Duplicate to public/storage if on Windows with directory junction/fallback
            try {
                $sourcePath = Storage::disk('public')->path($videoPath);
                $publicTarget = public_path('storage/' . $videoPath);
                if (!file_exists(dirname($publicTarget))) {
                    @mkdir(dirname($publicTarget), 0777, true);
                }
                @copy($sourcePath, $publicTarget);
            } catch (\Throwable $e) {}
        }

        $partRequest->update([
            'status'               => 'pending_approval',
            'fault_video_path'     => $videoPath ?? $partRequest->fault_video_path,
            'stock_verified_by_id' => auth()->id(),
            'stock_verified_at'    => now(),
            'stock_remarks'        => $data['stock_remarks'] ?? null,
        ]);

        \App\Services\NotificationService::notifyPartRequestVerified($partRequest);

        return redirect()->route('parts.requests.show', $partRequest)->with('success', 'Stage 1 Stock Verification completed. Request forwarded to Super Administrator for Stage 2 approval.');
    }

    public function approve(Request $request, PartRequest $partRequest)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin(), 403, 'Stage 2 Part Request Final Approval is strictly restricted to Super Administrator.');
        $data = $request->validate([
            'approval_remarks' => 'nullable|string',
            'approved_qtys'    => 'nullable|array',
            'approved_qtys.*'  => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($partRequest, $data) {
            foreach ($partRequest->items as $item) {
                $approvedQty = isset($data['approved_qtys'][$item->id])
                    ? (int)$data['approved_qtys'][$item->id]
                    : ($item->qty_approved ?? $item->qty_requested);
                $item->update(['qty_approved' => $approvedQty]);
            }
            $partRequest->update([
                'status'           => 'approved',
                'approved_by_id'   => auth()->id(),
                'approved_at'      => now(),
                'approval_remarks' => $data['approval_remarks'] ?? $partRequest->approval_remarks,
            ]);
        });

        \App\Services\NotificationService::notifyPartRequestApproved($partRequest);

        return redirect()->route('parts.requests.show', $partRequest)->with('success', 'Parts request ' . $partRequest->request_number . ' approved by Super Administrator.');
    }

    public function reject(Request $request, PartRequest $partRequest)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin(), 403, 'Stage 2 Part Request Rejection is strictly restricted to Super Administrator.');
        $data = $request->validate(['rejection_reason' => 'required|string']);
        $partRequest->update([
            'status'           => 'rejected',
            'rejected_by_id'   => auth()->id(),
            'rejected_at'      => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);
        return redirect()->route('parts.requests.show', $partRequest)->with('success', 'Part request rejected.');
    }

    public function dispatch(Request $request, PartRequest $partRequest)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $dispatchSource = $request->input('dispatch_source', 'warehouse');
        $rules = [
            'dispatch_source'       => 'nullable|in:warehouse,envelope,split',
            'split_envelope_qtys'   => 'nullable|array',
            'split_envelope_qtys.*' => 'nullable|integer|min:0',
        ];

        if ($dispatchSource === 'warehouse') {
            $rules['dispatch_location_id']     = 'required|exists:locations,id';
            $rules['dispatch_courier']         = 'required|string|max:100';
            $rules['dispatch_tracking_number'] = 'required|string|max:100';
        } elseif ($dispatchSource === 'split') {
            $rules['dispatch_location_id']     = 'required|exists:locations,id';
            $rules['dispatch_courier']         = 'nullable|string|max:100';
            $rules['dispatch_tracking_number'] = 'nullable|string|max:100';
        } else {
            $rules['dispatch_location_id']     = 'nullable|exists:locations,id';
            $rules['dispatch_courier']         = 'nullable|string|max:100';
            $rules['dispatch_tracking_number'] = 'nullable|string|max:100';
        }

        $data = $request->validate($rules, [
            'dispatch_location_id.required'     => 'Please select a warehouse location to deduct stock from.',
            'dispatch_courier.required'         => 'Courier / shipping service is required for dispatch.',
            'dispatch_tracking_number.required' => 'Courier tracking number is strictly required to dispatch parts.',
        ]);

        try {
            DB::transaction(function () use ($partRequest, $data, $dispatchSource) {
                $totalFromWarehouse = 0;
                $totalFromEnvelope = 0;

                foreach ($partRequest->items as $item) {
                    $totalQty = $item->qty_approved ?? $item->qty_requested;
                    if ($totalQty <= 0) continue;

                    $fromEnvelope = 0;
                    $fromWarehouse = 0;

                    if ($dispatchSource === 'envelope') {
                        // 100% fulfill from employee's envelope
                        $fromEnvelope = $this->envelopeService->consumeFromEnvelope(
                            $partRequest->engineer_id,
                            $item->part_id,
                            $totalQty,
                            $partRequest->ticket_id,
                            $partRequest->id,
                            $partRequest->machine_model_id,
                            $partRequest->machine_serial_no,
                            auth()->id()
                        );
                        $fromWarehouse = max(0, $totalQty - $fromEnvelope);
                    } elseif ($dispatchSource === 'split') {
                        // Custom partial allocation specified by admin
                        $allocatedEnv = isset($data['split_envelope_qtys'][$item->id])
                            ? min($totalQty, (int)$data['split_envelope_qtys'][$item->id])
                            : 0;

                        if ($allocatedEnv > 0) {
                            $fromEnvelope = $this->envelopeService->consumeFromEnvelope(
                                $partRequest->engineer_id,
                                $item->part_id,
                                $allocatedEnv,
                                $partRequest->ticket_id,
                                $partRequest->id,
                                $partRequest->machine_model_id,
                                $partRequest->machine_serial_no,
                                auth()->id()
                            );
                        }
                        $fromWarehouse = max(0, $totalQty - $fromEnvelope);
                    } else {
                        // 100% fulfill from warehouse
                        $fromWarehouse = $totalQty;
                        $fromEnvelope = 0;
                    }

                    $totalFromWarehouse += $fromWarehouse;
                    $totalFromEnvelope += $fromEnvelope;

                    // Deduct from warehouse stock ledger if warehouse quantity > 0
                    if ($fromWarehouse > 0 && !empty($data['dispatch_location_id'])) {
                        $ledger = \App\Models\PartStockLedger::firstOrCreate(
                            ['part_id' => $item->part_id, 'location_id' => $data['dispatch_location_id']],
                            ['qty_on_hand' => 0, 'qty_reserved' => 0]
                        );

                        // Auto-adjust shortfall seamlessly
                        if ($ledger->qty_on_hand < $fromWarehouse) {
                            $shortfall = $fromWarehouse - $ledger->qty_on_hand;
                            $this->stock->credit(
                                partId: $item->part_id,
                                locationId: $data['dispatch_location_id'],
                                qty: $shortfall,
                                type: 'adjustment',
                                referenceType: 'part_request',
                                referenceId: $partRequest->id,
                                note: "Auto-stock adjustment for dispatch on {$partRequest->request_number}"
                            );
                        }

                        $this->stock->debit(
                            partId: $item->part_id,
                            locationId: $data['dispatch_location_id'],
                            qty: $fromWarehouse,
                            type: 'dispatch_out',
                            referenceType: 'part_request',
                            referenceId: $partRequest->id,
                            note: "Dispatch for {$partRequest->request_number}"
                        );
                    }

                    // Update item quantities
                    $item->update([
                        'qty_from_envelope' => $item->qty_from_envelope + $fromEnvelope,
                        'qty_dispatched'    => $fromWarehouse,
                    ]);
                }

                $courier = $data['dispatch_courier'] ?? ($totalFromWarehouse === 0 ? 'In-Hand Envelope Stock' : 'Direct Handover');
                $tracking = $data['dispatch_tracking_number'] ?? ($totalFromWarehouse === 0 ? 'ENV-FLOAT-DEDUCT' : 'HAND-DELIVERY');

                $partRequest->update([
                    'dispatch_location_id'     => $data['dispatch_location_id'] ?? null,
                    'dispatch_courier'         => $courier,
                    'dispatch_tracking_number' => $tracking,
                    'status'                   => 'dispatched',
                    'dispatched_by_id'         => auth()->id(),
                    'dispatched_at'            => now(),
                    'faulty_return_status'     => 'pending_return',
                ]);
            });

            \App\Services\NotificationService::notifyPartRequestDispatched($partRequest);
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()->route('parts.requests.show', $partRequest)->with('success', 'Parts dispatched successfully. Stock/envelope deductions completed.');
    }

    public function streamVideo(PartRequest $partRequest)
    {
        if (!$partRequest->fault_video_path) {
            abort(404, 'No video uploaded for this request.');
        }

        $path = Storage::disk('public')->path($partRequest->fault_video_path);
        if (!file_exists($path)) {
            $altPath = public_path('storage/' . $partRequest->fault_video_path);
            if (file_exists($altPath)) {
                $path = $altPath;
            } else {
                abort(404, 'Video file not found.');
            }
        }

        $mime = mime_content_type($path) ?: 'video/mp4';
        return response()->file($path, [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function downloadVideo(PartRequest $partRequest)
    {
        if (!$partRequest->fault_video_path) {
            abort(404, 'No video uploaded for this request.');
        }

        $path = Storage::disk('public')->path($partRequest->fault_video_path);
        if (!file_exists($path)) {
            $altPath = public_path('storage/' . $partRequest->fault_video_path);
            if (file_exists($altPath)) {
                $path = $altPath;
            } else {
                abort(404, 'Video file not found.');
            }
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'mp4';
        $downloadName = 'Fault_Video_' . $partRequest->request_number . '.' . $extension;

        return response()->download($path, $downloadName);
    }

    /**
     * Edit / Replace fault video for a part request and update record.
     */
    public function updateVideo(Request $request, PartRequest $partRequest)
    {
        $user = auth()->user();
        if ($user->isEngineer() && $partRequest->engineer_id !== $user->id) {
            abort(403, 'Unauthorized to update video for this part request.');
        }

        $request->validate([
            'fault_video' => 'required|file|mimes:mp4,mov,avi,wmv,webm,mkv|max:102400',
        ]);

        $videoPath = $request->file('fault_video')->store('part-requests/videos', 'public');

        try {
            $sourcePath = Storage::disk('public')->path($videoPath);
            $publicTarget = public_path('storage/' . $videoPath);
            if (!file_exists(dirname($publicTarget))) {
                @mkdir(dirname($publicTarget), 0777, true);
            }
            @copy($sourcePath, $publicTarget);
        } catch (\Throwable $e) {}

        // Delete old video if different
        if ($partRequest->fault_video_path && $partRequest->fault_video_path !== $videoPath) {
            try {
                Storage::disk('public')->delete($partRequest->fault_video_path);
            } catch (\Throwable $e) {}
        }

        $partRequest->update([
            'fault_video_path' => $videoPath,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Fault diagnostic video updated and verified successfully.',
                'path' => $videoPath,
                'stream_url' => route('parts.requests.video', $partRequest),
                'download_url' => route('parts.requests.video.download', $partRequest),
            ]);
        }

        return back()->with('success', 'Fault diagnostic video updated successfully.');
    }

    public function verifyFaultyReturn(Request $request, PartRequest $partRequest)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $data = $request->validate([
            'faulty_return_status'           => 'required|in:returned,waived',
            'faulty_return_location_id'      => 'required_if:faulty_return_status,returned|nullable|exists:locations,id',
            'faulty_return_courier_tracking' => 'nullable|string|max:100',
            'faulty_return_remarks'          => 'nullable|string',
        ], [
            'faulty_return_status.required'      => 'Please select whether the faulty part was returned or waived.',
            'faulty_return_location_id.required_if' => 'Please select the warehouse or office receiving location.',
        ]);

        $partRequest->update([
            'faulty_return_status'           => $data['faulty_return_status'],
            'faulty_returned_at'             => now(),
            'faulty_return_received_by_id'   => auth()->id(),
            'faulty_return_location_id'      => $data['faulty_return_location_id'] ?? null,
            'faulty_return_courier_tracking' => $data['faulty_return_courier_tracking'] ?? null,
            'faulty_return_remarks'          => $data['faulty_return_remarks'] ?? null,
        ]);

        $msg = $data['faulty_return_status'] === 'returned'
            ? 'Faulty parts received and verified back into office/warehouse.'
            : 'Faulty parts return requirement waived.';

        return redirect()->route('parts.requests.show', $partRequest)->with('success', $msg);
    }

    public function gatePass(PartRequest $partRequest)
    {
        $partRequest->load([
            'ticket', 'engineer', 'machineModel', 'items.part',
            'stockVerifiedBy', 'approvedBy', 'dispatchedBy', 'dispatchLocation',
        ]);

        return view('parts.requests.gate_pass', compact('partRequest'));
    }
}
