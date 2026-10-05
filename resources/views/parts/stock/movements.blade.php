@extends('layouts.app')
@section('title', 'Stock Movement History')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 flex items-center gap-2">
            <i class="fas fa-check-circle text-emerald-500"></i>
            <span class="text-xs font-medium">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Stock Movement History</h1>
            <p class="text-xs text-slate-500 mt-0.5">Full audit trail of all inventory movements</p>
        </div>
        <a href="{{ route('parts.stock.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-xs font-semibold rounded-lg transition">
            <i class="fas fa-arrow-left"></i> Back to Stock Levels
        </a>
    </div>

    {{-- Filter Form --}}
    <div class="bg-white rounded-xl shadow border border-slate-200 p-4 mb-5">
        <form method="GET" action="{{ route('parts.stock.movements') }}" class="flex flex-wrap gap-3 items-end">

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-slate-500">Part</label>
                <select name="part_id"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none min-w-[160px]">
                    <option value="">All Parts</option>
                    @foreach($parts as $p)
                        <option value="{{ $p->id }}" {{ request('part_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->part_number }} — {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-slate-500">Location</label>
                <select name="location_id"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none min-w-[140px]">
                    <option value="">All Locations</option>
                    @foreach($locations as $l)
                        <option value="{{ $l->id }}" {{ request('location_id') == $l->id ? 'selected' : '' }}>
                            {{ $l->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-slate-500">Movement Type</label>
                <select name="type"
                        class="border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none min-w-[140px]">
                    <option value="">All Types</option>
                    <option value="grn_in"       {{ request('type') === 'grn_in'       ? 'selected' : '' }}>GRN In</option>
                    <option value="dispatch_out" {{ request('type') === 'dispatch_out' ? 'selected' : '' }}>Dispatch Out</option>
                    <option value="transfer_in"  {{ request('type') === 'transfer_in'  ? 'selected' : '' }}>Transfer In</option>
                    <option value="transfer_out" {{ request('type') === 'transfer_out' ? 'selected' : '' }}>Transfer Out</option>
                    <option value="adjustment"   {{ request('type') === 'adjustment'   ? 'selected' : '' }}>Adjustment</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-slate-500">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}"
                       class="border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-slate-500">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}"
                       class="border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="{{ route('parts.stock.movements') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Movements Table --}}
    <div class="bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-exchange-alt text-slate-400 text-xs"></i>
                <h2 class="text-sm font-semibold text-slate-700">Movement Records</h2>
            </div>
            @if($movements->total() > 0)
                <span class="text-xs text-slate-400">{{ $movements->total() }} records found</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">Date / Time</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">Part</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Location</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Reference</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Qty</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">After Qty</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">Done By</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $m)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap">
                                {{ $m->created_at->format('d M Y') }}<br>
                                <span class="text-slate-400">{{ $m->created_at->format('H:i') }}</span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="font-mono font-semibold text-slate-700">{{ $m->part->part_number ?? '—' }}</span>
                                <br><span class="text-slate-500">{{ $m->part->name ?? '' }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">
                                {{ $m->location->name ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @php
                                    $typeBadge = match($m->type) {
                                        'grn_in'       => ['bg-emerald-100 text-emerald-700', 'GRN In'],
                                        'dispatch_out' => ['bg-rose-100 text-rose-700',       'Dispatch Out'],
                                        'transfer_in'  => ['bg-blue-100 text-blue-700',       'Transfer In'],
                                        'transfer_out' => ['bg-amber-100 text-amber-700',     'Transfer Out'],
                                        'adjustment'   => ['bg-slate-100 text-slate-700',     'Adjustment'],
                                        default        => ['bg-slate-100 text-slate-500',     ucfirst(str_replace('_',' ',$m->type))],
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $typeBadge[0] }}">
                                    {{ $typeBadge[1] }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500 font-mono">{{ $m->reference ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-center font-semibold whitespace-nowrap">
                                @if($m->qty >= 0)
                                    <span class="text-emerald-600">+{{ $m->qty }}</span>
                                @else
                                    <span class="text-rose-600">{{ $m->qty }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center text-slate-600 font-semibold">{{ $m->qty_after ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ $m->user->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500 max-w-[200px] truncate" title="{{ $m->note }}">
                                {{ $m->note ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-10 text-slate-400">
                                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                No stock movements found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movements->hasPages())
            <div class="px-4 py-4 border-t border-slate-100">
                {{ $movements->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
