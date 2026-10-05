<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass - {{ $partRequest->request_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .gatepass-container {
                box-shadow: none !important;
                border: 2px solid #000000 !important;
                margin: 0 !important;
                padding: 18px !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased min-h-screen py-8">

    {{-- Non-printable Top Actions Bar --}}
    <div class="no-print max-w-4xl mx-auto mb-5 px-4 flex items-center justify-between">
        <a href="{{ route('parts.requests.show', $partRequest) }}"
           class="inline-flex items-center gap-2 text-xs font-semibold bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-3.5 py-2 rounded-lg shadow-xs transition">
            <i class="fas fa-arrow-left"></i> Back to Part Request
        </a>
        <div class="flex items-center gap-2">
            <button onclick="window.print()"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </div>
    </div>

    {{-- Official Printable Gate Pass Document --}}
    <div class="gatepass-container max-w-4xl mx-auto bg-white rounded-xl shadow-lg border-2 border-slate-300 p-8">

        {{-- Header Section --}}
        <div class="border-b-2 border-slate-800 pb-4 mb-4">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-slate-900 text-white flex items-center justify-center text-xl font-black">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-black uppercase tracking-wider text-slate-900 leading-tight">
                            Bank Complaint &amp; Field Engineering Support
                        </h1>
                        <p class="text-xs font-medium text-slate-600">Central Hardware Inventory &amp; Logistics Department</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-block bg-slate-900 text-white text-xs font-black uppercase px-3 py-1 rounded tracking-widest mb-1">
                        OUTWARD MATERIAL GATE PASS
                    </span>
                    <div class="text-xs text-slate-500">Security &amp; Logistics Copy</div>
                </div>
            </div>
        </div>

        {{-- Meta Badges Bar --}}
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs mb-5">
            <div>
                <span class="text-slate-500 font-semibold block text-[10px] uppercase">Gate Pass No:</span>
                <span class="font-mono font-black text-slate-900 text-sm">GP-{{ $partRequest->request_number }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-semibold block text-[10px] uppercase">Date &amp; Time:</span>
                <span class="font-bold text-slate-800">{{ now()->format('d M Y, H:i') }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-semibold block text-[10px] uppercase">Request Ref:</span>
                <span class="font-mono font-bold text-sky-700">{{ $partRequest->request_number }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-semibold block text-[10px] uppercase">Complaint Ticket:</span>
                <span class="font-mono font-bold text-slate-800">
                    {{ $partRequest->ticket ? '#' . $partRequest->ticket->ticket_no : '—' }}
                </span>
            </div>
        </div>

        {{-- Logistics & Destination Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs mb-5">
            {{-- Source --}}
            <div class="border border-slate-200 rounded-lg p-3.5 bg-slate-50/50">
                <div class="font-black text-slate-800 border-b border-slate-200 pb-1.5 mb-2 uppercase tracking-wide flex items-center gap-1.5">
                    <i class="fas fa-warehouse text-slate-600"></i> Dispatch Origin (Source)
                </div>
                <dl class="space-y-1.5">
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">Warehouse / Office:</dt>
                        <dd class="font-bold text-slate-800">{{ $partRequest->dispatchLocation?->name ?? 'Lahore Head Office' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">City / Location:</dt>
                        <dd class="text-slate-700">{{ $partRequest->dispatchLocation?->city ?? 'Central Hub' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">Dispatched By:</dt>
                        <dd class="font-medium text-slate-800">{{ $partRequest->dispatchedBy?->name ?? auth()->user()->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">Approval Authority:</dt>
                        <dd class="font-bold text-purple-800">{{ $partRequest->approvedBy?->name ?? 'Operations Manager' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Destination & Bearer --}}
            <div class="border border-slate-200 rounded-lg p-3.5 bg-slate-50/50">
                <div class="font-black text-slate-800 border-b border-slate-200 pb-1.5 mb-2 uppercase tracking-wide flex items-center gap-1.5">
                    <i class="fas fa-location-dot text-slate-600"></i> Destination &amp; Carrier Bearer
                </div>
                <dl class="space-y-1.5">
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">Bank &amp; Branch:</dt>
                        <dd class="font-bold text-slate-800">
                            {{ $partRequest->ticket?->bank_name ?? 'Client Bank' }}
                            @if($partRequest->ticket?->branch_name)
                                - {{ $partRequest->ticket->branch_name }}
                            @elseif($partRequest->ticket?->branch_location)
                                ({{ $partRequest->ticket->branch_location }})
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">Machine Model / S/N:</dt>
                        <dd class="font-mono text-slate-800">
                            {{ $partRequest->machineModel?->name ?? '—' }}
                            @if($partRequest->machine_serial_no)
                                (S/N: {{ $partRequest->machine_serial_no }})
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">Assigned Engineer:</dt>
                        <dd class="font-bold text-slate-800">
                            {{ $partRequest->engineer?->name ?? 'Ali Khan' }}
                            @if($partRequest->engineer?->phone_whatsapp)
                                <span class="text-slate-500 font-normal">({{ $partRequest->engineer->phone_whatsapp }})</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500 font-medium">Courier / Transport:</dt>
                        <dd class="font-bold text-slate-900">
                            {{ $partRequest->dispatch_courier ?? 'By Hand / Courier' }}
                            @if($partRequest->dispatch_tracking_number)
                                <span class="font-mono text-emerald-700">({{ $partRequest->dispatch_tracking_number }})</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Material Items Table --}}
        <div class="border border-slate-300 rounded-lg overflow-hidden mb-5">
            <div class="bg-slate-800 text-white px-4 py-2 flex items-center justify-between text-xs font-bold uppercase tracking-wider">
                <span>Authorized Spare Parts &amp; Components</span>
                <span>Total Items: {{ $partRequest->items->count() }}</span>
            </div>
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-300 text-slate-700 font-bold">
                        <th class="py-2 px-3 text-center w-12">#</th>
                        <th class="py-2 px-3 text-left font-mono">Part Code</th>
                        <th class="py-2 px-3 text-left">Item Description</th>
                        <th class="py-2 px-3 text-center w-16">Unit</th>
                        <th class="py-2 px-3 text-center w-20">Req Qty</th>
                        <th class="py-2 px-3 text-center w-24">Outward Qty</th>
                        <th class="py-2 px-3 text-left">Remarks / Purpose</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($partRequest->items as $index => $item)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2 px-3 text-center text-slate-500 font-medium">{{ $index + 1 }}</td>
                            <td class="py-2 px-3 font-mono font-bold text-slate-900">{{ $item->part?->part_number ?? '—' }}</td>
                            <td class="py-2 px-3 font-medium text-slate-800">{{ $item->part?->name ?? '—' }}</td>
                            <td class="py-2 px-3 text-center text-slate-600">{{ $item->part?->unit ?? 'PCS' }}</td>
                            <td class="py-2 px-3 text-center font-medium text-slate-600">{{ $item->qty_requested }}</td>
                            <td class="py-2 px-3 text-center font-bold text-slate-900 bg-slate-50">
                                {{ $item->qty_dispatched ?? ($item->qty_approved ?? $item->qty_requested) }}
                            </td>
                            <td class="py-2 px-3 text-slate-500 text-[11px]">{{ $item->note ?? 'Replacement for ATM Maintenance' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-slate-400">No items listed.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Security Notice & Core Return Instructions --}}
        <div class="bg-amber-50/70 border border-amber-300 rounded-lg p-3 text-xs text-amber-950 mb-6">
            <div class="font-bold flex items-center gap-1.5 mb-0.5">
                <i class="fas fa-shield-halved text-amber-600"></i> Notice to Security / Gatekeeper:
            </div>
            <p class="leading-relaxed text-[11px] text-amber-900">
                1. Inspect physical carton count and serial tags against this Gate Pass before allowing material exit.<br>
                2. <strong>Defective Core Return:</strong> The field engineer is strictly required to return all replaced defective components back to warehouse custody for warranty/scrap verification.
            </p>
        </div>

        {{-- Signatures Matrix --}}
        <div class="grid grid-cols-4 gap-3 text-center text-xs pt-4 border-t-2 border-slate-800">
            <div>
                <div class="h-14 flex items-end justify-center pb-1 text-slate-400 italic text-[11px]">
                    {{ $partRequest->engineer?->name }}
                </div>
                <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 uppercase text-[10px]">
                    Requested By (Engineer)
                </div>
                <div class="text-[9px] text-slate-500 mt-0.5">Date: {{ $partRequest->created_at->format('d/m/Y') }}</div>
            </div>

            <div>
                <div class="h-14 flex items-end justify-center pb-1 text-purple-900 font-bold text-[11px]">
                    {{ $partRequest->approvedBy?->name ?? 'Operations Manager' }}
                </div>
                <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 uppercase text-[10px]">
                    Authorized By (Manager)
                </div>
                <div class="text-[9px] text-slate-500 mt-0.5">Approval Date: {{ $partRequest->approved_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
            </div>

            <div>
                <div class="h-14 flex items-end justify-center pb-1 text-slate-800 font-semibold text-[11px]">
                    {{ $partRequest->dispatchedBy?->name ?? 'Store Incharge' }}
                </div>
                <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 uppercase text-[10px]">
                    Issued By (Store Incharge)
                </div>
                <div class="text-[9px] text-slate-500 mt-0.5">Outward Verification</div>
            </div>

            <div>
                <div class="h-14 flex items-end justify-center pb-1 border-dashed border-b border-slate-300 text-[10px] text-slate-400">
                    Sign &amp; Stamp Here
                </div>
                <div class="border-t border-slate-400 pt-1 font-bold text-slate-800 uppercase text-[10px]">
                    Gate Security Clearance
                </div>
                <div class="text-[9px] text-slate-500 mt-0.5">Time Out: ___________</div>
            </div>
        </div>

        {{-- Footer System Watermark --}}
        <div class="mt-6 pt-2 border-t border-slate-200 text-center text-[9px] text-slate-400 flex items-center justify-between">
            <span>Generated by Bank Complaint Manager Logistics Engine</span>
            <span>Security Document Reference: GP-{{ $partRequest->request_number }}</span>
            <span>{{ now()->format('Y-m-d H:i:s') }}</span>
        </div>

    </div>

    {{-- Auto Print Script if print=1 in query string --}}
    @if(request()->query('print') == 1)
        <script>
            window.addEventListener('load', function() {
                window.print();
            });
        </script>
    @endif

</body>
</html>
