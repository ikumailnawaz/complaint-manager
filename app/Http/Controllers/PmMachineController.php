<?php

namespace App\Http\Controllers;

use App\Models\MachineModel;
use App\Models\PmMachine;
use App\Models\User;
use Illuminate\Http\Request;

class PmMachineController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $query = PmMachine::with(['machineModel', 'assignedEngineer', 'schedules'])
            ->where('is_active', true);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('serial_number', 'like', "%$s%")
                  ->orWhere('location', 'like', "%$s%")
                  ->orWhere('bank_name', 'like', "%$s%")
                  ->orWhere('asset_tag', 'like', "%$s%");
            });
        }

        if ($request->filled('engineer')) {
            $query->where('assigned_engineer_id', $request->engineer);
        }

        if ($request->filter === 'overdue') {
            $query->whereHas('schedules', fn($q) =>
                $q->where('is_active', true)->where('next_due_date', '<', now()->toDateString())
            );
        } elseif ($request->filter === 'due_soon') {
            $query->whereHas('schedules', fn($q) =>
                $q->where('is_active', true)
                  ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            );
        }

        $machines  = $query->latest()->paginate(20)->withQueryString();
        $engineers = User::where('role', 'engineer')->where('is_available', true)->orderBy('name')->get();

        // Stats
        $totalMachines  = PmMachine::where('is_active', true)->count();
        $overdueCount   = PmMachine::where('is_active', true)
            ->whereHas('schedules', fn($q) =>
                $q->where('is_active', true)->where('next_due_date', '<', now()->toDateString())
            )->count();
        $dueSoonCount   = PmMachine::where('is_active', true)
            ->whereHas('schedules', fn($q) =>
                $q->where('is_active', true)
                  ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            )->count();

        return view('pm.machines.index', compact('machines', 'engineers', 'totalMachines', 'overdueCount', 'dueSoonCount'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $machineModels = MachineModel::where('is_active', true)->orderBy('name')->get();
        $engineers     = User::where('role', 'engineer')->orderBy('name')->get();
        return view('pm.machines.create', compact('machineModels', 'engineers'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $data = $request->validate([
            'machine_model_id'    => 'required|exists:machine_models,id',
            'serial_number'       => 'required|string|unique:pm_machines,serial_number',
            'asset_tag'           => 'nullable|string|max:100',
            'location'            => 'required|string|max:255',
            'bank_name'           => 'nullable|string|max:255',
            'installed_at'        => 'nullable|date',
            'assigned_engineer_id'=> 'nullable|exists:users,id',
            'notes'               => 'nullable|string',
        ]);

        $machine = PmMachine::create($data);

        return redirect()->route('pm.machines.show', $machine)
            ->with('success', "Machine {$machine->serial_number} registered successfully.");
    }

    public function show(PmMachine $machine)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $machine->load([
            'machineModel',
            'assignedEngineer',
            'schedules.assignedEngineer',
            'schedules.records.performedBy',
            'records' => fn($q) => $q->with('schedule', 'performedBy')->latest('performed_at')->limit(50),
        ]);

        $engineers = User::where('role', 'engineer')->orderBy('name')->get();

        return view('pm.machines.show', compact('machine', 'engineers'));
    }

    public function edit(PmMachine $machine)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $machineModels = MachineModel::where('is_active', true)->orderBy('name')->get();
        $engineers     = User::where('role', 'engineer')->orderBy('name')->get();
        return view('pm.machines.edit', compact('machine', 'machineModels', 'engineers'));
    }

    public function update(Request $request, PmMachine $machine)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $data = $request->validate([
            'machine_model_id'    => 'required|exists:machine_models,id',
            'serial_number'       => 'required|string|unique:pm_machines,serial_number,'.$machine->id,
            'asset_tag'           => 'nullable|string|max:100',
            'location'            => 'required|string|max:255',
            'bank_name'           => 'nullable|string|max:255',
            'installed_at'        => 'nullable|date',
            'assigned_engineer_id'=> 'nullable|exists:users,id',
            'notes'               => 'nullable|string',
            'is_active'           => 'boolean',
        ]);

        $machine->update($data);

        return redirect()->route('pm.machines.show', $machine)
            ->with('success', 'Machine updated successfully.');
    }
}
