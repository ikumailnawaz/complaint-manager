@extends('layouts.app')

@section('title', 'Machine — ' . $machine->serial_number)

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Flash --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm font-semibold">
        <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div class="flex items-center space-x-4">
            <a href="{{ route('pm.machines.index') }}" class="text-slate-400 hover:text-slate-700 transition text-xl">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-black text-slate-900">{{ $machine->machineModel->name }}</h1>
                    @if($machine->is_overdue)
                        <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold animate-pulse">🔴 OVERDUE</span>
                    @elseif($machine->is_due_soon)
                        <span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">🟡 Due Soon</span>
                    @else
                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">✅ OK</span>
                    @endif
                </div>
                <p class="text-slate-500 text-sm font-mono mt-0.5">SN: {{ $machine->serial_number }}
                    @if($machine->asset_tag) · Asset: {{ $machine->asset_tag }} @endif
                </p>
            </div>
        </div>
        <a href="{{ route('pm.machines.edit', $machine) }}" class="inline-flex items-center space-x-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition text-sm">
            <i class="fa-solid fa-pen-to-square"></i><span>Edit</span>
        </a>
    </div>

    <div class="grid grid-cols-3 gap-6">

        {{-- Machine Info --}}
        <div class="col-span-1 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Machine Info</h3>
                <div class="space-y-2 text-sm">
                    <div><span class="text-slate-400">Manufacturer:</span> <span class="font-semibold text-slate-800">{{ $machine->machineModel->manufacturer ?? '—' }}</span></div>
                    <div><span class="text-slate-400">Type:</span> <span class="font-semibold text-slate-800 capitalize">{{ $machine->machineModel->machine_type ?? '—' }}</span></div>
                    <div><span class="text-slate-400">Bank:</span> <span class="font-semibold text-slate-800">{{ $machine->bank_name ?? '—' }}</span></div>
                    <div><span class="text-slate-400">Location:</span> <span class="font-semibold text-slate-800">{{ $machine->location }}</span></div>
                    <div><span class="text-slate-400">Installed:</span> <span class="font-semibold text-slate-800">{{ $machine->installed_at?->format('d M Y') ?? '—' }}</span></div>
                    <div><span class="text-slate-400">Engineer:</span> <span class="font-semibold text-slate-800">{{ $machine->assignedEngineer?->name ?? '—' }}</span></div>
                </div>
                @if($machine->notes)
                <div class="pt-2 border-t border-slate-100 text-xs text-slate-500">{{ $machine->notes }}</div>
                @endif
            </div>

            {{-- Active Schedules --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wider">Active Schedules</h3>
                    <a href="{{ route('pm.schedules.create') }}?machine={{ $machine->id }}" class="text-xs text-teal-600 hover:underline font-bold">+ Add</a>
                </div>
                @forelse($machine->schedules->where('is_active', true) as $sched)
                <div class="border border-slate-100 rounded-xl p-3 space-y-1 {{ $sched->is_overdue ? 'border-red-200 bg-red-50' : ($sched->is_due_soon ? 'border-amber-200 bg-amber-50' : '') }}">
                    <div class="font-semibold text-slate-800 text-sm">{{ $sched->title }}</div>
                    <div class="text-xs text-slate-500">{{ $sched->frequency_label }}</div>
                    <div class="text-xs font-bold {{ $sched->is_overdue ? 'text-red-600' : ($sched->is_due_soon ? 'text-amber-600' : 'text-emerald-600') }}">
                        Next: {{ $sched->next_due_date?->format('d M Y') ?? '—' }}
                        @if($sched->is_overdue) ({{ abs($sched->days_until_due) }} days overdue)
                        @elseif($sched->days_until_due >= 0) (in {{ $sched->days_until_due }} days)
                        @endif
                    </div>
                    <div class="text-xs text-slate-400">Engineer: {{ $sched->responsible_engineer?->name ?? 'Machine default' }}</div>
                    <a href="{{ route('pm.schedules.edit', $sched) }}" class="text-xs text-slate-400 hover:text-teal-600">Edit schedule →</a>
                </div>
                @empty
                <p class="text-sm text-slate-400">No active schedules. <a href="{{ route('pm.schedules.create') }}" class="text-teal-600 hover:underline">Add one →</a></p>
                @endforelse
            </div>
        </div>

        {{-- Full Service History --}}
        <div class="col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-700">Complete Service History</h3>
                    <span class="text-xs text-slate-400">{{ $machine->records->count() }} records</span>
                </div>

                @if($machine->records->isEmpty())
                <div class="py-14 text-center text-slate-400">
                    <i class="fa-solid fa-clipboard-list text-4xl mb-3 opacity-30"></i>
                    <p class="font-semibold">No service records yet.</p>
                </div>
                @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600">Date</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600">Task</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600">Engineer</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600">Status</th>
                            <th class="text-left px-4 py-3 font-semibold text-slate-600">Doc</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($machine->records as $record)
                        <tr class="hover:bg-slate-50 transition {{ $record->status === 'missed' ? 'bg-red-50' : ($record->is_overdue ? 'bg-amber-50' : '') }}">
                            <td class="px-4 py-3 text-slate-700">
                                @if($record->performed_at)
                                    <div class="font-semibold">{{ $record->performed_at->format('d M Y') }}</div>
                                    <div class="text-xs text-slate-400">{{ $record->performed_at->format('h:i A') }}</div>
                                @else
                                    <div class="text-slate-400">—</div>
                                @endif
                                <div class="text-xs text-slate-400">Due: {{ $record->due_date->format('d M Y') }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <div class="font-medium">{{ $record->schedule?->title ?? '—' }}</div>
                                @if($record->notes)
                                    <div class="text-xs text-slate-400 mt-0.5 truncate max-w-48" title="{{ $record->notes }}">{{ $record->notes }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $record->performedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($record->status === 'completed' && !$record->is_overdue)
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">✅ On Time</span>
                                @elseif($record->status === 'completed' && $record->is_overdue)
                                    <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">⚠ Late</span>
                                @else
                                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">✗ Missed</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-2">
                                    @if($record->hasDocument())
                                        <a href="{{ route('pm.records.view', $record) }}" target="_blank" class="inline-flex items-center space-x-1 text-teal-600 hover:text-teal-800 text-xs font-bold bg-teal-50 hover:bg-teal-100 px-2.5 py-1 rounded-lg border border-teal-200 transition" title="View Document in Browser">
                                            <i class="fa-solid fa-file-lines text-teal-500"></i>
                                            <span>View</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-teal-400"></i>
                                        </a>
                                        <a href="{{ route('pm.records.download', $record) }}" class="text-slate-400 hover:text-slate-600 text-xs p-1" title="Download">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    @else
                                        <span class="text-slate-300 text-xs">—</span>
                                    @endif

                                    <a href="{{ route('pm.records.edit', $record) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold p-1" title="Edit this service record">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
