@extends('layouts.app')

@section('title', 'Goods Received Notes (GRN) - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Top Header Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-blue-600"></i>
                <span>Goods Received Notes (GRN)</span>
            </h1>
            <p class="text-xs text-slate-500">Official intake records for spare parts inventory entering office hubs</p>
        </div>
        @if(auth()->user()->isSuperior())
        <div>
            <a href="{{ route('parts.grn.create') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                <i class="fa-solid fa-plus"></i>
                <span>Create New GRN</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Filter Form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <form method="GET" action="{{ route('parts.grn.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <!-- Search -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">Search</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="GRN # or Supplier..."
                        class="w-full pl-8 text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">Status</label>
                <select name="status" class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft (Not Confirmed)</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed (Stock Added)</option>
                </select>
            </div>

            <!-- Location Filter -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">Receiving Location</label>
                <select name="location_id" class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="">All Locations</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Buttons -->
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg shadow-sm transition">
                    Filter
                </button>
                <a href="{{ route('parts.grn.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-lg transition" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- GRN Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">GRN #</th>
                        <th class="py-3 px-3">Received Date</th>
                        <th class="py-3 px-3">Location</th>
                        <th class="py-3 px-3">Supplier / Vendor</th>
                        <th class="py-3 px-3 text-center">Items</th>
                        <th class="py-3 px-3 text-right">Total Cost (PKR)</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3">Entered By</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($grns as $grn)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3 px-4 font-mono font-bold text-sky-600">
                                <a href="{{ route('parts.grn.show', $grn) }}" class="hover:underline">
                                    {{ $grn->grn_number }}
                                </a>
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                {{ $grn->received_at ? $grn->received_at->format('d M Y') : 'N/A' }}
                            </td>
                            <td class="py-3 px-3 font-medium text-slate-800">
                                {{ $grn->location->name ?? '—' }}
                            </td>
                            <td class="py-3 px-3 text-slate-700">
                                <div class="font-semibold">{{ $grn->supplier_name ?? 'Direct Procurement' }}</div>
                                @if($grn->invoice_number)
                                    <div class="text-[10px] text-slate-400 font-mono">Inv: {{ $grn->invoice_number }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-slate-700">
                                {{ $grn->total_items }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900">
                                PKR {{ number_format($grn->total_value, 2) }}
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($grn->status === 'confirmed')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <i class="fa-solid fa-check-circle mr-0.5"></i> Confirmed
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">
                                        <i class="fa-solid fa-clock mr-0.5"></i> Draft
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-slate-500">
                                {{ $grn->createdBy->name ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('parts.grn.show', $grn) }}" class="inline-flex items-center space-x-1 px-2.5 py-1 bg-slate-100 hover:bg-sky-600 hover:text-white text-slate-700 font-semibold rounded text-xs transition">
                                    <i class="fa-solid fa-eye text-[10px]"></i>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400">
                                <i class="fa-solid fa-file-invoice text-3xl mb-2 text-slate-300"></i>
                                <p>No Goods Received Notes found matching current filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($grns->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $grns->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
