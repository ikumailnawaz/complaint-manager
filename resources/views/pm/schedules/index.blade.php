@extends('layouts.app')

@section('title', 'PM Schedules')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3">
                <i class="fa-solid fa-calendar-check text-teal-600"></i>
                <span>PM Schedules &amp; Monthly Service</span>
            </h1>
            <p class="text-slate-500 text-sm mt-0.5">Track recurring maintenance, assigned engineers, and supporting documents</p>
        </div>
        <a href="{{ route('pm.schedules.create') }}" class="inline-flex items-center space-x-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow transition">
            <i class="fa-solid fa-plus"></i><span>Add Schedule</span>
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center">
                <i class="fa-solid fa-calendar-check text-teal-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-900">{{ $totalSchedules }}</div>
                <div class="text-xs text-slate-500 font-semibold uppercase">Active Schedules</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-red-200 shadow-sm p-5 flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                <i class="fa-solid fa-triangle-exclamation text-red-600 text-xl"></i>
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

    {{-- Flash --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm font-semibold">
        <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
    </div>
    @endif

    {{-- Enhanced Filter Bar with Date Filters --}}
    <form method="GET" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Search Keywords</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Search machine serial, location, bank, task title..." 
                           class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-sm"></i>
                </div>
            </div>

            {{-- Machine --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Machine</label>
                <select name="machine" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                    <option value="">All Machines</option>
                    @foreach($machines as $m)
                        <option value="{{ $m->id }}" @selected(request('machine') == $m->id)>
                            {{ $m->serial_number }} ({{ $m->machineModel->name ?? '' }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Engineer --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Assigned Engineer</label>
                <select name="engineer" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                    <option value="">All Engineers</option>
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}" @selected(request('engineer') == $eng->id)>{{ $eng->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Date Filters Row --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 pt-3 border-t border-slate-100 items-end">
            {{-- Date Type --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Date Target</label>
                <select name="date_type" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                    <option value="due_date" @selected(request('date_type') !== 'last_done')>Filter by Due Date</option>
                    <option value="last_done" @selected(request('date_type') === 'last_done')>Filter by Completion Date</option>
                </select>
            </div>

            {{-- Month Filter --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Month Filter</label>
                <input type="month" name="month" value="{{ request('month') }}"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
            </div>

            {{-- From Date --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
            </div>

            {{-- To Date --}}
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
            </div>

            {{-- Actions --}}
            <div class="flex items-center space-x-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-bold hover:bg-teal-700 transition shadow-sm">
                    <i class="fa-solid fa-filter mr-1"></i>Apply Filters
                </button>
                <a href="{{ route('pm.schedules.index') }}" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200 transition" title="Clear Filters">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                </a>
            </div>
        </div>
    </form>

    {{-- Schedules Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        @if($schedules->isEmpty())
            <div class="py-20 text-center text-slate-400">
                <i class="fa-solid fa-calendar-check text-5xl mb-3 opacity-30"></i>
                <p class="font-semibold text-base">No schedules match the criteria.</p>
                <a href="{{ route('pm.schedules.create') }}" class="mt-3 inline-block text-teal-600 hover:underline font-bold">Create new schedule →</a>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3.5 font-bold text-slate-700">Machine</th>
                        <th class="text-left px-4 py-3.5 font-bold text-slate-700">Task Title</th>
                        <th class="text-left px-4 py-3.5 font-bold text-slate-700">Frequency</th>
                        <th class="text-left px-4 py-3.5 font-bold text-slate-700">Assigned Engineer</th>
                        <th class="text-left px-4 py-3.5 font-bold text-slate-700">Next Due</th>
                        <th class="text-left px-4 py-3.5 font-bold text-slate-700">Last Done &amp; Supporting Doc</th>
                        <th class="text-right px-4 py-3.5 font-bold text-slate-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($schedules as $sched)
                    @php
                        $latestRecord = $sched->records->where('status', 'completed')->sortByDesc('performed_at')->first();
                        $isOverdue    = $sched->is_overdue;
                        $isDueSoon    = $sched->is_due_soon;
                        $recordsCount = $sched->records->count();
                    @endphp
                    <tr class="hover:bg-slate-50/80 transition {{ $isOverdue ? 'bg-red-50/20' : ($isDueSoon ? 'bg-amber-50/20' : '') }}">
                        {{-- Machine --}}
                        <td class="px-4 py-3.5">
                            <a href="{{ route('pm.machines.show', $sched->machine) }}" class="font-mono font-bold text-teal-700 hover:text-teal-900 hover:underline flex items-center space-x-1">
                                <span>{{ $sched->machine->serial_number }}</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-50"></i>
                            </a>
                            <div class="text-xs text-slate-600 font-medium">{{ $sched->machine->machineModel->name ?? '' }}</div>
                            <div class="text-[11px] text-slate-400 truncate max-w-xs">{{ $sched->machine->location }}</div>
                        </td>

                        {{-- Task --}}
                        <td class="px-4 py-3.5">
                            <span class="font-bold text-slate-900 block">{{ $sched->title }}</span>
                            @if($sched->machine->bank_name)
                                <span class="text-xs text-slate-500">{{ $sched->machine->bank_name }}</span>
                            @endif
                        </td>

                        {{-- Frequency --}}
                        <td class="px-4 py-3.5 text-slate-600 font-medium">
                            <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold">
                                {{ $sched->frequency_label }}
                            </span>
                        </td>

                        {{-- Assigned Engineer --}}
                        <td class="px-4 py-3.5">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-800 font-bold flex items-center justify-center text-xs shrink-0 shadow-sm border border-teal-200">
                                    {{ strtoupper(substr($sched->responsible_engineer?->name ?? 'U', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800 text-xs">
                                        {{ $sched->responsible_engineer?->name ?? 'Unassigned' }}
                                    </div>
                                    <div class="flex items-center space-x-1.5 mt-0.5">
                                        @if($sched->assigned_engineer_id)
                                            <span class="text-[10px] bg-teal-50 text-teal-700 px-1.5 py-0.2 rounded font-semibold border border-teal-200">Override</span>
                                        @elseif($sched->machine?->assignedEngineer)
                                            <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.2 rounded font-semibold border border-slate-200">Machine Default</span>
                                        @else
                                            <span class="text-[10px] bg-amber-50 text-amber-700 px-1.5 py-0.2 rounded font-semibold border border-amber-200">None</span>
                                        @endif

                                        {{-- Inline Quick Reassign Trigger --}}
                                        <button type="button" 
                                                onclick="openReassignModal({{ $sched->id }}, '{{ addslashes($sched->title) }}', '{{ addslashes($sched->machine->serial_number) }}', '{{ $sched->assigned_engineer_id ?? '' }}')"
                                                class="text-[11px] text-teal-600 hover:text-teal-800 font-bold hover:underline">
                                            Reassign
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Next Due --}}
                        <td class="px-4 py-3.5">
                            <div class="font-bold {{ $isOverdue ? 'text-red-600' : ($isDueSoon ? 'text-amber-600' : 'text-slate-800') }}">
                                {{ $sched->next_due_date?->format('d M Y') ?? '—' }}
                            </div>
                            @if($isOverdue)
                                <span class="px-1.5 py-0.2 bg-red-100 text-red-700 rounded text-[10px] font-bold">
                                    {{ abs($sched->days_until_due) }}d overdue
                                </span>
                            @elseif($isDueSoon)
                                <span class="px-1.5 py-0.2 bg-amber-100 text-amber-800 rounded text-[10px] font-bold">
                                    in {{ $sched->days_until_due }}d
                                </span>
                            @elseif($sched->days_until_due >= 0)
                                <span class="text-[11px] text-slate-400">
                                    in {{ $sched->days_until_due }} days
                                </span>
                            @endif
                        </td>

                        {{-- Last Done & Supporting Document --}}
                        <td class="px-4 py-3.5">
                            @if($latestRecord)
                                <div class="font-bold text-slate-800 text-xs">
                                    {{ $latestRecord->performed_at?->format('d M Y') ?? $sched->last_performed_at?->format('d M Y') }}
                                </div>
                                <div class="text-[11px] text-slate-500 flex items-center space-x-1 mt-0.5">
                                    <i class="fa-solid fa-user-check text-[10px] text-teal-600"></i>
                                    <span>By: {{ $latestRecord->performedBy?->name ?? 'Engineer' }}</span>
                                </div>
                                @if($latestRecord->hasDocument())
                                    <div class="mt-1 flex items-center space-x-1.5">
                                        <a href="{{ route('pm.records.view', $latestRecord) }}" target="_blank"
                                           class="inline-flex items-center space-x-1 text-teal-700 hover:text-teal-900 text-xs font-bold bg-teal-50 hover:bg-teal-100 px-2.5 py-0.5 rounded-lg border border-teal-200 transition shadow-sm"
                                           title="View Document in Browser">
                                            <i class="fa-solid fa-file-lines text-[11px] text-teal-600"></i>
                                            <span>View Doc</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-teal-500 ml-0.5"></i>
                                        </a>
                                        <a href="{{ route('pm.records.download', $latestRecord) }}" 
                                           class="text-slate-400 hover:text-slate-700 text-xs p-1" title="Download">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    </div>
                                @else
                                    <div class="mt-0.5 text-[11px] text-amber-600 italic">No doc attached</div>
                                @endif
                            @else
                                <span class="text-slate-400 text-xs italic">Never performed</span>
                            @endif
                        </td>

                        {{-- Action Buttons --}}
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end space-x-2">
                                {{-- View Monthly Docs & History Modal Trigger --}}
                                <button type="button" 
                                        onclick="openHistoryModal({{ $sched->id }})"
                                        class="inline-flex items-center space-x-1 px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition"
                                        title="View all monthly records & supporting documents">
                                    <i class="fa-solid fa-folder-open text-teal-600"></i>
                                    <span>Monthly Docs ({{ $recordsCount }})</span>
                                </button>

                                {{-- Edit Schedule --}}
                                <a href="{{ route('pm.schedules.edit', $sched) }}" 
                                   class="inline-flex items-center space-x-1 px-2.5 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <span>Edit</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $schedules->links() }}</div>
        @endif
    </div>
</div>

{{-- Reassign Engineer Modal --}}
<div id="reassign-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Reassign Engineer</h3>
            <button type="button" onclick="closeReassignModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="space-y-1">
            <div id="reassign-task-title" class="font-black text-slate-800 text-base"></div>
            <div id="reassign-machine-sn" class="text-xs text-slate-500 font-mono"></div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Select Responsible Engineer</label>
            <select id="reassign-engineer-select" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                <option value="">-- Revert to Machine Default --</option>
                @foreach($engineers as $eng)
                    <option value="{{ $eng->id }}">{{ $eng->name }} ({{ $eng->base_city }})</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
            <button type="button" onclick="closeReassignModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-200">
                Cancel
            </button>
            <button type="button" id="reassign-submit-btn" onclick="submitReassign()" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-sm font-bold shadow">
                Save Assignment
            </button>
        </div>
    </div>
</div>

{{-- Monthly Records & Supporting Documents Modal for each schedule --}}
@foreach($schedules as $sched)
<div id="history-modal-{{ $sched->id }}" class="history-modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-3xl w-full max-h-[85vh] flex flex-col overflow-hidden">
        {{-- Modal Header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <div>
                <h3 class="font-black text-slate-900 text-lg flex items-center space-x-2">
                    <span>{{ $sched->title }}</span>
                    <span class="text-xs font-mono bg-teal-100 text-teal-800 px-2 py-0.5 rounded-full font-bold">
                        {{ $sched->machine->serial_number }}
                    </span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ $sched->machine->location }} • {{ $sched->frequency_label }}</p>
            </div>
            <button type="button" onclick="closeHistoryModal({{ $sched->id }})" class="text-slate-400 hover:text-slate-700 text-xl p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="p-6 overflow-y-auto space-y-4 flex-1">
            @if($sched->records->isEmpty())
                <div class="py-12 text-center text-slate-400">
                    <i class="fa-solid fa-clipboard-list text-4xl mb-2 opacity-30"></i>
                    <p class="font-semibold">No service records logged yet for this schedule.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($sched->records->sortByDesc('due_date') as $rec)
                    <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm hover:border-teal-300 transition space-y-2">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="font-black text-slate-800 text-sm">
                                        Month: {{ $rec->due_date->format('F Y') }}
                                    </span>
                                    @if($rec->status === 'completed' && !$rec->is_overdue)
                                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">✅ On Time</span>
                                    @elseif($rec->status === 'completed')
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full text-xs font-bold">⚠ Late</span>
                                    @else
                                        <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-bold">✗ Missed</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 mt-1 flex items-center space-x-3">
                                    <span>Due: {{ $rec->due_date->format('d M Y') }}</span>
                                    @if($rec->performed_at)
                                        <span>•</span>
                                        <span>Performed: {{ $rec->performed_at->format('d M Y, h:i A') }}</span>
                                        <span>•</span>
                                        <span class="font-semibold text-slate-700">By: {{ $rec->performedBy?->name ?? 'Engineer' }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Actions for this monthly record --}}
                            <div class="flex items-center space-x-2 shrink-0">
                                @if($rec->hasDocument())
                                    <a href="{{ route('pm.records.view', $rec) }}" target="_blank"
                                       class="px-2.5 py-1 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold transition flex items-center space-x-1 shadow-sm">
                                        <i class="fa-solid fa-file-lines"></i>
                                        <span>View Doc</span>
                                    </a>
                                    <a href="{{ route('pm.records.download', $rec) }}"
                                       class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition" title="Download">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400 italic">No document</span>
                                @endif

                                <a href="{{ route('pm.records.edit', $rec) }}"
                                   class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold transition flex items-center space-x-1">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <span>Edit</span>
                                </a>
                            </div>
                        </div>

                        {{-- Record Notes --}}
                        @if($rec->notes)
                            <div class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="font-semibold text-slate-700">Notes:</span> {{ $rec->notes }}
                            </div>
                        @endif

                        {{-- Document Details Preview --}}
                        @if($rec->hasDocument())
                            <div class="text-[11px] text-slate-500 flex items-center space-x-1.5 pt-1">
                                <i class="fa-solid fa-paperclip text-slate-400"></i>
                                <span class="font-mono text-slate-700 font-semibold">{{ $rec->document_original_name ?? 'Attachment' }}</span>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Modal Footer --}}
        <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
            <span class="text-xs text-slate-400">{{ $recordsCount }} record(s) logged</span>
            <button type="button" onclick="closeHistoryModal({{ $sched->id }})" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition">
                Close
            </button>
        </div>
    </div>
</div>
@endforeach

<script>
let currentReassignScheduleId = null;

function openReassignModal(id, title, sn, currentEngId) {
    currentReassignScheduleId = id;
    document.getElementById('reassign-task-title').textContent = title;
    document.getElementById('reassign-machine-sn').textContent = 'Machine Serial: ' + sn;
    document.getElementById('reassign-engineer-select').value = currentEngId || '';
    document.getElementById('reassign-modal').classList.remove('hidden');
}

function closeReassignModal() {
    document.getElementById('reassign-modal').classList.add('hidden');
    currentReassignScheduleId = null;
}

function submitReassign() {
    if (!currentReassignScheduleId) return;
    const btn = document.getElementById('reassign-submit-btn');
    const value = document.getElementById('reassign-engineer-select').value;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving...';

    fetch(`/pm/schedules/${currentReassignScheduleId}/reassign`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ assigned_engineer_id: value || null })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Could not update engineer assignment.');
            btn.disabled = false;
            btn.textContent = 'Save Assignment';
        }
    }).catch(() => {
        alert('Network error while updating assignment.');
        btn.disabled = false;
        btn.textContent = 'Save Assignment';
    });
}

function openHistoryModal(id) {
    const modal = document.getElementById('history-modal-' + id);
    if (modal) modal.classList.remove('hidden');
}

function closeHistoryModal(id) {
    const modal = document.getElementById('history-modal-' + id);
    if (modal) modal.classList.add('hidden');
}
</script>
@endsection
