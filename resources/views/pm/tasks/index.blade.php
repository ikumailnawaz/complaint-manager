@extends('layouts.app')

@section('title', 'My PM Tasks')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3">
            <i class="fa-solid fa-wrench text-teal-600"></i>
            <span>My PM Tasks</span>
        </h1>
        <p class="text-slate-500 text-sm mt-0.5">Preventive maintenance tasks assigned to you</p>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm font-semibold">
        <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
    </div>
    @endif

    {{-- Tabs --}}
    <div class="flex space-x-2 border-b border-slate-200">
        <a href="{{ route('pm.tasks.index') }}?tab=due"
           class="px-4 py-2.5 text-sm font-semibold rounded-t-xl transition {{ $tab === 'due' || $tab === '' ? 'bg-teal-600 text-white' : 'text-slate-500 hover:text-slate-800' }}">
            Due / Overdue
            @if($overdueCount + $dueSoonCount > 0)
                <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-black {{ $overdueCount > 0 ? 'bg-red-500 text-white' : 'bg-amber-400 text-slate-900' }}">{{ $overdueCount + $dueSoonCount }}</span>
            @endif
        </a>
        <a href="{{ route('pm.tasks.index') }}?tab=completed"
           class="px-4 py-2.5 text-sm font-semibold rounded-t-xl transition {{ $tab === 'completed' ? 'bg-teal-600 text-white' : 'text-slate-500 hover:text-slate-800' }}">
            Completed This Month
        </a>
    </div>

    {{-- Task Cards --}}
    @if($schedules->isEmpty())
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm py-16 text-center text-slate-400">
        <i class="fa-solid fa-circle-check text-5xl mb-3 opacity-30 text-teal-500"></i>
        <p class="font-semibold text-lg">
            @if($tab === 'completed') No completions recorded this month yet.
            @else 🎉 No pending PM tasks this month!
            @endif
        </p>
    </div>
    @else
    <div class="space-y-3">
        @foreach($schedules as $sched)
        @php
            $isOverdue  = $sched->is_overdue;
            $isDueSoon  = $sched->is_due_soon;
            $daysUntil  = $sched->days_until_due;
            
            // Check if this schedule is completed this month
            $completedThisMonth = $sched->records
                ->where('status', 'completed')
                ->filter(fn($r) => $r->performed_at && $r->performed_at->format('Y-m') === now()->format('Y-m'))
                ->sortByDesc('performed_at')
                ->first() ?? ($tab === 'completed' ? $sched->latest_completed_record : null);
        @endphp
        <div class="bg-white rounded-2xl border shadow-sm overflow-hidden transition
            {{ $completedThisMonth ? 'border-emerald-200 bg-emerald-50/10' : ($isOverdue ? 'border-red-300 bg-red-50/10' : ($isDueSoon ? 'border-amber-300 bg-amber-50/10' : 'border-slate-200')) }}">
            <div class="flex items-start justify-between p-5">
                <div class="flex items-start space-x-4">
                    {{-- Status icon --}}
                    <div class="mt-0.5 w-11 h-11 rounded-xl flex items-center justify-center shrink-0
                        {{ $completedThisMonth ? 'bg-emerald-100 text-emerald-700' : ($isOverdue ? 'bg-red-100 text-red-600' : ($isDueSoon ? 'bg-amber-100 text-amber-600' : 'bg-teal-100 text-teal-600')) }}">
                        @if($completedThisMonth)
                            <i class="fa-solid fa-circle-check text-xl"></i>
                        @elseif($isOverdue)
                            <i class="fa-solid fa-circle-exclamation text-xl"></i>
                        @elseif($isDueSoon)
                            <i class="fa-solid fa-clock text-xl"></i>
                        @else
                            <i class="fa-solid fa-wrench text-xl"></i>
                        @endif
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center space-x-2 flex-wrap">
                            <span class="font-black text-slate-900 text-base">{{ $sched->title }}</span>
                            @if($completedThisMonth)
                                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">
                                    ✅ Done {{ $completedThisMonth->performed_at?->format('d M') }}
                                </span>
                            @elseif($isOverdue)
                                <span class="px-2.5 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-bold animate-pulse">
                                    🔴 OVERDUE
                                </span>
                            @elseif($isDueSoon)
                                <span class="px-2.5 py-0.5 bg-amber-100 text-amber-800 rounded-full text-xs font-bold">
                                    🟡 Due Soon
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center space-x-2 text-xs text-slate-600 flex-wrap">
                            <span class="font-mono font-bold text-teal-700 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-200">
                                {{ $sched->machine->serial_number }}
                            </span>
                            <span>{{ $sched->machine->machineModel->name ?? '' }}</span>
                            <span>•</span>
                            <span>{{ $sched->machine->location }}</span>
                            @if($sched->machine->bank_name)
                                <span>•</span>
                                <span class="text-slate-500 font-semibold">{{ $sched->machine->bank_name }}</span>
                            @endif
                        </div>

                        <div class="flex items-center space-x-3 text-xs pt-0.5">
                            <span class="font-semibold {{ $isOverdue ? 'text-red-600' : ($isDueSoon ? 'text-amber-600' : 'text-slate-700') }}">
                                Next Due: {{ $sched->next_due_date?->format('d M Y') ?? '—' }}
                                @if(!$completedThisMonth && $isOverdue)
                                    ({{ abs($daysUntil) }} days overdue)
                                @elseif(!$completedThisMonth && $daysUntil >= 0)
                                    (in {{ $daysUntil }} days)
                                @endif
                            </span>
                            <span class="text-slate-400">•</span>
                            <span class="text-slate-500 font-medium">{{ $sched->frequency_label }}</span>
                            @if($sched->last_performed_at)
                                <span class="text-slate-400">•</span>
                                <span class="text-slate-500">Last completed: {{ $sched->last_performed_at->format('d M Y') }}</span>
                            @endif
                        </div>

                        {{-- If completed, show document and notes bar --}}
                        @if($completedThisMonth)
                            <div class="mt-2.5 pt-2 border-t border-slate-100 space-y-1.5">
                                @if($completedThisMonth->notes)
                                    <p class="text-xs text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-200">
                                        <span class="font-semibold text-slate-700">Notes:</span> {{ $completedThisMonth->notes }}
                                    </p>
                                @endif

                                <div class="flex items-center space-x-2 flex-wrap">
                                    <span class="text-xs text-slate-500 font-medium">Document:</span>
                                    @if($completedThisMonth->hasDocument())
                                        <a href="{{ route('pm.records.view', $completedThisMonth) }}" target="_blank"
                                           class="inline-flex items-center space-x-1.5 px-2.5 py-1 bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 rounded-lg text-xs font-bold transition shadow-sm">
                                            <i class="fa-solid fa-file-lines text-teal-600"></i>
                                            <span>{{ $completedThisMonth->document_original_name ?? 'View Document' }}</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-teal-500 ml-0.5"></i>
                                        </a>
                                        <a href="{{ route('pm.records.download', $completedThisMonth) }}"
                                           class="inline-flex items-center space-x-1 px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition" title="Download Document">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    @else
                                        <span class="inline-flex items-center space-x-1 text-amber-700 text-xs font-medium bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            <span>No document attached yet</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-col sm:flex-row items-end sm:items-center space-y-2 sm:space-y-0 sm:space-x-2 ml-4 shrink-0">
                    @if($completedThisMonth)
                        <a href="{{ route('pm.records.edit', $completedThisMonth) }}"
                           class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold rounded-xl transition text-xs shadow-sm">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Edit Task / Doc</span>
                        </a>
                    @else
                        <a href="{{ route('pm.tasks.show', $sched) }}"
                           class="inline-flex items-center space-x-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow transition text-sm">
                            <i class="fa-solid fa-play"></i>
                            <span>Do Task</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="mt-2">{{ $schedules->links() }}</div>
    @endif
</div>
@endsection
