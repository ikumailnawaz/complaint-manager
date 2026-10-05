@extends('layouts.app')

@section('title', 'GRN ' . $grn->grn_number . ' - Bank Complaint Manager')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Ribbon & Action Bar (Hidden on Print) -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('parts.grn.index') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back to GRN List">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="flex items-center space-x-2">
                    <h1 class="text-xl font-bold font-mono text-slate-900">{{ $grn->grn_number }}</h1>
                    @if($grn->status === 'confirmed')
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <i class="fa-solid fa-check-circle mr-1"></i> Stock Posted & Confirmed
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200 animate-pulse">
                            <i class="fa-solid fa-clock mr-1"></i> Draft Requisition
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500">Received at {{ $grn->location->name ?? '—' }} &bull; {{ $grn->received_at ? $grn->received_at->format('d M Y') : 'N/A' }}</p>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <!-- Print Button -->
            <button onclick="window.print()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-xs transition flex items-center space-x-1.5 cursor-pointer">
                <i class="fa-solid fa-print"></i>
                <span>Print Official Note</span>
            </button>

            <!-- Confirm GRN Button (If Draft) -->
            @if($grn->status === 'draft' && auth()->user()->isSuperior())
                <form action="{{ route('parts.grn.confirm', $grn) }}" method="POST" onsubmit="return confirm('Confirm this GRN?\n\nThis will permanently post all {{ $grn->total_items }} item quantities directly into {{ $grn->location->name }} stock ledger.');" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-md transition flex items-center space-x-1.5 cursor-pointer active:scale-95">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Confirm & Update Stock</span>
                    </button>
                </form>

                <!-- Delete Draft -->
                @if(auth()->user()->isAdmin())
                <form action="{{ route('parts.grn.destroy', $grn) }}" method="POST" onsubmit="return confirm('Permanently discard this draft GRN?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-2 text-rose-500 hover:text-rose-700 rounded-lg hover:bg-rose-50 transition" title="Delete Draft">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </form>
                @endif
            @endif
        </div>
    </div>

    <!-- Official Printable Goods Received Note Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 space-y-6 print:border-none print:shadow-none print:p-0">

        <!-- Document Header (Print Visible) -->
        <div class="border-b border-slate-200 pb-6 flex items-start justify-between">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="bg-sky-600 text-white p-2 rounded-lg inline-flex items-center justify-center">
                        <i class="fa-solid fa-building-columns text-base"></i>
                    </span>
                    <div>
                        <div class="font-extrabold text-base text-slate-900 tracking-wide uppercase">Bank Complaint Manager</div>
                        <div class="text-[10px] text-sky-600 font-semibold tracking-wider uppercase">Field Operations & Spare Parts ERP</div>
                    </div>
                </div>
                <div class="text-xs text-slate-500 mt-2">
                    Official Goods Received & Inspection Certificate
                </div>
            </div>

            <div class="text-right">
                <div class="text-2xl font-black font-mono text-slate-900 tracking-tight">{{ $grn->grn_number }}</div>
                <div class="text-xs text-slate-500 mt-1">
                    Date: <span class="font-semibold text-slate-800">{{ $grn->received_at ? $grn->received_at->format('d M Y') : now()->format('d M Y') }}</span>
                </div>
                <div class="text-xs mt-0.5">
                    Status: <span class="font-bold uppercase {{ $grn->status === 'confirmed' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $grn->status }}</span>
                </div>
            </div>
        </div>

        <!-- 2 Column Metadata Grid -->
        <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl text-xs border border-slate-100 print:bg-white print:border-slate-200">
            <div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Receiving Facility</div>
                <div class="font-bold text-slate-900 text-sm">{{ $grn->location->name ?? '—' }}</div>
                <div class="text-slate-600 mt-0.5">{{ $grn->location->address ?? $grn->location->city }}</div>
            </div>

            <div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Supplier / Procurement Reference</div>
                <div class="font-bold text-slate-900 text-sm">{{ $grn->supplier_name ?? 'Local Vendor / Direct' }}</div>
                <div class="text-slate-600 mt-0.5 font-mono">
                    @if($grn->invoice_number)
                        Invoice #: <span class="font-bold text-slate-800">{{ $grn->invoice_number }}</span>
                        @if($grn->invoice_date) ({{ $grn->invoice_date->format('d M Y') }}) @endif
                    @else
                        No invoice number attached
                    @endif
                </div>
            </div>
        </div>

        @if($grn->remarks)
            <div class="p-3 bg-amber-50 rounded-lg text-xs text-amber-900 border border-amber-200">
                <span class="font-bold">Shipment Notes:</span> {{ $grn->remarks }}
            </div>
        @endif

        <!-- Received Line Items Table -->
        <div class="space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Received Items Inventory List</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse border border-slate-200">
                    <thead class="bg-slate-100 text-slate-800 font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3 border-r border-slate-200 text-center w-12">#</th>
                            <th class="py-2.5 px-3 border-r border-slate-200">Part SKU / Number</th>
                            <th class="py-2.5 px-3 border-r border-slate-200">Component Description</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 text-center">Unit</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 text-center">Qty Received</th>
                            <th class="py-2.5 px-3 border-r border-slate-200 text-right">Unit Price (PKR)</th>
                            <th class="py-2.5 px-3 text-right">Subtotal (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($grn->items as $index => $item)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-2 px-3 border-r border-slate-200 text-center text-slate-500 font-mono">{{ $index + 1 }}</td>
                                <td class="py-2 px-3 border-r border-slate-200 font-mono font-bold text-sky-700">{{ $item->part->part_number ?? '—' }}</td>
                                <td class="py-2 px-3 border-r border-slate-200">
                                    <div class="font-semibold text-slate-800">{{ $item->part->name ?? '—' }}</div>
                                    <div class="text-[10px] text-slate-400 capitalize">Condition: {{ $item->condition }}</div>
                                </td>
                                <td class="py-2 px-3 border-r border-slate-200 text-center text-slate-600 uppercase">{{ $item->part->unit ?? 'PCS' }}</td>
                                <td class="py-2 px-3 border-r border-slate-200 text-center font-bold text-slate-900 text-sm">{{ $item->qty_received }}</td>
                                <td class="py-2 px-3 border-r border-slate-200 text-right font-mono text-slate-700">{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">{{ number_format($item->total_cost, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-bold border-t-2 border-slate-300">
                        <tr>
                            <td colspan="4" class="py-3 px-4 text-right uppercase text-slate-600">Grand Total Received Value:</td>
                            <td class="py-3 px-3 text-center text-sm font-black text-slate-900">{{ $grn->total_items }}</td>
                            <td class="py-3 px-3 border-r border-slate-200"></td>
                            <td class="py-3 px-3 text-right text-sm font-black text-slate-900 font-mono">
                                PKR {{ number_format($grn->total_value, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Signatures & Verification Block (Print Ready) -->
        <div class="pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs">
            <div>
                <div class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-6">Received By (Store Keeper / Admin)</div>
                <div class="border-b border-slate-300 pb-1 font-semibold text-slate-800">
                    {{ $grn->createdBy->name ?? 'Store In-Charge' }}
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                    Signature & Date: {{ $grn->created_at ? $grn->created_at->format('d M Y, h:i A') : '' }}
                </div>
            </div>

            <div>
                <div class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-6">Confirmed & Inspected By (Manager / Auditor)</div>
                <div class="border-b border-slate-300 pb-1 font-semibold text-slate-800">
                    {{ $grn->confirmedBy->name ?? 'Pending Manager Signature' }}
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                    Signature & Date: {{ $grn->confirmed_at ? $grn->confirmed_at->format('d M Y, h:i A') : 'Pending Confirmation' }}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    header, nav, footer, .no-print {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
    }
}
</style>
@endsection
