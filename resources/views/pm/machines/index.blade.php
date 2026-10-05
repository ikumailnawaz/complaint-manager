@extends('layouts.app')

@section('title', 'Machine Registry')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3">
                <i class="fa-solid fa-desktop text-teal-600"></i>
                <span>Machine Registry</span>
            </h1>
            <p class="text-slate-500 text-sm mt-0.5">All registered physical machines tracked by serial number</p>
        </div>
        <a href="{{ route('pm.machines.create') }}" class="inline-flex items-center space-x-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow transition">
            <i class="fa-solid fa-plus"></i><span>Add Machine</span>
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center">
                <i class="fa-solid fa-desktop text-teal-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-900">{{ $totalMachines }}</div>
                <div class="text-xs text-slate-500 font-semibold uppercase">Total Machines</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-red-200 shadow-sm p-5 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                <i class="fa-solid fa-circle-exclamation text-red-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-red-600">{{ $overdueCount }}</div>
                <div class="text-xs text-slate-500 font-semibold uppercase">Overdue</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                <i class="fa-solid fa-clock text-amber-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-amber-600">{{ $dueSoonCount }}</div>
                <div class="text-xs text-slate-500 font-semibold uppercase">Due Within 7 Days</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-semibold text-slate-500 mb-1">Search</label>
            <input name="search" value="{{ request('search') }}" placeholder="Serial, location, bank…"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Engineer</label>
            <select name="engineer" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                <option value="">All Engineers</option>
                @foreach($engineers as $eng)
                    <option value="{{ $eng->id }}" @selected(request('engineer') == $eng->id)>{{ $eng->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Status</label>
            <select name="filter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                <option value="">All</option>
                <option value="overdue" @selected(request('filter') === 'overdue')>🔴 Overdue</option>
                <option value="due_soon" @selected(request('filter') === 'due_soon')>🟡 Due Soon</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-semibold hover:bg-slate-700">Filter</button>
        <a href="{{ route('pm.machines.index') }}" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-200">Reset</a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        @if($machines->isEmpty())
            <div class="py-20 text-center text-slate-400">
                <i class="fa-solid fa-desktop text-5xl mb-3 opacity-30"></i>
                <p class="font-semibold">No machines found.</p>
                <a href="{{ route('pm.machines.create') }}" class="mt-3 inline-block text-teal-600 hover:underline font-bold">Register the first machine →</a>
            </div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Serial #</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Model</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Location / Bank</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Assigned Engineer</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Next Due</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($machines as $machine)
                @php
                    $isOverdue  = $machine->is_overdue;
                    $isDueSoon  = $machine->is_due_soon;
                    $nextDue    = $machine->schedules->where('is_active', true)->sortBy('next_due_date')->first()?->next_due_date;
                @endphp
                <tr class="hover:bg-slate-50 transition {{ $isOverdue ? 'bg-red-50' : ($isDueSoon ? 'bg-amber-50' : '') }}">
                    <td class="px-4 py-3 font-mono font-bold text-slate-800">{{ $machine->serial_number }}</td>
                    <td class="px-4 py-3 text-slate-700">
                        <div class="font-semibold">{{ $machine->machineModel->name ?? '—' }}</div>
                        <div class="text-xs text-slate-400">{{ $machine->machineModel->machine_type ?? '' }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-slate-700 font-medium">{{ $machine->location }}</div>
                        @if($machine->bank_name)<div class="text-xs text-slate-400">{{ $machine->bank_name }}</div>@endif
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $machine->assignedEngineer?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $nextDue ? $nextDue->format('d M Y') : '—' }}</td>
                    <td class="px-4 py-3">
                        @if($isOverdue)
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">🔴 Overdue</span>
                        @elseif($isDueSoon)
                            <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">🟡 Due Soon</span>
                        @else
                            <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">✅ OK</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('pm.machines.show', $machine) }}" class="text-teal-600 hover:underline font-semibold text-xs">View History →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-slate-100">{{ $machines->links() }}</div>
        @endif
    </div>
</div>
@endsection
