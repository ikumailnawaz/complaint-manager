@extends('layouts.app')

@section('title', 'Open Complaints & Daily SLA Feedback - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="p-2 bg-amber-100 text-amber-800 rounded-xl text-sm">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                    Open Tickets &amp; Daily SLA Feedback
                </h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-10">
                Active unresolved bank complaints. Operations Administrators record daily progress updates and monitor field SLA.
            </p>
        </div>
        @if(!auth()->user()->isOfficeStaff())
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('tickets.index') }}" class="inline-flex items-center px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                <i class="fa-solid fa-list-check mr-1.5 text-slate-500"></i> All Tickets Registry
            </a>
            <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl shadow-sm hover:shadow transition">
                <i class="fa-solid fa-plus mr-1.5"></i> Register Complaint
            </a>
        </div>
        @endif
    </div>

    <!-- Active SLA Metric Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Open Tickets</div>
                <div class="text-2xl font-black text-slate-900 mt-0.5">{{ $stats['total_open'] }}</div>
                <div class="text-[10px] text-slate-500">Unresolved in field</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-folder-open"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Needs Feedback Today</div>
                <div class="text-2xl font-black {{ $stats['needs_feedback'] > 0 ? 'text-rose-600' : 'text-emerald-600' }} mt-0.5">
                    {{ $stats['needs_feedback'] }}
                </div>
                <div class="text-[10px] text-slate-500">Pending operations log</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-bell"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">High Priority (4h)</div>
                <div class="text-2xl font-black text-orange-600 mt-0.5">{{ $stats['high_priority'] }}</div>
                <div class="text-[10px] text-slate-500">Urgent SLA compliance</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Escalated Tickets</div>
                <div class="text-2xl font-black text-purple-600 mt-0.5">{{ $stats['escalated'] }}</div>
                <div class="text-[10px] text-slate-500">Awaiting superior</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
        <form action="{{ route('tickets.open') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs">
            <div class="lg:col-span-4">
                <label class="block font-semibold text-slate-700 mb-1">Search Open Tickets</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" 
                        placeholder="Search Ticket #, Bank, Serial, City, Contact..." 
                        class="w-full pl-9 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <div class="lg:col-span-2">
                <label class="block font-semibold text-slate-700 mb-1">Status</label>
                <select name="status" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-sky-500" onchange="this.form.submit()">
                    <option value="">All Open Statuses</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open (Unassigned)</option>
                    <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Assigned</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="awaiting_workshop" {{ request('status') === 'awaiting_workshop' ? 'selected' : '' }}>Awaiting Workshop</option>
                    <option value="escalated" {{ request('status') === 'escalated' ? 'selected' : '' }}>Escalated</option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block font-semibold text-slate-700 mb-1">Urgency</label>
                <select name="urgency" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-sky-500" onchange="this.form.submit()">
                    <option value="">All Urgencies</option>
                    <option value="high" {{ request('urgency') === 'high' ? 'selected' : '' }}>High Priority</option>
                    <option value="medium" {{ request('urgency') === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="low" {{ request('urgency') === 'low' ? 'selected' : '' }}>Low</option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block font-semibold text-slate-700 mb-1">City / Region</label>
                <input type="text" name="location" value="{{ request('location') }}" placeholder="e.g. Lahore, Karachi" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs">
            </div>

            <div class="lg:col-span-2 flex items-end space-x-2">
                <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-xs transition">
                    Filter
                </button>
                <a href="{{ route('tickets.open') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Open Tickets Table with Operations Daily Feedback Drawer / Action -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-4">Ticket &amp; Bank</th>
                        <th class="py-3 px-3">Machine &amp; S/N</th>
                        <th class="py-3 px-3">Assigned Engineer</th>
                        <th class="py-3 px-3">Status &amp; SLA</th>
                        <th class="py-3 px-4">Daily SLA Feedback History</th>
                        <th class="py-3 px-4 text-right">Operations Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($tickets as $ticket)
                        @php
                            $todayFeedback = $ticket->feedbacks->first(function($f) {
                                return ($f->submitted_at && $f->submitted_at->isToday()) || ($f->created_at && $f->created_at->isToday());
                            });
                            $lastFeedback = $ticket->feedbacks->last();
                            $feedbackCount = $ticket->feedbacks->count();
                            $calendarDay = max(1, (int) ($ticket->created_at ?? now())->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1);
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition align-top">
                            <!-- Ticket & Bank -->
                            <td class="py-3.5 px-4 space-y-1">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-extrabold text-sky-700 hover:text-sky-900 hover:underline text-xs">
                                        {{ $ticket->ticket_no }}
                                    </a>
                                    @if($ticket->urgency === 'high')
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-100 text-rose-700 uppercase">High</span>
                                    @endif
                                </div>
                                <div class="font-bold text-slate-900">{{ $ticket->bank_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $ticket->branch_name }} &bull; {{ $ticket->branch_location }}</div>
                                <div class="text-[10px] text-slate-400">Created {{ $ticket->created_at->diffForHumans() }}</div>
                            </td>

                            <!-- Machine & S/N -->
                            <td class="py-3.5 px-3 space-y-1">
                                <div class="font-semibold text-slate-800">{{ $ticket->machine_type ?? 'Unspecified' }}</div>
                                <div class="text-[11px] text-slate-600">Model: {{ $ticket->machine_model ?? 'N/A' }}</div>
                                <div class="text-[11px] font-mono text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded inline-block">
                                    S/N: {{ $ticket->machine_serial_no ?? 'N/A' }}
                                </div>
                            </td>

                            <!-- Assigned Engineer -->
                            <td class="py-3.5 px-3 space-y-1">
                                @if($ticket->engineer)
                                    <div class="font-bold text-slate-900">{{ $ticket->engineer->name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $ticket->engineer->base_city }}</div>
                                    <div class="text-[11px] font-mono text-emerald-700">
                                        <i class="fa-brands fa-whatsapp text-emerald-600"></i> {{ $ticket->engineer->phone_whatsapp }}
                                    </div>
                                @else
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 animate-pulse">
                                        Unassigned
                                    </span>
                                @endif
                            </td>

                            <!-- Status & SLA -->
                            <td class="py-3.5 px-3 space-y-1">
                                <div>
                                    @if($ticket->status === 'open')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Open</span>
                                    @elseif($ticket->status === 'assigned')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">Assigned</span>
                                    @elseif($ticket->status === 'in_progress')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">In Progress</span>
                                    @elseif($ticket->status === 'awaiting_workshop')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-800">Workshop</span>
                                    @elseif($ticket->status === 'escalated')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">Escalated</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    Deadline: <strong class="text-slate-700">{{ $ticket->sla_deadline ? $ticket->sla_deadline->format('d M, h:i A') : 'Standard SLA' }}</strong>
                                </div>
                            </td>

                            <!-- Daily SLA Feedback History -->
                            <td class="py-3.5 px-4 space-y-1.5 max-w-xs">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $feedbackCount > 0 ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $feedbackCount }} {{ Str::plural('Update', $feedbackCount) }} Logged
                                    </span>
                                    @if($todayFeedback)
                                        <span class="text-[10px] font-semibold text-emerald-700 flex items-center space-x-1">
                                            <i class="fa-solid fa-circle-check"></i> <span>Logged Today (Day {{ $todayFeedback->day_number }})</span>
                                        </span>
                                    @else
                                        <span class="text-[10px] font-semibold text-rose-600 flex items-center space-x-1">
                                            <i class="fa-solid fa-triangle-exclamation"></i> <span>Due Today (Day {{ $calendarDay }})</span>
                                        </span>
                                    @endif
                                </div>

                                @if($lastFeedback)
                                    <div class="text-[11px] text-slate-600 bg-slate-50 p-2 rounded border border-slate-200">
                                        <div class="font-bold text-slate-800 text-[10px]">
                                            Day {{ $lastFeedback->day_number }}: {{ $lastFeedback->action_taken }}
                                        </div>
                                        <div class="text-slate-600 line-clamp-2 mt-0.5">{{ $lastFeedback->feedback_text }}</div>
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400 italic">No feedback updates recorded yet.</div>
                                @endif
                            </td>

                            <!-- Operations Action (Submit Feedback Modal / Button) -->
                            <td class="py-3.5 px-4 text-right space-y-2">
                                @if(!auth()->user()->isEngineer())
                                    <details class="text-left inline-block w-full">
                                        <summary class="cursor-pointer list-none inline-flex items-center justify-center w-full px-3 py-1.5 {{ $todayFeedback ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white rounded-lg font-bold text-xs shadow-sm transition">
                                            <i class="fa-solid {{ $todayFeedback ? 'fa-pen-to-square' : 'fa-clipboard-check' }} mr-1.5"></i>
                                            {{ $todayFeedback ? "Edit Day {$todayFeedback->day_number} Feedback" : "Log Day {$calendarDay} Feedback" }}
                                        </summary>
                                        <div class="mt-2 p-3 bg-indigo-50/80 border border-indigo-200 rounded-xl space-y-2 text-xs">
                                            <div class="font-bold text-indigo-950 flex items-center justify-between">
                                                <span>{{ $todayFeedback ? "Edit Day {$todayFeedback->day_number} Update (Today)" : "Log Day {$calendarDay} Update:" }}</span>
                                                <span class="text-[10px] text-indigo-700">Admin Feed</span>
                                            </div>
                                            <form action="{{ route('tickets.feedback', $ticket) }}" method="POST" class="space-y-2">
                                                @csrf
                                                <div>
                                                    <label class="block text-[10px] font-bold text-indigo-900 mb-0.5">Action Taken *</label>
                                                    <select name="action_taken" required class="w-full bg-white border border-indigo-200 rounded p-1.5 text-xs">
                                                        <option value="Parts Arranged &amp; En Route" {{ ($todayFeedback?->action_taken == 'Parts Arranged & En Route') ? 'selected' : '' }}>Parts Arranged &amp; En Route</option>
                                                        <option value="On-Site Diagnosis Performed" {{ ($todayFeedback?->action_taken == 'On-Site Diagnosis Performed') ? 'selected' : '' }}>On-Site Diagnosis Performed</option>
                                                        <option value="Component Replaced &amp; Testing" {{ ($todayFeedback?->action_taken == 'Component Replaced & Testing') ? 'selected' : '' }}>Component Replaced &amp; Testing</option>
                                                        <option value="Branch Closed - Visit Rescheduled" {{ ($todayFeedback?->action_taken == 'Branch Closed - Visit Rescheduled') ? 'selected' : '' }}>Branch Closed - Visit Rescheduled</option>
                                                        <option value="Awaiting Bank Approval / PO" {{ ($todayFeedback?->action_taken == 'Awaiting Bank Approval / PO') ? 'selected' : '' }}>Awaiting Bank Approval / PO</option>
                                                        <option value="Machine Hardware Repaired" {{ ($todayFeedback?->action_taken == 'Machine Hardware Repaired') ? 'selected' : '' }}>Machine Hardware Repaired</option>
                                                        <option value="Workshop Inbound" {{ ($todayFeedback?->action_taken == 'Workshop Inbound') ? 'selected' : '' }}>Workshop Inbound</option>
                                                        <option value="Workshop Outbound" {{ ($todayFeedback?->action_taken == 'Workshop Outbound') ? 'selected' : '' }}>Workshop Outbound</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-indigo-900 mb-0.5">Feedback Note *</label>
                                                    <textarea name="feedback_text" required rows="2" placeholder="Enter daily status note..." class="w-full bg-white border border-indigo-200 rounded p-1.5 text-xs">{{ $todayFeedback?->feedback_text }}</textarea>
                                                </div>
                                                <button type="submit" class="w-full py-1.5 {{ $todayFeedback ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-indigo-700 hover:bg-indigo-800' }} text-white font-bold rounded text-xs transition">
                                                    {{ $todayFeedback ? "Update Day {$todayFeedback->day_number} Feedback" : "Submit Day {$calendarDay} Feedback" }}
                                                </button>
                                            </form>
                                        </div>
                                    </details>
                                @endif

                                <div class="flex items-center justify-end space-x-2 pt-1">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="inline-flex items-center text-xs text-slate-600 hover:text-sky-600 font-semibold">
                                        <span>View Ticket</span> <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                                    </a>
                                    @if(!auth()->user()->isEngineer() && !$ticket->is_human_verified)
                                    <form action="{{ route('tickets.destroy', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('DELETE WRONGLY IDENTIFIED COMPLAINT?\n\nTicket #{{ $ticket->ticket_no }} has not yet been human finalized. Deleting will discard this complaint and reset the email back to Needs Triage.\n\nProceed?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center space-x-1 text-[11px] text-rose-600 hover:text-rose-800 font-bold bg-rose-50 hover:bg-rose-100 border border-rose-200 px-2 py-0.5 rounded transition">
                                            <i class="fa-solid fa-trash-can"></i>
                                            <span>Delete</span>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-400">
                                <i class="fa-solid fa-circle-check text-3xl text-emerald-400 mb-2 block"></i>
                                <span class="font-bold text-slate-600">All caught up! No open complaints pending.</span>
                                <p class="text-xs text-slate-400 mt-1">All bank tickets are resolved or closed.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
