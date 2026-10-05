@extends('layouts.app')

@section('title', 'Operations Dashboard - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Top Welcome & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                Operations Command Center
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Real-time monitoring of bank complaints, engineer field alignment, daily feedback, and tour expenses.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if(!auth()->user()->isEngineer())
            <a href="{{ route('settings.email') }}" class="inline-flex items-center px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-envelope-open-text mr-1.5 text-sky-400"></i> GoDaddy Email Sync
            </a>
            <a href="{{ route('tickets.simulate-ingest') }}" class="inline-flex items-center px-3.5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-robot mr-1.5 text-purple-200"></i> AI Email Simulator
            </a>
            <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-plus mr-1.5"></i> Register Complaint
            </a>
            @endif
            <a href="{{ route('expenses.index') }}" class="inline-flex items-center px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-receipt mr-1.5"></i> Tour Expenses
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Total -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Complaints</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['total_tickets'] }}</div>
            <div class="text-[10px] text-slate-400 mt-1">All time received</div>
        </div>

        <!-- Open / Needs Alignment -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-amber-300 bg-amber-50/30">
            <div class="text-[11px] font-semibold text-amber-700 uppercase tracking-wider flex items-center justify-between">
                <span>Unassigned</span>
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
            </div>
            <div class="text-2xl font-bold text-amber-700 mt-1">{{ $stats['open_unassigned'] }}</div>
            <div class="text-[10px] text-amber-600 mt-1 font-medium">Needs Engineer Pick</div>
        </div>

        <!-- Assigned -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Assigned</div>
            <div class="text-2xl font-bold text-sky-600 mt-1">{{ $stats['assigned'] }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Pending first feedback</div>
        </div>

        <!-- In Progress -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">In Progress</div>
            <div class="text-2xl font-bold text-indigo-600 mt-1">{{ $stats['in_progress'] }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Day 1 / Day 2 active</div>
        </div>

        <!-- Escalated (SLA Breached) -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-rose-300 bg-rose-50/30">
            <div class="text-[11px] font-semibold text-rose-700 uppercase tracking-wider flex items-center justify-between">
                <span>Escalated</span>
                <i class="fa-solid fa-fire text-rose-500"></i>
            </div>
            <div class="text-2xl font-bold text-rose-700 mt-1">{{ $stats['escalated'] }}</div>
            <div class="text-[10px] text-rose-600 mt-1 font-medium">With Superior</div>
        </div>

        <!-- Pending Expenses -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Pending Claims</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['pending_expenses'] }}</div>
            <div class="text-[10px] text-slate-400 mt-1">Needs verification</div>
        </div>
    </div>

    <!-- Action Workflow Pipelines Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1: Tickets Awaiting Manual Alignment -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 bg-amber-50 border-b border-amber-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="p-1 bg-amber-500 text-white rounded text-xs"><i class="fa-solid fa-user-plus"></i></span>
                    <h2 class="text-xs font-bold text-amber-900 uppercase tracking-wide">1. Needs Engineer Alignment</h2>
                </div>
                <span class="bg-amber-200 text-amber-900 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $unassignedTickets->count() }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($unassignedTickets as $ticket)
                    <div class="p-3.5 hover:bg-slate-50 transition">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-800">{{ $ticket->ticket_no }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $ticket->urgency === 'high' ? 'bg-rose-100 text-rose-700' : ($ticket->urgency === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ strtoupper($ticket->urgency) }}
                            </span>
                        </div>
                        <div class="text-xs font-medium text-slate-700 mt-1">{{ $ticket->bank_name }} - {{ $ticket->branch_location }}</div>
                        <div class="text-[11px] text-slate-500 truncate mt-0.5">{{ $ticket->machine_type }}: {{ $ticket->issue_summary }}</div>
                        <div class="mt-2.5 flex items-center justify-between">
                            <span class="text-[10px] text-slate-400"><i class="fa-regular fa-clock mr-1"></i>{{ $ticket->created_at->diffForHumans() }}</span>
                            <a href="{{ route('tickets.show', $ticket) }}" class="text-xs font-semibold text-sky-600 hover:text-sky-800">
                                Assign Engineer <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-slate-400">
                        <i class="fa-regular fa-circle-check text-emerald-500 text-2xl mb-1 block"></i>
                        All complaints currently assigned!
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 2: Assigned -> Needs WhatsApp Group Notification -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 bg-emerald-50 border-b border-emerald-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="p-1 bg-emerald-600 text-white rounded text-xs"><i class="fa-brands fa-whatsapp"></i></span>
                    <h2 class="text-xs font-bold text-emerald-900 uppercase tracking-wide">2. Pending WhatsApp Tag</h2>
                </div>
                <span class="bg-emerald-200 text-emerald-900 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $needWhatsAppTickets->count() }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($needWhatsAppTickets as $ticket)
                    <div class="p-3.5 hover:bg-slate-50 transition">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-800">{{ $ticket->ticket_no }}</span>
                            <span class="text-[10px] text-slate-500 font-medium">{{ $ticket->branch_location }}</span>
                        </div>
                        <div class="text-xs text-slate-700 mt-1">
                            Assigned to: <strong class="text-slate-900">{{ $ticket->engineer?->name }}</strong>
                            <span class="text-[10px] text-slate-500">(@{{ $ticket->engineer?->phone_whatsapp }})</span>
                        </div>
                        <div class="mt-2.5 flex items-center justify-between">
                            <form action="{{ route('tickets.notify-whatsapp', $ticket) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold rounded shadow-sm transition">
                                    <i class="fa-brands fa-whatsapp mr-1"></i> Send WA Alert
                                </button>
                            </form>
                            <a href="{{ route('tickets.show', $ticket) }}" class="text-xs text-slate-500 hover:text-slate-700">Details &rarr;</a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-slate-400">
                        <i class="fa-solid fa-check-double text-emerald-500 text-2xl mb-1 block"></i>
                        All assigned engineers notified on WhatsApp!
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 3: WhatsApp Sent -> Needs Bank Assignment Email -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 bg-sky-50 border-b border-sky-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="p-1 bg-sky-600 text-white rounded text-xs"><i class="fa-solid fa-envelope"></i></span>
                    <h2 class="text-xs font-bold text-sky-900 uppercase tracking-wide">3. Send Bank Email</h2>
                </div>
                <span class="bg-sky-200 text-sky-900 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $needEmailTickets->count() }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($needEmailTickets as $ticket)
                    <div class="p-3.5 hover:bg-slate-50 transition">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-800">{{ $ticket->ticket_no }}</span>
                            <span class="text-[10px] text-emerald-600 font-bold"><i class="fa-brands fa-whatsapp mr-0.5"></i> WA Sent</span>
                        </div>
                        <div class="text-xs text-slate-700 mt-1">
                            Bank: <strong class="text-slate-900">{{ $ticket->bank_name }}</strong> &bull; {{ $ticket->customer_email ?? 'No email' }}
                        </div>
                        <div class="mt-2.5 flex items-center justify-between">
                            <form action="{{ route('tickets.send-assignment-email', $ticket) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold rounded shadow-sm transition">
                                    <i class="fa-solid fa-paper-plane mr-1"></i> Send Reply Email
                                </button>
                            </form>
                            <a href="{{ route('tickets.show', $ticket) }}" class="text-xs text-slate-500 hover:text-slate-700">Preview &rarr;</a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-slate-400">
                        <i class="fa-solid fa-circle-check text-sky-500 text-2xl mb-1 block"></i>
                        All bank confirmation emails dispatched!
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- Bottom Row: Escalated Tickets & Daily Feedbacks Feed -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Escalated Tickets (Superior Review Section) -->
        <div class="bg-white rounded-xl shadow-sm border border-rose-200 overflow-hidden">
            <div class="px-5 py-3.5 bg-rose-50 border-b border-rose-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="p-1.5 bg-rose-600 text-white rounded text-xs shadow"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div>
                        <h2 class="text-sm font-bold text-rose-900">Critical: Escalated Complaints (SLA Breached)</h2>
                        <p class="text-[10px] text-rose-700">Tickets that missed Turnaround Time (TAT) and require Superior review</p>
                    </div>
                </div>
                <span class="bg-rose-200 text-rose-900 text-xs font-bold px-2 py-0.5 rounded-full">{{ $escalatedTickets->count() }}</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($escalatedTickets as $t)
                    <div class="p-4 hover:bg-slate-50 transition">
                        <div class="flex items-center justify-between">
                            <div class="font-bold text-xs text-slate-900">{{ $t->ticket_no }} &bull; {{ $t->bank_name }} ({{ $t->branch_location }})</div>
                            <span class="px-2 py-0.5 bg-rose-100 text-rose-800 text-[10px] font-bold rounded-full">ESCALATED</span>
                        </div>
                        <div class="text-xs text-slate-600 mt-1">
                            Assigned Engineer: <strong>{{ $t->engineer?->name ?? 'Unassigned' }}</strong> &bull;
                            Assigned Superior: <strong class="text-purple-700">{{ $t->escalatedTo?->name ?? 'Head Office' }}</strong>
                        </div>
                        <div class="text-[11px] text-rose-700 mt-1 bg-rose-50 p-2 rounded border border-rose-100">
                            <i class="fa-solid fa-clock-rotate-left mr-1"></i>
                            SLA Deadline was: {{ $t->sla_deadline?->format('d M, h:i A') }} ({{ $t->sla_deadline?->diffForHumans() }})
                        </div>
                        <div class="mt-2 text-right">
                            <a href="{{ route('tickets.show', $t) }}" class="text-xs font-semibold text-rose-700 hover:text-rose-900">
                                Review & Intervene &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        <i class="fa-solid fa-shield-heart text-emerald-500 text-2xl mb-1 block"></i>
                        No escalated complaints! All active tickets within SLA threshold.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Daily Feedbacks -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="p-1.5 bg-indigo-600 text-white rounded text-xs shadow"><i class="fa-solid fa-clipboard-list"></i></span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Daily Feedback Stream</h2>
                        <p class="text-[10px] text-slate-500">Day 1, Day 2 progress updates submitted from field</p>
                    </div>
                </div>
            </div>

            <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                @forelse($recentFeedbacks as $fb)
                    <div class="p-4 hover:bg-slate-50 transition">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-sky-700">{{ $fb->ticket?->ticket_no }}</span>
                            <span class="bg-indigo-100 text-indigo-800 font-bold px-2 py-0.5 rounded text-[10px]">Day {{ $fb->day_number }}</span>
                        </div>
                        <div class="text-xs font-medium text-slate-800 mt-1">
                            {{ $fb->engineer?->name }} &bull; <span class="text-slate-500">{{ $fb->action_taken }}</span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1 italic bg-slate-50 p-2 rounded border border-slate-100">
                            "{{ $fb->feedback_text }}"
                        </p>
                        <div class="text-[10px] text-slate-400 mt-1 text-right">
                            {{ $fb->submitted_at?->diffForHumans() ?? $fb->created_at->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        No feedback logs yet.
                    </div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
