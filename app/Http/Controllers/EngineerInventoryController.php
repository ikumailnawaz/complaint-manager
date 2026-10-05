<?php

namespace App\Http\Controllers;

use App\Models\EngineerAdvanceTransaction;
use App\Models\EngineerInventory;
use App\Models\Location;
use App\Models\Part;
use App\Models\User;
use App\Services\EngineerEnvelopeService;
use Illuminate\Http\Request;

class EngineerInventoryController extends Controller
{
    public function __construct(
        protected EngineerEnvelopeService $envelopeService
    ) {}

    /**
     * Dashboard / registry of all engineer advance envelopes and consumption history.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isEngineer = $user->isEngineer();

        // Query for current stock balances
        $stockQuery = EngineerInventory::with(['engineer', 'part']);

        // Query for consumptions ("Where & How Much Used")
        $usageQuery = EngineerAdvanceTransaction::with([
            'engineer', 'part', 'ticket', 'partRequest', 'machineModel',
        ])->where('type', 'consumed_complaint')->latest();

        // Query for all transactions
        $allTxQuery = EngineerAdvanceTransaction::with([
            'engineer', 'part', 'sourceLocation', 'destinationLocation', 'ticket', 'createdBy',
        ])->latest();

        if ($isEngineer) {
            $stockQuery->where('engineer_id', $user->id);
            $usageQuery->where('engineer_id', $user->id);
            $allTxQuery->where('engineer_id', $user->id);
            $selectedEngineer = $user;
        } else {
            if ($request->filled('engineer_id')) {
                $stockQuery->where('engineer_id', $request->engineer_id);
                $usageQuery->where('engineer_id', $request->engineer_id);
                $allTxQuery->where('engineer_id', $request->engineer_id);
                $selectedEngineer = User::find($request->engineer_id);
            } else {
                $selectedEngineer = null;
            }
        }

        if ($request->filled('part_id')) {
            $stockQuery->where('part_id', $request->part_id);
            $usageQuery->where('part_id', $request->part_id);
            $allTxQuery->where('part_id', $request->part_id);
        }

        if ($request->filled('from_date')) {
            $usageQuery->whereDate('created_at', '>=', $request->from_date);
            $allTxQuery->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $usageQuery->whereDate('created_at', '<=', $request->to_date);
            $allTxQuery->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $s = '%' . trim($request->search) . '%';
            $stockQuery->whereHas('part', fn($p) => $p->where('name', 'like', $s)->orWhere('part_number', 'like', $s));
            $usageQuery->where(function ($q) use ($s) {
                $q->whereHas('part', fn($p) => $p->where('name', 'like', $s)->orWhere('part_number', 'like', $s))
                  ->orWhereHas('ticket', fn($t) => $t->where('ticket_no', 'like', $s)->orWhere('bank_name', 'like', $s))
                  ->orWhere('machine_serial_no', 'like', $s);
            });
            $allTxQuery->where(function ($q) use ($s) {
                $q->whereHas('part', fn($p) => $p->where('name', 'like', $s)->orWhere('part_number', 'like', $s))
                  ->orWhere('notes', 'like', $s);
            });
        }

        // Summary Stats
        $statScope = $isEngineer
            ? EngineerInventory::where('engineer_id', $user->id)
            : ($selectedEngineer ? EngineerInventory::where('engineer_id', $selectedEngineer->id) : EngineerInventory::query());

        $totalOnHand = (int) (clone $statScope)->sum('qty_on_hand');
        $totalAllocated = (int) (clone $statScope)->sum('qty_allocated');
        $totalUsed = (int) (clone $statScope)->sum('qty_used');
        $activeEngineersCount = EngineerInventory::where('qty_on_hand', '>', 0)->distinct('engineer_id')->count('engineer_id');

        $envelopes = $stockQuery->orderBy('engineer_id')->orderBy('part_id')->paginate(25, ['*'], 'envelopes_page');
        $usages = $usageQuery->paginate(25, ['*'], 'usage_page');
        $transactions = $allTxQuery->paginate(25, ['*'], 'tx_page');

        $engineers = User::where('role', 'engineer')->orderBy('name')->get(['id', 'name', 'phone_whatsapp', 'base_city']);
        $parts = Part::where('is_active', true)->orderBy('name')->get(['id', 'name', 'part_number']);
        $locations = Location::where('is_active', true)->orderBy('name')->get(['id', 'name', 'city']);

        $activeTab = $request->get('tab', 'envelopes');

        return view('parts.envelopes.index', compact(
            'envelopes', 'usages', 'transactions',
            'totalOnHand', 'totalAllocated', 'totalUsed', 'activeEngineersCount',
            'engineers', 'parts', 'locations',
            'selectedEngineer', 'isEngineer', 'activeTab'
        ));
    }

    /**
     * Lend / Issue advance parts to an engineer.
     */
    public function lend(Request $request)
    {
        if (auth()->user()->isEngineer()) {
            abort(403, 'Unauthorized. Only managers and store supervisors can lend advance stock.');
        }

        $data = $request->validate([
            'engineer_id'        => 'required|exists:users,id',
            'source_location_id' => 'required|exists:locations,id',
            'part_id'            => 'required|exists:parts,id',
            'qty'                => 'required|integer|min:1',
            'notes'              => 'nullable|string|max:255',
        ]);

        $this->envelopeService->lendAdvanceStock(
            $data['engineer_id'],
            $data['source_location_id'],
            $data['part_id'],
            $data['qty'],
            $data['notes'] ?? 'Issued advance float',
            auth()->id()
        );

        $eng = User::find($data['engineer_id']);
        $part = Part::find($data['part_id']);

        return redirect()->route('parts.envelopes.index', ['engineer_id' => $data['engineer_id']])
            ->with('success', "Successfully lent {$data['qty']} unit(s) of {$part->name} to {$eng->name}'s Advance Envelope.");
    }

    /**
     * Return unused advance parts from an engineer's envelope back into a warehouse.
     */
    public function returnToStore(Request $request)
    {
        $data = $request->validate([
            'engineer_id'             => 'required|exists:users,id',
            'destination_location_id' => 'required|exists:locations,id',
            'part_id'                 => 'required|exists:parts,id',
            'qty'                     => 'required|integer|min:1',
            'notes'                   => 'nullable|string|max:255',
        ]);

        // Engineer can only return their own stock; superior can return for any
        if (auth()->user()->isEngineer() && auth()->id() != $data['engineer_id']) {
            abort(403, 'Unauthorized.');
        }

        try {
            $this->envelopeService->returnToWarehouse(
                $data['engineer_id'],
                $data['destination_location_id'],
                $data['part_id'],
                $data['qty'],
                $data['notes'] ?? 'Returned to warehouse',
                auth()->id()
            );

            $part = Part::find($data['part_id']);
            return redirect()->back()->with('success', "Returned {$data['qty']} unit(s) of {$part->name} to warehouse inventory.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
