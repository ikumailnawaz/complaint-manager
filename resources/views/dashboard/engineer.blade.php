@extends('layouts.app')

@section('title', 'Engineer Field Portal - ' . auth()->user()->name)

@section('content')
<div class="space-y-6">

    <!-- Profile & Status Header -->
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xl">
                <i class="fa-solid fa-wrench"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-900">{{ auth()->user()->name }}</h1>
                <div class="text-xs text-slate-500">
                    Base: <span class="font-semibold text-slate-700">{{ auth()->user()->base_city }}</span> &bull; 
                    Specialization: <span class="font-semibold text-slate-700">{{ auth()->user()->specialization }}</span> &bull;
                    WhatsApp: <span class="font-semibold text-emerald-700">{{ auth()->user()->phone_whatsapp }}</span>
                </div>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <span class="px-3 py-1 text-xs font-bold rounded-full {{ auth()->user()->is_available ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                <i class="fa-solid fa-circle text-[8px] mr-1"></i> {{ auth()->user()->is_available ? 'Ready for Assignment' : 'Unavailable / On Leave' }}
            </span>
        </div>
    </div>

    <!-- Engineer Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 uppercase">Active Jobs</div>
            <div class="text-2xl font-bold text-sky-600 mt-1">{{ $stats['active_tickets'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 uppercase">Resolved Jobs</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['resolved_tickets'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 uppercase">Pending Expenses</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['pending_expenses'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 uppercase">Approved / Paid</div>
            <div class="text-2xl font-bold text-indigo-600 mt-1">{{ $stats['approved_expenses'] }}</div>
        </div>
    </div>

    <!-- Active Tickets Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="font-bold text-sm text-slate-800">
                <i class="fa-solid fa-list-check text-sky-600 mr-1.5"></i> My Assigned Complaints
            </div>
            <span class="text-xs text-slate-500">{{ $myTickets->count() }} active unresolved</span>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($myTickets as $ticket)
                @php
                    $sla = $ticket->getSlaHealth();
                    $slaPercent = $sla['percent'] ?? 100;
                    $isBreached = $sla['breached'] ?? false;
                    $isResolved = in_array($ticket->status, ['resolved', 'closed']);
                @endphp
                <div class="p-4 hover:bg-slate-50 transition">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="space-y-2 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-sm text-slate-900">{{ $ticket->ticket_no }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $ticket->urgency === 'high' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ strtoupper($ticket->urgency) }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                    {{ $ticket->status === 'assigned' ? 'bg-sky-100 text-sky-700' : '' }}
                                    {{ $ticket->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}
                                    {{ $ticket->status === 'awaiting_workshop' ? 'bg-purple-100 text-purple-700' : '' }}
                                    {{ $ticket->status === 'escalated' ? 'bg-rose-100 text-rose-700' : '' }}
                                    {{ $ticket->status === 'resolved' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                    {{ str_replace('_', ' ', $ticket->status) }}
                                </span>
                            </div>
                            <div class="text-xs font-medium text-slate-700">
                                {{ $ticket->bank_name }} &bull; {{ $ticket->branch_name ?? $ticket->branch_location }} ({{ $ticket->branch_location }})
                            </div>
                            <div class="text-xs text-slate-500">
                                Machine: <strong>{{ $ticket->machine_type }}</strong> &bull; Model: {{ $ticket->machine_model ?? 'N/A' }} &bull; Serial: <code class="font-mono text-slate-700">{{ $ticket->machine_serial_no ?? 'N/A' }}</code>
                            </div>
                            <p class="text-xs text-slate-600 bg-slate-50 p-2 rounded border border-slate-100">
                                {{ $ticket->issue_summary }}
                            </p>

                            <!-- SLA Turnaround Progress Bar (Visible directly on table) -->
                            <div class="bg-white p-2.5 rounded-xl border border-slate-200/90 shadow-xs max-w-xl space-y-1.5">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold flex items-center gap-1.5 {{ $isBreached ? 'text-rose-600 font-extrabold' : ($isResolved ? 'text-emerald-700' : 'text-slate-700') }}">
                                        <i class="fa-solid {{ $isResolved ? 'fa-circle-check text-emerald-600' : ($isBreached ? 'fa-triangle-exclamation text-rose-600 animate-pulse' : 'fa-gauge-high text-sky-600') }} text-[11px]"></i>
                                        <span>SLA Progress:</span>
                                        <span class="font-medium text-slate-600">
                                            @if($isResolved)
                                                Resolved {{ $ticket->resolved_at ? $ticket->resolved_at->diffForHumans() : 'Successfully' }}
                                            @elseif($isBreached)
                                                Breached {{ $ticket->sla_deadline ? $ticket->sla_deadline->diffForHumans() : '' }}
                                            @else
                                                {{ $sla['label'] }} Remaining
                                            @endif
                                        </span>
                                    </span>
                                    <span class="font-mono text-[10px] font-bold {{ $isBreached ? 'text-rose-600' : ($isResolved ? 'text-emerald-600' : 'text-slate-600') }}">
                                        {{ $isResolved ? '100% Completed' : $slaPercent . '% Remaining' }}
                                    </span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden shadow-inner border border-slate-200/60">
                                    <div class="h-2 rounded-full transition-all duration-500 {{ $isResolved ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : ($isBreached ? 'bg-rose-600 animate-pulse' : ($sla['color'] === 'amber' ? 'bg-gradient-to-r from-amber-400 to-amber-500' : ($sla['color'] === 'rose' ? 'bg-gradient-to-r from-rose-500 to-red-500' : 'bg-gradient-to-r from-sky-400 to-emerald-500'))) }}" 
                                         style="width: {{ $isResolved ? 100 : max(5, $slaPercent) }}%"></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>Due: <strong class="text-slate-600">{{ $ticket->sla_deadline ? $ticket->sla_deadline->format('d M, h:i A') : 'Standard SLA' }}</strong></span>
                                    @if($ticket->hasSupportingDocument())
                                        <a href="{{ $ticket->supportingDocumentUrl() }}" target="_blank" class="text-emerald-700 hover:text-emerald-800 hover:underline flex items-center gap-1 font-semibold">
                                            <i class="fa-solid fa-paperclip"></i>
                                            <span>Supporting Doc Attached</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col sm:flex-row lg:flex-col items-start lg:items-end gap-2 mt-2 lg:mt-0 shrink-0">
                            <a href="{{ route('tickets.show', $ticket) }}" class="inline-flex items-center px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                                <i class="fa-solid fa-eye mr-1.5"></i> View Details
                            </a>

                            @if($ticket->status === 'awaiting_approval')
                                <span class="inline-flex items-center px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-300 rounded-lg text-xs font-bold" title="SLA Clock is Paused awaiting authorization">
                                    <i class="fa-solid fa-hourglass-half mr-1.5 text-amber-600"></i> Awaiting Approval (SLA Paused)
                                </span>
                            @elseif($ticket->status === 'awaiting_workshop')
                                <span class="inline-flex items-center px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-300 rounded-lg text-xs font-bold" title="Machine in transit to workshop. Actions locked until physically received at workshop.">
                                    <i class="fa-solid fa-truck-fast mr-1.5 text-amber-600"></i> In Workshop Transit (Locked)
                                </span>
                            @elseif($ticket->status === 'in_workshop_repair')
                                @if(auth()->id() === $ticket->assigned_engineer_id)
                                    <button type="button" 
                                            onclick="openCompleteModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')" 
                                            class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition active:scale-95 cursor-pointer">
                                        <i class="fa-solid fa-circle-check mr-1.5"></i> Mark Bench Done
                                    </button>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 bg-purple-50 text-purple-800 border border-purple-300 rounded-lg text-xs font-bold" title="Machine undergoing bench repair at Central Workshop">
                                        <i class="fa-solid fa-warehouse mr-1.5 text-purple-600"></i> In Central Workshop
                                    </span>
                                @endif
                            @elseif($ticket->status === 'workshop_repaired')
                                <span class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-800 border border-blue-300 rounded-lg text-xs font-bold">
                                    <i class="fa-solid fa-box-archive mr-1.5 text-blue-600"></i> Bench Done (Ready for Return)
                                </span>
                            @elseif($ticket->status === 'return_transit')
                                <span class="inline-flex items-center px-2.5 py-1 bg-indigo-50 text-indigo-800 border border-indigo-300 rounded-lg text-xs font-bold">
                                    <i class="fa-solid fa-truck mr-1.5 text-indigo-600"></i> In Return Cargo
                                </span>
                            @elseif(!$isResolved)
                                <div class="flex items-center space-x-1.5">
                                    <button type="button" 
                                            onclick="openCompleteModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')" 
                                            class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition active:scale-95 cursor-pointer">
                                        <i class="fa-solid fa-circle-check mr-1.5"></i> Mark as Complete
                                    </button>
                                    <form action="{{ route('tickets.request-approval', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('Pause SLA timer and mark Ticket #{{ $ticket->ticket_no }} as Waiting for Approval?');">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-2.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold shadow-sm transition active:scale-95 cursor-pointer" title="Pause SLA countdown if customer/bank authorization is pending">
                                            <i class="fa-solid fa-pause mr-1"></i> Wait Approval
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="flex items-center space-x-1.5">
                                    <span class="inline-flex items-center px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-lg text-xs font-bold">
                                        <i class="fa-solid fa-check-double mr-1.5 text-emerald-600"></i> Service Resolved
                                    </span>
                                    @if($ticket->hasClaimedExpenses())
                                        <span class="inline-flex items-center px-2 py-1 bg-slate-100 text-slate-500 border border-slate-300 rounded-lg text-xs font-semibold" title="Resolution cannot be undone because expenses have been claimed">
                                            <i class="fa-solid fa-lock mr-1 text-slate-400"></i> Locked
                                        </span>
                                    @else
                                        <form action="{{ route('tickets.undo-resolve', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('Revert Ticket #{{ $ticket->ticket_no }} back to In Progress?');">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 rounded-lg text-xs font-semibold shadow-xs transition active:scale-95 cursor-pointer" title="Accidentally marked as done? Click to undo and restore ticket to In Progress">
                                                <i class="fa-solid fa-rotate-left mr-1 text-amber-600"></i> Undo
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif

                            @if($ticket->canClaimExpense(auth()->id()))
                                <a href="{{ route('expenses.create', ['ticket_id' => $ticket->id]) }}" class="inline-flex items-center px-3 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 rounded-lg text-xs font-semibold transition">
                                    <i class="fa-solid fa-receipt mr-1.5"></i> Claim Tour Expense
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-400">
                    No active tickets assigned to you.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Recent Expense Claims Submitted by this Engineer -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="font-bold text-sm text-slate-800">
                <i class="fa-solid fa-receipt text-emerald-600 mr-1.5"></i> My Tour Expense Claims
            </div>
            <a href="{{ route('expenses.index') }}" class="text-xs text-sky-600 hover:text-sky-800">View All &rarr;</a>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($myExpenses as $exp)
                <div class="p-4 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-800">
                            Ticket {{ $exp->ticket?->ticket_no }} &bull; {{ $exp->from_city }} &rarr; {{ $exp->to_city }} ({{ $exp->trip_type }})
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            AI Round-trip Distance: <strong>{{ $exp->ai_distance_km }} km</strong> &bull; Submitted: {{ $exp->created_at->format('d M Y') }}
                        </div>
                        @if($exp->status === 'rejected')
                            <div class="text-xs text-rose-700 bg-rose-50 p-1.5 rounded mt-1 border border-rose-200">
                                <strong>Rejection Reason:</strong> {{ $exp->admin_notes }}
                            </div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-slate-900">PKR {{ number_format($exp->claimed_amount, 2) }}</div>
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase
                            {{ $exp->status === 'submitted' ? 'bg-amber-100 text-amber-800' : '' }}
                            {{ $exp->status === 'approved' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $exp->status === 'rejected' ? 'bg-rose-100 text-rose-800' : '' }}
                            {{ $exp->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : '' }}">
                            {{ $exp->status }}
                        </span>
                        @if($exp->status === 'rejected')
                            <div class="mt-1">
                                <a href="{{ route('expenses.show', $exp) }}" class="text-xs text-rose-700 underline font-semibold">Re-submit Voucher</a>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-xs text-slate-400">
                    No expense claims submitted yet.
                </div>
            @endforelse
        </div>
    </div>

</div>

@include('tickets.partials.mark-complete-modal')
@include('tickets.partials.approval-modals')
@endsection
