<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Part;
use App\Models\PartStockLedger;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class PartStockController extends Controller
{
    public function index(Request $request)
    {
        $locations = Location::where('is_active', true)->orderBy('name')->get();
        $models = \App\Models\MachineModel::where('is_active', true)->orderBy('name')->get();
        $parts = Part::where('is_active', true)->with(['stockLedgers', 'machineModels'])->orderBy('name')->get();

        // Build grid: part_id => location_id => ledger
        $grid = [];
        foreach ($parts as $part) {
            foreach ($part->stockLedgers as $ledger) {
                $grid[$part->id][$ledger->location_id] = $ledger;
            }
        }

        // Stats
        $totalParts = $parts->count();
        $outOfStock = $parts->filter(fn($p) => $p->totalStock() <= 0)->count();
        $lowStock   = $parts->filter(fn($p) => $p->isLowStock() && $p->totalStock() > 0)->count();
        $totalValue = PartStockLedger::join('parts', 'parts.id', '=', 'part_stock_ledgers.part_id')
            ->selectRaw('SUM(part_stock_ledgers.qty_on_hand * parts.unit_cost) as val')
            ->value('val') ?? 0;

        return view('parts.stock.index', compact(
            'locations', 'parts', 'models', 'grid',
            'totalParts', 'outOfStock', 'lowStock', 'totalValue'
        ));
    }

    public function movements(Request $request)
    {
        $query = StockMovement::with(['part', 'location', 'createdBy'])->orderByDesc('created_at');

        if ($request->part_id) $query->where('part_id', $request->part_id);
        if ($request->location_id) $query->where('location_id', $request->location_id);
        if ($request->type) $query->where('type', $request->type);
        if ($request->from_date) $query->whereDate('created_at', '>=', $request->from_date);
        if ($request->to_date) $query->whereDate('created_at', '<=', $request->to_date);

        $movements = $query->paginate(30)->withQueryString();
        $parts = Part::orderBy('name')->get(['id', 'name', 'part_number']);
        $locations = Location::orderBy('name')->get(['id', 'name']);

        return view('parts.stock.movements', compact('movements', 'parts', 'locations'));
    }

    public function adjustment(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'part_id'     => 'required|exists:parts,id',
            'location_id' => 'required|exists:locations,id',
            'qty_new'     => 'required|integer|min:0',
            'note'        => 'required|string|max:255',
        ]);

        $ledger = PartStockLedger::firstOrCreate(
            ['part_id' => $data['part_id'], 'location_id' => $data['location_id']],
            ['qty_on_hand' => 0, 'qty_reserved' => 0]
        );

        $diff = $data['qty_new'] - $ledger->qty_on_hand;
        $ledger->update(['qty_on_hand' => $data['qty_new'], 'updated_at' => now()]);

        StockMovement::create([
            'part_id'        => $data['part_id'],
            'location_id'    => $data['location_id'],
            'type'           => 'adjustment',
            'reference_type' => 'manual',
            'reference_id'   => null,
            'qty'            => $diff,
            'qty_after'      => $data['qty_new'],
            'note'           => $data['note'],
            'created_by_id'  => auth()->id(),
            'created_at'     => now(),
        ]);

        return back()->with('success', 'Stock adjusted successfully.');
    }
}
