<?php

namespace App\Http\Controllers;

use App\Models\PmMachine;
use App\Models\PmSchedule;
use App\Models\User;
use Illuminate\Http\Request;

class PmScheduleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $query = PmSchedule::with([
            'machine.machineModel',
            'machine.assignedEngineer',
            'assignedEngineer',
            'records' => fn($q) => $q->with('performedBy')->latest('performed_at'),
        ])->where('is_active', true);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhereHas('machine', function ($mq) use ($s) {
                      $mq->where('serial_number', 'like', "%{$s}%")
                         ->orWhere('location', 'like', "%{$s}%")
                         ->orWhere('bank_name', 'like', "%{$s}%");
                  });
            });
        }

        if ($request->filled('machine')) {
            $query->where('pm_machine_id', $request->machine);
        }
        if ($request->filled('engineer')) {
            $query->where(function ($q) use ($request) {
                $q->where('assigned_engineer_id', $request->engineer)
                  ->orWhere(function ($q2) use ($request) {
                      $q2->whereNull('assigned_engineer_id')
                         ->whereHas('machine', fn($m) => $m->where('assigned_engineer_id', $request->engineer));
                  });
            });
        }
        if ($request->filter === 'overdue') {
            $query->where('next_due_date', '<', now()->toDateString());
        } elseif ($request->filter === 'due_soon') {
            $query->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
        }

        // Date range filtering
        $dateField = $request->get('date_type', 'due_date') === 'last_done' ? 'last_performed_at' : 'next_due_date';

        if ($request->filled('month')) {
            $parts = explode('-', $request->month);
            if (count($parts) === 2) {
                $year = (int)$parts[0];
                $month = (int)$parts[1];
                $query->whereYear($dateField, $year)->whereMonth($dateField, $month);
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate($dateField, '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate($dateField, '<=', $request->to_date);
        }

        $schedules = $query->orderBy('next_due_date')->paginate(25)->withQueryString();
        $machines  = PmMachine::where('is_active', true)->orderBy('serial_number')->get();
        $engineers = User::where('role', 'engineer')->orderBy('name')->get();

        // Stats
        $overdueCount  = PmSchedule::where('is_active', true)->where('next_due_date', '<', now()->toDateString())->count();
        $dueSoonCount  = PmSchedule::where('is_active', true)
            ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])->count();
        $totalSchedules = PmSchedule::where('is_active', true)->count();

        return view('pm.schedules.index', compact('schedules', 'machines', 'engineers', 'overdueCount', 'dueSoonCount', 'totalSchedules'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $machines  = PmMachine::with('machineModel')->where('is_active', true)->orderBy('serial_number')->get();
        $engineers = User::where('role', 'engineer')->orderBy('name')->get();
        return view('pm.schedules.create', compact('machines', 'engineers'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $data = $request->validate([
            'pm_machine_id'        => 'required|exists:pm_machines,id',
            'title'                => 'required|string|max:255',
            'frequency_days'       => 'required|integer|min:1',
            'assigned_engineer_id' => 'nullable|exists:users,id',
            'next_due_date'        => 'required|date',
        ]);

        $schedule = PmSchedule::create($data);

        if ($schedule->next_due_date <= now()->addDays(3)->toDateString()) {
            \App\Services\NotificationService::notifyPmDue($schedule);
        }

        return redirect()->route('pm.schedules.index')
            ->with('success', 'Maintenance schedule created.');
    }

    public function edit(PmSchedule $schedule)
    {
        abort_unless(auth()->user()->isSuperior(), 403);
        $machines  = PmMachine::with('machineModel')->where('is_active', true)->orderBy('serial_number')->get();
        $engineers = User::where('role', 'engineer')->orderBy('name')->get();
        return view('pm.schedules.edit', compact('schedule', 'machines', 'engineers'));
    }

    public function update(Request $request, PmSchedule $schedule)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $data = $request->validate([
            'pm_machine_id'        => 'required|exists:pm_machines,id',
            'title'                => 'required|string|max:255',
            'frequency_days'       => 'required|integer|min:1',
            'assigned_engineer_id' => 'nullable|exists:users,id',
            'next_due_date'        => 'required|date',
            'is_active'            => 'boolean',
        ]);

        $schedule->update($data);

        return redirect()->route('pm.schedules.index')
            ->with('success', 'Schedule updated.');
    }

    /** Quick AJAX reassign from the schedules index table */
    public function reassign(Request $request, PmSchedule $schedule)
    {
        abort_unless(auth()->user()->isSuperior(), 403);

        $request->validate(['assigned_engineer_id' => 'nullable|exists:users,id']);
        $schedule->update(['assigned_engineer_id' => $request->assigned_engineer_id]);

        return response()->json(['success' => true]);
    }
}
