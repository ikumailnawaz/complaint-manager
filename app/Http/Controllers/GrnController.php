<?php

namespace App\Http\Controllers;

use App\Models\Grn;
use App\Models\GrnItem;
use App\Models\Location;
use App\Models\Part;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrnController extends Controller
{
    public function __construct(private StockLedgerService $stock) {}

    public function index(Request $request)
    {
        $query = Grn::with(['location', 'createdBy'])->orderByDesc('created_at');
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('grn_number', 'like', "%{$request->search}%")
                  ->orWhere('supplier_name', 'like', "%{$request->search}%");
            });
        }
        if ($request->status) $query->where('status', $request->status);
        if ($request->location_id) $query->where('location_id', $request->location_id);
        $grns = $query->paginate(20)->withQueryString();
        $locations = Location::where('is_active', true)->orderBy('name')->get();
        return view('parts.grn.index', compact('grns', 'locations'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $locations = Location::where('is_active', true)->orderBy('name')->get();
        $parts = Part::where('is_active', true)->orderBy('name')->get();
        return view('parts.grn.create', compact('locations', 'parts'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $data = $request->validate([
            'location_id'    => 'required|exists:locations,id',
            'supplier_name'  => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date'   => 'nullable|date',
            'received_at'    => 'required|date',
            'remarks'        => 'nullable|string',
            'items'          => 'required|array|min:1',
            'items.*.part_id'      => 'required|exists:parts,id',
            'items.*.qty_received' => 'required|integer|min:1',
            'items.*.unit_cost'    => 'nullable|numeric|min:0',
            'items.*.condition'    => 'nullable|in:new,refurbished,used',
            'items.*.batch_number' => 'nullable|string|max:100',
        ]);

        $grn = null;

        DB::transaction(function () use ($data, &$grn) {
            $grn = Grn::create([
                'grn_number'     => Grn::generateGrnNumber(),
                'location_id'    => $data['location_id'],
                'supplier_name'  => $data['supplier_name'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date'   => $data['invoice_date'] ?? null,
                'received_at'    => $data['received_at'],
                'status'         => 'draft',
                'remarks'        => $data['remarks'] ?? null,
                'created_by_id'  => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                $unitCost = $item['unit_cost'] ?? 0;
                GrnItem::create([
                    'grn_id'       => $grn->id,
                    'part_id'      => $item['part_id'],
                    'qty_received' => $item['qty_received'],
                    'unit_cost'    => $unitCost,
                    'total_cost'   => $unitCost * $item['qty_received'],
                    'condition'    => $item['condition'] ?? 'new',
                    'batch_number' => $item['batch_number'] ?? null,
                ]);
            }
        });

        return redirect()->route('parts.grn.show', $grn)
            ->with('success', 'GRN created as draft. Review and confirm to update stock.');
    }

    public function show(Grn $grn)
    {
        $grn->load(['location', 'createdBy', 'confirmedBy', 'items.part']);
        return view('parts.grn.show', compact('grn'));
    }

    public function confirm(Grn $grn)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        if ($grn->status !== 'draft') {
            return back()->with('error', 'GRN is already confirmed.');
        }

        DB::transaction(function () use ($grn) {
            foreach ($grn->items as $item) {
                $this->stock->credit(
                    partId: $item->part_id,
                    locationId: $grn->location_id,
                    qty: $item->qty_received,
                    type: 'grn_in',
                    referenceType: 'grn',
                    referenceId: $grn->id,
                    note: "GRN #{$grn->grn_number}"
                );
            }

            $grn->update([
                'status'          => 'confirmed',
                'confirmed_by_id' => auth()->id(),
                'confirmed_at'    => now(),
            ]);
        });

        return redirect()->route('parts.grn.show', $grn)->with('success', 'GRN confirmed. Stock has been updated.');
    }

    public function destroy(Grn $grn)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        if ($grn->status === 'confirmed') {
            return back()->with('error', 'Cannot delete a confirmed GRN.');
        }
        $grn->items()->delete();
        $grn->delete();
        return redirect()->route('parts.grn.index')->with('success', 'Draft GRN deleted.');
    }
}
