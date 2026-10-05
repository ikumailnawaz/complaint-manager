@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <!-- HEADER & STATS -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center space-x-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-600 text-white flex items-center justify-center text-xl shadow-md shadow-amber-900/20">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-800 tracking-tight flex items-center gap-2">
                    <span>Pending Approvals Command Hub</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 font-bold border border-amber-300">
                        SLA Clock Paused
                    </span>
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Tickets on hold awaiting bank/customer authorization. SLA resolution timers are frozen until approval is marked arrived.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('tickets.index') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 transition flex items-center space-x-1.5">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Complaints Registry</span>
            </a>
            <a href="{{ route('tickets.open') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 transition flex items-center space-x-1.5">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Open Tickets SLA</span>
            </a>
        </div>
    </div>

    <!-- STATS TILES -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- 1. Total On Hold -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>Total Awaiting Approval</span>
                <i class="fa-solid fa-pause text-amber-500"></i>
            </div>
            <div class="text-2xl font-black text-amber-600 mt-1.5 font-mono">{{ $stats['total_pending'] }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 font-medium">SLA timers actively frozen</div>
        </div>

        <!-- 2. Paused > 24h -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>Paused &gt; 24 Hours</span>
                <i class="fa-solid fa-clock-rotate-left text-orange-500"></i>
            </div>
            <div class="text-2xl font-black text-orange-600 mt-1.5 font-mono">{{ $stats['paused_over_24h'] }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 font-medium">May require escalation with bank</div>
        </div>

        <!-- 3. Distinct Banks -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>Institutions Pending</span>
                <i class="fa-solid fa-building-columns text-sky-600"></i>
            </div>
            <div class="text-2xl font-black text-slate-800 mt-1.5 font-mono">{{ $stats['banks_count'] }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 font-medium">Across active banking clients</div>
        </div>
    </div>

    <!-- SEARCH & FILTER -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('approvals.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
            <div class="sm:col-span-6 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search ticket #, bank, machine serial, or reason..."
                       class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 outline-none text-xs">
            </div>

            <div class="sm:col-span-3">
                <input type="text" name="bank" value="{{ request('bank') }}"
                       placeholder="Filter by Bank Name"
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 outline-none text-xs">
            </div>

            <div class="sm:col-span-3 flex items-center space-x-2">
                <button type="submit" class="flex-1 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl shadow-sm transition">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'bank', 'location']))
                    <a href="{{ route('approvals.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- TICKETS LIST -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-black text-slate-800 tracking-tight flex items-center space-x-2">
                <i class="fa-solid fa-list-check text-amber-500"></i>
                <span>Tickets Waiting for Approval ({{ $tickets->total() }})</span>
            </h2>
            <span class="text-xs text-slate-400">
                Sorted by most recently paused
            </span>
        </div>

        @if($tickets->isEmpty())
            <div class="p-12 text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl mb-3">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No Tickets Waiting for Approval!</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    All service calls have active SLA clocks running smoothly with no pending client authorizations.
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($tickets as $ticket)
                    @php
                        $slaHealth = $ticket->getSlaHealth();
                        $pausedSince = $ticket->sla_paused_at ?? $ticket->approval_requested_at;
                        $pausedDuration = $pausedSince ? $pausedSince->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : 'Just now';
                    @endphp
                    <div class="p-4 sm:p-5 hover:bg-slate-50/80 transition flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <!-- Left Details -->
                        <div class="space-y-2 flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-mono font-bold text-sm text-sky-600 hover:underline">
                                    #{{ $ticket->ticket_no }}
                                </a>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                    <i class="fa-solid fa-pause text-[9px] mr-1"></i>SLA Paused ({{ $pausedDuration }})
                                </span>
                                @if($ticket->approval_source)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        Source: <strong class="text-slate-900">{{ $ticket->approval_source }}</strong>
                                    </span>
                                @endif
                            </div>

                            <!-- Bank & Branch & Machine -->
                            <div class="text-xs text-slate-700">
                                <span class="font-extrabold text-slate-900">{{ $ticket->bank_name }}</span>
                                <span class="text-slate-400 mx-1">•</span>
                                <span class="font-medium text-slate-600">{{ $ticket->branch_name ?? $ticket->branch_location }}</span>
                                @if($ticket->machine_model || $ticket->machine_serial_no)
                                    <span class="text-slate-400 mx-1">•</span>
                                    <span class="font-mono text-slate-500">Machine: {{ $ticket->machine_model ?? 'Model' }} (S/N: {{ $ticket->machine_serial_no ?? 'N/A' }})</span>
                                @endif
                            </div>

                            <!-- Reason Box -->
                            <div class="bg-amber-50/70 border border-amber-200/80 rounded-xl p-3 text-xs text-amber-950">
                                <div class="flex items-center space-x-1.5 font-bold text-[11px] text-amber-800 mb-1">
                                    <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                                    <span>Approval Requirement / Reason:</span>
                                </div>
                                <p class="text-slate-700 italic leading-relaxed">
                                    "{{ $ticket->approval_request_reason }}"
                                </p>
                                <div class="mt-2 text-[10px] text-slate-500 flex flex-wrap items-center gap-3">
                                    <span>
                                        <i class="fa-solid fa-user-pen mr-1 text-slate-400"></i>Requested by: <strong>{{ $ticket->approvalRequestedBy?->name ?? 'Engineer' }}</strong>
                                    </span>
                                    <span>
                                        <i class="fa-regular fa-clock mr-1 text-slate-400"></i>Paused at: <strong>{{ $ticket->sla_paused_at?->format('d M Y, h:i A') ?? 'N/A' }}</strong>
                                    </span>
                                    @if($ticket->engineer)
                                        <span>
                                            <i class="fa-solid fa-helmet-safety mr-1 text-slate-400"></i>Assigned: <strong>{{ $ticket->engineer->name }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions & SLA Info -->
                        <div class="flex flex-col sm:flex-row lg:flex-col items-end justify-between gap-3 shrink-0">
                            <!-- Frozen SLA Widget -->
                            <div class="w-full sm:w-48 text-right bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-400">Frozen SLA Timer</div>
                                <div class="text-xs font-black text-amber-700 mt-0.5">
                                    {{ $slaHealth['label'] }}
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-1.5 mt-1.5 overflow-hidden">
                                    <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ $slaHealth['percent'] }}%"></div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="flex items-center space-x-2 w-full sm:w-auto">
                                <a href="{{ route('tickets.show', $ticket) }}" class="px-3 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                    <i class="fa-solid fa-eye mr-1"></i> View
                                </a>

                                <button type="button"
                                        onclick="openGrantApprovalModal({{ $ticket->id }}, '{{ addslashes($ticket->ticket_no) }}', '{{ addslashes($ticket->bank_name) }}', '{{ $pausedDuration }}')"
                                        class="px-3.5 py-2 text-xs font-extrabold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-900/20 transition flex items-center space-x-1.5">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span>Approval Arrived (Resume SLA)</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- PAGINATION -->
            <div class="p-4 border-t border-slate-100">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

</div>

<!-- MODAL: MARK APPROVAL ARRIVED -->
<div id="grantApprovalModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-800 text-base">Record Approval Arrival</h3>
                    <p class="text-xs text-slate-500">Resume ticket SLA timer &amp; extend engineer TAT</p>
                </div>
            </div>
            <button type="button" onclick="closeGrantApprovalModal()" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="grantApprovalForm" method="POST" action="" class="space-y-4 mt-4">
            @csrf

            <!-- Ticket Info Highlight -->
            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Ticket:</span>
                    <span id="modalTicketNo" class="font-mono font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Bank / Client:</span>
                    <span id="modalBankName" class="font-bold text-slate-800"></span>
                </div>
                <div class="flex justify-between text-amber-700">
                    <span class="font-semibold">Paused Duration:</span>
                    <span id="modalPausedDuration" class="font-bold"></span>
                </div>
            </div>

            <!-- Smart Notice -->
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900 flex items-start space-x-2">
                <i class="fa-solid fa-circle-info text-emerald-600 text-sm mt-0.5 shrink-0"></i>
                <div class="leading-relaxed">
                    <strong>Smart SLA Adjustment:</strong> Resuming the ticket shifts the SLA deadline forward by exactly the paused duration, ensuring the engineer gets their remaining TAT without penalty.
                </div>
            </div>

            <!-- Input: Approval Remarks -->
            <div class="space-y-1.5">
                <label for="approvalRemarks" class="block text-xs font-bold text-slate-700">
                    Approval Confirmation Remarks / Reference <span class="text-rose-500">*</span>
                </label>
                <textarea id="approvalRemarks" name="remarks" rows="3" required
                          placeholder="e.g. Received written approval from Bank Operations Head / Branch Manager via email (Ref #10482). Authorized to proceed with part replacement."
                          class="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none leading-relaxed"></textarea>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="closeGrantApprovalModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-900/20 transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-play"></i>
                    <span>Confirm Approval &amp; Resume Clock</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openGrantApprovalModal(ticketId, ticketNo, bankName, pausedDuration) {
        document.getElementById('grantApprovalForm').action = "/tickets/" + ticketId + "/grant-approval";
        document.getElementById('modalTicketNo').textContent = '#' + ticketNo;
        document.getElementById('modalBankName').textContent = bankName;
        document.getElementById('modalPausedDuration').textContent = pausedDuration;
        document.getElementById('approvalRemarks').value = '';
        document.getElementById('grantApprovalModal').classList.remove('hidden');
    }

    function closeGrantApprovalModal() {
        document.getElementById('grantApprovalModal').classList.add('hidden');
    }
</script>
@endsection
