@extends('layouts.app')

@section('title', 'Part Request ' . $partRequest->request_number)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    {{-- Back Link --}}
    <a href="{{ route('parts.requests.index') }}"
       class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-sky-600 mb-4 transition">
        <i class="fas fa-arrow-left"></i> Back to Part Requests
    </a>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-xs">
            <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-xs">
            <i class="fas fa-exclamation-circle text-rose-500"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex items-start justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $partRequest->request_number }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">Part request submitted on {{ $partRequest->created_at->format('d M Y \a\t H:i') }}</p>
        </div>
        @php
            $statusColors = [
                'pending_stock_check' => ['bg' => 'amber',   'label' => 'Pending Stock Check'],
                'stock_verified'      => ['bg' => 'blue',    'label' => 'Stock Verified'],
                'pending_approval'    => ['bg' => 'purple',  'label' => 'Pending Approval'],
                'approved'            => ['bg' => 'green',   'label' => 'Approved'],
                'dispatched'          => ['bg' => 'emerald', 'label' => 'Dispatched'],
                'rejected'            => ['bg' => 'rose',    'label' => 'Rejected'],
            ];
            $sc = $statusColors[$partRequest->status] ?? ['bg' => 'slate', 'label' => ucfirst($partRequest->status)];
        @endphp
        <div class="flex items-center gap-2">
            @if(in_array($partRequest->status, ['approved', 'dispatched']))
                <a href="{{ route('parts.requests.gate-pass', $partRequest) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
                   title="View and Print Outward Material Gate Pass for Security">
                    <i class="fas fa-file-invoice"></i> Gate Pass (PDF)
                </a>
            @endif
            @if($partRequest->isEditable() && (!auth()->user()->isEngineer() || auth()->id() === $partRequest->engineer_id))
                <a href="{{ route('parts.requests.edit', $partRequest) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-2xs transition">
                    <i class="fas fa-edit"></i> Edit Request
                </a>
            @endif
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold
                         bg-{{ $sc['bg'] }}-100 text-{{ $sc['bg'] }}-700">
                {{ $sc['label'] }}
            </span>
        </div>
    </div>

    {{-- Status Timeline --}}
    @php
        $steps = [
            ['key' => 'pending_stock_check', 'label' => 'Submitted',        'icon' => 'fa-paper-plane'],
            ['key' => 'stock_verified',      'label' => 'Stock Verified',   'icon' => 'fa-boxes-stacked'],
            ['key' => 'pending_approval',    'label' => 'Pending Approval', 'icon' => 'fa-user-check'],
            ['key' => 'approved',            'label' => 'Approved',         'icon' => 'fa-check-circle'],
            ['key' => 'dispatched',          'label' => 'Dispatched',       'icon' => 'fa-truck'],
        ];
        $statusOrder = [
            'pending_stock_check' => 0,
            'stock_verified'      => 1,
            'pending_approval'    => 2,
            'approved'            => 3,
            'dispatched'          => 4,
        ];
        $currentOrder = $statusOrder[$partRequest->status] ?? 0;
        $isRejected   = $partRequest->status === 'rejected';
    @endphp

    <div class="flex items-center gap-2 bg-white rounded-xl shadow border border-slate-200 p-4 mb-6">
        @foreach($steps as $i => $step)
            <div class="flex flex-col items-center text-center min-w-[60px]">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm transition
                    {{ $isRejected && $step['key'] === 'dispatched'
                        ? 'bg-rose-100 text-rose-400'
                        : ($i <= $currentOrder && !$isRejected
                            ? 'bg-emerald-500 text-white shadow-sm'
                            : 'bg-slate-100 text-slate-400') }}">
                    <i class="fas {{ $step['icon'] }}"></i>
                </div>
                <div class="text-[10px] mt-1 {{ $i <= $currentOrder && !$isRejected ? 'text-emerald-600 font-medium' : 'text-slate-400' }}">
                    {{ $step['label'] }}
                </div>
            </div>
            @if(!$loop->last)
                <div class="flex-1 h-px {{ $i < $currentOrder && !$isRejected ? 'bg-emerald-400' : 'bg-slate-200' }}"></div>
            @endif
        @endforeach

        @if($isRejected)
            <div class="flex-1 h-px bg-rose-200"></div>
            <div class="flex flex-col items-center min-w-[60px]">
                <div class="w-9 h-9 rounded-full bg-rose-100 text-rose-500 flex items-center justify-center">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="text-[10px] mt-1 text-rose-500 font-medium">Rejected</div>
            </div>
        @endif
    </div>

    {{-- Details Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
        {{-- Left: Request Details --}}
        <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="font-bold text-slate-700 text-sm mb-4 flex items-center gap-2">
                <i class="fas fa-info-circle text-sky-500"></i> Request Details
            </h3>
            <dl class="space-y-3 text-xs">
                <div class="flex justify-between">
                    <dt class="text-slate-500 font-medium">PR No.</dt>
                    <dd class="font-bold text-slate-800">{{ $partRequest->request_number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 font-medium">Related Ticket</dt>
                    <dd>
                        @if($partRequest->ticket)
                            <a href="{{ route('tickets.show', $partRequest->ticket) }}"
                               class="text-sky-600 hover:underline font-medium">
                                #{{ $partRequest->ticket->ticket_no }}
                            </a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 font-medium">Machine Model</dt>
                    <dd class="font-medium text-slate-700">{{ $partRequest->machineModel?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 font-medium">Serial No.</dt>
                    <dd class="font-mono text-slate-600">{{ $partRequest->machine_serial_no ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 font-medium">Engineer</dt>
                    <dd class="font-medium text-slate-700">{{ $partRequest->engineer?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 font-medium">Submitted At</dt>
                    <dd class="text-slate-600">{{ $partRequest->created_at->format('d M Y H:i') }}</dd>
                </div>
                @if($partRequest->stock_remarks)
                    <div class="pt-2 border-t border-slate-100">
                        <dt class="text-slate-500 font-medium mb-1">Stock Remarks</dt>
                        <dd class="text-slate-600 bg-slate-50 rounded-lg p-2 leading-relaxed">{{ $partRequest->stock_remarks }}</dd>
                    </div>
                @endif
                @if($partRequest->approval_remarks)
                    <div class="pt-2 border-t border-slate-100">
                        <dt class="text-slate-500 font-medium mb-1">Approval Remarks</dt>
                        <dd class="text-slate-600 bg-slate-50 rounded-lg p-2 leading-relaxed">{{ $partRequest->approval_remarks }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Right: Fault Description + Status --}}
        <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="font-bold text-slate-700 text-sm mb-4 flex items-center gap-2">
                <i class="fas fa-file-alt text-amber-500"></i> Fault Description
            </h3>
            <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 rounded-lg p-3">
                {{ $partRequest->fault_description ?? 'No description provided.' }}
            </p>

            <div class="mt-5">
                <h4 class="font-bold text-slate-700 text-xs mb-2">Current Status</h4>
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold
                             bg-{{ $sc['bg'] }}-100 text-{{ $sc['bg'] }}-700">
                    <i class="fas fa-circle text-{{ $sc['bg'] }}-400 text-[8px] mr-2"></i>
                    {{ $sc['label'] }}
                </span>
            </div>
        </div>
    </div>

    {{-- Parts Table --}}
    <div class="bg-white rounded-xl shadow border border-slate-200 overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                <i class="fas fa-list text-sky-500"></i> Requested Parts
            </h3>
            <span class="text-xs text-slate-400">{{ $partRequest->items->count() }} item(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Part No.</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Part Name</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Unit</th>
                        <th class="text-center px-4 py-3 font-semibold text-amber-800 bg-amber-50/60">From Advance Float</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Warehouse Req.</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Approved</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Dispatched</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($partRequest->items as $item)
                        @php
                            $rowBg = '';
                            if ($partRequest->status === 'dispatched') $rowBg = 'bg-emerald-50';
                            elseif (in_array($partRequest->status, ['approved'])) $rowBg = 'bg-green-50';
                        @endphp
                        <tr class="{{ $rowBg }} hover:bg-slate-50 transition">
                            <td class="px-4 py-3 font-mono text-slate-600">{{ $item->part?->part_number ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $item->part?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center text-slate-500">{{ $item->part?->unit ?? 'pcs' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($item->qty_from_envelope > 0)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-black text-xs bg-amber-100 text-amber-800 border border-amber-300 shadow-2xs">
                                        <i class="fa-solid fa-briefcase text-[9px]"></i> {{ $item->qty_from_envelope }}
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center font-bold text-slate-700">{{ $item->qty_requested }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($item->qty_approved !== null)
                                    <span class="font-bold text-green-700">{{ $item->qty_approved }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($item->qty_dispatched !== null)
                                    <span class="font-bold text-emerald-700">{{ $item->qty_dispatched }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $item->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-400">No items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Fault Video --}}
    @if($partRequest->fault_video_path)
        <div class="bg-slate-50 rounded-xl border border-slate-200 p-5 mb-5 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                    <i class="fas fa-video text-sky-500"></i> Fault Video Evidence
                </h4>
                <div class="flex items-center gap-2">
                    <a href="{{ route('parts.requests.video', $partRequest) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold transition">
                        <i class="fas fa-up-right-from-square"></i> Open Video
                    </a>
                    <a href="{{ route('parts.requests.video.download', $partRequest) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-xs transition">
                        <i class="fas fa-download"></i> Download Video
                    </a>
                </div>
            </div>
            <div class="max-w-2xl bg-black rounded-lg overflow-hidden shadow">
                <video controls playsinline preload="metadata" class="w-full max-h-[420px]">
                    <source src="{{ route('parts.requests.video', $partRequest) }}">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="mt-2 text-[11px] text-slate-500 flex items-center justify-between">
                <span>Stored File: <span class="font-mono text-slate-700">{{ basename($partRequest->fault_video_path) }}</span></span>
                <span class="text-slate-400">Uploaded during stock verification</span>
            </div>
        </div>
    @endif

    {{-- Rejection Notice --}}
    @if($partRequest->status === 'rejected')
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 mb-5">
            <div class="flex items-start gap-3">
                <i class="fas fa-ban text-rose-500 mt-0.5"></i>
                <div>
                    <strong class="text-rose-700 text-sm">Request Rejected</strong>
                    <p class="text-xs text-rose-600 mt-1">
                        By <strong>{{ $partRequest->rejectedBy?->name ?? 'Unknown' }}</strong>
                        on {{ $partRequest->rejected_at?->format('d M Y H:i') ?? '—' }}
                    </p>
                    <p class="text-xs text-rose-700 mt-2 bg-rose-100 rounded-lg px-3 py-2">
                        <strong>Reason:</strong> {{ $partRequest->rejection_reason ?? 'No reason provided.' }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Dispatch Info & Faulty Part Return Tracker --}}
    @if($partRequest->status === 'dispatched')
        <div class="space-y-4 mb-5">
            {{-- Dispatch Card --}}
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 shadow-xs">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <strong class="text-emerald-800 text-sm">Parts Dispatched from Warehouse</strong>
                            <span class="text-xs text-emerald-700 font-semibold">{{ $partRequest->dispatched_at?->format('d M Y H:i') ?? '—' }}</span>
                        </div>
                        <p class="text-xs text-emerald-600 mt-0.5">
                            Handled by <strong>{{ $partRequest->dispatchedBy?->name ?? 'Office Staff' }}</strong>
                        </p>
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 bg-white/70 border border-emerald-100 rounded-lg p-3 text-xs">
                            <div>
                                <span class="text-slate-500 block font-medium">Dispatched Location</span>
                                <span class="text-slate-800 font-semibold">{{ $partRequest->dispatchLocation?->name ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 block font-medium">Courier Service</span>
                                <span class="text-slate-800 font-semibold">{{ $partRequest->dispatch_courier ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 block font-medium">Tracking Number</span>
                                <span class="text-emerald-700 font-mono font-bold">{{ $partRequest->dispatch_tracking_number ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Defective / Faulty Part Reverse Return Status --}}
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3 pb-3 border-b border-slate-100">
                    <div>
                        <h4 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                            <i class="fas fa-boxes-packing text-amber-500"></i> Defective / Faulty Part Reverse Return
                        </h4>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Track return of old/replaced defective parts from the site engineer back to the office/warehouse.
                        </p>
                    </div>
                    <div>
                        @if($partRequest->faulty_return_status === 'returned')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <i class="fas fa-check-circle text-emerald-600"></i> Defective Parts Returned &amp; Verified
                            </span>
                        @elseif($partRequest->faulty_return_status === 'waived')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                <i class="fas fa-ban text-slate-500"></i> Return Waived / Discarded On-Site
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                <i class="fas fa-clock text-amber-600"></i> Pending Return from Engineer
                            </span>
                        @endif
                    </div>
                </div>

                @if($partRequest->faulty_return_status === 'returned')
                    <div class="bg-emerald-50/60 border border-emerald-200 rounded-lg p-3 text-xs grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <span class="text-slate-500 block font-medium">Received By</span>
                            <span class="font-bold text-slate-800">{{ $partRequest->faultyReturnReceivedBy?->name ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block font-medium">Received At</span>
                            <span class="font-bold text-slate-800">{{ $partRequest->faulty_returned_at?->format('d M Y H:i') ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block font-medium">Receiving Location</span>
                            <span class="font-bold text-slate-800">{{ $partRequest->faultyReturnLocation?->name ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block font-medium">Courier / In-Hand Tracking</span>
                            <span class="font-mono text-slate-800">{{ $partRequest->faulty_return_courier_tracking ?? 'In Person' }}</span>
                        </div>
                        @if($partRequest->faulty_return_remarks)
                            <div class="col-span-full pt-2 border-t border-emerald-100 text-slate-700">
                                <span class="text-slate-500 font-medium">Return Remarks:</span> {{ $partRequest->faulty_return_remarks }}
                            </div>
                        @endif
                    </div>
                @elseif($partRequest->faulty_return_status === 'waived')
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs">
                        <p class="text-slate-700">
                            <strong>Return Waived By:</strong> {{ $partRequest->faultyReturnReceivedBy?->name ?? 'Manager' }}
                            on {{ $partRequest->faulty_returned_at?->format('d M Y H:i') ?? '—' }}
                        </p>
                        @if($partRequest->faulty_return_remarks)
                            <p class="mt-1 text-slate-600"><strong>Reason:</strong> {{ $partRequest->faulty_return_remarks }}</p>
                        @endif
                    </div>
                @else
                    <div class="bg-amber-50/70 border border-amber-200 rounded-lg p-3 text-xs mb-3 text-amber-900">
                        <i class="fas fa-triangle-exclamation text-amber-600 mr-1.5"></i>
                        The engineer is expected to send back the replaced defective parts to the office or warehouse. Once received, office staff or management can verify and record the return below.
                    </div>
                @endif

                {{-- Return Verification Action (Staff/Manager) --}}
                @if(auth()->user()->isSuperior() && $partRequest->faulty_return_status !== 'returned')
                    <form method="POST" action="{{ route('parts.requests.verify-faulty-return', $partRequest) }}" class="mt-4 pt-4 border-t border-slate-100">
                        @csrf
                        <div class="text-xs font-bold text-slate-700 mb-2">Record / Verify Return of Defective Part</div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs mb-3">
                            <div>
                                <label class="font-medium text-slate-700 block mb-1">Return Status <span class="text-rose-500">*</span></label>
                                <select name="faulty_return_status" required class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-sky-400">
                                    <option value="returned" {{ old('faulty_return_status', $partRequest->faulty_return_status) === 'returned' ? 'selected' : '' }}>Defective Part Received at Warehouse</option>
                                    <option value="waived" {{ old('faulty_return_status', $partRequest->faulty_return_status) === 'waived' ? 'selected' : '' }}>Waive Return (Discarded on Site)</option>
                                </select>
                            </div>
                            <div>
                                <label class="font-medium text-slate-700 block mb-1">Receiving Warehouse/Office</label>
                                <select name="faulty_return_location_id" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-sky-400">
                                    <option value="">Select receiving location...</option>
                                    @foreach($locations as $loc)
                                        <option value="{{ $loc->id }}" {{ $partRequest->faulty_return_location_id == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="font-medium text-slate-700 block mb-1">Return Courier / Tracking</label>
                                <input type="text" name="faulty_return_courier_tracking" value="{{ old('faulty_return_courier_tracking', $partRequest->faulty_return_courier_tracking) }}"
                                       placeholder="TCS/By Hand/Courier tracking"
                                       class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-sky-400">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-xs font-medium text-slate-700 block mb-1">Verification Remarks / Condition of Old Part</label>
                            <input type="text" name="faulty_return_remarks" value="{{ old('faulty_return_remarks', $partRequest->faulty_return_remarks) }}"
                                   placeholder="e.g. Received burnt motor assembly, verified S/N"
                                   class="w-full text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-sky-400">
                        </div>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-xs transition">
                            <i class="fas fa-check-double"></i> Save Return Verification
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- ACTION CARDS --}}

    {{-- Office Staff: Verify Stock (Stage 1) --}}
    @if((auth()->user()->isOfficeStaff() || auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()) && $partRequest->status === 'pending_stock_check')
        <div class="bg-white rounded-xl shadow border border-amber-200 p-6 mt-4">
            <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-boxes-stacked text-amber-500"></i> Stage 1: Stock Verification &amp; Evidence (Office Staff)
            </h3>
            <form method="POST" action="{{ route('parts.requests.verify-stock', $partRequest) }}" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-700">Fault Video (optional, MP4/MOV/AVI/WEBM/MKV)</label>
                        <input type="file" name="fault_video" accept="video/*"
                                class="mt-1 block w-full text-xs border border-slate-300 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700">Stock Verification Remarks</label>
                        <textarea name="stock_remarks" rows="3"
                                  class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400"
                                  placeholder="Note stock availability across locations, any substitutes..."></textarea>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        <i class="fas fa-check"></i> Verify &amp; Forward to Super Admin
                    </button>
                    <span class="text-xs text-slate-400">Advances the request to Stage 2 Super Admin Approval</span>
                </div>
            </form>
        </div>
    @endif

    {{-- Office Staff Info Notice when awaiting Super Admin Approval --}}
    @if(auth()->user()->isOfficeStaff() && $partRequest->status === 'pending_approval')
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mt-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clock text-lg"></i>
                </div>
                <div>
                    <h4 class="font-bold text-amber-900 text-sm">Stage 1 Verified &mdash; Awaiting Stage 2 Super Admin Approval</h4>
                    <p class="text-xs text-amber-700 mt-0.5">
                        Stock verification was completed by Office Staff. This request is now pending final authorization by the Super Administrator before parts can be dispatched.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Stage 2: Super Admin / Admin Approval / Rejection / Adjust Quantities --}}
    @if((auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()) && in_array($partRequest->status, ['pending_stock_check', 'pending_approval', 'approved']))
        <div class="bg-white rounded-xl shadow border border-purple-200 p-6 mt-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-shield-halved text-purple-600"></i> Stage 2: Super Admin Final Approval &amp; Allocation
                </h3>
                @if($partRequest->status === 'pending_stock_check')
                    <span class="text-[11px] font-semibold text-purple-700 bg-purple-50 px-2.5 py-1 rounded-full border border-purple-200">
                        <i class="fas fa-bolt text-amber-500 mr-1"></i> Direct Fast-Track Super Admin Override
                    </span>
                @elseif($partRequest->status === 'approved')
                    <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                        <i class="fas fa-check-circle text-emerald-500 mr-1"></i> Approved (Modify quantities below if needed)
                    </span>
                @endif
            </div>

            @if($partRequest->status === 'pending_stock_check')
                <div class="mb-4 p-3 bg-purple-50/70 border border-purple-200 rounded-lg text-xs text-purple-900">
                    <i class="fas fa-info-circle text-purple-600 mr-1"></i>
                    <strong>Super Admin Override:</strong> This request is currently awaiting Office Staff stock verification, but as Super Admin you may review and approve it directly.
                </div>
            @endif

            <form method="POST" action="{{ route('parts.requests.approve', $partRequest) }}">
                @csrf
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-purple-50 border-b border-purple-100">
                                <th class="text-left px-3 py-2 font-semibold text-purple-700">Part</th>
                                <th class="text-center px-3 py-2 font-semibold text-purple-700">Part No.</th>
                                <th class="text-center px-3 py-2 font-semibold text-purple-700">Requested</th>
                                <th class="text-center px-3 py-2 font-semibold text-purple-700">Approve Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($partRequest->items as $item)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 font-medium text-slate-700">{{ $item->part?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center font-mono text-slate-500">{{ $item->part?->part_number ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center font-bold text-slate-700">{{ $item->qty_requested }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <input type="number"
                                               name="approved_qtys[{{ $item->id }}]"
                                               value="{{ $item->qty_approved ?? $item->qty_requested }}"
                                               min="0"
                                               class="border border-slate-300 rounded px-2 py-1 w-20 text-xs text-center focus:outline-none focus:ring-1 focus:ring-purple-400">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <textarea name="approval_remarks" rows="2"
                          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs mb-3 focus:outline-none focus:ring-2 focus:ring-purple-400"
                          placeholder="Approval remarks (optional)...">{{ old('approval_remarks', $partRequest->approval_remarks) }}</textarea>
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        <i class="fas fa-check"></i> {{ $partRequest->status === 'approved' ? 'Update Approved Quantities' : 'Approve Request' }}
                    </button>
                    @if($partRequest->status === 'approved')
                        <a href="{{ route('parts.requests.gate-pass', $partRequest) }}" target="_blank"
                           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-xs transition">
                            <i class="fas fa-file-invoice"></i> Open Gate Pass (PDF)
                        </a>
                    @endif
                </div>
            </form>

            @if($partRequest->status !== 'approved')
                <form method="POST" action="{{ route('parts.requests.reject', $partRequest) }}" class="mt-4 pt-4 border-t border-slate-100">
                    @csrf
                    <label class="text-xs font-medium text-slate-700 block mb-1.5">Reject this Request</label>
                    <div class="flex gap-2">
                        <input type="text" name="rejection_reason" required
                               placeholder="Provide a rejection reason..."
                               class="flex-1 border border-slate-300 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-rose-400">
                        <button type="submit"
                                onclick="return confirm('Are you sure you want to reject this request?')"
                                class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    {{-- Office Staff: Dispatch --}}
    @if(auth()->user()->isSuperior() && $partRequest->status === 'approved')
        <div class="bg-white rounded-xl shadow border border-emerald-200 p-6 mt-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-truck text-emerald-500"></i> Dispatch Parts
                </h3>
                <a href="{{ route('parts.requests.gate-pass', $partRequest) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-xs transition">
                    <i class="fas fa-file-invoice"></i> Print Gate Pass (PDF)
                </a>
            </div>
            <p class="text-xs text-slate-500 mb-4">Select the warehouse location to deduct stock and enter shipment courier details.</p>

            {{-- Live Warehouse Stock Availability Breakdown --}}
            <div class="mb-5 bg-slate-50 border border-slate-200 rounded-xl p-4">
                <div class="text-xs font-bold text-slate-700 mb-2.5 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <i class="fas fa-boxes-stacked text-sky-500"></i> Stock Availability by Location for Approved Parts:
                    </span>
                    <a href="{{ route('parts.stock.index') }}" target="_blank" class="text-sky-600 hover:underline font-semibold text-[11px] inline-flex items-center gap-1">
                        <i class="fas fa-arrow-up-right-from-square text-[9px]"></i> Parts Stock Ledger
                    </a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                    @foreach($locations as $loc)
                        @php
                            $allAvailable = true;
                            $stockDetails = [];
                            foreach($partRequest->items as $it) {
                                $reqQty = $it->qty_approved ?? $it->qty_requested;
                                $onHand = isset($itemStocks[$loc->id][$it->part_id]) ? $itemStocks[$loc->id][$it->part_id]->first()->qty_on_hand : 0;
                                $partName = $it->part?->name ?? 'Part #' . $it->part_id;
                                $partCode = $it->part?->part_number ?? '';
                                $stockDetails[] = [
                                    'name' => $partName . ($partCode ? " ({$partCode})" : ""),
                                    'onHand' => $onHand,
                                    'req' => $reqQty,
                                    'ok' => $onHand >= $reqQty,
                                ];
                                if ($onHand < $reqQty) {
                                    $allAvailable = false;
                                }
                            }
                        @endphp
                        <div class="p-3 rounded-lg border text-xs {{ $allAvailable ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950' : 'bg-amber-50/70 border-amber-200 text-amber-950' }}">
                            <div class="flex items-center justify-between font-bold mb-1">
                                <span>{{ $loc->name }}</span>
                                @if($allAvailable)
                                    <span class="text-emerald-700 text-[10px] bg-emerald-100 px-1.5 py-0.5 rounded font-bold">In Stock ✅</span>
                                @else
                                    <span class="text-amber-800 text-[10px] bg-amber-100 px-1.5 py-0.5 rounded font-bold">Auto-Adjusts ⚡</span>
                                @endif
                            </div>
                            <div class="text-[11px] space-y-1 text-slate-600 mt-2">
                                @foreach($stockDetails as $sd)
                                    <div class="flex items-center justify-between">
                                        <span class="truncate max-w-[150px]" title="{{ $sd['name'] }}">{{ $sd['name'] }}:</span>
                                        <span class="font-mono font-bold {{ $sd['ok'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                            {{ $sd['onHand'] }} / {{ $sd['req'] }} pcs
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <form method="POST" action="{{ route('parts.requests.dispatch', $partRequest) }}" id="dispatchForm">
                @csrf

                {{-- Admin Fulfillment Choice: Warehouse vs Employee Envelope vs Partial Split --}}
                <div class="mb-5 bg-white border border-slate-200 rounded-xl p-4 shadow-2xs">
                    <label class="text-xs font-bold text-slate-800 block mb-2 flex items-center gap-2">
                        <i class="fa-solid fa-layer-group text-sky-600"></i> Select Dispatch / Fulfillment Source:
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {{-- Option 1: Warehouse Stock --}}
                        <label id="lbl_dispatch_warehouse" class="flex items-start gap-2.5 p-3 rounded-lg border-2 border-emerald-500 bg-emerald-50/60 cursor-pointer transition">
                            <input type="radio" name="dispatch_source" value="warehouse" checked onchange="toggleDispatchSource('warehouse')" class="accent-emerald-600 mt-1">
                            <div>
                                <div class="font-extrabold text-xs text-slate-800">🏢 Central Warehouse</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">Deduct 100% from warehouse and ship to engineer via courier.</div>
                            </div>
                        </label>

                        {{-- Option 2: Employee Envelope --}}
                        @php
                            $engineerHasFloat = false;
                            foreach($partRequest->items as $it) {
                                $envQty = isset($engineerEnvelopes[$it->part_id]) ? $engineerEnvelopes[$it->part_id]->qty_on_hand : 0;
                                if ($envQty > 0) $engineerHasFloat = true;
                            }
                        @endphp
                        <label id="lbl_dispatch_envelope" class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-300 bg-white hover:border-amber-400 cursor-pointer transition">
                            <input type="radio" name="dispatch_source" value="envelope" onchange="toggleDispatchSource('envelope')" class="accent-amber-600 mt-1">
                            <div>
                                <div class="font-extrabold text-xs text-slate-800 flex items-center gap-1.5">
                                    <span>💼 Employee Envelope</span>
                                    @if($engineerHasFloat)
                                        <span class="px-1.5 py-0.2 rounded text-[10px] bg-amber-100 text-amber-800 font-bold">Float Available</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">Deduct directly from {{ $partRequest->engineer?->name ?? 'engineer' }}'s advance float. Zero warehouse courier needed!</div>
                            </div>
                        </label>

                        {{-- Option 3: Split / Partial --}}
                        <label id="lbl_dispatch_split" class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-300 bg-white hover:border-indigo-400 cursor-pointer transition">
                            <input type="radio" name="dispatch_source" value="split" onchange="toggleDispatchSource('split')" class="accent-indigo-600 mt-1">
                            <div>
                                <div class="font-extrabold text-xs text-slate-800">⚡ Partial / Split</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">Specify custom split (e.g. 2 from envelope + 2 from warehouse).</div>
                            </div>
                        </label>
                    </div>

                    {{-- Split Allocation Sub-table --}}
                    <div id="splitTableContainer" class="hidden mt-4 pt-3 border-t border-slate-200">
                        <div class="text-xs font-bold text-slate-700 mb-2">Custom Item Allocation:</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="bg-slate-100 border-b border-slate-200">
                                        <th class="text-left px-3 py-2 font-semibold text-slate-600">Part</th>
                                        <th class="text-center px-3 py-2 font-semibold text-slate-600">Total Approved</th>
                                        <th class="text-center px-3 py-2 font-semibold text-amber-800 bg-amber-50">In Engineer Envelope</th>
                                        <th class="text-center px-3 py-2 font-semibold text-indigo-700 bg-indigo-50">Deduct from Envelope</th>
                                        <th class="text-center px-3 py-2 font-semibold text-emerald-700 bg-emerald-50">Remaining from Warehouse</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($partRequest->items as $it)
                                        @php
                                            $tot = $it->qty_approved ?? $it->qty_requested;
                                            $envOnHand = isset($engineerEnvelopes[$it->part_id]) ? $engineerEnvelopes[$it->part_id]->qty_on_hand : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-800">{{ $it->part?->name }} ({{ $it->part?->part_number }})</td>
                                            <td class="px-3 py-2 text-center font-bold">{{ $tot }}</td>
                                            <td class="px-3 py-2 text-center font-bold text-amber-700 bg-amber-50/50">{{ $envOnHand }} pcs</td>
                                            <td class="px-3 py-2 text-center bg-indigo-50/40">
                                                <input type="number" name="split_envelope_qtys[{{ $it->id }}]" id="split_env_{{ $it->id }}"
                                                       value="{{ min($tot, $envOnHand) }}" min="0" max="{{ min($tot, $envOnHand) }}"
                                                       oninput="updateSplitWarehouse({{ $it->id }}, {{ $tot }})"
                                                       class="border border-indigo-300 rounded px-2 py-1 w-20 text-center font-bold text-xs focus:ring-1 focus:ring-indigo-400">
                                            </td>
                                            <td class="px-3 py-2 text-center font-bold text-emerald-700 bg-emerald-50/40">
                                                <span id="split_wh_{{ $it->id }}">{{ max(0, $tot - min($tot, $envOnHand)) }}</span> pcs
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="warehouseShippingSection">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-medium text-slate-700">
                            Dispatch From Location <span class="text-rose-500">*</span>
                        </label>
                        <select name="dispatch_location_id" required
                                class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            <option value="">Select location...</option>
                            @foreach($locations as $loc)
                                @php
                                    $locStockSum = 0;
                                    $hasAll = true;
                                    foreach($partRequest->items as $it) {
                                        $q = isset($itemStocks[$loc->id][$it->part_id]) ? $itemStocks[$loc->id][$it->part_id]->first()->qty_on_hand : 0;
                                        $reqQ = $it->qty_approved ?? $it->qty_requested;
                                        if ($q < $reqQ) $hasAll = false;
                                        $locStockSum += $q;
                                    }
                                @endphp
                                <option value="{{ $loc->id }}" {{ (old('dispatch_location_id', $partRequest->dispatch_location_id) == $loc->id) ? 'selected' : '' }}>
                                    {{ $loc->name }} (Available: {{ $locStockSum }} pcs{{ $hasAll ? ' - In Stock' : ' - Ready' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700">
                            Courier Service <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="dispatch_courier" required
                               value="{{ old('dispatch_courier', $partRequest->dispatch_courier) }}"
                               class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-400"
                               placeholder="TCS, Leopard, BluEx, Rider, etc.">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700">
                            Courier Tracking No. <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="dispatch_tracking_number" required
                               value="{{ old('dispatch_tracking_number', $partRequest->dispatch_tracking_number) }}"
                               class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-400"
                               placeholder="e.g. 1234567890">
                    </div>
                </div>

                </div>

                {{-- Envelope 100% Confirmation Notice (Shown only when envelope selected) --}}
                <div id="envelopeOnlyNotice" class="hidden mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900 flex items-center gap-2">
                    <i class="fa-solid fa-briefcase text-amber-600 text-base"></i>
                    <div>
                        <strong>100% In-Hand Advance Float Deduction:</strong> All approved items will be deducted immediately from {{ $partRequest->engineer?->name ?? 'the engineer' }}'s personal advance envelope float. No physical warehouse dispatch or courier tracking is needed!
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" id="dispatchSubmitBtn"
                            onclick="return confirm('Confirm dispatch / stock deduction for this request?')"
                            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                        <i class="fas fa-truck"></i> Confirm Dispatch &amp; Deduct Stock
                    </button>
                    <span class="text-xs text-slate-400" id="dispatchHelpNote">Courier tracking number is strictly required when dispatching from warehouse.</span>
                </div>
            </form>
        </div>
    @endif

</div>

@push('scripts')
<script>
function toggleDispatchSource(source) {
    const lblWh = document.getElementById('lbl_dispatch_warehouse');
    const lblEnv = document.getElementById('lbl_dispatch_envelope');
    const lblSplit = document.getElementById('lbl_dispatch_split');
    const splitContainer = document.getElementById('splitTableContainer');
    const whSection = document.getElementById('warehouseShippingSection');
    const envNotice = document.getElementById('envelopeOnlyNotice');
    const courierInput = document.querySelector('input[name="dispatch_courier"]');
    const trackingInput = document.querySelector('input[name="dispatch_tracking_number"]');
    const locationSelect = document.querySelector('select[name="dispatch_location_id"]');
    const helpNote = document.getElementById('dispatchHelpNote');

    // Reset card styles
    [lblWh, lblEnv, lblSplit].forEach(lbl => {
        if (lbl) {
            lbl.className = 'flex items-start gap-2.5 p-3 rounded-lg border border-slate-300 bg-white hover:border-sky-400 cursor-pointer transition';
        }
    });

    if (source === 'warehouse') {
        if (lblWh) lblWh.className = 'flex items-start gap-2.5 p-3 rounded-lg border-2 border-emerald-500 bg-emerald-50/60 cursor-pointer transition';
        if (splitContainer) splitContainer.classList.add('hidden');
        if (whSection) whSection.classList.remove('hidden');
        if (envNotice) envNotice.classList.add('hidden');
        if (courierInput) courierInput.required = true;
        if (trackingInput) trackingInput.required = true;
        if (locationSelect) locationSelect.required = true;
        if (helpNote) helpNote.textContent = 'Courier tracking number is strictly required when dispatching from warehouse.';
    } else if (source === 'envelope') {
        if (lblEnv) lblEnv.className = 'flex items-start gap-2.5 p-3 rounded-lg border-2 border-amber-500 bg-amber-50/60 cursor-pointer transition';
        if (splitContainer) splitContainer.classList.add('hidden');
        if (whSection) whSection.classList.add('hidden');
        if (envNotice) envNotice.classList.remove('hidden');
        if (courierInput) courierInput.required = false;
        if (trackingInput) trackingInput.required = false;
        if (locationSelect) locationSelect.required = false;
        if (helpNote) helpNote.textContent = 'Parts will be deducted from the engineer\'s advance float envelope immediately.';
    } else if (source === 'split') {
        if (lblSplit) lblSplit.className = 'flex items-start gap-2.5 p-3 rounded-lg border-2 border-indigo-500 bg-indigo-50/60 cursor-pointer transition';
        if (splitContainer) splitContainer.classList.remove('hidden');
        if (whSection) whSection.classList.remove('hidden');
        if (envNotice) envNotice.classList.add('hidden');
        if (courierInput) courierInput.required = false;
        if (trackingInput) trackingInput.required = false;
        if (locationSelect) locationSelect.required = true;
        if (helpNote) helpNote.textContent = 'Warehouse shortfall will be dispatched; remainder deducted from envelope.';
    }
}

function updateSplitWarehouse(itemId, total) {
    const envInput = document.getElementById(`split_env_${itemId}`);
    const whSpan = document.getElementById(`split_wh_${itemId}`);
    if (!envInput || !whSpan) return;

    let envVal = parseInt(envInput.value) || 0;
    if (envVal < 0) envVal = 0;
    if (envVal > total) envVal = total;
    envInput.value = envVal;

    whSpan.textContent = Math.max(0, total - envVal);
}
</script>
@endpush
@endsection
