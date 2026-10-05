<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\MachineModel;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartsController extends Controller
{
    // ─── PARTS CATALOG ───────────────────────────────────────────────────────

    public function partsIndex(Request $request)
    {
        $query = Part::query();
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('part_number', 'like', "%{$request->search}%");
            });
        }
        if ($request->active !== null && $request->active !== '') {
            $query->where('is_active', $request->active == '1');
        }
        $parts = $query->orderBy('name')->paginate(25)->withQueryString();
        return view('parts.master.parts.index', compact('parts'));
    }

    public function partsCreate()
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $models = MachineModel::orderBy('name')->get();
        return view('parts.master.parts.form', compact('models'));
    }

    public function partsStore(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'part_number' => 'required|string|max:100|unique:parts',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit'        => 'required|string|max:30',
            'unit_cost'   => 'nullable|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'model_ids'   => 'nullable|array',
            'model_ids.*' => 'exists:machine_models,id',
        ]);
        $part = Part::create(array_merge($data, ['is_active' => true]));
        if (!empty($data['model_ids'])) {
            $part->machineModels()->sync($data['model_ids']);
        }
        return redirect()->route('parts.master.parts')->with('success', 'Part "' . $part->name . '" created successfully.');
    }

    public function partsEdit(Part $part)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $models = MachineModel::orderBy('name')->get();
        $selectedModelIds = $part->machineModels()->pluck('machine_models.id')->toArray();
        return view('parts.master.parts.form', compact('part', 'models', 'selectedModelIds'));
    }

    public function partsUpdate(Request $request, Part $part)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'part_number'   => 'required|string|max:100|unique:parts,part_number,' . $part->id,
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'unit'          => 'required|string|max:30',
            'unit_cost'     => 'nullable|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'is_active'     => 'sometimes|boolean',
            'model_ids'     => 'nullable|array',
            'model_ids.*'   => 'exists:machine_models,id',
        ]);
        $part->update(array_merge($data, ['is_active' => $request->has('is_active')]));
        $part->machineModels()->sync($request->model_ids ?? []);
        return redirect()->route('parts.master.parts')->with('success', 'Part updated successfully.');
    }

    public function partsDestroy(Part $part)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $inUse = $part->requestItems()->exists() || $part->grnItems()->exists();
        if ($inUse) {
            return back()->with('error', 'Cannot delete part that has been used in requests or GRNs. Deactivate it instead.');
        }
        $part->machineModels()->detach();
        $part->delete();
        return redirect()->route('parts.master.parts')->with('success', 'Part deleted.');
    }

    public function partsToggleActive(Part $part)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $part->update(['is_active' => !$part->is_active]);
        return back()->with('success', 'Part ' . ($part->is_active ? 'activated' : 'deactivated') . '.');
    }

    // ─── MACHINE MODELS ───────────────────────────────────────────────────────

    public function modelsIndex()
    {
        $models = MachineModel::withCount(['parts', 'partRequests'])->orderBy('name')->get();
        return view('parts.master.models.index', compact('models'));
    }

    public function modelsStore(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'manufacturer' => 'nullable|string|max:100',
            'machine_type' => 'required|in:atm,cdm,pos,kiosk,other',
            'description'  => 'nullable|string',
        ]);
        $model = MachineModel::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Machine model "' . $model->name . '" added.');
    }

    public function modelsUpdate(Request $request, MachineModel $model)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'manufacturer' => 'nullable|string|max:100',
            'machine_type' => 'required|in:atm,cdm,pos,kiosk,other',
            'description'  => 'nullable|string',
        ]);
        $model->update($data);
        return back()->with('success', 'Machine model updated.');
    }

    public function modelsDestroy(MachineModel $model)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        if ($model->partRequests()->exists()) {
            return back()->with('error', 'Cannot delete model with existing part requests.');
        }
        $model->parts()->detach();
        $model->delete();
        return back()->with('success', 'Machine model deleted.');
    }

    public function modelsAttachPart(Request $request, MachineModel $model)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate(['part_id' => 'required|exists:parts,id', 'is_common' => 'sometimes|boolean']);
        $model->parts()->syncWithoutDetaching([$data['part_id'] => ['is_common' => $request->boolean('is_common')]]);
        return back()->with('success', 'Part attached to model.');
    }

    public function modelsDetachPart(MachineModel $model, Part $part)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $model->parts()->detach($part->id);
        return back()->with('success', 'Part removed from model.');
    }

    // ─── LOCATIONS ────────────────────────────────────────────────────────────

    public function locationsIndex()
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $locations = Location::withCount('grns')->orderBy('name')->get();
        return view('parts.master.locations.index', compact('locations'));
    }

    public function locationsStore(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'    => 'required|string|max:200',
            'city'    => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'type'    => 'required|in:office,warehouse,hub',
        ]);
        Location::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Location added.');
    }

    public function locationsUpdate(Request $request, Location $location)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'    => 'required|string|max:200',
            'city'    => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'type'    => 'required|in:office,warehouse,hub',
            'is_active' => 'sometimes|boolean',
        ]);
        $location->update(array_merge($data, ['is_active' => $request->has('is_active')]));
        return back()->with('success', 'Location updated.');
    }

    // ─── AJAX: search parts (for inline add to model) ────────────────────────
    public function searchParts(Request $request)
    {
        $parts = Part::where('is_active', true)
            ->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->q}%")
                  ->orWhere('part_number', 'like', "%{$request->q}%");
            })->limit(10)->get(['id', 'part_number', 'name', 'unit']);
        return response()->json($parts);
    }

    // ─── AJAX: search machine models ─────────────────────────────────────────
    public function searchModels(Request $request)
    {
        $models = MachineModel::where('is_active', true)
            ->where('name', 'like', "%{$request->q}%")
            ->limit(10)->get(['id', 'name', 'manufacturer', 'machine_type']);
        return response()->json($models);
    }

    // ─── AJAX: get compatible parts for a model ───────────────────────────────
    public function modelParts(MachineModel $model)
    {
        $parts = $model->parts()->where('is_active', true)->get(['parts.id', 'parts.part_number', 'parts.name', 'parts.unit', 'parts.unit_cost']);
        return response()->json($parts);
    }
}
