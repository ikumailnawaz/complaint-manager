@extends('layouts.app')

@section('title', 'Part Requests')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-xs shadow-xs">
            <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-xs shadow-xs">
            <i class="fas fa-exclamation-circle text-rose-500 text-sm"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fas fa-screwdriver-wrench text-sky-600"></i> Part Requests Registry
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Central management of spare parts requisition, manager approval, warehouse dispatch, and gate pass generation.</p>
        </div>
        <div class="flex items-center gap-2">
            @if(auth()->user()->isEngineer())
                <a href="{{ route('parts.requests.create') }}"
                   class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-xs transition">
                    <i class="fas fa-plus"></i> New Part Request
                </a>
            @endif
            <a href="{{ route('parts.stock.index') }}"
               class="inline-flex items-center gap-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-3.5 py-2 rounded-lg text-xs font-semibold shadow-xs transition">
                <i class="fas fa-boxes-stacked text-sky-500"></i> Stock Ledger
            </a>
        </div>
    </div>

    {{-- Status Filter Tabs (with counts) --}}
    @php
        $currentStatus = request('status', '');
        $statusTabs = [
            ''                    => ['label' => 'All',                  'count' => $statusCounts['all'] ?? 0,                 'color' => 'slate'],
            'pending_stock_check' => ['label' => 'Pending Stock Check',  'count' => $statusCounts['pending_stock_check'] ?? 0, 'color' => 'amber'],
            'pending_approval'    => ['label' => 'Pending Approval',     'count' => $statusCounts['pending_approval'] ?? 0,    'color' => 'purple'],
            'approved'            => ['label' => 'Approved',             'count' => $statusCounts['approved'] ?? 0,            'color' => 'green'],
            'dispatched'          => ['label' => 'Dispatched',           'count' => $statusCounts['dispatched'] ?? 0,          'color' => 'emerald'],
            'rejected'            => ['label' => 'Rejected',             'count' => $statusCounts['rejected'] ?? 0,            'color' => 'rose'],
        ];
    @endphp

    <div class="flex gap-2 flex-wrap mb-3 pb-1">
        @foreach($statusTabs as $stKey => $stData)
            @php
                $isActive = ($currentStatus === $stKey);
                $query = request()->except(['page', 'status']);
                if ($stKey !== '') {
                    $query['status'] = $stKey;
                }
                $tabUrl = route('parts.requests.index', $query);
            @endphp
            <a href="{{ $tabUrl }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition border
                      {{ $isActive
                          ? 'bg-slate-900 border-slate-900 text-white shadow-xs'
                          : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                <span>{{ $stData['label'] }}</span>
                <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] font-bold
                             {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">
                    {{ $stData['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Faulty Core Return Quick Filter Pills --}}
    @php
        $returnTabs = [
            ''               => ['label' => 'All Return Statuses', 'count' => ($returnCounts['pending_return'] ?? 0) + ($returnCounts['returned'] ?? 0) + ($returnCounts['waived'] ?? 0)],
            'pending_return' => ['label' => '⚠️ Return Pending (Not Received)', 'count' => $returnCounts['pending_return'] ?? 0, 'active_class' => 'bg-amber-600 text-white border-amber-600'],
            'returned'       => ['label' => '✅ Returned & Received',             'count' => $returnCounts['returned'] ?? 0,       'active_class' => 'bg-emerald-600 text-white border-emerald-600'],
            'waived'         => ['label' => '➖ Return Waived',                   'count' => $returnCounts['waived'] ?? 0,         'active_class' => 'bg-slate-700 text-white border-slate-700'],
        ];
        $currentReturnStatus = request('faulty_return_status', '');
    @endphp
    <div class="flex items-center gap-2 flex-wrap mb-4 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        <span class="font-bold text-slate-700 flex items-center gap-1.5 mr-1">
            <i class="fas fa-rotate-left text-sky-600"></i> Faulty Core Return:
        </span>
        @foreach($returnTabs as $retKey => $retData)
            @php
                $isRetActive = ($currentReturnStatus === $retKey);
                $retQuery = request()->except(['page', 'faulty_return_status']);
                if ($retKey !== '') {
                    $retQuery['faulty_return_status'] = $retKey;
                }
                $retUrl = route('parts.requests.index', $retQuery);
            @endphp
            <a href="{{ $retUrl }}"
               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold transition border
                      {{ $isRetActive
                          ? ($retData['active_class'] ?? 'bg-slate-900 text-white border-slate-900 shadow-xs')
                          : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>{{ $retData['label'] }}</span>
                <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] font-bold
                             {{ $isRetActive ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">
                    {{ $retData['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Date & Search Filter Form --}}
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4 mb-5">
        <form method="GET" action="{{ route('parts.requests.index') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 text-xs items-end">
            {{-- Retain current status in form --}}
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            {{-- Search Input --}}
            <div class="{{ auth()->user()->isEngineer() ? 'md:col-span-3' : 'md:col-span-3' }}">
                <label class="block font-medium text-slate-700 mb-1">Search Keywords</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="PR #, ticket #, part, serial, courier..."
                           class="w-full border border-slate-300 rounded-lg pl-8 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <i class="fas fa-search absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                </div>
            </div>

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

            {{-- Faulty Core Return Dropdown --}}
            <div class="{{ auth()->user()->isEngineer() ? 'md:col-span-3' : 'md:col-span-2' }}">
                <label class="block font-medium text-slate-700 mb-1">Faulty Core Return</label>
                <select name="faulty_return_status"
                        class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <option value="">All Return Statuses</option>
                    <option value="pending_return" {{ request('faulty_return_status') == 'pending_return' ? 'selected' : '' }}>
                        ⚠️ Return Pending
                    </option>
                    <option value="returned" {{ request('faulty_return_status') == 'returned' ? 'selected' : '' }}>
                        ✅ Returned &amp; Received
                    </option>
                    <option value="waived" {{ request('faulty_return_status') == 'waived' ? 'selected' : '' }}>
                        ➖ Return Waived
                    </option>
                </select>
            </div>

            {{-- Engineer Filter (for office staff & managers) --}}
            @if(!auth()->user()->isEngineer())
                <div class="md:col-span-2">
                    <label class="block font-medium text-slate-700 mb-1">Field Engineer</label>
                    <select name="engineer_id"
                            class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400">
                        <option value="">All Engineers</option>
                        @foreach($engineers as $eng)
                            <option value="{{ $eng->id }}" {{ request('engineer_id') == $eng->id ? 'selected' : '' }}>
                                {{ $eng->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Filter Actions --}}
            <div class="{{ auth()->user()->isEngineer() ? 'md:col-span-2' : 'md:col-span-1' }} flex items-center gap-1.5">
                <button type="submit"
                        class="flex-1 bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-2.5 rounded-lg text-xs transition inline-flex items-center justify-center gap-1 shadow-xs"
                        title="Apply Filters">
                    <i class="fas fa-filter"></i> Apply
                </button>
                @if(request()->hasAny(['search', 'from_date', 'to_date', 'engineer_id', 'status', 'faulty_return_status']))
                    <a href="{{ route('parts.requests.index') }}"
                       class="bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 px-2 rounded-lg text-xs font-semibold transition"
                       title="Reset all filters">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Main Requests Table --}}
    <div class="bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
        @if($requests->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                <i class="fas fa-box-open text-5xl mb-4 text-slate-300"></i>
                <p class="text-sm font-bold text-slate-600">No part requests match your criteria</p>
                <p class="text-xs text-slate-400 mt-1 max-w-sm text-center">
                    Try adjusting your date range, search query, or status tab filter.
                </p>
                <a href="{{ route('parts.requests.index') }}"
                   class="mt-4 inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-rotate-left"></i> Reset Filters
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-[11px] tracking-wider">
                            <th class="text-left px-4 py-3.5">PR &amp; Ticket</th>
                            <th class="text-left px-4 py-3.5">Machine Details</th>
                            @if(!auth()->user()->isEngineer())
                                <th class="text-left px-4 py-3.5">Engineer</th>
                            @endif
                            <th class="text-left px-4 py-3.5">Requested Parts</th>
                            <th class="text-center px-4 py-3.5">Status</th>
                            <th class="text-left px-4 py-3.5">Dispatch Information</th>
                            <th class="text-left px-4 py-3.5">Faulty Core Return</th>
                            <th class="text-left px-4 py-3.5">Submitted</th>
                            <th class="text-center px-4 py-3.5 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($requests as $req)
                            <tr class="hover:bg-slate-50/80 transition">
                                {{-- PR & Ticket --}}
                                <td class="px-4 py-3.5">
                                    <a href="{{ route('parts.requests.show', $req) }}"
                                       class="font-mono font-bold text-sky-600 hover:text-sky-800 hover:underline block">
                                        {{ $req->request_number }}
                                    </a>
                                    @if($req->ticket)
                                        <div class="mt-0.5">
                                            <a href="{{ route('tickets.show', $req->ticket) }}"
                                               class="font-semibold text-slate-700 hover:text-sky-600">
                                                #{{ $req->ticket->ticket_no }}
                                            </a>
                                            <div class="text-[10px] text-slate-400 truncate max-w-[140px]" title="{{ $req->ticket->bank_name }}">
                                                {{ $req->ticket->bank_name }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400 text-[10px]">No linked ticket</span>
                                    @endif
                                </td>

                                {{-- Machine Details --}}
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-slate-800">{{ $req->machineModel?->name ?? '—' }}</div>
                                    @if($req->machine_serial_no)
                                        <div class="font-mono text-slate-500 text-[10px] mt-0.5">S/N: {{ $req->machine_serial_no }}</div>
                                    @endif
                                </td>

                                {{-- Engineer --}}
                                @if(!auth()->user()->isEngineer())
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center text-[10px] font-bold uppercase flex-shrink-0">
                                                {{ substr($req->engineer?->name ?? '?', 0, 1) }}
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-800 block truncate max-w-[120px]">{{ $req->engineer?->name ?? '—' }}</span>
                                                @if($req->engineer?->phone_whatsapp)
                                                    <span class="text-[10px] text-slate-400 font-mono">{{ $req->engineer->phone_whatsapp }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                @endif

                                {{-- Requested Parts --}}
                                <td class="px-4 py-3.5">
                                    <div class="space-y-1 max-w-[200px]">
                                        @foreach($req->items->take(2) as $item)
                                            <div class="truncate text-[11px] text-slate-700">
                                                <span class="font-bold text-slate-900">{{ $item->qty_requested }}x</span>
                                                <span>{{ $item->part?->name ?? 'Part' }}</span>
                                                <span class="font-mono text-slate-400 text-[10px]">({{ $item->part?->part_number ?? '—' }})</span>
                                            </div>
                                        @endforeach
                                        @if($req->items->count() > 2)
                                            <div class="text-[10px] text-slate-400 font-medium">
                                                +{{ $req->items->count() - 2 }} more component(s)
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold
                                                 bg-{{ $req->status_color }}-100 text-{{ $req->status_color }}-700 border border-{{ $req->status_color }}-200">
                                        {{ $req->status_label }}
                                    </span>
                                </td>

                                {{-- Dispatch Information --}}
                                <td class="px-4 py-3.5">
                                    @if($req->status === 'dispatched')
                                        <div class="text-[11px]">
                                            <div class="font-semibold text-emerald-800 flex items-center gap-1">
                                                <i class="fas fa-truck text-[10px]"></i>
                                                <span>{{ $req->dispatch_courier ?? 'Courier' }}</span>
                                            </div>
                                            @if($req->dispatch_tracking_number)
                                                <div class="font-mono text-slate-600 font-bold text-[10px]">
                                                    Trk: {{ $req->dispatch_tracking_number }}
                                                </div>
                                            @endif
                                            <div class="text-slate-400 text-[10px]">
                                                {{ $req->dispatchLocation?->name ?? 'Warehouse' }}
                                                @if($req->dispatched_at)
                                                    • {{ $req->dispatched_at->format('d M') }}
                                                @endif
                                            </div>
                                        </div>
                                    @elseif($req->status === 'approved')
                                        <span class="text-green-600 font-semibold text-[11px]">
                                            <i class="fas fa-box text-[10px]"></i> Awaiting Dispatch
                                        </span>
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                </td>

                                {{-- Faulty Core Return --}}
                                <td class="px-4 py-3.5">
                                    @if($req->status === 'dispatched')
                                        @if($req->faulty_return_status === 'returned')
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                    <i class="fas fa-check-circle text-[9px]"></i> Returned
                                                </span>
                                                @if($req->faultyReturnLocation)
                                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                                        To: {{ $req->faultyReturnLocation->name }}
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif($req->faulty_return_status === 'waived')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                                <i class="fas fa-ban text-[8px]"></i> Waived
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200 animate-pulse" title="Defective part awaiting return from engineer">
                                                <i class="fas fa-clock text-[8px]"></i> Return Pending
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                </td>

                                {{-- Submitted Date --}}
                                <td class="px-4 py-3.5 text-slate-500 whitespace-nowrap">
                                    <div class="font-medium text-slate-700">{{ $req->created_at->format('d M Y') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $req->created_at->format('H:i') }}</div>
                                </td>

                                {{-- Actions (View, Gate Pass, Quick Approve, Edit) --}}
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        {{-- View Button --}}
                                        <a href="{{ route('parts.requests.show', $req) }}"
                                           class="inline-flex items-center gap-1 bg-sky-50 hover:bg-sky-100 text-sky-700 px-2.5 py-1.5 rounded-lg font-bold transition text-xs shadow-2xs"
                                           title="View Full Request Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>

                                        {{-- Gate Pass (PDF) Option --}}
                                        @if(in_array($req->status, ['approved', 'dispatched']))
                                            <a href="{{ route('parts.requests.gate-pass', $req) }}" target="_blank"
                                               class="inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-2.5 py-1.5 rounded-lg font-bold transition text-xs shadow-2xs"
                                               title="Print or Save Gate Pass PDF for Security Gatekeeper">
                                                <i class="fas fa-file-invoice"></i> Gate Pass
                                            </a>
                                        @endif

                                        {{-- Quick Manager Approve Link --}}
                                        @if(auth()->user()->isSuperior() && in_array($req->status, ['pending_stock_check', 'pending_approval']))
                                            <a href="{{ route('parts.requests.show', $req) }}"
                                               class="inline-flex items-center gap-1 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 px-2.5 py-1.5 rounded-lg font-bold transition text-xs"
                                               title="Review & Approve Parts Request">
                                                <i class="fas fa-user-check"></i> Approve
                                            </a>
                                        @endif

                                        {{-- Edit Link --}}
                                        @if($req->isEditable() && (!auth()->user()->isEngineer() || auth()->id() === $req->engineer_id))
                                            <a href="{{ route('parts.requests.edit', $req) }}"
                                               class="inline-flex items-center gap-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-2 py-1.5 rounded-lg font-semibold transition text-xs"
                                               title="Edit Part Request">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($requests->hasPages())
                <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                    {{ $requests->appends(request()->query())->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection
