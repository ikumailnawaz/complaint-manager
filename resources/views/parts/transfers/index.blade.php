@extends('layouts.app')

@section('title', 'Inter-Location Transfers - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Top Header Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-truck-ramp-box text-purple-600"></i>
                <span>Inter-Office Part Transfers</span>
            </h1>
            <p class="text-xs text-slate-500">Track spare components dispatched between regional offices, central warehouse and hubs</p>
        </div>
        @if(auth()->user()->isSuperior())
        <div>
            <a href="{{ route('parts.transfers.create') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-md transition flex items-center space-x-1.5 cursor-pointer">
                <i class="fa-solid fa-plus"></i>
                <span>Initiate Part Transfer</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Filter Form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <form method="GET" action="{{ route('parts.transfers.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <!-- Status Filter -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">Transfer Status</label>
                <select name="status" class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Dispatch</option>
                    <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                    <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received & Verified</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <!-- Location Filter -->
            <div>
                <label class="block font-bold text-slate-700 mb-1">Origin or Destination Office</label>
                <select name="location_id" class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="">All Locations</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Actions -->
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg shadow-sm transition">
                    Filter
                </button>
                <a href="{{ route('parts.transfers.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-lg transition" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Transfers Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Transfer #</th>
                        <th class="py-3 px-3">From (Origin)</th>
                        <th class="py-3 px-3">To (Destination)</th>
                        <th class="py-3 px-3 text-center">Items</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3">Requested By</th>
                        <th class="py-3 px-3">Initiated Date</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transfers as $t)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3 px-4 font-mono font-bold text-sky-600">
                                <a href="{{ route('parts.transfers.show', $t) }}" class="hover:underline">
                                    {{ $t->transfer_number }}
                                </a>
                            </td>
                            <td class="py-3 px-3 font-semibold text-slate-800">
                                <i class="fa-solid fa-arrow-up-from-bracket text-rose-500 mr-1 text-[10px]"></i>
                                {{ $t->fromLocation->name ?? '—' }}
                            </td>
                            <td class="py-3 px-3 font-semibold text-slate-800">
                                <i class="fa-solid fa-arrow-down-to-bracket text-emerald-500 mr-1 text-[10px]"></i>
                                {{ $t->toLocation->name ?? '—' }}
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-slate-700">
                                {{ $t->items->count() }} items ({{ $t->items->sum('qty') }} pcs)
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($t->status === 'received')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Received
                                    </span>
                                @elseif($t->status === 'in_transit')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">
                                        In Transit
                                    </span>
                                @elseif($t->status === 'pending')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">
                                        Pending
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-100 text-slate-600 border border-slate-200">
                                        Cancelled
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                {{ $t->requestedBy->name ?? '—' }}
                            </td>
                            <td class="py-3 px-3 text-slate-500">
                                {{ $t->created_at->format('d M Y') }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('parts.transfers.show', $t) }}" class="inline-flex items-center space-x-1 px-2.5 py-1 bg-slate-100 hover:bg-sky-600 hover:text-white text-slate-700 font-semibold rounded text-xs transition">
                                    <i class="fa-solid fa-eye text-[10px]"></i>
                                    <span>Track</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">
                                <i class="fa-solid fa-truck-ramp-box text-3xl mb-2 text-slate-300"></i>
                                <p>No part transfers found matching current filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
