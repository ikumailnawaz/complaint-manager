@extends('layouts.app')

@section('title', 'Tour Expense Registry - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="p-2 bg-emerald-100 text-emerald-700 rounded-xl text-sm">
                    <i class="fa-solid fa-receipt"></i>
                </span>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                    Tour Expense Registry &amp; Disbursements
                </h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-10">
                Field engineer tour travel claims, tentative distance verification, voucher auditing, and bulk disbursement.
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <!-- Quick New Claim Button -->
            <button type="button" 
                    onclick="openExpenseClaimModal()"
                    class="inline-flex items-center px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-sm transition cursor-pointer">
                <i class="fa-solid fa-plus-circle mr-1.5"></i> Claim Tour Expense
            </button>
            @if(!auth()->user()->isEngineer())
            <!-- Multi-Sheet Excel Export (Master + Engineer Summary) -->
            <a href="{{ route('expenses.export-csv', array_merge(request()->query(), ['format' => 'excel'])) }}" 
               title="Export true Excel workbook with Sheet 1 (Master Claims) and Sheet 2 (Engineer Summary)"
               class="inline-flex items-center px-3.5 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-file-excel mr-1.5 text-emerald-200"></i> Export Excel (2 Sheets)
            </a>
            <!-- CSV Export (Master + Summary) -->
            <a href="{{ route('expenses.export-csv', array_merge(request()->query(), ['format' => 'csv'])) }}" 
               title="Export CSV with Master Sheet and Engineer Summary sections"
               class="inline-flex items-center px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-file-csv mr-1.5 text-emerald-400"></i> Export CSV
            </a>
            @endif
        </div>
    </div>

    <!-- Resolved Ticket Prompt Banner (When redirected from completing a ticket) -->
    @if(isset($selectedTicket) && $selectedTicket)
    <div class="bg-gradient-to-r from-emerald-950 via-slate-900 to-teal-950 border-2 border-emerald-500/60 rounded-2xl p-5 shadow-xl text-white space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center space-x-3.5">
                <span class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl border border-emerald-500/40 shadow-inner flex-shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt"></i> Work Completed &bull; Claim Tour Expenses
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-900/80 text-emerald-200 border border-emerald-700">#{{ $selectedTicket->ticket_no }}</span>
                    </div>
                    <h2 class="text-base font-bold text-white mt-0.5">
                        {{ $selectedTicket->bank_name }} &bull; {{ $selectedTicket->branch_name ?? 'Branch' }} ({{ $selectedTicket->branch_location }})
                    </h2>
                    <p class="text-xs text-slate-300 mt-0.5">
                        <span>Machine: <strong class="text-white">{{ $selectedTicket->machine_type ?? 'Banking Device' }}</strong> (S/N: {{ $selectedTicket->machine_serial_no ?? 'N/A' }})</span>
                        <span class="mx-2 text-slate-600">&bull;</span>
                        <span>Designated Engineer: <strong class="text-white">{{ $selectedTicket->engineer?->name ?? auth()->user()->name }}</strong></span>
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-2 flex-shrink-0">
                <button type="button" 
                        onclick="openExpenseClaimModal({{ $selectedTicket->id }}, '{{ $selectedTicket->ticket_no }}', '{{ addslashes($selectedTicket->bank_name) }}', '{{ addslashes($selectedTicket->branch_location) }}', '{{ addslashes($selectedTicket->engineer?->base_city ?? auth()->user()->base_city ?? 'Lahore') }}')"
                        class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-lg hover:shadow transition flex items-center space-x-1.5 cursor-pointer active:scale-95">
                    <i class="fa-solid fa-receipt text-sm"></i>
                    <span>Claim Tour Expense Now</span>
                </button>
                <a href="{{ route('tickets.show', $selectedTicket) }}" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-xs font-semibold rounded-xl transition" title="View Ticket Details">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-xs">
        <a href="{{ route('expenses.index') }}" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-slate-400 transition group block">
            <span class="text-slate-500 uppercase font-semibold text-[10px] group-hover:text-slate-700">Total Claims</span>
            <div class="text-xl font-extrabold text-slate-800 mt-1">{{ $stats['total_claims'] }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5 font-medium">All logged expenses</div>
        </a>
        <a href="{{ route('expenses.index', ['settlement_status' => 'paid']) }}" class="bg-white p-4 rounded-xl border border-emerald-200 bg-emerald-50/20 shadow-sm hover:border-emerald-400 transition group block {{ request('settlement_status') === 'paid' ? 'ring-2 ring-emerald-500' : '' }}">
            <span class="text-emerald-700 uppercase font-semibold text-[10px]">Paid &amp; Settled</span>
            <div class="text-xl font-extrabold text-emerald-700 mt-1">{{ $stats['paid'] }} Paid</div>
            <div class="text-[10px] text-emerald-600 mt-0.5 font-bold">PKR {{ number_format($stats['total_paid_amount'], 2) }}</div>
        </a>
        <a href="{{ route('expenses.index', ['settlement_status' => 'unpaid']) }}" class="bg-white p-4 rounded-xl border border-amber-200 bg-amber-50/20 shadow-sm hover:border-amber-400 transition group block {{ request('settlement_status') === 'unpaid' ? 'ring-2 ring-amber-500' : '' }}">
            <span class="text-amber-700 uppercase font-semibold text-[10px]">Unpaid &amp; Unsettled</span>
            <div class="text-xl font-extrabold text-amber-700 mt-1">{{ $stats['unpaid'] }} Claims</div>
            <div class="text-[10px] text-amber-600 mt-0.5 font-bold">PKR {{ number_format($stats['total_unpaid_amount'], 2) }}</div>
        </a>
        <a href="{{ route('expenses.index', ['claim_status' => 'approved', 'settlement_status' => 'unpaid']) }}" class="bg-white p-4 rounded-xl border border-blue-200 bg-blue-50/20 shadow-sm hover:border-blue-400 transition group block {{ (request('claim_status') === 'approved' && request('settlement_status') === 'unpaid') ? 'ring-2 ring-blue-500' : '' }}">
            <span class="text-blue-700 uppercase font-semibold text-[10px]">Approved (Ready to Pay)</span>
            <div class="text-xl font-extrabold text-blue-700 mt-1">{{ $stats['approved'] }}</div>
            <div class="text-[10px] text-blue-600 mt-0.5 font-semibold">Verified for disbursement</div>
        </a>
        <a href="{{ route('expenses.index', ['claim_status' => 'not_submitted']) }}" class="bg-white p-4 rounded-xl border border-orange-200 bg-orange-50/20 shadow-sm hover:border-orange-400 transition group block {{ request('claim_status') === 'not_submitted' ? 'ring-2 ring-orange-500' : '' }}">
            <span class="text-orange-700 uppercase font-semibold text-[10px]">Not Submitted</span>
            <div class="text-xl font-extrabold text-orange-700 mt-1">{{ $stats['not_submitted'] }} Tickets</div>
            <div class="text-[10px] text-orange-600 mt-0.5 font-semibold">Eligible tickets pending claim</div>
        </a>
    </div>

    <!-- Data Table Container with Filters & "Show By" Selector -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

        <!-- Filters Form -->
        <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50/75 space-y-3">
            <form action="{{ route('expenses.index') }}" method="GET" class="space-y-3">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs">
                    <!-- Search (3 cols) -->
                    <div class="lg:col-span-3">
                        <label class="block font-semibold text-slate-700 mb-1">
                            <i class="fa-solid fa-magnifying-glass mr-1 text-slate-400"></i> Search Tour Claims
                        </label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ request('search') }}" 
                                placeholder="Ticket #, Engineer, City, Ref #..." 
                                class="w-full pl-3 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 transition">
                        </div>
                    </div>

                    <!-- Engineer Filter (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">
                            <i class="fa-solid fa-user-gear mr-1 text-slate-400"></i> Engineer
                        </label>
                        <select name="engineer_id" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-emerald-500" onchange="this.form.submit()">
                            <option value="">All Engineers</option>
                            @if(isset($engineers))
                                @foreach($engineers as $eng)
                                    <option value="{{ $eng->id }}" {{ request('engineer_id') == $eng->id ? 'selected' : '' }}>
                                        {{ $eng->name }} ({{ $eng->base_city }})
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <!-- Filter 1: Settlement / Payment Status (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">
                            <i class="fa-solid fa-money-bill-check mr-1 text-emerald-600"></i> Settlement Status
                        </label>
                        <select name="settlement_status" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs font-semibold focus:ring-2 focus:ring-emerald-500" onchange="this.form.submit()">
                            <option value="">All Settlement</option>
                            <option value="paid" {{ request('settlement_status') === 'paid' ? 'selected' : '' }}>Paid &amp; Settled</option>
                            <option value="unpaid" {{ request('settlement_status') === 'unpaid' ? 'selected' : '' }}>Unpaid &amp; Unsettled</option>
                        </select>
                    </div>

                    <!-- Filter 2: Claim Lifecycle Status (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">
                            <i class="fa-solid fa-clipboard-check mr-1 text-blue-600"></i> Claim Status
                        </label>
                        <select name="claim_status" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs font-semibold focus:ring-2 focus:ring-emerald-500" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="approved" {{ request('claim_status', request('status')) === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('claim_status', request('status')) === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="submitted" {{ request('claim_status', request('status')) === 'submitted' ? 'selected' : '' }}>Submitted (Pending)</option>
                            <option value="not_submitted" {{ request('claim_status', request('status')) === 'not_submitted' ? 'selected' : '' }}>Not Submitted</option>
                        </select>
                    </div>

                    <!-- Filter 3: Date Range (From & To) (3 cols) -->
                    <div class="lg:col-span-3 grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                <i class="fa-regular fa-calendar mr-1 text-slate-400"></i> From
                            </label>
                            <input type="date" name="from_date" value="{{ request('from_date', request('date_from')) }}" class="w-full bg-white border border-slate-300 rounded-xl py-1.5 px-2 text-xs focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                <i class="fa-regular fa-calendar mr-1 text-slate-400"></i> To
                            </label>
                            <input type="date" name="to_date" value="{{ request('to_date', request('date_to')) }}" class="w-full bg-white border border-slate-300 rounded-xl py-1.5 px-2 text-xs focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 border-t border-slate-200/60 text-xs">
                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs shadow-sm transition">
                            <i class="fa-solid fa-filter mr-1"></i> Apply Filters
                        </button>
                        <a href="{{ route('expenses.index') }}" class="px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold rounded-xl text-xs transition">
                            Reset
                        </a>
                        @if(request('settlement_status') || request('claim_status') || request('status') || request('engineer_id') || request('search') || request('from_date') || request('to_date'))
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i> Active Filtered View
                            </span>
                        @endif
                    </div>

                    <!-- SHOW BY PER PAGE SELECTOR -->
                    <div class="flex items-center space-x-2">
                        <label class="font-semibold text-slate-600 whitespace-nowrap">Show by:</label>
                        <select name="per_page" class="bg-white border border-slate-300 rounded-xl py-1.5 px-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500" onchange="this.form.submit()">
                            <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10 / page</option>
                            <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 / page</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 / page</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 / page</option>
                            <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>Show All</option>
                        </select>
                    </div>
                </div>

            </form>
        </div>

        @if(isset($isNotSubmitted) && $isNotSubmitted)
            <!-- NOT SUBMITTED TICKETS VIEW -->
            <div class="p-3 bg-amber-50/80 border-b border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                <div class="flex items-center space-x-2 text-amber-900 font-bold">
                    <span class="p-1.5 bg-amber-200 text-amber-800 rounded-lg"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <span>Tickets Pending Tour Expense Claim Submission</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-200/90 text-amber-800">{{ $notSubmittedTickets->total() }} pending</span>
                </div>
                <div class="text-[11px] text-amber-700">
                    Engineers can submit their travel claims directly for these active/resolved tickets.
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                            <th class="py-3 px-4 whitespace-nowrap">Ticket #</th>
                            <th class="py-3 px-4">Designated Engineer</th>
                            <th class="py-3 px-4">Bank &amp; Branch Location</th>
                            <th class="py-3 px-4">Machine &amp; Urgency</th>
                            <th class="py-3 px-4 text-center">Ticket Status</th>
                            <th class="py-3 px-4 text-center">Claim Status</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($notSubmittedTickets as $t)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 font-bold text-slate-800 whitespace-nowrap">
                                    <a href="{{ route('tickets.show', $t) }}" class="text-sky-600 hover:text-sky-800 hover:underline">
                                        #{{ $t->ticket_no }}
                                    </a>
                                    <div class="text-[10px] text-slate-400 font-normal mt-0.5">{{ $t->created_at->format('d M Y H:i') }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800">{{ $t->assignedEngineer?->name ?? 'Unassigned' }}</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ $t->assignedEngineer?->base_city ?? 'Lahore' }} &bull; {{ $t->assignedEngineer?->phone_whatsapp ?? 'N/A' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800">{{ $t->bank_name }}</div>
                                    <div class="text-[11px] text-slate-600 mt-0.5">{{ $t->branch_name ?? 'Branch' }} ({{ $t->branch_location }})</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-800">{{ $t->machine_type ?? 'ATM / Machine' }}</div>
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[9px] font-bold uppercase
                                        {{ $t->urgency === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $t->urgency ?? 'Normal' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase inline-block bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ str_replace('_', ' ', $t->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase inline-block bg-amber-100 text-amber-800">
                                        Not Submitted
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <button type="button" 
                                            onclick="openExpenseClaimModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', '{{ addslashes($t->branch_location) }}', '{{ addslashes($t->assignedEngineer?->base_city ?? 'Lahore') }}')"
                                            class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                        <i class="fa-solid fa-receipt mr-1 text-[11px]"></i>
                                        <span>Claim Now</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                    <i class="fa-solid fa-circle-check text-3xl text-emerald-400 mb-2 block"></i>
                                    No tickets found pending tour expense claim submission!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
                <div>
                    Showing <strong class="text-slate-900">{{ $notSubmittedTickets->firstItem() ?? 0 }}</strong> to <strong class="text-slate-900">{{ $notSubmittedTickets->lastItem() ?? 0 }}</strong> of <strong class="text-slate-900">{{ $notSubmittedTickets->total() }}</strong> unsubmitted tickets
                </div>
                <div>
                    {{ $notSubmittedTickets->links() }}
                </div>
            </div>

        @else
            <!-- Bulk Pay Form & Standard Data Table -->
            <form action="{{ route('expenses.bulk-pay') }}" method="POST">
                @csrf

                <!-- Admin Bulk Action Bar -->
                @if(!auth()->user()->isEngineer())
                    <div class="p-3 bg-slate-100 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="flex items-center space-x-2">
                            <span class="p-1 bg-emerald-600 text-white rounded text-[10px]"><i class="fa-solid fa-money-bill-transfer"></i></span>
                            <span class="font-bold text-slate-800">Bulk Batch Payment:</span>
                            <span class="text-slate-500 text-[11px] hidden sm:inline">Select approved claims with checkboxes below</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <select name="payment_method" required class="border border-slate-300 rounded-lg px-2 py-1 text-xs bg-white">
                                <option value="bank_transfer">Online Bank Transfer</option>
                                <option value="cash">Cash Voucher</option>
                                <option value="cheque">Company Cheque</option>
                            </select>
                            <input type="text" name="batch_reference" required placeholder="Batch Ref # (e.g. BATCH-9921)" class="border border-slate-300 rounded-lg px-2 py-1 text-xs bg-white">
                            <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                                Disburse Selected
                            </button>
                        </div>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                                @if(!auth()->user()->isEngineer())
                                    <th class="py-3 px-4 w-8 text-center">
                                        <input type="checkbox" onclick="toggleAllClaims(this)" class="rounded text-emerald-600">
                                    </th>
                                @endif
                                <th class="py-3 px-4 whitespace-nowrap">Claim #</th>
                                <th class="py-3 px-4">Engineer</th>
                                <th class="py-3 px-4">Linked Complaint Ticket</th>
                                <th class="py-3 px-4">Route &amp; AI Distance</th>
                                <th class="py-3 px-4">Amount Claimed</th>
                                <th class="py-3 px-3 text-center">Voucher File</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Audit &amp; Pay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($claims as $c)
                                <tr class="hover:bg-slate-50 transition">
                                    @if(!auth()->user()->isEngineer())
                                        <td class="py-3 px-4 text-center">
                                            @if($c->status === 'approved')
                                                <input type="checkbox" name="claim_ids[]" value="{{ $c->id }}" class="claim-check rounded text-emerald-600">
                                            @endif
                                        </td>
                                    @endif

                                    <!-- Claim # -->
                                    <td class="py-3 px-4 font-bold text-slate-800 whitespace-nowrap">
                                        <a href="{{ route('expenses.show', $c) }}" class="text-sky-600 hover:text-sky-800 hover:underline">
                                            #EXP-{{ str_pad($c->id, 4, '0', STR_PAD_LEFT) }}
                                        </a>
                                        <div class="text-[10px] text-slate-400 font-normal mt-0.5">{{ $c->created_at->format('d M Y') }}</div>
                                        <span class="inline-block mt-1 px-1.5 py-0.2 text-[9px] font-bold rounded uppercase
                                            {{ $c->category === 'fuel' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $c->category === 'accommodation' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                            {{ $c->category === 'parts' ? 'bg-purple-100 text-purple-800' : '' }}
                                            {{ $c->category === 'food' ? 'bg-teal-100 text-teal-800' : '' }}
                                            {{ $c->category === 'misc' ? 'bg-slate-100 text-slate-800' : '' }}
                                            {{ (!$c->category || $c->category === 'travel') ? 'bg-sky-100 text-sky-800' : '' }}">
                                            {{ $c->category ?? 'Travel' }}
                                        </span>
                                    </td>

                                    <!-- Engineer -->
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-800">{{ $c->engineer?->name }}</div>
                                        <div class="text-[10px] text-slate-500 mt-0.5">{{ $c->engineer?->base_city }} &bull; {{ $c->engineer?->phone_whatsapp }}</div>
                                    </td>

                                    <!-- Linked Ticket -->
                                    <td class="py-3 px-4">
                                        <a href="{{ route('tickets.show', $c->ticket) }}" class="font-bold text-sky-600 hover:underline">
                                            {{ $c->ticket?->ticket_no }}
                                        </a>
                                        <div class="text-[11px] text-slate-600 mt-0.5">{{ $c->ticket?->bank_name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $c->ticket?->branch_location }}</div>
                                    </td>

                                    <!-- Route & Distance -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-bold text-slate-800">{{ $c->from_city }} &rarr; {{ $c->to_city }}</div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            {{ $c->trip_type === 'round_trip' ? 'Round Trip' : 'One Way' }} &bull;
                                            <strong class="text-slate-800 font-bold">{{ $c->ai_distance_km ?? 0 }} km</strong> (AI Est)
                                        </div>
                                    </td>

                                    <!-- Claimed Amount -->
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-extrabold text-slate-900 text-sm">PKR {{ number_format($c->claimed_amount, 2) }}</div>
                                        @if($c->suggested_amount)
                                            <div class="text-[10px] text-slate-400 mt-0.5">Rate guide: PKR {{ number_format($c->suggested_amount, 2) }}</div>
                                        @endif
                                    </td>

                                    <!-- Voucher Document -->
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        @if($c->voucher_file)
                                            <a href="{{ asset('storage/' . $c->voucher_file) }}" target="_blank" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition">
                                                <i class="fa-solid fa-file-pdf mr-1 text-rose-500"></i> View
                                            </a>
                                        @else
                                            <span class="text-slate-400 text-[10px]">No File</span>
                                        @endif
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase inline-block
                                            {{ $c->status === 'submitted' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $c->status === 'approved' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $c->status === 'rejected' ? 'bg-rose-100 text-rose-800' : '' }}
                                            {{ $c->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : '' }}">
                                            {{ $c->status }}
                                        </span>
                                        @if($c->resubmission_count > 0)
                                            <span class="block text-[9px] text-amber-700 font-bold mt-0.5">Re-submitted x{{ $c->resubmission_count }}</span>
                                        @endif
                                    </td>

                                    <!-- Action -->
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <a href="{{ route('expenses.show', $c) }}" class="inline-flex items-center px-3 py-1.5 bg-slate-900 hover:bg-sky-600 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                            <span>Audit</span>
                                            <i class="fa-solid fa-arrow-right ml-1.5 text-[9px]"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-12 text-center text-slate-400 text-xs">
                                        <i class="fa-solid fa-receipt text-3xl text-slate-300 mb-2 block"></i>
                                        No tour expense claims found matching current criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
                    <div>
                        Showing <strong class="text-slate-900">{{ $claims->firstItem() ?? 0 }}</strong> to <strong class="text-slate-900">{{ $claims->lastItem() ?? 0 }}</strong> of <strong class="text-slate-900">{{ $claims->total() }}</strong> claims
                    </div>
                    <div>
                        {{ $claims->links() }}
                    </div>
                </div>

            </form>
        @endif
    </div>
</div>

<!-- Quick Tour Expense Claim Modal -->
<div id="quickExpenseClaimModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full border border-slate-200 overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="bg-emerald-600 px-6 py-4 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg shadow-inner">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm tracking-wide">Submit Tour Travel &amp; Field Expense Claim</h3>
                    <p class="text-[11px] text-emerald-100" id="claimModalSubtitle">
                        Linked to verified complaint service visit
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeExpenseClaimModal()" class="text-emerald-100 hover:text-white text-lg p-1.5 rounded-lg hover:bg-white/10 transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Form -->
        <form action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 text-xs">
            @csrf

            <!-- Ticket Selection -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">Linked Complaint Ticket <span class="text-rose-500">*</span></label>
                <select name="ticket_id" id="modal_ticket_id" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs bg-white focus:ring-2 focus:ring-emerald-500" onchange="autoFillClaimCities(this)">
                    <option value="">-- Choose Completed Complaint Ticket --</option>
                    @if(isset($claimableTickets))
                        @foreach($claimableTickets as $ct)
                            <option value="{{ $ct->id }}"
                                data-branch-city="{{ $ct->branch_location }}"
                                data-engineer-city="{{ $ct->engineer?->base_city ?? auth()->user()->base_city ?? 'Lahore' }}"
                                {{ (isset($selectedTicket) && $selectedTicket->id == $ct->id) ? 'selected' : '' }}>
                                {{ $ct->ticket_no }} &bull; {{ $ct->bank_name }} ({{ $ct->branch_location }}) &bull; [{{ strtoupper(str_replace('_', ' ', $ct->status)) }}]
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>

            <!-- Cities & Trip Type -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Starting City <span class="text-rose-500">*</span></label>
                    <input type="text" id="modal_from_city" name="from_city" required 
                           value="{{ old('from_city', (isset($selectedTicket) && $selectedTicket->engineer) ? $selectedTicket->engineer->base_city : (auth()->user()->base_city ?? 'Lahore')) }}" 
                           placeholder="e.g. Lahore" class="w-full border border-slate-300 rounded-xl p-2 text-xs">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Destination City <span class="text-rose-500">*</span></label>
                    <input type="text" id="modal_to_city" name="to_city" required 
                           value="{{ old('to_city', isset($selectedTicket) ? $selectedTicket->branch_location : '') }}" 
                           placeholder="e.g. Sahiwal" class="w-full border border-slate-300 rounded-xl p-2 text-xs">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Trip Type <span class="text-rose-500">*</span></label>
                    <select name="trip_type" id="modal_trip_type" required class="w-full border border-slate-300 rounded-xl p-2 text-xs bg-white">
                        <option value="round_trip" selected>Round Trip (Return)</option>
                        <option value="one_way">One Way</option>
                    </select>
                </div>
            </div>

            <!-- Category & Claim Amount -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Expense Category <span class="text-rose-500">*</span></label>
                    <select name="category" required class="w-full border border-slate-300 rounded-xl p-2 text-xs bg-white">
                        <option value="travel" selected>🚗 Inter-City Travel &amp; Fuel</option>
                        <option value="fuel">⛽ Local Branch Fuel / Patrol</option>
                        <option value="accommodation">🏨 Hotel &amp; Lodging</option>
                        <option value="parts">🔩 Emergency Parts / Hardware</option>
                        <option value="food">🍱 Daily Meals / Per Diem</option>
                        <option value="misc">🧾 Tolls &amp; Miscellaneous</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Claim Amount (PKR) <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">PKR</span>
                        <input type="number" step="0.01" min="1" name="claimed_amount" required placeholder="e.g. 5000.00" 
                               class="w-full pl-12 pr-3 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Description / Notes</label>
                <textarea name="description" rows="2" placeholder="Describe trip purpose, toll plazas crossed, or maintenance parts incurred..." class="w-full border border-slate-300 rounded-xl p-2 text-xs focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <!-- Vouchers & Proof -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="p-3 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50/50 hover:border-emerald-500 transition">
                    <label class="block font-bold text-slate-700 mb-0.5">
                        <i class="fa-solid fa-receipt text-emerald-600 mr-1"></i> Toll / Fuel Voucher
                    </label>
                    <p class="text-[10px] text-slate-400 mb-1.5">PDF or photo of receipts</p>
                    <input type="file" name="voucher_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200">
                </div>

                <div class="p-3 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50/50 hover:border-sky-500 transition">
                    <label class="block font-bold text-slate-700 mb-0.5">
                        <i class="fa-solid fa-file-signature text-sky-600 mr-1"></i> Supporting Doc / JVC
                    </label>
                    <p class="text-[10px] text-slate-400 mb-1.5">Signed JVC slip or work report</p>
                    <input type="file" name="supporting_doc" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-sky-100 file:text-sky-800 hover:file:bg-sky-200">
                </div>
            </div>

            <!-- Strict Audit Notice -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-[11px] text-amber-900 flex items-start space-x-2">
                <i class="fa-solid fa-shield-halved text-amber-600 mt-0.5"></i>
                <div>
                    <strong>STRICT AUDIT RULE:</strong> Once this expense claim is submitted and registered against the resolved ticket, the resolution is permanently locked and cannot be undone.
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeExpenseClaimModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Submit Tour Claim</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAllClaims(master) {
        document.querySelectorAll('.claim-check').forEach(cb => cb.checked = master.checked);
    }

    function openExpenseClaimModal(ticketId, ticketNo, bankName, branchCity, engineerCity) {
        const modal = document.getElementById('quickExpenseClaimModal');
        if (!modal) return;

        if (ticketId) {
            const select = document.getElementById('modal_ticket_id');
            if (select) {
                select.value = ticketId;
            }
        }

        if (ticketNo) {
            const sub = document.getElementById('claimModalSubtitle');
            if (sub) {
                sub.innerHTML = '<span class="font-mono font-bold bg-emerald-800/80 px-1.5 py-0.5 rounded">#' + ticketNo + '</span> ' + (bankName ? ('• ' + bankName) : '');
            }
        }

        if (branchCity) {
            const toCityInput = document.getElementById('modal_to_city');
            if (toCityInput) toCityInput.value = branchCity;
        }

        if (engineerCity) {
            const fromCityInput = document.getElementById('modal_from_city');
            if (fromCityInput && !fromCityInput.value) fromCityInput.value = engineerCity;
        }

        modal.classList.remove('hidden');
    }

    function closeExpenseClaimModal() {
        const modal = document.getElementById('quickExpenseClaimModal');
        if (modal) modal.classList.add('hidden');
    }

    function autoFillClaimCities(select) {
        const option = select.options[select.selectedIndex];
        if (option && option.dataset.branchCity) {
            document.getElementById('modal_to_city').value = option.dataset.branchCity;
            if (option.dataset.engineerCity) {
                document.getElementById('modal_from_city').value = option.dataset.engineerCity;
            }
        }
    }

    // Auto-open modal if redirected with ticket_id or claim_modal query parameter
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('claim_modal') === '1' || urlParams.get('ticket_id')) {
            const ticketId = urlParams.get('ticket_id');
            @if(isset($selectedTicket) && $selectedTicket)
                openExpenseClaimModal(
                    {{ $selectedTicket->id }}, 
                    '{{ $selectedTicket->ticket_no }}', 
                    '{{ addslashes($selectedTicket->bank_name) }}', 
                    '{{ addslashes($selectedTicket->branch_location) }}', 
                    '{{ addslashes($selectedTicket->engineer?->base_city ?? auth()->user()->base_city ?? 'Lahore') }}'
                );
            @else
                if (ticketId) {
                    openExpenseClaimModal(ticketId);
                }
            @endif
        }
    });
</script>
@endsection
