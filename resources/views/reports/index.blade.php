@extends('layouts.app')

@section('title', 'Executive Reports & SLA Analytics - Bank Complaint Manager ERP')

@section('header_title', 'Executive Audit & SLA Analytics')

@section('content')
<div class="space-y-6">

    <!-- Top Header Banner & Filter Ribbon -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2.5">
                <span class="p-2.5 bg-sky-100 text-sky-700 rounded-xl text-base shadow-inner">
                    <i class="fa-solid fa-chart-pie"></i>
                </span>
                <div>
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        {{ auth()->user()->isOfficeStaff() ? 'Machine Faults & Parts Report' : 'Executive Reports & SLA Compliance Dashboard' }}
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-full font-bold uppercase bg-sky-100 text-sky-800 border border-sky-200">
                            {{ auth()->user()->isOfficeStaff() ? 'Office Staff' : 'Enterprise Audit' }}
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ auth()->user()->isOfficeStaff() ? 'Machine fault diagnostics, parts requested per serial number, and downloadable video proofs for supplier warranty claims.' : 'Bank ticket lifecycle audit trails, machine fault diagnostics with video proof, and field engineer parts consumption & TAT analytics.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- CSV & PDF Export & Print Actions -->
        <div class="flex items-center space-x-2 flex-shrink-0">
            @if($activeTab === 'bank_tickets')
            <a href="{{ route('reports.export-pdf', array_merge(request()->query(), ['type' => 'bank_tickets'])) }}" 
               class="inline-flex items-center px-4 py-2.5 bg-rose-700 hover:bg-rose-600 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-file-pdf mr-1.5 text-rose-200 text-sm"></i>
                <span>Download Bank Audit PDF</span>
            </a>
            @elseif($activeTab === 'machine_faults')
            <a href="{{ route('reports.export-pdf', array_merge(request()->query(), ['type' => 'machine_faults'])) }}" 
               class="inline-flex items-center px-4 py-2.5 bg-rose-700 hover:bg-rose-600 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-file-pdf mr-1.5 text-rose-200 text-sm"></i>
                <span>Download Supplier Warranty Claim PDF</span>
            </a>
            @endif

            <a href="{{ route('reports.export-csv', array_merge(request()->query(), ['type' => $activeTab])) }}" 
               class="inline-flex items-center px-4 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-sm transition">
                <i class="fa-solid fa-file-csv mr-1.5 text-emerald-200 text-sm"></i>
                <span>Download {{ $activeTab === 'overview' ? 'Executive' : ($activeTab === 'bank_tickets' ? 'Bank Audit' : ($activeTab === 'machine_faults' ? 'Supplier Parts' : ucfirst(str_replace('_', ' ', $activeTab)))) }} CSV</span>
            </a>
            <button type="button" onclick="window.print()" class="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition cursor-pointer" title="Print Current Report View">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>
    </div>

    <!-- MAIN MODULE TABS NAVIGATION -->
    <div class="flex items-center space-x-1.5 border-b border-slate-200 bg-white px-4 pt-3 rounded-t-2xl shadow-xs overflow-x-auto">
        @if(!auth()->user()->isOfficeStaff())
        <!-- Tab 1: Executive Overview -->
        <a href="{{ route('reports.index', array_merge(request()->query(), ['tab' => 'overview'])) }}" 
           class="flex items-center space-x-2 px-4 py-2.5 border-b-2 font-bold text-xs whitespace-nowrap transition {{ $activeTab === 'overview' ? 'border-sky-600 text-sky-700 bg-sky-50/50 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Executive Overview</span>
        </a>

        <!-- Tab 2: Bank-wise Ticket Detail & Audit Trail -->
        <a href="{{ route('reports.index', array_merge(request()->query(), ['tab' => 'bank_tickets'])) }}" 
           class="flex items-center space-x-2 px-4 py-2.5 border-b-2 font-bold text-xs whitespace-nowrap transition {{ $activeTab === 'bank_tickets' ? 'border-sky-600 text-sky-700 bg-sky-50/50 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <i class="fa-solid fa-building-columns"></i>
            <span>Bank Tickets Audit Trail</span>
        </a>
        @endif

        <!-- Tab 3: Machine Faults & Video Evidence -->
        <a href="{{ route('reports.index', array_merge(request()->query(), ['tab' => 'machine_faults'])) }}" 
           class="flex items-center space-x-2 px-4 py-2.5 border-b-2 font-bold text-xs whitespace-nowrap transition {{ $activeTab === 'machine_faults' ? 'border-sky-600 text-sky-700 bg-sky-50/50 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <i class="fa-solid fa-video"></i>
            <span>Machine Faults &amp; Parts (Video)</span>
        </a>

        @if(!auth()->user()->isOfficeStaff())
        <!-- Tab 4: Engineer Parts & TAT Performance -->
        <a href="{{ route('reports.index', array_merge(request()->query(), ['tab' => 'engineer_parts'])) }}" 
           class="flex items-center space-x-2 px-4 py-2.5 border-b-2 font-bold text-xs whitespace-nowrap transition {{ $activeTab === 'engineer_parts' ? 'border-sky-600 text-sky-700 bg-sky-50/50 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <i class="fa-solid fa-user-gear"></i>
            <span>Engineer Parts &amp; In-TAT / Out-TAT</span>
        </a>
        @endif
    </div>

    <!-- Date Range & Preset Filters Bar -->
    <div class="bg-white p-4 rounded-b-2xl rounded-t-none -mt-6 shadow-sm border border-slate-200">
        <form action="{{ route('reports.index') }}" method="GET" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-xs">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            @if(request('bank_filter'))
                <input type="hidden" name="bank_filter" value="{{ request('bank_filter') }}">
            @endif
            @if(request('engineer_filter'))
                <input type="hidden" name="engineer_filter" value="{{ request('engineer_filter') }}">
            @endif

            <!-- Quick Preset Buttons -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-slate-500 font-bold mr-1 flex items-center">
                    <i class="fa-solid fa-clock-rotate-left mr-1 text-slate-400"></i> Timeframe:
                </span>
                <a href="{{ route('reports.index', array_merge(request()->query(), ['preset' => '7_days', 'from_date' => '', 'to_date' => ''])) }}" 
                   class="px-3 py-1.5 rounded-lg font-bold transition {{ ($preset === '7_days' && !request('from_date')) ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    Last 7 Days
                </a>
                <a href="{{ route('reports.index', array_merge(request()->query(), ['preset' => '30_days', 'from_date' => '', 'to_date' => ''])) }}" 
                   class="px-3 py-1.5 rounded-lg font-bold transition {{ ($preset === '30_days' && !request('from_date')) ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    Last 30 Days
                </a>
                <a href="{{ route('reports.index', array_merge(request()->query(), ['preset' => 'this_month', 'from_date' => '', 'to_date' => ''])) }}" 
                   class="px-3 py-1.5 rounded-lg font-bold transition {{ ($preset === 'this_month' && !request('from_date')) ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    This Month
                </a>
                <a href="{{ route('reports.index', array_merge(request()->query(), ['preset' => 'all', 'from_date' => '', 'to_date' => ''])) }}" 
                   class="px-3 py-1.5 rounded-lg font-bold transition {{ ($preset === 'all' && !request('from_date')) ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    All Time
                </a>
            </div>

            <!-- Custom Date Inputs -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center space-x-1">
                    <label class="text-slate-600 font-medium">From:</label>
                    <input type="date" name="from_date" value="{{ $fromDate }}" class="bg-white border border-slate-300 rounded-lg py-1 px-2 text-xs focus:ring-2 focus:ring-sky-500">
                </div>
                <div class="flex items-center space-x-1">
                    <label class="text-slate-600 font-medium">To:</label>
                    <input type="date" name="to_date" value="{{ $toDate }}" class="bg-white border border-slate-300 rounded-lg py-1 px-2 text-xs focus:ring-2 focus:ring-sky-500">
                </div>
                <button type="submit" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg font-bold transition cursor-pointer">
                    <i class="fa-solid fa-filter mr-1"></i> Apply
                </button>
                @if(request('from_date') || request('to_date') || (request('preset') && request('preset') !== '30_days'))
                    <a href="{{ route('reports.index', ['tab' => $activeTab]) }}" class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg font-semibold transition" title="Reset to Default">
                        Reset
                    </a>
                @endif
            </div>

        </form>
    </div>


    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1: EXECUTIVE OVERVIEW                                             --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'overview')
    <div class="space-y-6">

        <!-- Executive Summary KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-slate-500 uppercase font-semibold text-[10px] flex items-center justify-between">
                    <span>Total Complaints</span>
                    <i class="fa-solid fa-ticket text-slate-400"></i>
                </span>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ $kpis['total_tickets'] }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5">
                    <span class="font-bold text-emerald-600">{{ $kpis['resolved_tickets'] }}</span> resolved &bull;
                    <span class="font-bold text-amber-600">{{ $kpis['open_tickets'] }}</span> active
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border {{ $kpis['sla_compliance_rate'] >= 80 ? 'border-emerald-200 bg-emerald-50/20' : 'border-rose-200 bg-rose-50/20' }} shadow-sm">
                <span class="uppercase font-semibold text-[10px] flex items-center justify-between {{ $kpis['sla_compliance_rate'] >= 80 ? 'text-emerald-700' : 'text-rose-700' }}">
                    <span>SLA Compliance</span>
                    <i class="fa-solid fa-shield-halved"></i>
                </span>
                <div class="text-2xl font-black mt-1 {{ $kpis['sla_compliance_rate'] >= 80 ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $kpis['sla_compliance_rate'] }}%
                </div>
                <div class="text-[10px] text-slate-500 mt-0.5">
                    <span class="font-bold text-emerald-600">{{ $kpis['sla_compliant_count'] }} OK</span> &bull; 
                    <span class="font-bold text-rose-600">{{ $kpis['sla_breached_count'] }} Breached</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-slate-500 uppercase font-semibold text-[10px] flex items-center justify-between">
                    <span>Mean MTTR</span>
                    <i class="fa-solid fa-stopwatch text-slate-400"></i>
                </span>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ $kpis['mttr_hours'] }}h</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-medium">Avg resolution turnaround</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-slate-500 uppercase font-semibold text-[10px] flex items-center justify-between">
                    <span>Resolution Rate</span>
                    <i class="fa-solid fa-circle-check text-slate-400"></i>
                </span>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ $kpis['resolution_rate'] }}%</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-medium">{{ $kpis['resolved_tickets'] }} of {{ $kpis['total_tickets'] }} completed</div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-slate-500 uppercase font-semibold text-[10px] flex items-center justify-between">
                    <span>Expenses Settled</span>
                    <i class="fa-solid fa-receipt text-slate-400"></i>
                </span>
                <div class="text-lg font-black text-emerald-700 mt-1.5 truncate">
                    PKR {{ number_format($kpis['total_paid']) }}
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">
                    Claimed: PKR {{ number_format($kpis['total_claimed']) }}
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-slate-500 uppercase font-semibold text-[10px] flex items-center justify-between">
                    <span>Field Travel (AI)</span>
                    <i class="fa-solid fa-route text-slate-400"></i>
                </span>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ number_format($kpis['total_distance_km']) }} km</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-medium">Benchmark: PKR {{ number_format($kpis['total_benchmark']) }}</div>
            </div>
        </div>

        <!-- Bank Portfolio Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="p-1.5 bg-blue-100 text-blue-700 rounded-lg text-xs"><i class="fa-solid fa-building-columns"></i></span>
                        Bank Client Portfolio &amp; Contract SLA Compliance
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Breakdown of complaint volumes, resolution percentages, SLA compliance rate, and average turnaround time per banking institution.
                    </p>
                </div>
                <a href="{{ route('reports.index', ['tab' => 'bank_tickets']) }}" class="text-xs font-bold text-sky-700 hover:text-sky-900 flex items-center gap-1">
                    <span>View Full Audit Trail</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                            <th class="py-3 px-4">Bank Name</th>
                            <th class="py-3 px-4 text-center">Total Complaints</th>
                            <th class="py-3 px-4 text-center">Resolved</th>
                            <th class="py-3 px-4 text-center">Active / Pending</th>
                            <th class="py-3 px-4 text-center">SLA Breaches</th>
                            <th class="py-3 px-4">SLA Compliance Progress</th>
                            <th class="py-3 px-4 text-right">Avg MTTR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($bankMetrics as $bm)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    <a href="{{ route('reports.index', ['tab' => 'bank_tickets', 'bank_filter' => $bm['bank_name']]) }}" class="hover:text-sky-600 hover:underline flex items-center space-x-2">
                                        <span class="w-2 h-2 rounded-full {{ $bm['compliance_rate'] >= 80 ? 'bg-emerald-500' : ($bm['compliance_rate'] >= 60 ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
                                        <span>{{ $bm['bank_name'] }}</span>
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-800">{{ $bm['total'] }}</td>
                                <td class="py-3 px-4 text-center text-emerald-700 font-bold">{{ $bm['resolved'] }}</td>
                                <td class="py-3 px-4 text-center text-amber-700 font-bold">{{ $bm['open'] }}</td>
                                <td class="py-3 px-4 text-center">
                                    @if($bm['breached'] > 0)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                            {{ $bm['breached'] }} Breached
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            0 (100% OK)
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="w-full flex items-center space-x-2">
                                        <div class="flex-1 bg-slate-200 rounded-full h-2 overflow-hidden">
                                            <div class="h-2 rounded-full {{ $bm['compliance_rate'] >= 80 ? 'bg-emerald-500' : ($bm['compliance_rate'] >= 60 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ min(100, max(0, $bm['compliance_rate'])) }}%"></div>
                                        </div>
                                        <span class="text-[11px] font-bold {{ $bm['compliance_rate'] >= 80 ? 'text-emerald-700' : ($bm['compliance_rate'] >= 60 ? 'text-amber-700' : 'text-rose-700') }}">
                                            {{ $bm['compliance_rate'] }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-slate-800">
                                    {{ $bm['avg_resolution_hours'] }} hrs
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-400">
                                    No bank complaint data found for this timeframe.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Field Engineer Performance Scorecard Summary -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg text-xs"><i class="fa-solid fa-user-gear"></i></span>
                        Field Engineer Performance &amp; Expense Scorecards
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Field resolution efficiency, ticket completion rate, SLA adherence, travel distance, and claimed vs reimbursed funds.
                    </p>
                </div>
                <a href="{{ route('reports.index', ['tab' => 'engineer_parts']) }}" class="text-xs font-bold text-sky-700 hover:text-sky-900 flex items-center gap-1">
                    <span>View In-TAT &amp; Parts Requisitions</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                            <th class="py-3 px-4">Engineer</th>
                            <th class="py-3 px-4">Base City</th>
                            <th class="py-3 px-4 text-center">Assigned</th>
                            <th class="py-3 px-4 text-center">Resolved</th>
                            <th class="py-3 px-4 text-center">SLA Compliance</th>
                            <th class="py-3 px-4 text-center">Travel (km)</th>
                            <th class="py-3 px-4 text-right">Expenses Claimed</th>
                            <th class="py-3 px-4 text-right">Disbursed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($engineerTatMetrics as $eng)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs uppercase">
                                            {{ substr($eng['name'], 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900">{{ $eng['name'] }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ $eng['phone'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-600 font-semibold">{{ $eng['base_city'] }}</td>
                                <td class="py-3 px-4 text-center font-bold text-slate-800">{{ $eng['total_assigned'] }}</td>
                                <td class="py-3 px-4 text-center text-emerald-700 font-bold">{{ $eng['resolved'] }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $eng['tat_compliance_rate'] >= 80 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $eng['tat_compliance_rate'] }}%
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-slate-700">
                                    {{ number_format($eng['distance_km']) }} km
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">
                                    PKR {{ number_format($eng['claimed_amount'], 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-emerald-700">
                                    PKR {{ number_format($eng['paid_amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center text-slate-400">
                                    No engineer activity recorded for this timeframe.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Chronic Machines Watchlist -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-200 bg-amber-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="p-1.5 bg-amber-100 text-amber-800 rounded-lg text-xs"><i class="fa-solid fa-triangle-exclamation"></i></span>
                        Chronic Machine Watchlist (Repeat Breakdowns)
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Terminal units logging recurrent technical failures. Operational alert for root-cause overhaul or workshop pull.
                    </p>
                </div>
                <div class="flex items-center space-x-2 text-xs">
                    <span class="text-slate-500">Threshold:</span>
                    <span class="font-bold bg-white px-2.5 py-1 rounded-lg border border-slate-200 text-slate-800">&ge; {{ $chronicThreshold }} Breakdowns</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                            <th class="py-3 px-4">Serial Number</th>
                            <th class="py-3 px-4">Machine Type</th>
                            <th class="py-3 px-4">Bank &amp; Branch Location</th>
                            <th class="py-3 px-4 text-center">Failure Count</th>
                            <th class="py-3 px-4">Last Logged</th>
                            <th class="py-3 px-4 text-right">Recommended Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($chronicMachines as $cm)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                    <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-200">{{ $cm['serial_no'] }}</span>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-800">{{ $cm['machine_type'] }}</td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ $cm['bank_name'] }}</div>
                                    <div class="text-[10px] text-slate-500">{{ $cm['location'] }}</div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-black {{ $cm['severity'] === 'critical' ? 'bg-rose-100 text-rose-800 animate-pulse' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $cm['complaint_count'] }} Tickets
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500">{{ $cm['last_complaint_at'] }}</td>
                                <td class="py-3 px-4 text-right">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold {{ $cm['severity'] === 'critical' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                                        <i class="fa-solid fa-wrench mr-1"></i> {{ $cm['action_needed'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-slate-400">
                                    <i class="fa-solid fa-circle-check text-2xl text-emerald-500 mb-1.5 block"></i>
                                    Excellent! No chronic machines exceeding {{ $chronicThreshold }} complaints recorded in this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    @endif


    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2: BANK-WISE TICKET DETAIL & COMPLETE AUDIT TRAIL                 --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'bank_tickets')
    <div class="space-y-4">

        <!-- Bank Filter Bar -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center space-x-2">
                <span class="p-2 bg-blue-100 text-blue-700 rounded-xl"><i class="fa-solid fa-building-columns"></i></span>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Bank Client Lifecycle Audit Trail</h3>
                    <p class="text-[11px] text-slate-500">Track email in/out, day-by-day logs, approval pauses, and workshop round-trip transit.</p>
                </div>
            </div>

            <form action="{{ route('reports.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="bank_tickets">
                @if(request('preset')) <input type="hidden" name="preset" value="{{ request('preset') }}"> @endif
                @if(request('from_date')) <input type="hidden" name="from_date" value="{{ request('from_date') }}"> @endif
                @if(request('to_date')) <input type="hidden" name="to_date" value="{{ request('to_date') }}"> @endif

                <!-- Live In-Table Search Input -->
                <div class="flex items-center space-x-1.5 bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-2xs focus-within:ring-2 focus-within:ring-sky-500 focus-within:border-sky-500 transition min-w-[240px]">
                    <i class="fa-solid fa-magnifying-glass text-slate-400 text-xs"></i>
                    <input type="text" 
                           id="bankAuditLiveSearch" 
                           name="bank_search" 
                           value="{{ request('bank_search') }}" 
                           placeholder="Live search ticket #, serial, model, branch, engineer..." 
                           class="w-full bg-transparent text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-hidden">
                    @if(request('bank_search'))
                        <a href="{{ route('reports.index', array_merge(request()->except('bank_search'), ['tab' => 'bank_tickets'])) }}" class="text-slate-400 hover:text-slate-600 text-xs shrink-0" title="Clear search">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </div>

                <div class="flex items-center space-x-1.5 bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-2xs">
                    <label class="font-bold text-slate-600 whitespace-nowrap text-xs">Bank:</label>
                    <select name="bank_filter" class="bg-transparent border-0 py-0 pl-1 pr-6 text-xs font-bold text-slate-800 focus:outline-hidden cursor-pointer" onchange="this.form.submit()">
                        <option value="">All Banks</option>
                        @foreach($allBankNames as $bName)
                            <option value="{{ $bName }}" {{ request('bank_filter') == $bName ? 'selected' : '' }}>
                                {{ $bName }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(request('bank_filter') || request('bank_search'))
                    <a href="{{ route('reports.index', ['tab' => 'bank_tickets']) }}" class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs transition flex items-center space-x-1">
                        <i class="fa-solid fa-xmark text-[11px]"></i>
                        <span>Reset</span>
                    </a>
                @endif
            </form>
        </div>

        <!-- Bank Audit Aggregate Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[10px] uppercase font-bold text-slate-400">Audited Complaints</div>
                <div class="text-xl font-black text-slate-900 mt-0.5">{{ $bankAuditSummary['total'] }}</div>
                <div class="text-[10px] text-slate-500 font-medium">In selected timeframe</div>
            </div>
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[10px] uppercase font-bold text-amber-600 flex items-center justify-between">
                    <span>Reopened / Multi-Tour</span>
                    <i class="fa-solid fa-arrows-rotate text-amber-500"></i>
                </div>
                <div class="text-xl font-black text-amber-700 mt-0.5">{{ $bankAuditSummary['reopened_count'] }}</div>
                <div class="text-[10px] text-slate-500 font-medium">Bank recurrent calls</div>
            </div>
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[10px] uppercase font-bold text-slate-400">Avg Gross Calendar TAT</div>
                <div class="text-xl font-black text-slate-800 mt-0.5">{{ $bankAuditSummary['avg_gross_hours'] }}h</div>
                <div class="text-[10px] text-slate-500 font-medium">Receipt to completion</div>
            </div>
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[10px] uppercase font-bold text-amber-600">Avg Excluded Non-Working</div>
                <div class="text-xl font-black text-amber-700 mt-0.5">-{{ $bankAuditSummary['avg_deducted_hours'] }}h</div>
                <div class="text-[10px] text-slate-500 font-medium">Weekends &amp; Transit</div>
            </div>
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[10px] uppercase font-bold text-sky-600">Avg Net Business TAT</div>
                <div class="text-xl font-black text-sky-800 mt-0.5">{{ $bankAuditSummary['avg_net_hours'] }}h</div>
                <div class="text-[10px] text-slate-500 font-medium">Chargeable operating time</div>
            </div>
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="text-[10px] uppercase font-bold text-emerald-600">Net SLA Adherence</div>
                <div class="text-xl font-black text-emerald-800 mt-0.5">{{ $bankAuditSummary['compliance_rate'] }}%</div>
                <div class="text-[10px] text-emerald-700 font-medium">{{ $bankAuditSummary['in_tat_count'] }} In-TAT / {{ $bankAuditSummary['out_tat_count'] }} Out</div>
            </div>
        </div>

        <!-- Tickets Audit Trail Cards -->
        <div class="space-y-4">
            @forelse($bankAuditTickets as $t)
                @php
                    $auditMetrics = $t->calculateAuditTrailMetrics();
                @endphp
                <div class="bank-audit-card bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:border-slate-300 transition"
                     data-search="{{ strtolower($t->ticket_no . ' ' . $t->customer_ref_no . ' ' . $t->bank_name . ' ' . $t->branch_name . ' ' . $t->branch_location . ' ' . $t->machine_serial_no . ' ' . $t->machine_model . ' ' . $t->issue_summary . ' ' . ($t->assignedEngineer?->name ?? '')) }}">
                    
                    <!-- Card Top Header -->
                    <div class="p-4 sm:p-5 border-b border-slate-100 bg-slate-50/75 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                        <div class="flex items-start sm:items-center space-x-3">
                            <span class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-sm shadow-xs flex-shrink-0">
                                <i class="fa-solid fa-receipt"></i>
                            </span>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="{{ route('tickets.show', $t) }}" class="font-black text-sky-700 hover:underline text-sm font-mono">
                                        #{{ $t->ticket_no }}
                                    </a>
                                    @if($t->customer_ref_no)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-200 text-slate-800">
                                            Ref: {{ $t->customer_ref_no }}
                                        </span>
                                    @endif
                                    @if($t->reopen_count > 0 || ($t->current_cycle_no && $t->current_cycle_no > 1))
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-900 border border-amber-300 flex items-center gap-1 shadow-2xs">
                                            <i class="fa-solid fa-arrows-rotate text-amber-600"></i>
                                            <span>Tour {{ $t->current_cycle_no ?? ($t->reopen_count + 1) }} &bull; Reopened {{ $t->reopen_count }}x</span>
                                        </span>
                                    @endif
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase
                                        {{ $t->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        {{ $t->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $t->status === 'escalated' ? 'bg-rose-100 text-rose-800' : '' }}
                                        {{ in_array($t->status, ['awaiting_workshop', 'in_workshop_repair', 'workshop_repaired', 'return_transit']) ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $t->status === 'awaiting_approval' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ $t->status === 'open' ? 'bg-slate-200 text-slate-800' : '' }}">
                                        {{ str_replace('_', ' ', $t->status) }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                        {{ $t->urgency === 'critical' ? 'bg-rose-100 text-rose-800' : ($t->urgency === 'high' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $t->urgency ?? 'Normal' }} Urgency
                                    </span>
                                </div>
                                <h3 class="font-extrabold text-slate-900 mt-1 text-sm">
                                    {{ $t->bank_name }} &bull; {{ $t->branch_name ?? 'Branch' }} ({{ $t->branch_location }})
                                </h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    <span>Terminal: <strong class="text-slate-800">{{ $t->machine_type ?? 'Device' }}</strong> (S/N: <span class="font-mono">{{ $t->machine_serial_no ?? 'N/A' }}</span>)</span>
                                    <span class="mx-1.5 text-slate-300">&bull;</span>
                                    <span>Assigned: <strong class="text-slate-800">{{ $t->assignedEngineer?->name ?? 'Unassigned' }}</strong> <span class="text-[9px] px-1 py-0.2 rounded bg-sky-100 text-sky-800 font-bold ml-0.5 uppercase">Lead</span></span>
                                    @php
                                        $supportEngs = $t->activeTicketEngineers->filter(fn($e) => !$e->isLead() && $e->engineer);
                                    @endphp
                                    @if($supportEngs->count() > 0)
                                        <span class="text-slate-500 text-[10px] ml-1">
                                            + {{ $supportEngs->count() }} Support ({{ $supportEngs->pluck('engineer.name')->implode(', ') }})
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 self-start md:self-auto flex-shrink-0">
                            <a href="{{ route('tickets.show', $t) }}" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                                <span>Inspect Ticket</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Turnaround Time & Net Business SLA Calculation Banner -->
                    <div class="px-4 sm:px-5 py-3.5 bg-slate-900 text-white flex flex-col xl:flex-row xl:items-center justify-between gap-3 text-xs border-b border-slate-800">
                        <div class="flex items-start sm:items-center space-x-3">
                            <span class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 flex items-center justify-center font-bold text-sm flex-shrink-0">
                                <i class="fa-solid fa-stopwatch"></i>
                            </span>
                            <div>
                                <div class="text-[11px] uppercase tracking-wider text-slate-400 font-bold flex items-center gap-1.5">
                                    <span>Total Lifecycle Turnaround Time (TAT) &amp; Working Hours Audit</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                    <span class="text-sm font-black text-white">
                                        Gross: {{ $auditMetrics['gross_formatted'] }} ({{ $auditMetrics['gross_hours'] }}h)
                                    </span>
                                    <span class="text-slate-400 text-[11px]">
                                        ({{ $auditMetrics['start_at']->format('d M H:i') }} &rarr; {{ $auditMetrics['end_at']->format('d M H:i') }})
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- SLA Deduction Breakdown & Net Calculation -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Weekend Exclusion Badge -->
                            <div class="px-2.5 py-1 bg-slate-800/90 rounded-lg border border-slate-700 text-[11px]">
                                <span class="text-slate-400">Weekends (Sat &amp; Sun):</span>
                                <strong class="text-amber-300 font-mono ml-1">-{{ $auditMetrics['weekend_formatted'] }}</strong>
                            </div>

                            <!-- Workshop Transit Exclusion Badge -->
                            @if($auditMetrics['transit_minutes'] > 0)
                                <div class="px-2.5 py-1 bg-slate-800/90 rounded-lg border border-slate-700 text-[11px]">
                                    <span class="text-slate-400">Transit:</span>
                                    <strong class="text-purple-300 font-mono ml-1">-{{ $auditMetrics['transit_formatted'] }}</strong>
                                </div>
                            @endif

                            <!-- Approval Pauses Badge -->
                            @if($auditMetrics['approval_minutes'] > 0)
                                <div class="px-2.5 py-1 bg-slate-800/90 rounded-lg border border-slate-700 text-[11px]">
                                    <span class="text-slate-400">Approval Wait:</span>
                                    <strong class="text-amber-300 font-mono ml-1">-{{ $auditMetrics['approval_formatted'] }}</strong>
                                </div>
                            @endif

                            <!-- Net SLA Working Resolution Time -->
                            <div class="px-3 py-1 bg-sky-950/90 rounded-lg border border-sky-600/50 text-[11px] flex items-center gap-1.5">
                                <span class="text-sky-300 font-bold">Net Business Time:</span>
                                <strong class="text-white font-mono text-xs font-black">{{ $auditMetrics['net_formatted'] }}</strong>
                            </div>

                            <!-- In-TAT / Out-of-TAT Badge -->
                            <div class="px-2.5 py-1 rounded-lg text-[11px] font-bold flex items-center gap-1 border
                                {{ $auditMetrics['is_in_tat'] ? 'bg-emerald-950/80 text-emerald-300 border-emerald-600/60' : 'bg-rose-950/80 text-rose-300 border-rose-600/60' }}">
                                <i class="fa-solid {{ $auditMetrics['is_in_tat'] ? 'fa-circle-check text-emerald-400' : 'fa-triangle-exclamation text-rose-400' }}"></i>
                                <span>{{ $auditMetrics['is_in_tat'] ? 'In-TAT Compliant' : 'Out-of-TAT Breached' }}</span>
                                <span class="text-[10px] font-mono opacity-80">({{ $auditMetrics['net_hours'] }}h Net vs {{ $auditMetrics['target_sla_hours'] }}h Target)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Milestones & Timing Audit Grid -->
                    <div class="p-4 sm:p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 bg-white text-xs border-b border-slate-100">
                        
                        <!-- 1. Email Received & Reply Dispatched -->
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center justify-between">
                                <span>1. Email Ingest &amp; Reply</span>
                                <i class="fa-solid fa-envelope text-sky-600"></i>
                            </span>
                            <div>
                                <div class="text-[11px] font-semibold text-slate-700">Received at:</div>
                                <div class="font-bold text-slate-900">{{ $t->created_at->format('d M Y H:i') }}</div>
                                @if($t->email_subject)
                                    <div class="text-[10px] text-slate-400 truncate mt-0.5" title="{{ $t->email_subject }}">&ldquo;{{ $t->email_subject }}&rdquo;</div>
                                @endif
                            </div>
                            <div class="pt-1.5 border-t border-slate-200/80">
                                <div class="text-[11px] font-semibold text-slate-700">Reply Dispatched:</div>
                                @if($t->email_assignment_sent_at)
                                    <div class="font-bold text-emerald-700 flex items-center justify-between">
                                        <span>{{ $t->email_assignment_sent_at->format('d M Y H:i') }}</span>
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-mono font-bold">
                                            +{{ $t->created_at->diffInMinutes($t->email_assignment_sent_at) }}m TAT
                                        </span>
                                    </div>
                                @elseif($t->whatsapp_notified_at)
                                    <div class="font-bold text-emerald-700">WhatsApp Alert @ {{ $t->whatsapp_notified_at->format('H:i') }}</div>
                                @else
                                    <div class="text-slate-400 italic">No formal dispatch recorded</div>
                                @endif
                            </div>
                        </div>

                        <!-- 2. Approval Wait Time -->
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center justify-between">
                                <span>2. Approval Waiting Time</span>
                                <i class="fa-solid fa-hourglass-half text-amber-600"></i>
                            </span>
                            @if($t->approval_requested_at || $t->approval_paused_seconds > 0)
                                @php
                                    $pausedMins = $t->approval_paused_seconds 
                                        ? round($t->approval_paused_seconds / 60) 
                                        : (($t->approval_requested_at && $t->approval_arrived_at) ? $t->approval_requested_at->diffInMinutes($t->approval_arrived_at) : 0);
                                @endphp
                                <div>
                                    <div class="text-base font-black text-amber-700">{{ $pausedMins }} mins ({{ round($pausedMins/60, 1) }}h)</div>
                                    <div class="text-[10px] text-slate-500 font-medium">SLA paused for management approval</div>
                                </div>
                                <div class="pt-1 border-t border-slate-200/80 text-[10px] text-slate-600 space-y-0.5">
                                    <div>Source: <strong>{{ $t->approval_source ?? 'Management' }}</strong></div>
                                    @if($t->approval_request_reason)
                                        <div class="text-slate-400 truncate" title="{{ $t->approval_request_reason }}">{{ $t->approval_request_reason }}</div>
                                    @endif
                                </div>
                            @else
                                <div class="text-slate-400 italic mt-2">No approval pauses incurred. Straight-through SLA.</div>
                            @endif
                        </div>

                        <!-- 3. Central Workshop Transit Round-Trip -->
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center justify-between">
                                <span>3. Workshop Round-Trip</span>
                                <i class="fa-solid fa-truck-fast text-purple-600"></i>
                            </span>
                            @if($t->workshop_dispatched_at || $t->workshop_location)
                                @php
                                    $transitInHours = ($t->workshop_dispatched_at && $t->workshop_received_at)
                                        ? round($t->workshop_dispatched_at->diffInMinutes($t->workshop_received_at) / 60, 1)
                                        : 'In Transit';
                                    $transitOutHours = ($t->return_dispatched_at && $t->bank_received_at)
                                        ? round($t->return_dispatched_at->diffInMinutes($t->bank_received_at) / 60, 1)
                                        : ($t->return_dispatched_at ? 'In Return Transit' : 'Pending Return');
                                @endphp
                                <div class="space-y-1 text-[11px]">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Transit In:</span>
                                        <strong class="text-purple-900 font-mono">{{ is_numeric($transitInHours) ? $transitInHours . 'h' : $transitInHours }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Repair Duration:</span>
                                        <strong class="text-purple-900 font-mono">
                                            {{ ($t->workshop_received_at && $t->workshop_repaired_at) ? round($t->workshop_received_at->diffInMinutes($t->workshop_repaired_at) / 60, 1) . 'h' : 'Bench Work' }}
                                        </strong>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Return Delivery:</span>
                                        <strong class="text-purple-900 font-mono">{{ is_numeric($transitOutHours) ? $transitOutHours . 'h' : $transitOutHours }}</strong>
                                    </div>
                                </div>
                            @else
                                <div class="text-slate-400 italic mt-2">Field serviced; not transferred to central workshop.</div>
                            @endif
                        </div>

                        <!-- 4. Final Resolution & Proof -->
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center justify-between">
                                <span>4. Resolution &amp; Bank Sign-Off</span>
                                <i class="fa-solid fa-certificate text-emerald-600"></i>
                            </span>
                            @if(in_array($t->status, ['resolved', 'closed']))
                                <div>
                                    <div class="font-bold text-emerald-800">{{ $t->resolved_at ? \Carbon\Carbon::parse($t->resolved_at)->format('d M Y H:i') : 'Resolved' }}</div>
                                    <div class="text-[10px] text-slate-500 truncate" title="{{ $t->resolution_summary }}">{{ $t->resolution_summary ?? 'Work completed' }}</div>
                                </div>
                                <div class="pt-1.5 border-t border-slate-200/80 space-y-1">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="text-slate-500">Gross TAT:</span>
                                        <strong class="text-slate-800 font-mono">{{ $auditMetrics['gross_formatted'] }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="text-slate-500">Net Business:</span>
                                        <strong class="text-sky-800 font-mono font-bold">{{ $auditMetrics['net_formatted'] }}</strong>
                                    </div>
                                    @if($t->resolution_email_sent)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            <i class="fa-solid fa-paper-plane mr-1"></i> Official Email Dispatched to Bank
                                        </span>
                                    @else
                                        <span class="text-[10px] text-slate-400 italic">Resolution email pending</span>
                                    @endif
                                </div>
                            @else
                                <div class="text-amber-700 font-bold mt-2 flex items-center gap-1">
                                    <i class="fa-solid fa-spinner animate-spin text-[10px]"></i>
                                    <span>Complaint Currently Active</span>
                                </div>
                                <div class="text-[10px] text-slate-400">Target SLA: {{ $t->sla_deadline ? $t->sla_deadline->format('d M H:i') : 'Standard' }}</div>
                            @endif
                        </div>

                    </div>

                    <!-- Day-by-Day Daily Feedback Timeline -->
                    <div class="p-4 sm:p-5 bg-white space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-calendar-day text-slate-400"></i>
                                <span>Daily Operational Progress Feedback ({{ $t->feedbacks->count() }} Updates Logged)</span>
                            </h4>
                        </div>

                        @if($t->feedbacks->count() > 0)
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($t->feedbacks as $fb)
                                    <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200 text-xs space-y-1.5">
                                        <div class="flex items-center justify-between border-b border-slate-200/70 pb-1">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-sky-100 text-sky-800">
                                                Day {{ $fb->day_number }}
                                            </span>
                                            <span class="text-[10px] text-slate-400">{{ $fb->submitted_at ? $fb->submitted_at->format('d M Y H:i') : $fb->created_at->format('d M Y H:i') }}</span>
                                        </div>
                                        <p class="text-slate-800 font-medium text-[11px] leading-relaxed">{{ $fb->feedback_text }}</p>
                                        @if($fb->action_taken)
                                            <div class="text-[10px] text-slate-500">
                                                <strong class="text-slate-700">Action:</strong> {{ $fb->action_taken }}
                                            </div>
                                        @endif
                                        @if($fb->parts_required && $fb->parts_required !== 'None')
                                            <div class="text-[10px] text-amber-700 font-semibold bg-amber-50 px-2 py-1 rounded border border-amber-200">
                                                <i class="fa-solid fa-microchip mr-1"></i> Parts: {{ $fb->parts_required }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-3 bg-slate-50 rounded-xl text-slate-400 text-xs italic text-center">
                                No intermediate daily feedback updates were recorded while this complaint was open.
                            </div>
                        @endif
                    </div>

                    <!-- Reopen & Multi-Tour Lifecycle History Audit (Cycles / Tours) -->
                    @if($t->cycles && $t->cycles->count() > 1)
                        <div class="p-4 sm:p-5 bg-amber-50/40 border-t border-amber-200/80 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <h4 class="text-xs font-black text-amber-950 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fa-solid fa-arrows-rotate text-amber-700"></i>
                                    <span>Reopen &amp; Multi-Tour Lifecycle History ({{ $t->cycles->count() }} Tours Logged)</span>
                                </h4>
                                <span class="px-2 py-0.5 rounded bg-amber-200/80 text-amber-900 text-[10px] font-bold self-start sm:self-auto">
                                    Recurrent Bank Issue &bull; Fresh SLA Clock Per Reopening
                                </span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse bg-white rounded-xl border border-amber-200 overflow-hidden shadow-2xs">
                                    <thead>
                                        <tr class="bg-amber-100/70 border-b border-amber-200 text-amber-950 uppercase font-black tracking-wider text-[10px]">
                                            <th class="py-2.5 px-3">Tour #</th>
                                            <th class="py-2.5 px-3">Status</th>
                                            <th class="py-2.5 px-3">Opened / Reopened At</th>
                                            <th class="py-2.5 px-3">Authorized By</th>
                                            <th class="py-2.5 px-3">Reopen Reason / Bank Recurrence</th>
                                            <th class="py-2.5 px-3">Resolved / Re-Closed At</th>
                                            <th class="py-2.5 px-3 text-right">Tour TAT Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-amber-100 text-[11px]">
                                        @foreach($t->cycles as $cycle)
                                            <tr class="hover:bg-amber-50/50 transition {{ $cycle->isOpen() ? 'bg-amber-50/30 font-semibold' : '' }}">
                                                <td class="py-2.5 px-3 font-mono font-bold text-slate-900 whitespace-nowrap">
                                                    <span class="px-2 py-0.5 rounded {{ $cycle->cycle_no === 1 ? 'bg-slate-100 text-slate-800' : 'bg-amber-100 text-amber-900 font-black' }}">
                                                        Tour {{ $cycle->cycle_no }} {{ $cycle->cycle_no === 1 ? '(Initial)' : '(Reopened)' }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-3 whitespace-nowrap">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                                        {{ $cycle->status === 'closed' ? 'bg-slate-100 text-slate-700' : '' }}
                                                        {{ $cycle->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                                        {{ $cycle->status === 'open' ? 'bg-blue-100 text-blue-800' : '' }}">
                                                        {{ $cycle->status }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-3 whitespace-nowrap text-slate-800 font-medium">
                                                    {{ $cycle->opened_at ? $cycle->opened_at->format('d M Y H:i') : '—' }}
                                                </td>
                                                <td class="py-2.5 px-3 whitespace-nowrap text-slate-700">
                                                    {{ $cycle->openedBy?->name ?? ($cycle->cycle_no === 1 ? 'Intake System' : 'Operations Manager') }}
                                                </td>
                                                <td class="py-2.5 px-3 text-slate-700 max-w-xs">
                                                    @if($cycle->reopen_reason)
                                                        <span class="text-amber-900 font-medium" title="{{ $cycle->reopen_reason }}">
                                                            {{ $cycle->reopen_reason }}
                                                        </span>
                                                    @else
                                                        <span class="text-slate-400 italic">Initial complaint logged</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3 whitespace-nowrap text-slate-800">
                                                    @if($cycle->closed_at)
                                                        <div class="font-bold text-slate-900">{{ $cycle->closed_at->format('d M Y H:i') }}</div>
                                                        <div class="text-[10px] text-slate-400">Closed by {{ $cycle->closedBy?->name ?? 'Admin' }}</div>
                                                    @elseif($cycle->resolved_at)
                                                        <div class="font-bold text-emerald-800">{{ $cycle->resolved_at->format('d M Y H:i') }}</div>
                                                        <div class="text-[10px] text-slate-400">Resolved by {{ $cycle->resolvedBy?->name ?? 'Lead Engineer' }}</div>
                                                    @else
                                                        <span class="text-blue-700 font-bold flex items-center gap-1">
                                                            <i class="fa-solid fa-spinner animate-spin text-[10px]"></i> Running
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                                    <span class="font-mono font-bold text-slate-900">{{ $cycle->durationFormatted() }}</span>
                                                    @if($cycle->sla_deadline)
                                                        <div class="text-[10px] {{ $cycle->isInTat() ? 'text-emerald-700 font-semibold' : 'text-rose-700 font-bold' }}">
                                                            {{ $cycle->isInTat() ? 'In-TAT' : 'Overdue' }} (Target: {{ $cycle->sla_deadline->format('d M H:i') }})
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                </div>
            @empty
                <div class="bg-white rounded-2xl p-12 text-center text-slate-400 text-xs border border-slate-200">
                    <i class="fa-solid fa-building-columns text-3xl text-slate-300 mb-2 block"></i>
                    No tickets found matching the selected bank criteria.
                </div>
            @endforelse

            <div id="bankAuditNoResults" class="hidden bg-white rounded-2xl p-12 text-center text-slate-400 text-xs border border-slate-200">
                <i class="fa-solid fa-magnifying-glass text-3xl text-slate-300 mb-2 block"></i>
                No bank audit tickets match your live search term.
            </div>

            <div class="mt-4">
                {{ $bankAuditTickets->links() }}
            </div>
        </div>

    </div>
    @endif


    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 3: MACHINE FAULTS & PARTS REPORT (WITH DOWNLOADABLE VIDEO)        --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'machine_faults')
    <div class="space-y-4">

        <!-- Search Bar -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center space-x-2">
                <span class="p-2 bg-purple-100 text-purple-700 rounded-xl"><i class="fa-solid fa-video"></i></span>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Machine Diagnostics &amp; Fault Video Evidence</h3>
                    <p class="text-[11px] text-slate-500">Verified hardware breakdown recordings, component requisitions, and replacement audit trail.</p>
                </div>
            </div>

            <form action="{{ route('reports.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="machine_faults">
                @if(request('preset')) <input type="hidden" name="preset" value="{{ request('preset') }}"> @endif
                @if(request('from_date')) <input type="hidden" name="from_date" value="{{ request('from_date') }}"> @endif
                @if(request('to_date')) <input type="hidden" name="to_date" value="{{ request('to_date') }}"> @endif

                <select name="machine_model_id" onchange="this.form.submit()" class="bg-white border border-slate-300 rounded-xl py-1.5 px-3 text-xs font-semibold focus:ring-2 focus:ring-purple-500">
                    <option value="">-- All Machine Models --</option>
                    @foreach($machineModels as $mm)
                        <option value="{{ $mm->id }}" {{ request('machine_model_id') == $mm->id ? 'selected' : '' }}>
                            {{ $mm->name }} ({{ $mm->manufacturer ?? 'Terminal' }})
                        </option>
                    @endforeach
                </select>

                <input type="text" name="machine_search" value="{{ request('machine_search') }}" 
                       placeholder="Serial #, Fault, Bank..." 
                       class="bg-white border border-slate-300 rounded-xl py-1.5 px-3 text-xs focus:ring-2 focus:ring-sky-500">
                <button type="submit" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition cursor-pointer">
                    Search
                </button>
                @if(request('machine_model_id') || request('machine_search'))
                    <a href="{{ route('reports.index', ['tab' => 'machine_faults']) }}" class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold rounded-lg text-xs transition">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <!-- Machine Fault Cards with Video Player -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @forelse($machineFaultRequests as $pr)
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col justify-between">
                    
                    <div class="p-5 space-y-4">
                        <!-- Top Metadata -->
                        <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-xs bg-slate-900 text-white px-2 py-0.5 rounded-md">
                                        {{ $pr->request_number }}
                                    </span>
                                    <span class="font-mono font-extrabold text-sm text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                                        S/N: {{ $pr->machine_serial_no }}
                                    </span>
                                </div>
                                <h3 class="font-bold text-slate-900 text-sm mt-1">
                                    {{ $pr->machineModel?->name ?? $pr->machineModel?->model_name ?? 'Banking Automation Unit' }}
                                </h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    <span>Client: <strong>{{ $pr->ticket?->bank_name ?? 'Bank Client' }}</strong> ({{ $pr->ticket?->branch_location ?? 'Branch' }})</span>
                                    <span class="mx-1">&bull;</span>
                                    <span>Ticket: <a href="{{ route('tickets.show', $pr->ticket_id) }}" class="text-sky-600 hover:underline font-mono">#{{ $pr->ticket?->ticket_no }}</a></span>
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
                                {{ $pr->status === 'dispatched' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                {{ $pr->status === 'approved' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $pr->status === 'pending_approval' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $pr->status === 'pending_stock_check' ? 'bg-amber-100 text-amber-800' : '' }}">
                                {{ $pr->status_label ?? ucfirst(str_replace('_', ' ', $pr->status)) }}
                            </span>
                        </div>

                        <!-- Fault Description -->
                        <div class="p-3 bg-rose-50/60 rounded-xl border border-rose-200 text-xs">
                            <span class="font-bold text-rose-900 block mb-0.5">
                                <i class="fa-solid fa-triangle-exclamation mr-1 text-rose-600"></i> Logged Hardware Symptom:
                            </span>
                            <p class="text-rose-800 text-[11px]">{{ $pr->fault_description }}</p>
                        </div>

                        <!-- Video Evidence Player & Download -->
                        @if($pr->fault_video_path)
                            <div class="p-3 bg-slate-950 rounded-xl text-white space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold flex items-center gap-1.5 text-purple-300">
                                        <i class="fa-solid fa-circle-play text-rose-500 animate-pulse"></i>
                                        <span>Fault Video Evidence Recording</span>
                                    </span>
                                    <a href="{{ route('parts.requests.video.download', $pr) }}" 
                                       class="inline-flex items-center px-2.5 py-1 bg-purple-700 hover:bg-purple-600 text-white rounded-lg text-[10px] font-bold shadow-xs transition" title="Download Full Resolution Video">
                                        <i class="fa-solid fa-download mr-1"></i> Download Video
                                    </a>
                                </div>
                                <div class="rounded-lg overflow-hidden bg-black aspect-video max-h-56 flex items-center justify-center">
                                    <video controls preload="metadata" class="w-full h-full object-contain">
                                        <source src="{{ route('parts.requests.video', $pr) }}" type="video/mp4">
                                        Your browser does not support HTML5 video streaming.
                                    </video>
                                </div>
                            </div>
                        @else
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center text-slate-400 text-xs italic">
                                <i class="fa-solid fa-video-slash mr-1"></i> No video evidence uploaded for this fault request.
                            </div>
                        @endif

                        <!-- Parts Requested Table -->
                        <div class="space-y-1.5 text-xs">
                            <div class="font-bold text-slate-800 flex items-center justify-between">
                                <span>Spare Components Requisitioned:</span>
                                <span class="text-[11px] text-slate-500">{{ $pr->items->count() }} Items</span>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left text-[11px]">
                                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                                        <tr>
                                            <th class="py-2 px-3">Component Name</th>
                                            <th class="py-2 px-3 text-center">Part #</th>
                                            <th class="py-2 px-3 text-center">Qty Req</th>
                                            <th class="py-2 px-3 text-center">Qty Appr</th>
                                            <th class="py-2 px-3 text-right">Cost (PKR)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse($pr->items as $item)
                                            <tr>
                                                <td class="py-2 px-3 font-semibold text-slate-900">{{ $item->part?->name ?? 'Part' }}</td>
                                                <td class="py-2 px-3 text-center font-mono text-slate-500">{{ $item->part?->part_number ?? 'N/A' }}</td>
                                                <td class="py-2 px-3 text-center font-bold text-slate-800">{{ $item->qty_requested }}</td>
                                                <td class="py-2 px-3 text-center font-bold text-emerald-700">{{ $item->qty_approved }}</td>
                                                <td class="py-2 px-3 text-right font-bold text-slate-900">
                                                    PKR {{ number_format($item->qty_requested * ($item->unit_cost ?? 0), 2) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-2 px-3 text-center text-slate-400 italic">No itemized components listed</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- Card Footer: Courier / Logistics Info -->
                    <div class="p-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                        <div>
                            Requisitioned by: <strong class="text-slate-800">{{ $pr->engineer?->name ?? 'Engineer' }}</strong>
                        </div>
                        <div>
                            @if($pr->dispatch_courier)
                                <span class="text-emerald-700 font-semibold"><i class="fa-solid fa-truck mr-1"></i>{{ $pr->dispatch_courier }} ({{ $pr->dispatch_tracking_number }})</span>
                            @else
                                <span class="italic text-slate-400">Logistics dispatch pending</span>
                            @endif
                        </div>
                    </div>

                </div>
            @empty
                <div class="col-span-2 bg-white rounded-2xl p-12 text-center text-slate-400 text-xs border border-slate-200">
                    <i class="fa-solid fa-video text-3xl text-slate-300 mb-2 block"></i>
                    No machine fault reports with video evidence found for the selected criteria.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $machineFaultRequests->links() }}
        </div>

    </div>
    @endif


    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 4: ENGINEER PARTS & IN-TAT / OUT-OF-TAT RESOLUTIONS REPORT        --}}
    {{-- ═════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'engineer_parts')
    <div class="space-y-6">

        <!-- 1. Engineer TAT Performance Scorecards Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg text-xs"><i class="fa-solid fa-stopwatch-20"></i></span>
                        Engineer In-TAT vs. Out-of-TAT Resolution Scorecard
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Measurement of turnaround time (TAT) compliance against strict SLA contracts (In-TAT vs. Overdue Breaches).
                    </p>
                </div>
                <span class="text-xs font-bold text-slate-600 bg-slate-200/80 px-2.5 py-1 rounded-full self-start sm:self-auto">
                    {{ $engineerTatMetrics->count() }} Engineers Evaluated
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                            <th class="py-3 px-4">Engineer</th>
                            <th class="py-3 px-4">Base City</th>
                            <th class="py-3 px-4 text-center">Assigned Complaints</th>
                            <th class="py-3 px-4 text-center">In-TAT Resolved 🟢</th>
                            <th class="py-3 px-4 text-center">Out-of-TAT Breached 🔴</th>
                            <th class="py-3 px-4 text-center">Active Overdue ⚠️</th>
                            <th class="py-3 px-4">TAT Compliance Rate</th>
                            <th class="py-3 px-4 text-center">Avg TAT (hrs)</th>
                            <th class="py-3 px-4 text-right">Parts Requisitioned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($engineerTatMetrics as $eng)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    <a href="{{ route('reports.index', ['tab' => 'engineer_parts', 'engineer_filter' => $eng['id']]) }}" class="hover:text-sky-600 hover:underline flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs uppercase">
                                            {{ substr($eng['name'], 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900">{{ $eng['name'] }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal">{{ $eng['phone'] }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-600">{{ $eng['base_city'] }}</td>
                                <td class="py-3 px-4 text-center font-bold text-slate-800">{{ $eng['total_assigned'] }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                        {{ $eng['in_tat_resolved'] }} In-TAT
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if($eng['out_tat_resolved'] > 0)
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-rose-100 text-rose-800 border border-rose-300">
                                            {{ $eng['out_tat_resolved'] }} Overdue
                                        </span>
                                    @else
                                        <span class="text-slate-400 font-bold">0</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center font-bold {{ $eng['active_breached'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                    {{ $eng['active_breached'] }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="w-full flex items-center space-x-2">
                                        <div class="flex-1 bg-slate-200 rounded-full h-2 overflow-hidden">
                                            <div class="h-2 rounded-full {{ $eng['tat_compliance_rate'] >= 80 ? 'bg-emerald-500' : ($eng['tat_compliance_rate'] >= 60 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ min(100, max(0, $eng['tat_compliance_rate'])) }}%"></div>
                                        </div>
                                        <span class="text-[11px] font-bold {{ $eng['tat_compliance_rate'] >= 80 ? 'text-emerald-700' : ($eng['tat_compliance_rate'] >= 60 ? 'text-amber-700' : 'text-rose-700') }}">
                                            {{ $eng['tat_compliance_rate'] }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-slate-800">
                                    {{ $eng['avg_tat_hours'] }}h
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">
                                    {{ $eng['parts_requested_count'] }} parts
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-10 text-center text-slate-400">
                                    No engineer TAT performance data available for this timeframe.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Itemized Spare Parts Requisitioned by Engineer Against Machines -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="p-1.5 bg-teal-100 text-teal-700 rounded-lg text-xs"><i class="fa-solid fa-microchip"></i></span>
                        Itemized Parts Consumption by Engineer &amp; Target Machine
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Detailed audit showing which engineer requisitioned how many parts against each specific terminal serial number.
                    </p>
                </div>

                <form action="{{ route('reports.index') }}" method="GET" class="flex items-center space-x-2">
                    <input type="hidden" name="tab" value="engineer_parts">
                    @if(request('preset')) <input type="hidden" name="preset" value="{{ request('preset') }}"> @endif
                    @if(request('from_date')) <input type="hidden" name="from_date" value="{{ request('from_date') }}"> @endif
                    @if(request('to_date')) <input type="hidden" name="to_date" value="{{ request('to_date') }}"> @endif

                    <label class="font-semibold text-slate-600">Engineer:</label>
                    <select name="engineer_filter" class="bg-white border border-slate-300 rounded-xl py-1.5 px-3 text-xs font-semibold focus:ring-2 focus:ring-sky-500" onchange="this.form.submit()">
                        <option value="">-- All Engineers --</option>
                        @foreach($engineers as $en)
                            <option value="{{ $en->id }}" {{ request('engineer_filter') == $en->id ? 'selected' : '' }}>
                                {{ $en->name }} ({{ $en->base_city }})
                            </option>
                        @endforeach
                    </select>
                    @if(request('engineer_filter'))
                        <a href="{{ route('reports.index', ['tab' => 'engineer_parts']) }}" class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold transition">
                            Clear
                        </a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                            <th class="py-3 px-4">Engineer</th>
                            <th class="py-3 px-4">Machine Serial #</th>
                            <th class="py-3 px-4">Machine Model</th>
                            <th class="py-3 px-4">Bank &amp; Ticket</th>
                            <th class="py-3 px-4">Component Name</th>
                            <th class="py-3 px-4 text-center">Part #</th>
                            <th class="py-3 px-4 text-center">Qty Req</th>
                            <th class="py-3 px-4 text-center">Qty Appr</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Unit / Line Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($engineerPartsRequests as $pr)
                            @foreach($pr->items as $item)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-4 font-bold text-slate-900">
                                        {{ $pr->engineer?->name ?? 'Engineer' }}
                                        <div class="text-[10px] text-slate-400 font-normal">{{ $pr->engineer?->base_city }}</div>
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                        <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-200">{{ $pr->machine_serial_no }}</span>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-700">
                                        {{ $pr->machineModel?->name ?? $pr->machineModel?->model_name ?? 'ATM / Sorter' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-900">{{ $pr->ticket?->bank_name ?? 'Bank' }}</div>
                                        <div class="text-[10px] text-sky-600 font-mono">#{{ $pr->ticket?->ticket_no }}</div>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-900">
                                        {{ $item->part?->name ?? 'Part #' . $item->part_id }}
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono text-slate-500">
                                        {{ $item->part?->part_number ?? 'N/A' }}
                                    </td>
                                    <td class="py-3 px-4 text-center font-bold text-slate-800">{{ $item->qty_requested }}</td>
                                    <td class="py-3 px-4 text-center font-bold text-emerald-700">{{ $item->qty_approved }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                            {{ $pr->status === 'dispatched' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $pr->status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-900">
                                        PKR {{ number_format($item->qty_requested * ($item->unit_cost ?? 0), 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="10" class="py-10 text-center text-slate-400">
                                    No parts requisitions found for this criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200">
                {{ $engineerPartsRequests->links() }}
            </div>
        </div>

    </div>
    @endif

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const liveSearchInput = document.getElementById('bankAuditLiveSearch');
        const noResultsBox = document.getElementById('bankAuditNoResults');

        if (liveSearchInput) {
            liveSearchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.bank-audit-card');
                let matchCount = 0;

                cards.forEach(card => {
                    const data = (card.getAttribute('data-search') || '').toLowerCase();
                    if (!query || data.includes(query)) {
                        card.style.display = '';
                        matchCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (noResultsBox) {
                    if (cards.length > 0 && matchCount === 0) {
                        noResultsBox.classList.remove('hidden');
                    } else {
                        noResultsBox.classList.add('hidden');
                    }
                }
            });
        }
    });
</script>
@endsection
