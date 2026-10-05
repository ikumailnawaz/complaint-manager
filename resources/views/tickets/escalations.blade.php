@extends('layouts.app')

@section('title', 'Superior Escalation Command Center — Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Top Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 border border-rose-900/60 rounded-2xl p-6 shadow-xl text-white">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2 text-rose-400 font-bold uppercase tracking-wider text-xs">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
                    <span>Executive Oversight &amp; Strict SLA Enforcement</span>
                </div>
                <h1 class="text-2xl font-extrabold tracking-tight mt-1 text-white">
                    Regional Superior Escalations Command
                </h1>
                <p class="text-sm text-slate-300 mt-1 max-w-2xl leading-relaxed">
                    Real-time monitoring of complaints with breached SLAs, stalled engineer feedback, or priority escalation flags across all commercial bank networks.
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <form action="{{ route('tickets.open') }}" method="GET">
                    <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow flex items-center space-x-2">
                        <i class="fa-solid fa-clock-rotate-left text-amber-400"></i>
                        <span>Unassigned Open ({{ $stats['unassigned'] }})</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total In Escalation -->
        <div class="bg-white border border-rose-200 rounded-2xl p-5 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Escalated Status</div>
                    <div class="text-3xl font-black text-rose-600 mt-1">{{ $stats['total_escalated'] }}</div>
                </div>
                <div class="w-12 h-12 bg-rose-50 border border-rose-100 rounded-2xl flex items-center justify-center text-rose-600 text-xl shadow-inner">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
            <div class="mt-3 text-[11px] text-slate-500 flex items-center gap-1 font-medium">
                <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                <span>Assigned to Superior for intervention</span>
            </div>
        </div>

        <!-- SLA Breached -->
        <div class="bg-white border border-red-200 rounded-2xl p-5 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">SLA Deadline Breached</div>
                    <div class="text-3xl font-black text-red-600 mt-1">{{ $stats['sla_breached'] }}</div>
                </div>
                <div class="w-12 h-12 bg-red-50 border border-red-100 rounded-2xl flex items-center justify-center text-red-600 text-xl shadow-inner">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>
            <div class="mt-3 text-[11px] text-red-600 font-bold flex items-center gap-1">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>Overdue turnaround time</span>
            </div>
        </div>

        <!-- High Urgency At Risk -->
        <div class="bg-white border border-amber-200 rounded-2xl p-5 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">High Urgency Breaches</div>
                    <div class="text-3xl font-black text-amber-600 mt-1">{{ $stats['high_urgency'] }}</div>
                </div>
                <div class="w-12 h-12 bg-amber-50 border border-amber-100 rounded-2xl flex items-center justify-center text-amber-600 text-xl shadow-inner">
                    <i class="fa-solid fa-bolt"></i>
                </div>
            </div>
            <div class="mt-3 text-[11px] text-amber-700 font-medium">
                VIP / Main branch ATMs &amp; CDMs
            </div>
        </div>

        <!-- Open Awaiting Alignment -->
        <div class="bg-white border border-sky-200 rounded-2xl p-5 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Unassigned Intake</div>
                    <div class="text-3xl font-black text-sky-600 mt-1">{{ $stats['unassigned'] }}</div>
                </div>
                <div class="w-12 h-12 bg-sky-50 border border-sky-100 rounded-2xl flex items-center justify-center text-sky-600 text-xl shadow-inner">
                    <i class="fa-solid fa-inbox"></i>
                </div>
            </div>
            <div class="mt-3 text-[11px] text-sky-700 font-medium">
                Pending engineer assignment
            </div>
        </div>
    </div>

    <!-- Escalated Tickets Table Card -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        
        <!-- Controls & Filter Toolbar -->
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/80 space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-fire text-rose-600"></i>
                        <span>Escalated Complaints Registry &amp; Strict Audit Table</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Filter by bank, show custom rows, or perform instantaneous in-table search across all fields.</p>
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-800 font-bold text-[11px] border border-rose-200">
                        {{ $escalatedTickets->total() }} Complaints Requiring Superior Intervention
                    </span>
                </div>
            </div>

            <!-- Interactive Filters Form -->
            <form action="{{ route('tickets.escalations') }}" method="GET" id="escalationFilterForm" class="flex flex-wrap items-center gap-2.5 pt-1">
                
                <!-- 1. Show By Entries -->
                <div class="flex items-center space-x-1.5 bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-xs">
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Show:</label>
                    <select name="per_page" onchange="this.form.submit()" class="bg-transparent text-xs font-bold text-slate-800 focus:outline-hidden cursor-pointer">
                        <option value="10" {{ ($perPageParam ?? '') === '10' ? 'selected' : '' }}>10 entries</option>
                        <option value="25" {{ ($perPageParam ?? '25') === '25' ? 'selected' : '' }}>25 entries</option>
                        <option value="50" {{ ($perPageParam ?? '') === '50' ? 'selected' : '' }}>50 entries</option>
                        <option value="100" {{ ($perPageParam ?? '') === '100' ? 'selected' : '' }}>100 entries</option>
                        <option value="all" {{ ($perPageParam ?? '') === 'all' ? 'selected' : '' }}>All entries</option>
                    </select>
                </div>

                <!-- 2. Bank Filter -->
                <div class="flex items-center space-x-1.5 bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-xs">
                    <i class="fa-solid fa-building-columns text-slate-400 text-xs"></i>
                    <select name="bank" onchange="this.form.submit()" class="bg-transparent text-xs font-bold text-slate-800 focus:outline-hidden cursor-pointer max-w-[210px]">
                        <option value="">-- All Banks (Filter) --</option>
                        @foreach($banks as $b)
                            <option value="{{ $b }}" {{ request('bank') === $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. Urgency Filter -->
                <div class="flex items-center space-x-1.5 bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-xs">
                    <i class="fa-solid fa-flag text-slate-400 text-xs"></i>
                    <select name="urgency" onchange="this.form.submit()" class="bg-transparent text-xs font-bold text-slate-800 focus:outline-hidden cursor-pointer">
                        <option value="">All Urgencies</option>
                        <option value="high" {{ request('urgency') === 'high' ? 'selected' : '' }}>High Priority Only</option>
                        <option value="medium" {{ request('urgency') === 'medium' ? 'selected' : '' }}>Medium Priority</option>
                        <option value="low" {{ request('urgency') === 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                <!-- 4. City Filter -->
                <div class="flex items-center space-x-1.5 bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-xs">
                    <i class="fa-solid fa-location-dot text-slate-400 text-xs"></i>
                    <input type="text" name="city" value="{{ request('city') }}" placeholder="City..." class="bg-transparent text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-hidden w-24">
                </div>

                <!-- 5. In-Table Search Input -->
                <div class="flex-1 min-w-[220px] flex items-center space-x-1.5 bg-white border border-slate-300 rounded-xl px-3 py-1.5 shadow-xs focus-within:ring-2 focus-within:ring-rose-500 focus-within:border-rose-500 transition">
                    <i class="fa-solid fa-magnifying-glass text-slate-400 text-xs"></i>
                    <input type="text" 
                           id="escalationTableSearchInput" 
                           name="search" 
                           value="{{ request('search') }}" 
                           placeholder="Search ticket #, serial, branch, issue summary in real-time..." 
                           class="w-full bg-transparent text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-hidden">
                    <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-[10px] font-bold px-2 py-0.5 transition shrink-0">
                        Search
                    </button>
                </div>

                <!-- Clear Filters Button -->
                @if(request('bank') || request('urgency') || request('city') || request('search') || request('per_page'))
                    <a href="{{ route('tickets.escalations') }}" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs transition flex items-center space-x-1 shadow-xs">
                        <i class="fa-solid fa-xmark text-[11px]"></i>
                        <span>Reset</span>
                    </a>
                @endif
            </form>
        </div>

        @if($escalatedTickets->isEmpty())
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-3 shadow-inner">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900">Zero Active Escalations Matching Filters</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                No complaints currently match the selected bank, search query, or urgency parameters.
            </p>
            @if(request('bank') || request('search') || request('city'))
                <a href="{{ route('tickets.escalations') }}" class="inline-block mt-3 px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl">Clear All Filters</a>
            @endif
        </div>
        @else
        <div class="overflow-x-auto overflow-y-auto max-h-[720px] border border-slate-200 rounded-2xl shadow-xs bg-white">
            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[1280px]" id="escalationTable">
                <thead class="sticky top-0 z-10 bg-slate-100 text-slate-700 uppercase font-black text-[11px] border-b border-slate-200 tracking-wider shadow-xs">
                    <tr>
                        <th class="px-4 py-3.5">Ticket &amp; Bank</th>
                        <th class="px-4 py-3.5">Hardware &amp; Serial</th>
                        <th class="px-4 py-3.5">Branch Location</th>
                        <th class="px-4 py-3.5">Bank Email Landed &amp; Elapsed</th>
                        <th class="px-4 py-3.5">SLA Health Status</th>
                        <th class="px-4 py-3.5">Daily Feedback Matrix</th>
                        <th class="px-4 py-3.5">Assigned Engineer</th>
                        <th class="px-4 py-3.5 text-right">Action &amp; Directives</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="escalationTableBody">
                    @foreach($escalatedTickets as $t)
                    @php
                        $health = $t->getSlaHealth();
                        $landing = $t->getEmailLandingAudit();
                        $fbMatrix = $t->getDailyFeedbackMatrix();
                        $missingDays = collect($fbMatrix)->where('is_logged', false)->count();
                    @endphp
                    <!-- Main Ticket Row -->
                    <tr class="escalation-row hover:bg-rose-50/40 transition {{ $health['breached'] ? 'bg-rose-50/15' : '' }}" 
                        data-search="{{ strtolower($t->ticket_no . ' ' . $t->customer_ref_no . ' ' . $t->bank_name . ' ' . $t->branch_name . ' ' . $t->branch_location . ' ' . $t->machine_serial_no . ' ' . $t->machine_model . ' ' . $t->issue_summary) }}">
                        
                        <!-- 1. Ticket & Bank -->
                        <td class="px-4 py-3.5">
                            <div class="flex items-center space-x-1.5">
                                <a href="{{ route('tickets.show', $t) }}" class="font-mono font-black text-sky-700 hover:underline text-xs">
                                    #{{ $t->ticket_no }}
                                </a>
                                @if($t->urgency === 'high')
                                    <span class="bg-rose-100 text-rose-800 border border-rose-300 font-black px-1.5 py-0.5 rounded text-[9px] uppercase">High</span>
                                @endif
                            </div>
                            <div class="font-extrabold text-slate-900 mt-0.5">{{ $t->bank_name }}</div>
                            @if($t->customer_ref_no)
                                <div class="text-[10px] text-slate-400 font-mono">Ref: {{ $t->customer_ref_no }}</div>
                            @endif
                            <div class="text-[10px] text-slate-500 truncate max-w-[200px] mt-0.5" title="{{ $t->issue_summary }}">
                                {{ $t->issue_summary }}
                            </div>
                        </td>

                        <!-- 2. Hardware -->
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-800">{{ $t->machine_model ?: ($t->machine_type ?: 'ATM') }}</div>
                            <div class="text-[11px] text-slate-600 font-mono font-bold">S/N: {{ $t->machine_serial_no ?: 'N/A' }}</div>
                            <div class="text-[10px] text-slate-400">{{ $t->machine_type }}</div>
                        </td>

                        <!-- 3. Branch Location -->
                        <td class="px-4 py-3.5">
                            <div class="font-extrabold text-slate-800">{{ $t->branch_location }}</div>
                            <div class="text-[11px] text-slate-500">{{ $t->branch_name ?: 'Main Branch' }}</div>
                            @if($t->branch_address)
                                <div class="text-[10px] text-slate-400 truncate max-w-[170px]" title="{{ $t->branch_address }}">
                                    <i class="fa-solid fa-location-dot mr-1 text-rose-500"></i>{{ $t->branch_address }}
                                </div>
                            @endif
                        </td>

                        <!-- 4. Email Landed & Elapsed to NOW -->
                        <td class="px-4 py-3.5 min-w-[200px]">
                            <div class="text-[10px] text-slate-500 font-semibold">Bank Email Landing Time:</div>
                            <div class="font-mono font-bold text-slate-900 text-xs">{{ $landing['landed_at_formatted'] }}</div>
                            <div class="mt-1">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-300">
                                    <i class="fa-solid fa-clock-rotate-left text-[9px]"></i>
                                    <span>{{ $landing['elapsed_to_now'] }} elapsed to NOW</span>
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-1">
                                First Reply: 
                                @if($landing['reply_tat_minutes'])
                                    <strong class="text-emerald-700">+{{ $landing['reply_tat_minutes'] }}m TAT</strong>
                                @else
                                    <span class="text-amber-700 font-bold">Pending Dispatch</span>
                                @endif
                            </div>
                        </td>

                        <!-- 5. SLA Health Status -->
                        <td class="px-4 py-3.5 min-w-[180px]">
                            <div class="flex items-center justify-between mb-1 text-[11px]">
                                <span class="font-bold {{ $health['breached'] ? 'text-red-700 font-black' : 'text-slate-700' }}">
                                    {{ $health['label'] }}
                                </span>
                                <span class="font-mono font-semibold text-[10px] {{ $health['breached'] ? 'text-red-700' : 'text-slate-500' }}">
                                    {{ $health['percent'] }}%
                                </span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden shadow-inner">
                                <div class="h-2 rounded-full transition-all duration-500 {{ $health['bar'] }}" style="width: {{ max(5, $health['percent']) }}%"></div>
                            </div>
                            <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $health['badge'] }}">
                                    {{ strtoupper($health['status']) }}
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    Target: {{ $t->sla_deadline?->format('d M, h:i A') ?? 'N/A' }}
                                </span>
                            </div>
                        </td>

                        <!-- 6. Daily Feedback Matrix & Missing Day Alerts -->
                        <td class="px-4 py-3.5 min-w-[220px]">
                            <div class="flex items-center gap-1 flex-wrap mb-1">
                                @foreach($fbMatrix as $item)
                                    @if($item['is_logged'])
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300" title="Day {{ $item['day_number'] }}: Logged by {{ $item['engineer_name'] }}">
                                            Day {{ $item['day_number'] }} ✓
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-black bg-rose-600 text-white animate-pulse" title="Day {{ $item['day_number'] }}: MISSING FEEDBACK (Violation)">
                                            Day {{ $item['day_number'] }} : N/A
                                        </span>
                                    @endif
                                @endforeach
                            </div>

                            @if($missingDays > 0)
                                <div class="text-[10px] font-black text-rose-700 flex items-center gap-1">
                                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                                    <span>MISSING FEEDBACK ({{ $missingDays }} Days)</span>
                                </div>
                            @else
                                <div class="text-[10px] font-bold text-emerald-700 flex items-center gap-1">
                                    <i class="fa-solid fa-check"></i>
                                    <span>All Daily Logs Complete</span>
                                </div>
                            @endif

                            <button type="button" 
                                    onclick="openFeedbackModal('feedback-modal-{{ $t->id }}')" 
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-300 rounded-lg transition shadow-2xs mt-1.5 cursor-pointer">
                                <i class="fa-solid fa-comments text-sky-600"></i>
                                <span>View Daily Feedback</span>
                            </button>
                        </td>

                        <!-- 7. Assigned Engineer -->
                        <td class="px-4 py-3.5">
                            @if($t->assignedEngineer)
                                <div class="font-bold text-slate-800">{{ $t->assignedEngineer->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">
                                    <i class="fa-brands fa-whatsapp text-emerald-600 mr-1"></i>{{ $t->assignedEngineer->phone_whatsapp }}
                                </div>
                                <div class="text-[10px] text-slate-400">Base: {{ $t->assignedEngineer->base_city }}</div>
                            @else
                                <span class="bg-amber-100 text-amber-900 border border-amber-300 px-2 py-0.5 rounded font-bold text-[10px]">
                                    UNASSIGNED
                                </span>
                            @endif
                        </td>

                        <!-- 8. Actions -->
                        <td class="px-4 py-3.5 text-right space-y-1">
                            <a href="{{ route('tickets.show', $t) }}" class="inline-flex items-center space-x-1 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs px-3 py-1.5 rounded-lg shadow-sm transition">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                <span>Inspect &amp; Act</span>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($escalatedTickets->hasPages())
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            {{ $escalatedTickets->links() }}
        </div>
        @endif
        @endif
    </div>

</div>

<!-- Daily Feedback Modals for Each Escalated Ticket -->
@foreach($escalatedTickets as $t)
@php
    $health = $t->getSlaHealth();
    $landing = $t->getEmailLandingAudit();
    $fbMatrix = $t->getDailyFeedbackMatrix();
    $missingDays = collect($fbMatrix)->where('is_logged', false)->count();
@endphp
<div id="feedback-modal-{{ $t->id }}" 
     class="feedback-modal fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-mono font-bold text-sky-300">#{{ $t->ticket_no }}</span>
                    @if($t->customer_ref_no)
                        <span class="text-xs text-slate-400 font-mono">Ref: {{ $t->customer_ref_no }}</span>
                    @endif
                    @if($t->urgency === 'high')
                        <span class="bg-rose-500/30 text-rose-300 border border-rose-400 text-[10px] font-black px-1.5 py-0.5 rounded uppercase">High</span>
                    @endif
                </div>
                <h3 class="text-base font-black text-white mt-0.5">{{ $t->bank_name }} &bull; {{ $t->branch_location }}</h3>
                <div class="text-xs text-slate-400 font-mono">
                    Model: {{ $t->machine_model ?: ($t->machine_type ?: 'ATM') }} | S/N: {{ $t->machine_serial_no ?: 'N/A' }}
                </div>
            </div>
            <button type="button" 
                    onclick="closeFeedbackModal('feedback-modal-{{ $t->id }}')" 
                    class="text-slate-400 hover:text-white text-xl p-1 rounded-lg transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="p-6 overflow-y-auto space-y-5">
            <!-- Email Landing Time & Ingest TAT Summary -->
            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div>
                    <div class="text-[11px] text-slate-500 font-semibold">Bank Email Landing Time:</div>
                    <div class="font-mono font-bold text-slate-900 text-sm">{{ $landing['landed_at_formatted'] }}</div>
                    <div class="mt-1">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-rose-100 text-rose-800 border border-rose-300">
                            <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                            <span>{{ $landing['elapsed_to_now'] }} elapsed to NOW ({{ $landing['elapsed_hours_to_now'] }}h)</span>
                        </span>
                    </div>
                    @if($landing['email_subject'])
                        <div class="text-[11px] text-slate-400 mt-1 italic">&ldquo;{{ $landing['email_subject'] }}&rdquo;</div>
                    @endif
                </div>
                <div class="text-right sm:border-l sm:border-slate-200 sm:pl-4">
                    <div class="text-[11px] text-slate-500">First Reply TAT:</div>
                    @if($landing['reply_tat_minutes'])
                        <div class="font-mono font-bold text-emerald-700 text-sm">+{{ $landing['reply_tat_minutes'] }}m TAT</div>
                    @else
                        <div class="font-bold text-amber-700 text-xs">Pending Dispatch</div>
                    @endif
                    <div class="mt-1">
                        @if($missingDays > 0)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-600 text-white">
                                ⚠️ {{ $missingDays }} Missing Feedback Days
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                ✓ All Daily Logs Up-to-Date
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Daily Feedback Day-by-Day Matrix -->
            <div>
                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider mb-3 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-check text-sky-600"></i>
                        <span>Daily Operational Progress Feedback ({{ count($fbMatrix) }} Days Evaluated)</span>
                    </span>
                    <span class="text-[11px] text-slate-400 font-normal">Missed days are highlighted as N/A violation</span>
                </h4>

                <div class="space-y-3">
                    @foreach($fbMatrix as $item)
                        @if($item['is_logged'])
                            <div class="p-3.5 bg-emerald-50/50 rounded-xl border border-emerald-200 text-xs space-y-1.5">
                                <div class="flex items-center justify-between border-b border-emerald-200/60 pb-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-emerald-600 text-white">
                                        Day {{ $item['day_number'] }} ✓ Logged
                                    </span>
                                    <span class="text-slate-500 font-mono text-[11px]">
                                        {{ $item['submitted_at']->format('d M Y, h:i A') }} (by {{ $item['engineer_name'] }})
                                    </span>
                                </div>
                                <div class="font-bold text-slate-900 mt-1">{{ $item['feedback_text'] }}</div>
                                @if($item['action_taken'])
                                    <div class="text-[11px] text-slate-600">
                                        <strong class="text-slate-700">Action:</strong> {{ $item['action_taken'] }}
                                    </div>
                                @endif
                                @if($item['parts_required'] && $item['parts_required'] !== 'None')
                                    <div class="text-[11px] text-amber-800 font-bold bg-amber-50 px-2 py-1 rounded border border-amber-200 inline-block mt-0.5">
                                        <i class="fa-solid fa-wrench mr-1"></i>Parts: {{ $item['parts_required'] }}
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="p-3.5 bg-rose-50 rounded-xl border-2 border-rose-300 text-xs space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between border-b border-rose-200 pb-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-rose-600 text-white">
                                        Day {{ $item['day_number'] }} : N/A
                                    </span>
                                    <span class="text-rose-700 font-extrabold uppercase text-[10px]">
                                        MISSING FEEDBACK VIOLATION
                                    </span>
                                </div>
                                <div class="text-rose-900 font-bold mt-1">
                                    Day {{ $item['day_number'] }}: N/A
                                </div>
                                <p class="text-[11px] text-rose-700">
                                    No engineer progress logged for this day. Violation of daily reporting protocol.
                                </p>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-3 bg-slate-100 border-t border-slate-200 flex items-center justify-between">
            <a href="{{ route('tickets.show', $t) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                <span>Inspect Ticket &amp; Directive</span>
            </a>
            <button type="button" 
                    onclick="closeFeedbackModal('feedback-modal-{{ $t->id }}')" 
                    class="px-4 py-1.5 bg-white hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs font-bold rounded-xl transition cursor-pointer">
                Close
            </button>
        </div>
    </div>
</div>
@endforeach

<!-- Real-Time Client-Side In-Table Filter & Modal JS -->
<script>
function openFeedbackModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeFeedbackModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

// Close modal on backdrop click or Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.feedback-modal').forEach(function(modal) {
            modal.classList.add('hidden');
        });
        document.body.classList.remove('overflow-hidden');
    }
});

document.addEventListener('click', function(event) {
    if (event.target.classList && event.target.classList.contains('feedback-modal')) {
        event.target.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
});

// In-table real-time client-side filter
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('escalationTableSearchInput');
    const tableBody = document.getElementById('escalationTableBody');
    if (!searchInput || !tableBody) return;

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const rows = tableBody.querySelectorAll('tr.escalation-row');

        rows.forEach(function (row) {
            const searchData = row.getAttribute('data-search') || '';
            const matches = !query || searchData.includes(query);

            if (matches) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>
@endsection
