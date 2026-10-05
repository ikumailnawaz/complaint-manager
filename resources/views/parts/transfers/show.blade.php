@extends('layouts.app')

@section('title', 'Transfer ' . $transfer->transfer_number . ' - Bank Complaint Manager')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('parts.transfers.index') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back to Transfers">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="flex items-center space-x-2">
                    <h1 class="text-xl font-bold font-mono text-slate-900">{{ $transfer->transfer_number }}</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                        {{ $transfer->status === 'received' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : '' }}
                        {{ $transfer->status === 'in_transit' ? 'bg-blue-100 text-blue-800 border border-blue-200 animate-pulse' : '' }}
                        {{ $transfer->status === 'pending' ? 'bg-amber-100 text-amber-800 border border-amber-200' : '' }}
                        {{ $transfer->status === 'cancelled' ? 'bg-slate-100 text-slate-700 border border-slate-200' : '' }}">
                        {{ str_replace('_', ' ', $transfer->status) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500">Initiated by {{ $transfer->requestedBy->name ?? 'Staff' }} on {{ $transfer->created_at->format('d M Y, h:i A') }}</p>
            </div>
        </div>

        <!-- Quick Cancel button if pending -->
        @if($transfer->status === 'pending' && auth()->user()->isAdmin())
            <form action="{{ route('parts.transfers.cancel', $transfer) }}" method="POST" onsubmit="return confirm('Cancel this transfer order?');" class="inline">
                @csrf
                <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold rounded-lg text-xs border border-rose-200 transition">
                    <i class="fa-solid fa-ban mr-1"></i> Cancel Order
                </button>
            </form>
        @endif
    </div>

    <!-- Progress Timeline Tracker -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        @php
            $steps = ['pending' => 'Order Initiated', 'in_transit' => 'Dispatched / In Transit', 'received' => 'Received & Stock Credited'];
            $currentIndex = match($transfer->status) {
                'pending' => 1,
                'in_transit' => 2,
                'received' => 3,
                default => 0,
            };
        @endphp

        <div class="flex items-center justify-between relative max-w-2xl mx-auto">
            <!-- Line Background -->
            <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-slate-100 z-0"></div>
            @if($transfer->status !== 'cancelled')
                <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-emerald-500 z-0 transition-all duration-500"
                    style="width: {{ $currentIndex === 1 ? '0%' : ($currentIndex === 2 ? '50%' : '100%') }};"></div>
            @endif

            <!-- Step 1: Initiated -->
            <div class="relative z-10 text-center">
                <div class="w-10 h-10 rounded-full mx-auto flex items-center justify-center font-bold text-xs shadow-sm
                    {{ $currentIndex >= 1 ? 'bg-emerald-600 text-white ring-4 ring-emerald-50' : 'bg-slate-200 text-slate-500' }}">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div class="text-[11px] font-bold text-slate-800 mt-2">1. Order Placed</div>
                <div class="text-[10px] text-slate-400">{{ $transfer->created_at->format('d M, h:i A') }}</div>
            </div>

            <!-- Step 2: Dispatched -->
            <div class="relative z-10 text-center">
                <div class="w-10 h-10 rounded-full mx-auto flex items-center justify-center font-bold text-xs shadow-sm
                    {{ $currentIndex >= 2 ? 'bg-emerald-600 text-white ring-4 ring-emerald-50' : 'bg-slate-200 text-slate-500' }}">
                    <i class="fa-solid fa-truck"></i>
                </div>
                <div class="text-[11px] font-bold text-slate-800 mt-2">2. Dispatched</div>
                <div class="text-[10px] text-slate-400">
                    {{ $transfer->dispatched_at ? $transfer->dispatched_at->format('d M, h:i A') : 'Awaiting Dispatch' }}
                </div>
            </div>

            <!-- Step 3: Received -->
            <div class="relative z-10 text-center">
                <div class="w-10 h-10 rounded-full mx-auto flex items-center justify-center font-bold text-xs shadow-sm
                    {{ $currentIndex >= 3 ? 'bg-emerald-600 text-white ring-4 ring-emerald-50' : 'bg-slate-200 text-slate-500' }}">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
                <div class="text-[11px] font-bold text-slate-800 mt-2">3. Stock Received</div>
                <div class="text-[10px] text-slate-400">
                    {{ $transfer->received_at ? $transfer->received_at->format('d M, h:i A') : 'Pending Arrival' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Origin & Destination Route Card -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- From -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 border-l-4 border-l-rose-500">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Dispatching Source</div>
            <div class="font-extrabold text-slate-900 text-sm">{{ $transfer->fromLocation->name ?? '—' }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $transfer->fromLocation->city }} &bull; {{ $transfer->fromLocation->address ?? 'Main Office' }}</div>
            @if($transfer->dispatched_at)
                <div class="mt-3 pt-2 border-t border-slate-100 text-[11px] text-slate-600 flex items-center space-x-1">
                    <i class="fa-solid fa-circle-check text-emerald-500"></i>
                    <span>Dispatched by <strong class="text-slate-800">{{ $transfer->dispatchedBy->name ?? 'Staff' }}</strong></span>
                </div>
            @endif
        </div>

        <!-- To -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 border-l-4 border-l-emerald-500">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Destination Facility</div>
            <div class="font-extrabold text-slate-900 text-sm">{{ $transfer->toLocation->name ?? '—' }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $transfer->toLocation->city }} &bull; {{ $transfer->toLocation->address ?? 'Regional Facility' }}</div>
            @if($transfer->received_at)
                <div class="mt-3 pt-2 border-t border-slate-100 text-[11px] text-slate-600 flex items-center space-x-1">
                    <i class="fa-solid fa-circle-check text-emerald-500"></i>
                    <span>Received & Accepted by <strong class="text-slate-800">{{ $transfer->receivedBy->name ?? 'Staff' }}</strong></span>
                </div>
            @endif
        </div>
    </div>

    @if($transfer->remarks)
        <div class="p-3 bg-purple-50 rounded-xl text-xs text-purple-900 border border-purple-200">
            <span class="font-bold">Transfer Remarks:</span> {{ $transfer->remarks }}
        </div>
    @endif

    <!-- Items Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Spare Components in Transit</h3>
            <span class="text-xs font-semibold text-slate-500">{{ $transfer->items->count() }} line items</span>
        </div>

        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                <tr>
                    <th class="py-2.5 px-4">Part SKU</th>
                    <th class="py-2.5 px-3">Description</th>
                    <th class="py-2.5 px-3 text-center">Unit</th>
                    <th class="py-2.5 px-3 text-center">Qty Dispatched</th>
                    <th class="py-2.5 px-4 text-center">Qty Received</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($transfer->items as $item)
                    <tr>
                        <td class="py-3 px-4 font-mono font-bold text-sky-700">{{ $item->part->part_number ?? '—' }}</td>
                        <td class="py-3 px-3 font-semibold text-slate-800">{{ $item->part->name ?? '—' }}</td>
                        <td class="py-3 px-3 text-center text-slate-500 uppercase">{{ $item->part->unit ?? 'PCS' }}</td>
                        <td class="py-3 px-3 text-center font-bold text-slate-900 text-sm">{{ $item->qty }}</td>
                        <td class="py-3 px-4 text-center font-bold text-sm {{ $item->qty_received !== null ? 'text-emerald-700' : 'text-slate-400' }}">
                            {{ $item->qty_received !== null ? $item->qty_received : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Action Workflow Cards -->

    <!-- Case A: Pending Dispatch (Source Office Staff / Manager) -->
    @if($transfer->status === 'pending' && auth()->user()->isSuperior())
        <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-6 space-y-3">
            <div class="flex items-center space-x-2 text-blue-700 font-bold text-sm">
                <i class="fa-solid fa-truck"></i>
                <span>Action Required: Dispatch Shipment from {{ $transfer->fromLocation->name }}</span>
            </div>
            <p class="text-xs text-slate-600">
                Clicking "Dispatch Shipment" will automatically debit the inventory quantities from <strong class="text-slate-800">{{ $transfer->fromLocation->name }}</strong> stock ledger and mark this transfer as "In Transit".
            </p>
            <form action="{{ route('parts.transfers.dispatch', $transfer) }}" method="POST" onsubmit="return confirm('Confirm dispatch of this transfer order?\n\nStock will be subtracted from {{ $transfer->fromLocation->name }}.');">
                @csrf
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs shadow-md transition flex items-center space-x-2 cursor-pointer">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Confirm Dispatch & Deduct Source Inventory</span>
                </button>
            </form>
        </div>
    @endif

    <!-- Case B: In Transit (Destination Office Staff / Manager to Receive) -->
    @if($transfer->status === 'in_transit' && auth()->user()->isSuperior())
        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-6 space-y-4">
            <div class="flex items-center space-x-2 text-emerald-800 font-bold text-sm">
                <i class="fa-solid fa-boxes-packing"></i>
                <span>Action Required: Confirm Physical Receipt at {{ $transfer->toLocation->name }}</span>
            </div>
            <p class="text-xs text-slate-600">
                Verify the delivered spare components. Enter the actual counted quantities received, then confirm to credit <strong class="text-slate-800">{{ $transfer->toLocation->name }}</strong> inventory ledger.
            </p>

            <form action="{{ route('parts.transfers.receive', $transfer) }}" method="POST" class="space-y-4">
                @csrf
                <div class="border rounded-xl border-slate-200 overflow-hidden">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">Component</th>
                                <th class="py-2.5 px-3 text-center">Dispatched Qty</th>
                                <th class="py-2.5 px-3 text-center">Actual Received Qty <span class="text-rose-500">*</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($transfer->items as $item)
                                <tr>
                                    <td class="py-2 px-3 font-semibold text-slate-800">
                                        {{ $item->part->name }} <span class="font-mono text-slate-400 text-[10px]">({{ $item->part->part_number }})</span>
                                    </td>
                                    <td class="py-2 px-3 text-center font-bold text-slate-700">{{ $item->qty }}</td>
                                    <td class="py-2 px-3 text-center">
                                        <input type="number" min="0" name="received_qtys[{{ $item->id }}]" value="{{ $item->qty }}" required
                                            class="w-24 text-center text-xs font-bold rounded border-slate-300 py-1.5 focus:ring-emerald-500 focus:border-emerald-500">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-md transition flex items-center space-x-2 cursor-pointer">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Confirm Receipt & Credit Destination Stock</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
