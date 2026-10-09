@extends('layouts.app')

@section('title', 'Complaints & Tickets Registry - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Header & Register Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="p-2 bg-sky-100 text-sky-700 rounded-xl text-sm">
                    <i class="fa-solid fa-ticket"></i>
                </span>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                    Bank Complaints Directory
                </h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-10">
                Centralized registry of all incoming bank ATM/POS machine complaints, manual engineer assignments, and field status.
            </p>
        </div>
        @if(!auth()->user()->isEngineer())
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('tickets.simulate-ingest') }}" class="inline-flex items-center px-3.5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-sm hover:shadow transition">
                <i class="fa-solid fa-robot mr-1.5 text-purple-200"></i> AI Email Ingest Simulator
            </a>
            <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl shadow-sm hover:shadow transition">
                <i class="fa-solid fa-plus mr-1.5"></i> Register New Complaint
            </a>
        </div>
        @endif
    </div>

    <!-- VIEW TABS: ALL TICKETS vs OPEN TICKETS -->
    <div class="flex items-center space-x-2 border-b border-slate-200 pb-1">
        <a href="{{ route('tickets.index') }}" class="px-4 py-2 border-b-2 border-sky-600 font-bold text-sky-700 text-xs flex items-center space-x-2">
            <i class="fa-solid fa-list-check"></i>
            <span>All Complaints Registry</span>
        </a>
        @if(!auth()->user()->isEngineer())
        <a href="{{ route('tickets.open') }}" class="px-4 py-2 border-b-2 border-transparent hover:border-slate-300 font-medium text-slate-500 hover:text-slate-800 text-xs flex items-center space-x-2 transition">
            <i class="fa-solid fa-clock-rotate-left text-amber-500"></i>
            <span>Open Tickets &amp; Daily Feedback</span>
            @php
                $openCount = \App\Models\Ticket::whereNotIn('status', ['closed', 'resolved'])->count();
            @endphp
            @if($openCount > 0)
                <span class="px-1.5 py-0.2 bg-amber-100 text-amber-800 text-[10px] font-bold rounded-full">{{ $openCount }}</span>
            @endif
        </a>
        @endif
    </div>

    <!-- Data Table Container with Filters & "Show By" Selector -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

        <!-- Top Table Controls: Search, Filters, and "Show by" entries -->
        <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50/75 space-y-3">
            <form action="{{ route('tickets.index') }}" method="GET" id="ticketsFilterForm" class="space-y-3">

                <!-- Row 1: Search + Quick Dropdowns + Show By -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs">
                    
                    <!-- Search Box (4 cols) -->
                    <div class="lg:col-span-4">
                        <label class="block font-semibold text-slate-700 mb-1">Search Anything</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}" 
                                placeholder="Search Ticket #, Bank, Serial, City, Contact..." 
                                class="w-full pl-9 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition">
                        </div>
                    </div>

                    <!-- Status Filter (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-sky-500" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open (Unassigned)</option>
                            <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Assigned</option>
                            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="awaiting_approval" {{ request('status') === 'awaiting_approval' ? 'selected' : '' }}>Awaiting Approval (SLA Paused)</option>
                            <option value="awaiting_workshop" {{ request('status') === 'awaiting_workshop' ? 'selected' : '' }}>Awaiting Workshop</option>
                            <option value="escalated" {{ request('status') === 'escalated' ? 'selected' : '' }}>Escalated</option>
                            <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>

                    <!-- Urgency Filter (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Urgency SLA</label>
                        <select name="urgency" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-sky-500" onchange="this.form.submit()">
                            <option value="">All Urgencies</option>
                            <option value="high" {{ request('urgency') === 'high' ? 'selected' : '' }}>High (4h SLA)</option>
                            <option value="medium" {{ request('urgency') === 'medium' ? 'selected' : '' }}>Medium (8h SLA)</option>
                            <option value="low" {{ request('urgency') === 'low' ? 'selected' : '' }}>Low (24h SLA)</option>
                        </select>
                    </div>

                    <!-- City Filter (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">City / Location</label>
                        <input type="text" name="location" value="{{ request('location') }}" placeholder="e.g. Lahore, Karachi" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-sky-500">
                    </div>

                    <!-- SHOW BY PER PAGE SELECTOR (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">
                            <i class="fa-solid fa-table-cells mr-1 text-slate-400"></i> Show by:
                        </label>
                        <select name="per_page" class="w-full bg-white border-2 border-slate-300 rounded-xl py-2 px-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-sky-500" onchange="this.form.submit()">
                            <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10 per page</option>
                            <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 per page</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per page</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per page</option>
                            <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>Show All</option>
                        </select>
                    </div>

                </div>

                <!-- Action Buttons & Quick Filter Chips -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs">
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('tickets.index', ['unassigned' => 1, 'per_page' => request('per_page', 25)]) }}" class="px-2.5 py-1 rounded-lg border {{ request('unassigned') ? 'bg-amber-100 border-amber-400 text-amber-900 font-bold' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' }} transition">
                            <i class="fa-solid fa-user-xmark mr-1 text-amber-600"></i> Unassigned Only
                        </a>
                        <a href="{{ route('tickets.index', ['status' => 'escalated', 'per_page' => request('per_page', 25)]) }}" class="px-2.5 py-1 rounded-lg border {{ request('status') === 'escalated' ? 'bg-rose-100 border-rose-400 text-rose-900 font-bold' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' }} transition">
                            <i class="fa-solid fa-fire mr-1 text-rose-600"></i> Escalated Only
                        </a>
                        <a href="{{ route('tickets.index', ['status' => 'in_progress', 'per_page' => request('per_page', 25)]) }}" class="px-2.5 py-1 rounded-lg border {{ request('status') === 'in_progress' ? 'bg-indigo-100 border-indigo-400 text-indigo-900 font-bold' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' }} transition">
                            <i class="fa-solid fa-wrench mr-1 text-indigo-600"></i> Active On-Site
                        </a>
                        <a href="{{ route('tickets.index', ['status' => 'awaiting_approval', 'per_page' => request('per_page', 25)]) }}" class="px-2.5 py-1 rounded-lg border {{ request('status') === 'awaiting_approval' ? 'bg-amber-100 border-amber-400 text-amber-900 font-bold' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' }} transition">
                            <i class="fa-solid fa-hourglass-half mr-1 text-amber-600"></i> Awaiting Approval
                        </a>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-filter text-[10px]"></i>
                            <span>Apply Filters</span>
                        </button>
                        <a href="{{ route('tickets.index') }}" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-xl text-xs transition" title="Reset Filters">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                    </div>
                </div>

            </form>
        </div>

        <!-- High-Capacity Data Table -->
        @php
            $isEngineer = auth()->user()->isEngineer();
        @endphp
        <div class="overflow-x-auto min-h-[320px]">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                        @if(!$isEngineer)
                            {{-- Operations Manager: Actions, Assigned Engineer, Ticket #, Bank & Branch, Status, SLA Progress, then rest --}}
                            <th class="py-3 px-3 text-center whitespace-nowrap">Actions</th>
                            <th class="py-3 px-4">Assigned Engineer</th>
                            <th class="py-3 px-4 whitespace-nowrap">Ticket #</th>
                            <th class="py-3 px-4">Bank &amp; Branch</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center whitespace-nowrap">SLA Progress</th>
                            <th class="py-3 px-3 text-center whitespace-nowrap">Resolution Email</th>
                            <th class="py-3 px-4">Machine &amp; Serial</th>
                            <th class="py-3 px-3 text-center">Urgency</th>
                            <th class="py-3 px-3 text-center whitespace-nowrap">WhatsApp</th>
                            <th class="py-3 px-3 text-center whitespace-nowrap">Bank Email</th>
                        @else
                            {{-- Field Engineer: WhatsApp & Bank Email completely hidden --}}
                            <th class="py-3 px-3 text-center whitespace-nowrap">Actions</th>
                            <th class="py-3 px-4 whitespace-nowrap">Ticket #</th>
                            <th class="py-3 px-4">Bank &amp; Branch</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center whitespace-nowrap">SLA Progress</th>
                            <th class="py-3 px-4">Machine &amp; Serial</th>
                            <th class="py-3 px-3 text-center">Urgency</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tickets as $t)
                        <tr class="hover:bg-sky-50/40 transition {{ $t->status === 'escalated' ? 'bg-rose-50/50' : '' }}">
                            
                            {{-- ACTIONS (Always 1st for both roles) --}}
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center space-x-1.5">
                                    <div class="relative inline-block text-left">
                                        <button type="button" 
                                                onclick="toggleActionMenu(event, 'action-menu-{{ $t->id }}')" 
                                                class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-slate-900 hover:bg-sky-600 text-white rounded-lg text-xs font-bold shadow-sm transition active:scale-95 focus:outline-none cursor-pointer">
                                            <span>Actions</span>
                                            <i class="fa-solid fa-chevron-down text-[9px] text-slate-300"></i>
                                        </button>
                                        <div id="action-menu-{{ $t->id }}" 
                                             class="action-menu-dropdown hidden absolute left-0 mt-1 w-52 bg-white border border-slate-200 rounded-xl shadow-2xl py-1.5 z-50 divide-y divide-slate-100 text-left">
                                            <div class="py-1">
                                                <a href="{{ route('tickets.show', $t) }}" class="flex items-center space-x-2 px-3 py-2 text-xs text-slate-700 hover:bg-sky-50 hover:text-sky-700 font-semibold transition">
                                                    <i class="fa-solid fa-eye text-sky-600 w-4"></i>
                                                    <span>View Ticket Details</span>
                                                </a>
                                                @if($t->hasSupportingDocument())
                                                <a href="{{ $t->supportingDocumentUrl() }}" target="_blank" class="flex items-center space-x-2 px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 font-bold transition">
                                                    <i class="fa-solid fa-file-circle-check text-emerald-600 w-4"></i>
                                                    <span>View Completion Proof</span>
                                                </a>
                                                @endif
                                            @if($t->status !== 'awaiting_workshop' && (auth()->id() === $t->assigned_engineer_id || !auth()->user()->isEngineer()))
                                                <a href="{{ route('parts.requests.create', ['ticket_id' => $t->id]) }}" class="flex items-center space-x-2 px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 font-bold transition">
                                                    <i class="fa-solid fa-gears text-emerald-600 w-4"></i>
                                                    <span>Request Spare Parts</span>
                                                </a>
                                            @endif
                                            @if($t->status === 'awaiting_approval')
                                                @if(!$isEngineer)
                                                    <button type="button" 
                                                            onclick="openGrantApprovalModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', '{{ $t->sla_paused_at ? $t->sla_paused_at->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : '' }}')"
                                                            class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 font-bold transition cursor-pointer">
                                                        <i class="fa-solid fa-play text-emerald-600 w-4"></i>
                                                        <span>Approval Arrived (Resume)</span>
                                                    </button>
                                                @else
                                                    <div class="flex items-center space-x-2 px-3 py-2 text-xs text-amber-800 bg-amber-50/70 font-semibold cursor-not-allowed">
                                                        <i class="fa-solid fa-hourglass-half text-amber-600 w-4"></i>
                                                        <span>Awaiting Approval (Paused)</span>
                                                    </div>
                                                @endif
                                            @elseif($t->status === 'awaiting_workshop')
                                                <div class="flex items-center space-x-2 px-3 py-2 text-xs text-amber-700 bg-amber-50/70 font-semibold cursor-not-allowed">
                                                    <i class="fa-solid fa-truck-fast text-amber-600 w-4"></i>
                                                    <span>In Workshop Transit</span>
                                                </div>
                                            @elseif($t->status === 'in_workshop_repair')
                                                @if(auth()->id() === $t->assigned_engineer_id || !auth()->user()->isEngineer())
                                                <button type="button" 
                                                        onclick="openCompleteModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}')"
                                                        class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 font-bold transition cursor-pointer">
                                                    <i class="fa-solid fa-circle-check text-emerald-600 w-4"></i>
                                                    <span>Mark Bench Done</span>
                                                </button>
                                                @else
                                                <div class="flex items-center space-x-2 px-3 py-2 text-xs text-purple-700 bg-purple-50/70 font-semibold">
                                                    <i class="fa-solid fa-warehouse text-purple-600 w-4"></i>
                                                    <span>In Central Workshop</span>
                                                </div>
                                                @endif
                                            @elseif(!in_array($t->status, ['resolved', 'closed', 'workshop_repaired', 'return_transit', 'awaiting_approval']))
                                                <button type="button" 
                                                        onclick="openCompleteModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}')"
                                                        class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 font-bold transition cursor-pointer">
                                                    <i class="fa-solid fa-circle-check text-emerald-600 w-4"></i>
                                                    <span>Mark Complete</span>
                                                </button>
                                                <button type="button" 
                                                        onclick="openWorkshopModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', '{{ $t->workshop_location ?? '' }}')"
                                                        class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-purple-700 hover:bg-purple-50 font-bold transition cursor-pointer">
                                                    <i class="fa-solid fa-truck-ramp-box text-purple-600 w-4"></i>
                                                    <span>Send to Workshop</span>
                                                </button>
                                                <form action="{{ route('tickets.request-approval', $t) }}" method="POST" onsubmit="return confirm('Pause SLA timer and mark Ticket #{{ $t->ticket_no }} as Waiting for Approval?');">
                                                    @csrf
                                                    <button type="submit" class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-amber-700 hover:bg-amber-50 font-bold transition cursor-pointer">
                                                        <i class="fa-solid fa-pause text-amber-600 w-4"></i>
                                                        <span>Waiting for Approval</span>
                                                    </button>
                                                </form>
                                            @endif
                                            @if($isEngineer && $t->canClaimExpense(auth()->id()))
                                                <button type="button" 
                                                        onclick="openTicketClaimExpenseModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', '{{ addslashes($t->branch_location) }}', '{{ addslashes($t->engineer?->base_city ?? auth()->user()->base_city ?? 'Lahore') }}')"
                                                        class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 font-bold transition cursor-pointer">
                                                    <i class="fa-solid fa-receipt text-emerald-600 w-4"></i>
                                                    <span>Claim Tour Expense</span>
                                                </button>
                                            @endif
                                            @if($t->status === 'resolved' || $t->status === 'closed')
                                                @if(auth()->user()->canReopenTickets())
                                                    @if($t->canBeReopened())
                                                        <button type="button" 
                                                                onclick="openIndexReopenModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', {{ ((int)$t->current_cycle_no ?: 1) + 1 }}, {{ $t->reopenDaysRemaining() }}, {{ $t->assigned_engineer_id ?? 'null' }}, {{ json_encode($t->activeTicketEngineers->where('role', 'support')->pluck('engineer_id')->values()->all()) }})"
                                                                class="w-full text-left flex items-center justify-between px-3 py-2 text-xs text-rose-700 hover:bg-rose-50 font-bold transition cursor-pointer">
                                                            <div class="flex items-center space-x-2">
                                                                <i class="fa-solid fa-rotate-left text-rose-600 w-4"></i>
                                                                <span>Reopen Ticket (Tour {{ ((int)$t->current_cycle_no ?: 1) + 1 }})</span>
                                                            </div>
                                                            <span class="text-[10px] font-normal text-rose-500">({{ $t->reopenDaysRemaining() }}d left)</span>
                                                        </button>
                                                    @elseif($t->isReopenWindowExpired())
                                                        <div class="flex items-center space-x-2 px-3 py-2 text-xs text-slate-400 font-semibold cursor-not-allowed" title="Reopen window expired (> 15 days)">
                                                            <i class="fa-solid fa-lock text-slate-400 w-4"></i>
                                                            <span>Reopen Expired (&gt; 15d)</span>
                                                        </div>
                                                    @endif
                                                @endif
                                                @if(!$isEngineer && $t->canSendResolutionEmail())
                                                    <button type="button" 
                                                            onclick="openResolutionEmailModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', '{{ addslashes($t->customer_email ?? '') }}', '{{ addslashes($t->customer_cc ?? '') }}', '{{ $t->hasSupportingDocument() ? $t->supportingDocumentUrl() : '' }}', '{{ $t->resolution_document_name ?? ($t->supporting_document ? basename($t->supporting_document) : '') }}')"
                                                            class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-sky-700 hover:bg-sky-50 font-bold transition cursor-pointer">
                                                        <i class="fa-solid fa-paper-plane text-sky-600 w-4"></i>
                                                        <span>{{ $t->resolution_email_sent ? 'Re-Send Resolution Email' : 'Send Resolution Email' }}</span>
                                                    </button>
                                                @endif
                                                @if(!$t->hasClaimedExpenses())
                                                    <form action="{{ route('tickets.undo-resolve', $t) }}" method="POST" onsubmit="return confirm('Revert Ticket #{{ $t->ticket_no }} back to In Progress?');">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-amber-700 hover:bg-amber-50 font-bold transition cursor-pointer">
                                                            <i class="fa-solid fa-rotate-left text-amber-600 w-4"></i>
                                                            <span>Undo Done</span>
                                                        </button>
                                                    </form>
                                                @else
                                                    <div class="flex items-center space-x-2 px-3 py-2 text-xs text-slate-400 font-semibold cursor-not-allowed">
                                                        <i class="fa-solid fa-lock text-slate-400 w-4"></i>
                                                        <span>Locked (Expenses Claimed)</span>
                                                    </div>
                                                @endif
                                            @endif
                                            @if(!$isEngineer)
                                                <a href="{{ route('tickets.edit', $t) }}" class="flex items-center space-x-2 px-3 py-2 text-xs text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 font-medium transition">
                                                    <i class="fa-solid fa-pen-to-square text-indigo-600 w-4"></i>
                                                    <span>Edit</span>
                                                </a>
                                            @endif
                                        </div>
                                        @if(!$isEngineer)
                                        <div class="py-1">
                                            @if(!$t->is_human_verified)
                                            <form action="{{ route('tickets.destroy', $t) }}" method="POST" onsubmit="return confirm('DELETE WRONGLY IDENTIFIED COMPLAINT?\n\nTicket #{{ $t->ticket_no }} has not yet been human finalized. Deleting will discard this complaint and reset the email back to Needs Triage.\n\nProceed?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left flex items-center space-x-2 px-3 py-2 text-xs text-rose-600 hover:bg-rose-50 font-bold transition">
                                                    <i class="fa-solid fa-trash-can text-rose-600 w-4"></i>
                                                    <span>Delete</span>
                                                </button>
                                            </form>
                                            @else
                                            <div class="flex items-center space-x-2 px-3 py-2 text-[11px] text-slate-400 cursor-not-allowed" title="Human finalized tickets cannot be deleted">
                                                <i class="fa-solid fa-lock text-slate-400 w-4"></i>
                                                <span>Delete (Locked)</span>
                                            </div>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- OPERATIONS MANAGER: Assigned Engineer column comes 2nd --}}
                            @if(!$isEngineer)
                                <td class="py-3 px-4">
                                    @if($t->engineer)
                                        <div class="font-bold text-slate-800">{{ $t->engineer->name }}</div>
                                        <div class="text-[11px] text-slate-500 flex items-center space-x-1 mt-0.5">
                                            <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                                            <span class="font-mono text-slate-600">{{ $t->engineer->phone_whatsapp }}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-400">{{ $t->engineer->base_city }}</div>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            <i class="fa-solid fa-user-xmark mr-1"></i> Needs Alignment
                                        </span>
                                    @endif
                                </td>
                            @endif

                            {{-- Ticket # --}}
                            <td class="py-3 px-4 font-bold text-slate-900 whitespace-nowrap">
                                <a href="{{ route('tickets.show', $t) }}" class="text-sky-600 hover:text-sky-800 hover:underline flex items-center space-x-1.5">
                                    <span>{{ $t->ticket_no }}</span>
                                </a>
                                @if($t->ticket_no_source === 'from_email')
                                    <span class="inline-block mt-0.5 text-[9px] px-1.5 py-0.2 bg-purple-100 text-purple-800 rounded font-semibold uppercase">From Bank Email</span>
                                @endif
                                <div>
                                    @if(!$t->is_human_verified)
                                        <span class="inline-block mt-0.5 text-[9px] px-1.5 py-0.2 bg-amber-100 text-amber-800 rounded font-semibold uppercase" title="Awaiting operator review and finalization">
                                            <i class="fa-solid fa-triangle-exclamation text-[8px] mr-0.5"></i> Needs Review
                                        </span>
                                    @else
                                        <span class="inline-block mt-0.5 text-[9px] px-1.5 py-0.2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded font-semibold uppercase" title="Finalized by human operator">
                                            <i class="fa-solid fa-check text-[8px] mr-0.5"></i> Finalized
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-slate-400 font-normal mt-0.5">{{ $t->created_at->format('d M Y, h:i A') }}</div>
                            </td>

                            {{-- Bank & Branch --}}
                            <td class="py-3 px-4">
                                <div class="font-extrabold text-slate-800">{{ $t->bank_name }}</div>
                                <div class="text-slate-600 text-[11px] mt-0.5">
                                    {{ $t->branch_name ?? 'Branch' }} &bull; <strong class="text-slate-800">{{ $t->branch_location }}</strong>
                                </div>
                                @if($t->branch_address)
                                    <div class="text-[10px] text-slate-500 truncate max-w-xs mt-0.5" title="{{ $t->branch_address }}">
                                        <i class="fa-solid fa-location-dot text-[9px] text-rose-500 mr-1"></i>{{ $t->branch_address }}
                                    </div>
                                @endif
                                @if($t->customer_name)
                                    <div class="text-[10px] text-slate-400 truncate max-w-xs mt-0.5">
                                        <i class="fa-solid fa-user text-[9px] mr-1"></i>{{ $t->customer_name }}
                                    </div>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase inline-block
                                    {{ $t->status === 'open' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $t->status === 'assigned' ? 'bg-sky-100 text-sky-800' : '' }}
                                    {{ $t->status === 'in_progress' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                    {{ $t->status === 'awaiting_approval' ? 'bg-amber-100 text-amber-900 border border-amber-300 font-extrabold' : '' }}
                                    {{ $t->status === 'awaiting_workshop' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $t->status === 'in_workshop_repair' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $t->status === 'workshop_repaired' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $t->status === 'return_transit' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                    {{ $t->status === 'escalated' ? 'bg-rose-100 text-rose-800 animate-pulse' : '' }}
                                    {{ $t->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $t->status === 'closed' ? 'bg-slate-200 text-slate-700' : '' }}">
                                    {{ str_replace('_', ' ', $t->status) }}
                                </span>
                                @if($t->hasSupportingDocument())
                                    <div class="mt-1">
                                        <a href="{{ $t->supportingDocumentUrl() }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition shadow-2xs" title="Signed slip / photo attached by engineer">
                                            <i class="fa-solid fa-paperclip text-emerald-600"></i>
                                            <span>Proof Attached</span>
                                        </a>
                                    </div>
                                @endif
                            </td>

                            {{-- SLA Progress Bar --}}
                            <td class="py-3 px-3 min-w-[130px] max-w-[170px]">
                                @php
                                    $sla = $t->getSlaHealth();
                                    $slaPercent = $sla['percent'] ?? 100;
                                    $isBreached = $sla['breached'] ?? false;
                                    $isResolved = in_array($t->status, ['resolved', 'closed']);
                                @endphp
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="font-bold flex items-center gap-1 {{ $isBreached ? 'text-rose-600 font-extrabold' : ($isResolved ? 'text-emerald-700' : 'text-slate-700') }}">
                                            <i class="fa-solid {{ $isResolved ? 'fa-circle-check text-emerald-600' : ($isBreached ? 'fa-triangle-exclamation text-rose-500 animate-pulse' : 'fa-gauge-high text-sky-600') }} text-[9px]"></i>
                                            <span>{{ $isResolved ? 'Resolved' : ($isBreached ? 'Breached' : $sla['label']) }}</span>
                                        </span>
                                        <span class="font-mono text-[10px] font-bold {{ $isBreached ? 'text-rose-600' : ($isResolved ? 'text-emerald-600' : 'text-slate-500') }}">
                                            {{ $isResolved ? '100%' : $slaPercent . '%' }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden shadow-inner">
                                        <div class="h-1.5 rounded-full transition-all duration-500 {{ $isResolved ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : ($isBreached ? 'bg-rose-600 animate-pulse' : ($sla['color'] === 'amber' ? 'bg-gradient-to-r from-amber-400 to-amber-500' : ($sla['color'] === 'rose' ? 'bg-gradient-to-r from-rose-500 to-red-500' : 'bg-gradient-to-r from-sky-400 to-emerald-500'))) }}" 
                                             style="width: {{ $isResolved ? 100 : max(5, $slaPercent) }}%"></div>
                                    </div>
                                    <div class="text-[9px] text-slate-400 flex items-center justify-between">
                                        <span>Due: {{ $t->sla_deadline ? $t->sla_deadline->format('d M, h:i A') : 'Standard SLA' }}</span>
                                        @if($t->hasSupportingDocument())
                                            <a href="{{ $t->supportingDocumentUrl() }}" target="_blank" class="text-emerald-700 font-bold hover:underline" title="View Supporting Document / Proof">
                                                <i class="fa-solid fa-paperclip"></i> Proof
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- OPERATIONS MANAGER: Resolution Email Column --}}
                            @if(!$isEngineer)
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    @if($t->resolution_email_sent)
                                        <div class="space-y-0.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200" title="Dispatched {{ $t->resolution_email_sent_at?->format('d M Y, h:i A') }}">
                                                <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i> Sent
                                            </span>
                                            <div class="text-[10px] text-slate-600 font-mono truncate max-w-[130px] mx-auto" title="{{ $t->resolution_email_to ?? $t->customer_email }}">
                                                {{ $t->resolution_email_to ?? $t->customer_email }}
                                            </div>
                                            @if($t->hasSupportingDocument())
                                                <div class="text-[9px] text-emerald-700 font-semibold" title="Engineer proof attached to email">
                                                    <i class="fa-solid fa-paperclip"></i> Proof Attached
                                                </div>
                                            @endif
                                        </div>
                                    @elseif(in_array($t->status, ['resolved', 'closed']))
                                        <button type="button" 
                                                onclick="openResolutionEmailModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', '{{ addslashes($t->customer_email ?? '') }}', '{{ addslashes($t->customer_cc ?? '') }}', '{{ $t->hasSupportingDocument() ? $t->supportingDocumentUrl() : '' }}', '{{ $t->resolution_document_name ?? ($t->supporting_document ? basename($t->supporting_document) : '') }}')"
                                                class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 transition cursor-pointer" 
                                                title="Complaint is resolved. Click to dispatch resolution email to bank">
                                            <i class="fa-solid fa-paper-plane mr-1 text-amber-600"></i> Send Email
                                        </button>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-400">
                                            Pending Resolution
                                        </span>
                                    @endif
                                </td>
                            @endif

                            {{-- Machine & Serial --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-bold text-slate-800">{{ $t->machine_type ?? 'Unspecified' }}</div>
                                <div class="text-[11px] text-slate-500 font-mono mt-0.5">{{ $t->machine_serial_no ?? 'No Serial' }}</div>
                                <span class="inline-block text-[9px] px-1.5 py-0.2 rounded font-bold uppercase mt-1 {{ $t->warranty_status === 'in_warranty' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ str_replace('_', ' ', $t->warranty_status) }}
                                </span>
                            </td>

                            {{-- Urgency --}}
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase inline-block
                                    {{ $t->urgency === 'high' ? 'bg-rose-100 text-rose-800 border border-rose-200' : '' }}
                                    {{ $t->urgency === 'medium' ? 'bg-amber-100 text-amber-800 border border-amber-200' : '' }}
                                    {{ $t->urgency === 'low' ? 'bg-slate-100 text-slate-700 border border-slate-200' : '' }}">
                                    {{ $t->urgency }}
                                </span>
                                <div class="text-[9px] text-slate-400 mt-0.5">{{ $t->expectedResponseHours() }}h TAT</div>
                            </td>

                            {{-- OPERATIONS MANAGER: WhatsApp & Bank Email columns --}}
                            @if(!$isEngineer)
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    @if($t->whatsapp_notified)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200" title="Dispatched {{ $t->whatsapp_notified_at?->format('h:i A') }}">
                                            <i class="fa-brands fa-whatsapp mr-1 text-emerald-600"></i> Sent
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-400">
                                            Not Sent
                                        </span>
                                    @endif
                                </td>

                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    @if($t->email_assignment_sent)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-200" title="Sent {{ $t->email_assignment_sent_at?->format('h:i A') }}">
                                            <i class="fa-solid fa-circle-check mr-1 text-sky-600"></i> Replied
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-400">
                                            Not Sent
                                        </span>
                                    @endif
                                </td>
                            @endif

                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ !$isEngineer ? 11 : 7 }}" class="py-12 text-center text-slate-400 text-xs">
                                <i class="fa-regular fa-folder-open text-3xl text-slate-300 mb-2 block"></i>
                                No complaints found matching current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Footer: Results Counter and Pagination -->
        <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
            <div>
                Showing <strong class="text-slate-900">{{ $tickets->firstItem() ?? 0 }}</strong> to <strong class="text-slate-900">{{ $tickets->lastItem() ?? 0 }}</strong> of <strong class="text-slate-900">{{ $tickets->total() }}</strong> complaints
            </div>
            <div>
                {{ $tickets->links() }}
            </div>
        </div>

    </div>

</div>

<script>
function toggleActionMenu(event, menuId) {
    event.stopPropagation();
    const targetMenu = document.getElementById(menuId);
    if (!targetMenu) return;
    const isCurrentlyOpen = !targetMenu.classList.contains('hidden');
    
    // Close all open action menus
    document.querySelectorAll('.action-menu-dropdown').forEach(m => m.classList.add('hidden'));
    
    if (!isCurrentlyOpen) {
        targetMenu.classList.remove('hidden');
    }
}

// Close any open action menu on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-menu-dropdown') && !e.target.closest('button')) {
        document.querySelectorAll('.action-menu-dropdown').forEach(m => m.classList.add('hidden'));
    }
});
</script>
@include('tickets.partials.mark-complete-modal')
@include('tickets.partials.workshop-modal')
@include('tickets.partials.claim-expense-modal')
@include('tickets.partials.approval-modals')
@if(!$isEngineer)
    @include('tickets.partials.send-resolution-email-modal')
    @include('tickets.partials.index-reopen-modal')
@endif
@endsection

