<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Part;
use App\Models\PartTransfer;
use App\Models\PartTransferItem;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartTransferController extends Controller
{
    public function __construct(private StockLedgerService $stock) {}

    public function index(Request $request)
    {
        $query = PartTransfer::with(['fromLocation', 'toLocation', 'requestedBy'])->orderByDesc('created_at');
        if ($request->status) $query->where('status', $request->status);
        if ($request->location_id) {
            $query->where(function($q) use ($request) {
                $q->where('from_location_id', $request->location_id)
                  ->orWhere('to_location_id', $request->location_id);
            });
        }
        $transfers = $query->paginate(20)->withQueryString();
        $locations = Location::where('is_active', true)->orderBy('name')->get();
        return view('parts.transfers.index', compact('transfers', 'locations'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $locations = Location::where('is_active', true)->orderBy('name')->get();
        $parts = Part::where('is_active', true)->orderBy('name')->get();
        return view('parts.transfers.create', compact('locations', 'parts'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $data = $request->validate([
            'from_location_id' => 'required|exists:locations,id|different:to_location_id',
            'to_location_id'   => 'required|exists:locations,id',
            'remarks'          => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.part_id'  => 'required|exists:parts,id',
            'items.*.qty'      => 'required|integer|min:1',
        ]);

        $transfer = DB::transaction(function () use ($data) {
            $t = PartTransfer::create([
                'transfer_number'  => PartTransfer::generateTransferNumber(),
                'from_location_id' => $data['from_location_id'],
                'to_location_id'   => $data['to_location_id'],
                'status'           => 'pending',
                'requested_by_id'  => auth()->id(),
                'remarks'          => $data['remarks'] ?? null,
            ]);
            foreach ($data['items'] as $item) {
                PartTransferItem::create([
                    'part_transfer_id' => $t->id,
                    'part_id'          => $item['part_id'],
                    'qty'              => $item['qty'],
                ]);
            }
            return $t;
        });

        return redirect()->route('parts.transfers.show', $transfer)->with('success', 'Transfer initiated.');
    }

    public function show(PartTransfer $transfer)
    {
        $transfer->load(['fromLocation', 'toLocation', 'requestedBy', 'dispatchedBy', 'receivedBy', 'items.part']);
        return view('parts.transfers.show', compact('transfer'));
    }

    public function dispatch(PartTransfer $transfer)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        if ($transfer->status !== 'pending') return back()->with('error', 'Transfer cannot be dispatched.');

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $this->stock->debit(
                    partId: $item->part_id,
                    locationId: $transfer->from_location_id,
                    qty: $item->qty,
                    type: 'transfer_out',
                    referenceType: 'part_transfer',
                    referenceId: $transfer->id,
                    note: "Transfer #{$transfer->transfer_number} to " . $transfer->toLocation->name
                );
            }
            $transfer->update([
                'status'           => 'in_transit',
                'dispatched_by_id' => auth()->id(),
                'dispatched_at'    => now(),
            ]);
        });

        return redirect()->route('parts.transfers.show', $transfer)->with('success', 'Transfer dispatched. Stock debited from source.');
    }

    public function receive(Request $request, PartTransfer $transfer)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        if ($transfer->status !== 'in_transit') return back()->with('error', 'Transfer is not in transit.');

        $data = $request->validate([
            'received_qtys'   => 'required|array',
            'received_qtys.*' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($transfer, $data) {
            foreach ($transfer->items as $item) {
                $receivedQty = (int) ($data['received_qtys'][$item->id] ?? 0);
                $item->update(['qty_received' => $receivedQty]);
                if ($receivedQty > 0) {
                    $this->stock->credit(
                        partId: $item->part_id,
                        locationId: $transfer->to_location_id,
                        qty: $receivedQty,
                        type: 'transfer_in',
                        referenceType: 'part_transfer',
                        referenceId: $transfer->id,
                        note: "Transfer #{$transfer->transfer_number} from " . $transfer->fromLocation->name
                    );
                }
            }
            $transfer->update([
                'status'          => 'received',
                'received_by_id'  => auth()->id(),
                'received_at'     => now(),
            ]);
        });

        return redirect()->route('parts.transfers.show', $transfer)->with('success', 'Transfer received. Stock updated.');
    }

    public function cancel(PartTransfer $transfer)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        if (!in_array($transfer->status, ['pending'])) return back()->with('error', 'Only pending transfers can be cancelled.');
        $transfer->update(['status' => 'cancelled']);
        return redirect()->route('parts.transfers.index')->with('success', 'Transfer cancelled.');
    }
}
