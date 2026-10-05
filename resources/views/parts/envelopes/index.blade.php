@extends('layouts.app')

@section('title', 'Engineer Advance Inventory & Float Envelopes')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('dashboard') }}" class="hover:text-slate-700">Dashboard</a>
                <span>/</span>
                <span>Parts &amp; Inventory</span>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Advance Envelopes</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-briefcase text-sky-600"></i>
                @if($isEngineer)
                    My Advance Parts Envelope
                @else
                    Engineer Advance Inventory &amp; Float Envelopes
                @endif
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                @if($isEngineer)
                    View parts you carry in your kit bag, automatic consumption history, and current stock balance.
                @else
                    Lend spare parts in advance to field engineers, track advance float envelopes, and audit complaint usage.
                @endif
            </p>
        </div>

        {{-- Actions for Superiors / Admins --}}
        <div class="flex items-center gap-2.5 flex-wrap">
            @if(!auth()->user()->isEngineer())
                <button type="button" onclick="openLendModal()"
                        class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-3.5 rounded-xl text-xs transition inline-flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-hand-holding-hand"></i> Lend Advance Parts
                </button>
            @endif
            <button type="button" onclick="openReturnModal()"
                    class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 font-bold py-2 px-3.5 rounded-xl text-xs transition inline-flex items-center gap-2 shadow-2xs">
                <i class="fa-solid fa-rotate-left text-slate-500"></i> Return to Store
            </button>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs px-4 py-3 rounded-xl mb-6 flex items-center gap-2 shadow-2xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-xs px-4 py-3 rounded-xl mb-6 flex items-center gap-2 shadow-2xs">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Summary Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Available In Envelope</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($totalOnHand) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Currently usable in kit bags</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Used On Complaints</span>
                <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-wrench"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($totalUsed) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Consumed on customer tickets</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Lifetime Lent</span>
                <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($totalAllocated) }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Cumulative float issued</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    {{ $isEngineer ? 'My Assigned City' : 'Engineers with Stock' }}
                </span>
                <span class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-user-gear"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900">
                {{ $isEngineer ? (auth()->user()->base_city ?? 'Lahore') : $activeEngineersCount }}
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
                {{ $isEngineer ? 'Operational territory' : 'Field staff carrying parts' }}
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-2xs border border-slate-200 p-4 mb-6">
        <form method="GET" action="{{ route('parts.envelopes.index') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 text-xs items-end">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            {{-- Search Keywords --}}
            <div class="{{ $isEngineer ? 'md:col-span-4' : 'md:col-span-3' }}">
                <label class="block font-medium text-slate-700 mb-1">Search Keywords</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Part name, code, ticket #, serial..."
                           class="w-full border border-slate-300 rounded-lg pl-8 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                </div>
            </div>

            {{-- Engineer Filter (for Superiors) --}}
            @if(!$isEngineer)
                <div class="md:col-span-3">
                    <label class="block font-medium text-slate-700 mb-1">Field Engineer</label>
                    <select name="engineer_id"
                            class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <option value="">All Engineers</option>
                        @foreach($engineers as $eng)
                            <option value="{{ $eng->id }}" {{ request('engineer_id') == $eng->id ? 'selected' : '' }}>
                                {{ $eng->name }} ({{ $eng->base_city ?? 'Field' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- From Date --}}
            <div class="md:col-span-2">
                <label class="block font-medium text-slate-700 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                       class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
            </div>

            {{-- To Date --}}
            <div class="md:col-span-2">
                <label class="block font-medium text-slate-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                       class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
            </div>

            {{-- Actions --}}
            <div class="{{ $isEngineer ? 'md:col-span-4' : 'md:col-span-2' }} flex items-center gap-1.5">
                <button type="submit"
                        class="flex-1 bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-3 rounded-lg text-xs transition inline-flex items-center justify-center gap-1.5 shadow-xs">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
                @if(request()->hasAny(['search', 'engineer_id', 'from_date', 'to_date']))
                    <a href="{{ route('parts.envelopes.index', ['tab' => $activeTab]) }}"
                       class="bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 px-2.5 rounded-lg text-xs font-semibold transition"
                       title="Reset all filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Main View Tabs --}}
    <div class="border-b border-slate-200 mb-6 flex gap-4">
        <a href="{{ route('parts.envelopes.index', array_merge(request()->query(), ['tab' => 'envelopes'])) }}"
           class="pb-3 text-xs font-bold transition flex items-center gap-2 border-b-2
                  {{ $activeTab === 'envelopes' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <i class="fa-solid fa-briefcase"></i>
            Current Envelopes Balance
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $activeTab === 'envelopes' ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-600' }}">
                {{ $envelopes->total() }}
            </span>
        </a>

        <a href="{{ route('parts.envelopes.index', array_merge(request()->query(), ['tab' => 'usage'])) }}"
           class="pb-3 text-xs font-bold transition flex items-center gap-2 border-b-2
                  {{ $activeTab === 'usage' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <i class="fa-solid fa-wrench"></i>
            Where &amp; How Much Used (Ticket Log)
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $activeTab === 'usage' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
                {{ $usages->total() }}
            </span>
        </a>

        <a href="{{ route('parts.envelopes.index', array_merge(request()->query(), ['tab' => 'audit'])) }}"
           class="pb-3 text-xs font-bold transition flex items-center gap-2 border-b-2
                  {{ $activeTab === 'audit' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            <i class="fa-solid fa-clock-rotate-left"></i>
            All Envelope Transactions
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $activeTab === 'audit' ? 'bg-slate-200 text-slate-700' : 'bg-slate-100 text-slate-600' }}">
                {{ $transactions->total() }}
            </span>
        </a>
    </div>

    {{-- TAB 1: Current Envelopes --}}
    @if($activeTab === 'envelopes')
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
            @if($envelopes->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                    <i class="fa-solid fa-briefcase text-4xl mb-3 text-slate-300"></i>
                    <p class="text-sm font-bold text-slate-700">No advance envelopes match your query</p>
                    <p class="text-xs text-slate-400 mt-1">Lend advance parts to field engineers to populate their kit bag envelopes.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-[11px] tracking-wider">
                                @if(!$isEngineer)
                                    <th class="text-left px-4 py-3.5">Field Engineer</th>
                                @endif
                                <th class="text-left px-4 py-3.5">Part Code</th>
                                <th class="text-left px-4 py-3.5">Part Description</th>
                                <th class="text-center px-4 py-3.5">Lifetime Lent</th>
                                <th class="text-center px-4 py-3.5">Used on Complaints</th>
                                <th class="text-center px-4 py-3.5">Current Usable Balance</th>
                                <th class="text-center px-4 py-3.5">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($envelopes as $env)
                                <tr class="hover:bg-slate-50 transition">
                                    @if(!$isEngineer)
                                        <td class="px-4 py-3.5">
                                            <div class="font-bold text-slate-800">{{ $env->engineer?->name ?? '—' }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $env->engineer?->phone_whatsapp ?? 'No phone' }}</div>
                                        </td>
                                    @endif
                                    <td class="px-4 py-3.5 font-mono font-bold text-slate-700">
                                        {{ $env->part?->part_number ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-slate-800">{{ $env->part?->name ?? '—' }}</div>
                                        <div class="text-[10px] text-slate-400">Unit: {{ $env->part?->unit ?? 'pcs' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-center font-semibold text-slate-600">
                                        {{ $env->qty_allocated }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            {{ $env->qty_used }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black
                                                     {{ $env->qty_on_hand > 0 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs' : 'bg-slate-100 text-slate-400' }}">
                                            {{ $env->qty_on_hand }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <a href="{{ route('parts.envelopes.index', ['tab' => 'usage', 'engineer_id' => $env->engineer_id, 'part_id' => $env->part_id]) }}"
                                               class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-md text-[11px] font-semibold transition"
                                               title="View where this part was used">
                                                <i class="fa-solid fa-wrench mr-1 text-[10px]"></i> View Usages
                                            </a>
                                            @if($env->qty_on_hand > 0)
                                                <button type="button"
                                                        onclick="quickReturnModal({{ $env->engineer_id }}, {{ $env->part_id }}, '{{ addslashes($env->part?->name) }}', {{ $env->qty_on_hand }})"
                                                        class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 px-2 py-1 rounded-md text-[11px] font-semibold transition"
                                                        title="Return stock to warehouse">
                                                    <i class="fa-solid fa-rotate-left mr-1 text-[10px]"></i> Return
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-slate-100">
                    {{ $envelopes->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- TAB 2: Where & How Much Used (Ticket Log) --}}
    @if($activeTab === 'usage')
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
            @if($usages->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                    <i class="fa-solid fa-clipboard-check text-4xl mb-3 text-slate-300"></i>
                    <p class="text-sm font-bold text-slate-700">No advance envelope usages recorded yet</p>
                    <p class="text-xs text-slate-400 mt-1">When an engineer needs a part on a complaint ticket, it is automatically deducted from their envelope and logged here.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-[11px] tracking-wider">
                                <th class="text-left px-4 py-3.5">Consumed Date</th>
                                @if(!$isEngineer)
                                    <th class="text-left px-4 py-3.5">Engineer</th>
                                @endif
                                <th class="text-left px-4 py-3.5">Complaint Ticket</th>
                                <th class="text-left px-4 py-3.5">Bank &amp; Branch</th>
                                <th class="text-left px-4 py-3.5">Machine Details</th>
                                <th class="text-left px-4 py-3.5">Part Consumed</th>
                                <th class="text-center px-4 py-3.5">Qty Deducted</th>
                                <th class="text-center px-4 py-3.5">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($usages as $use)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3.5 text-slate-500 whitespace-nowrap">
                                        <div class="font-semibold text-slate-800">{{ $use->created_at->format('d M Y') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $use->created_at->format('h:i A') }}</div>
                                    </td>
                                    @if(!$isEngineer)
                                        <td class="px-4 py-3.5 font-bold text-slate-800">
                                            {{ $use->engineer?->name ?? '—' }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-3.5">
                                        @if($use->ticket)
                                            <a href="{{ route('tickets.show', $use->ticket) }}"
                                               class="font-mono font-bold text-sky-600 hover:text-sky-800 hover:underline">
                                                #{{ $use->ticket->ticket_no }}
                                            </a>
                                        @else
                                            <span class="text-slate-400 font-mono">#{{ $use->ticket_id }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-800">{{ $use->ticket?->bank_name ?? '—' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $use->ticket?->branch_location ?? $use->ticket?->branch_name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-slate-700">{{ $use->machineModel?->name ?? $use->ticket?->machine_model ?? '—' }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            S/N: {{ $use->machine_serial_no ?? $use->ticket?->machine_serial_no ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-slate-800">{{ $use->part?->name ?? '—' }}</div>
                                        <div class="font-mono text-[10px] text-slate-400">{{ $use->part?->part_number ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-100 text-amber-800 border border-amber-300">
                                            -{{ $use->qty }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        @if($use->ticket)
                                            <a href="{{ route('tickets.show', $use->ticket) }}"
                                               class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded text-xs font-semibold transition inline-flex items-center gap-1">
                                                <i class="fa-solid fa-eye text-[10px]"></i> Ticket
                                            </a>
                                        @elseif($use->partRequest)
                                            <a href="{{ route('parts.requests.show', $use->partRequest) }}"
                                               class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded text-xs font-semibold transition inline-flex items-center gap-1">
                                                <i class="fa-solid fa-eye text-[10px]"></i> PR
                                            </a>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-slate-100">
                    {{ $usages->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- TAB 3: All Envelope Transactions --}}
    @if($activeTab === 'audit')
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
            @if($transactions->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                    <i class="fa-solid fa-clock-rotate-left text-4xl mb-3 text-slate-300"></i>
                    <p class="text-sm font-bold text-slate-700">No transaction records found</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-[11px] tracking-wider">
                                <th class="text-left px-4 py-3.5">Date &amp; Time</th>
                                @if(!$isEngineer)
                                    <th class="text-left px-4 py-3.5">Engineer</th>
                                @endif
                                <th class="text-center px-4 py-3.5">Type</th>
                                <th class="text-left px-4 py-3.5">Part</th>
                                <th class="text-center px-4 py-3.5">Qty</th>
                                <th class="text-left px-4 py-3.5">Origin / Destination / Reference</th>
                                <th class="text-left px-4 py-3.5">Performed By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($transactions as $tx)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3.5 text-slate-500 whitespace-nowrap">
                                        <div class="font-semibold text-slate-800">{{ $tx->created_at->format('d M Y') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $tx->created_at->format('h:i A') }}</div>
                                    </td>
                                    @if(!$isEngineer)
                                        <td class="px-4 py-3.5 font-bold text-slate-800">
                                            {{ $tx->engineer?->name ?? '—' }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $tx->type_badge_class }}">
                                            {{ $tx->type_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-slate-800">{{ $tx->part?->name ?? '—' }}</div>
                                        <div class="font-mono text-[10px] text-slate-400">{{ $tx->part?->part_number ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-center font-bold {{ $tx->type === 'consumed_complaint' ? 'text-amber-700' : ($tx->type === 'advance_issue' ? 'text-sky-700' : 'text-emerald-700') }}">
                                        {{ $tx->type === 'consumed_complaint' ? '-' : '+' }}{{ $tx->qty }}
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        @if($tx->sourceLocation)
                                            <div>From: <span class="font-semibold">{{ $tx->sourceLocation->name }}</span></div>
                                        @endif
                                        @if($tx->destinationLocation)
                                            <div>To: <span class="font-semibold">{{ $tx->destinationLocation->name }}</span></div>
                                        @endif
                                        @if($tx->ticket)
                                            <div>Ticket: <a href="{{ route('tickets.show', $tx->ticket) }}" class="text-sky-600 hover:underline font-bold">#{{ $tx->ticket->ticket_no }}</a></div>
                                        @endif
                                        @if($tx->notes)
                                            <div class="text-[10px] text-slate-400 mt-0.5 italic">{{ $tx->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-500">
                                        {{ $tx->createdBy?->name ?? 'System' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-slate-100">
                    {{ $transactions->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    @endif

</div>

{{-- Modal 1: Lend Advance Stock to Engineer --}}
@if(!auth()->user()->isEngineer())
<div id="lendModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full border border-slate-200 overflow-hidden transform transition-all">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-hand-holding-hand text-sky-600"></i>
                Lend Advance Parts to Engineer Float
            </h3>
            <button type="button" onclick="closeLendModal()" class="text-slate-400 hover:text-slate-600 text-base">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('parts.envelopes.lend') }}" class="p-6 space-y-4 text-xs">
            @csrf

            {{-- Engineer Selection --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Select Field Engineer <span class="text-rose-500">*</span></label>
                <select name="engineer_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-400">
                    <option value="">-- Choose Field Engineer --</option>
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}">{{ $eng->name }} ({{ $eng->base_city ?? 'Field' }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Source Warehouse Location --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Source Warehouse Location <span class="text-rose-500">*</span></label>
                <select name="source_location_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-400">
                    <option value="">-- Choose Warehouse --</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->city }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Part Selection with Inside Search --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block font-semibold text-slate-700">Spare Component / Part <span class="text-rose-500">*</span></label>
                    <span id="lendPartCountBadge" class="text-[10px] text-slate-400 font-medium">{{ count($parts) }} parts</span>
                </div>
                <div class="relative mb-1.5">
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" id="lendPartSearch" oninput="filterLendModalParts()"
                           placeholder="Type to search part code or name (e.g. cutter, motor, roller)..."
                           class="w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-sky-400 bg-slate-50 focus:bg-white transition">
                </div>
                <select name="part_id" id="lendPartSelect" required size="5" class="w-full border border-slate-300 rounded-lg p-1 text-xs focus:ring-2 focus:ring-sky-400 bg-white">
                    <option value="" disabled class="text-slate-400 py-1">-- Click to choose part --</option>
                    @foreach($parts as $p)
                        <option value="{{ $p->id }}" data-search="{{ strtolower($p->part_number . ' ' . $p->name) }}" class="py-1 px-2 hover:bg-sky-50 rounded">
                            {{ $p->part_number }} — {{ $p->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-slate-400 mt-1">Search by part code or description, then click to select.</p>
            </div>

            {{-- Quantity --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Quantity to Lend <span class="text-rose-500">*</span></label>
                <input type="number" name="qty" min="1" value="1" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-bold focus:ring-2 focus:ring-sky-400">
            </div>

            {{-- Notes --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Notes / Purpose (Optional)</label>
                <input type="text" name="notes" placeholder="e.g. Monthly preventative maintenance kit advance float"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-400">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeLendModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-bold shadow-xs transition inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i> Issue &amp; Lend Stock
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Modal 2: Return Stock to Warehouse --}}
<div id="returnModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full border border-slate-200 overflow-hidden transform transition-all">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-rotate-left text-amber-600"></i>
                Return Advance Parts to Store Warehouse
            </h3>
            <button type="button" onclick="closeReturnModal()" class="text-slate-400 hover:text-slate-600 text-base">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('parts.envelopes.return') }}" class="p-6 space-y-4 text-xs">
            @csrf

            {{-- Engineer Selection --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Field Engineer <span class="text-rose-500">*</span></label>
                @if($isEngineer)
                    <input type="hidden" name="engineer_id" value="{{ auth()->id() }}">
                    <input type="text" disabled value="{{ auth()->user()->name }}" class="w-full bg-slate-100 border border-slate-300 rounded-lg px-3 py-2 text-xs font-bold text-slate-700">
                @else
                    <select name="engineer_id" id="returnEngineerSelect" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-400">
                        <option value="">-- Choose Field Engineer --</option>
                        @foreach($engineers as $eng)
                            <option value="{{ $eng->id }}">{{ $eng->name }} ({{ $eng->base_city ?? 'Field' }})</option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- Destination Warehouse --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Destination Warehouse <span class="text-rose-500">*</span></label>
                <select name="destination_location_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-400">
                    <option value="">-- Choose Destination Warehouse --</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->city }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Part Selection with Inside Search --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block font-semibold text-slate-700">Part to Return <span class="text-rose-500">*</span></label>
                    <span id="returnPartCountBadge" class="text-[10px] text-slate-400 font-medium">{{ count($parts) }} parts</span>
                </div>
                <div class="relative mb-1.5">
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" id="returnPartSearch" oninput="filterReturnModalParts()"
                           placeholder="Type to search part code or name..."
                           class="w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-sky-400 bg-slate-50 focus:bg-white transition">
                </div>
                <select name="part_id" id="returnPartSelect" required size="5" class="w-full border border-slate-300 rounded-lg p-1 text-xs focus:ring-2 focus:ring-sky-400 bg-white">
                    <option value="" disabled class="text-slate-400 py-1">-- Click to choose part --</option>
                    @foreach($parts as $p)
                        <option value="{{ $p->id }}" data-search="{{ strtolower($p->part_number . ' ' . $p->name) }}" class="py-1 px-2 hover:bg-sky-50 rounded">
                            {{ $p->part_number }} — {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Quantity --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Quantity to Return <span class="text-rose-500">*</span></label>
                <input type="number" name="qty" id="returnQtyInput" min="1" value="1" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-bold focus:ring-2 focus:ring-sky-400">
            </div>

            {{-- Notes --}}
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Remarks / Note</label>
                <input type="text" name="notes" placeholder="e.g. Unused kit returned to Lahore store"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-sky-400">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeReturnModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold shadow-xs transition inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i> Return to Warehouse
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openLendModal() {
    const m = document.getElementById('lendModal');
    if (m) m.classList.remove('hidden');
}
function closeLendModal() {
    const m = document.getElementById('lendModal');
    if (m) m.classList.add('hidden');
}

function openReturnModal() {
    const m = document.getElementById('returnModal');
    if (m) m.classList.remove('hidden');
}
function closeReturnModal() {
    const m = document.getElementById('returnModal');
    if (m) m.classList.add('hidden');
}

function quickReturnModal(engId, partId, partName, onHand) {
    openReturnModal();
    const engSelect = document.getElementById('returnEngineerSelect');
    if (engSelect) {
        engSelect.value = engId;
    }
    const partSelect = document.getElementById('returnPartSelect');
    if (partSelect) {
        partSelect.value = partId;
    }
    const qtyInput = document.getElementById('returnQtyInput');
    if (qtyInput) {
        qtyInput.max = onHand;
        qtyInput.value = Math.min(1, onHand);
    }
}

function filterLendModalParts() {
    const q = (document.getElementById('lendPartSearch').value || '').toLowerCase().trim();
    const sel = document.getElementById('lendPartSelect');
    let matches = 0;
    let first = null;
    for (let opt of sel.options) {
        if (!opt.value) continue;
        const txt = (opt.getAttribute('data-search') || opt.text).toLowerCase();
        const match = !q || txt.includes(q);
        opt.style.display = match ? '' : 'none';
        if (match) {
            matches++;
            if (!first) first = opt;
        }
    }
    const badge = document.getElementById('lendPartCountBadge');
    if (badge) badge.textContent = `${matches} matching part(s)`;
    if (first && q) {
        first.selected = true;
    }
}

function filterReturnModalParts() {
    const q = (document.getElementById('returnPartSearch').value || '').toLowerCase().trim();
    const sel = document.getElementById('returnPartSelect');
    let matches = 0;
    let first = null;
    for (let opt of sel.options) {
        if (!opt.value) continue;
        const txt = (opt.getAttribute('data-search') || opt.text).toLowerCase();
        const match = !q || txt.includes(q);
        opt.style.display = match ? '' : 'none';
        if (match) {
            matches++;
            if (!first) first = opt;
        }
    }
    const badge = document.getElementById('returnPartCountBadge');
    if (badge) badge.textContent = `${matches} matching part(s)`;
    if (first && q) {
        first.selected = true;
    }
}
</script>
@endsection
