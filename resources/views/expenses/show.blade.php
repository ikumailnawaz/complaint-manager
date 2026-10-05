@extends('layouts.app')

@section('title', 'Expense Claim #EXP-' . str_pad($claim->id, 4, '0', STR_PAD_LEFT) . ' - Bank Complaint Manager')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('expenses.index') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900">
                    Expense Claim #EXP-{{ str_pad($claim->id, 4, '0', STR_PAD_LEFT) }}
                </h1>
                <div class="text-xs text-slate-500">
                    Claimed by: <strong class="text-slate-800">{{ $claim->engineer?->name }}</strong> &bull;
                    Submitted: {{ $claim->created_at->format('d M Y, h:i A') }}
                </div>
            </div>
        </div>

        <div>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                {{ $claim->status === 'submitted' ? 'bg-amber-100 text-amber-800' : '' }}
                {{ $claim->status === 'approved' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $claim->status === 'rejected' ? 'bg-rose-100 text-rose-800' : '' }}
                {{ $claim->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : '' }}">
                {{ $claim->status }}
            </span>
            @if($claim->resubmission_count > 0)
                <span class="block text-[10px] text-amber-700 text-right mt-1 font-semibold">Re-submitted x{{ $claim->resubmission_count }}</span>
            @endif
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Details, Distance, and Documents -->
        <div class="md:col-span-2 space-y-6">

            <!-- Route & AI Tentative Distance -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">1. Route & Distance Audit</h2>
                
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-400 block text-[10px]">TRAVEL ROUTE:</span>
                        <div class="font-bold text-slate-800 text-sm mt-0.5">
                            {{ $claim->from_city }} &rarr; {{ $claim->to_city }}
                        </div>
                        <div class="text-slate-500 text-[11px] capitalize mt-0.5">{{ str_replace('_', ' ', $claim->trip_type) }}</div>
                    </div>

                    <div class="bg-sky-50 p-3 rounded-lg border border-sky-200">
                        <span class="text-sky-600 block text-[10px] font-bold">AI TENTATIVE DISTANCE:</span>
                        <div class="font-extrabold text-sky-900 text-sm mt-0.5">
                            {{ $claim->ai_distance_km ?? 0 }} KM
                        </div>
                        <div class="text-sky-700 text-[11px] mt-0.5">~{{ $claim->ai_estimated_hours ?? 0 }} Hours Road Trip</div>
                    </div>
                </div>

                @php
                    $eng = $claim->engineer;
                    $t = $claim->ticket;
                    // Build Google Maps directions URL: origin = engineer home coordinates or home address or from_city; destination = branch street address or branch location
                    $gOrigin = !empty($eng?->home_coordinates) ? trim($eng->home_coordinates) : (!empty($eng?->home_address) ? trim($eng->home_address) : $claim->from_city);
                    $gDest = !empty($t?->branch_address) ? trim($t->branch_address) . ', ' . ($t->branch_location ?? '') : (!empty($t?->branch_location) ? trim($t->branch_location) : $claim->to_city);
                    $mapsUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($gOrigin) . '&destination=' . urlencode($gDest);
                @endphp

                <div class="p-3 bg-slate-50/80 rounded-lg border border-slate-200 text-xs space-y-2">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="space-y-0.5">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Origin (Engineer Starting Location):</span>
                            <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-house-user text-indigo-600"></i>
                                <span>{{ $eng?->name }}</span>
                                @if(!empty($eng?->home_coordinates))
                                    <span class="font-mono text-[10px] text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200">
                                        GPS: {{ $eng->home_coordinates }}
                                    </span>
                                @endif
                            </div>
                            @if(!empty($eng?->home_address))
                                <div class="text-[11px] text-slate-600 pl-4">{{ $eng->home_address }}</div>
                            @endif
                        </div>

                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-bold text-xs shadow-sm transition active:scale-95">
                            <i class="fa-solid fa-map-location-dot"></i>
                            <span>Verify Route on Google Maps</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        </a>
                    </div>

                    <div class="pt-2 border-t border-slate-200/60">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Destination (Bank Branch Physical Address):</span>
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5 mt-0.5">
                            <i class="fa-solid fa-building-columns text-emerald-600"></i>
                            <span>{{ $t?->bank_name }} - {{ $t?->branch_name ?? $t?->branch_location }}</span>
                        </div>
                        @if(!empty($t?->branch_address))
                            <div class="text-[11px] text-slate-600 pl-4">{{ $t->branch_address }}</div>
                        @endif
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-slate-500">Standard Suggested Rate (PKR 25/km):</span>
                        <strong class="text-slate-800 block text-sm">PKR {{ number_format($claim->suggested_amount, 2) }}</strong>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-500">Engineer Claimed Amount:</span>
                        <strong class="text-emerald-700 block text-base font-extrabold">PKR {{ number_format($claim->claimed_amount, 2) }}</strong>
                    </div>
                </div>
            </div>

            <!-- Linked Ticket Card -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">2. Linked Complaint Ticket</h2>
                <div class="flex items-center justify-between text-xs">
                    <div>
                        <a href="{{ route('tickets.show', $claim->ticket) }}" class="font-bold text-sky-600 hover:underline text-sm">
                            {{ $claim->ticket?->ticket_no }}
                        </a>
                        <div class="text-slate-700 font-medium mt-0.5">
                            {{ $claim->ticket?->bank_name }} - {{ $claim->ticket?->branch_name ?? 'Branch' }}
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            Machine: {{ $claim->ticket?->machine_type }} (Serial: {{ $claim->ticket?->machine_serial_no ?? 'N/A' }})
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700">
                        {{ $claim->ticket?->status }}
                    </span>
                </div>
            </div>

            <!-- Vouchers & Supporting Documents -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">3. Attached Proof & Vouchers</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-file-pdf text-rose-500 text-lg"></i>
                            <div>
                                <div class="font-semibold text-slate-800">Expense Voucher</div>
                                <div class="text-[10px] text-slate-500">Toll, Fuel, Receipts</div>
                            </div>
                        </div>
                        @if($claim->voucher_file)
                            <div class="flex items-center space-x-1.5">
                                <a href="{{ route('expenses.voucher', $claim) }}" target="_blank" class="px-2.5 py-1 bg-white border border-slate-300 hover:border-slate-400 rounded-lg text-slate-700 text-xs font-bold transition flex items-center space-x-1">
                                    <i class="fa-solid fa-eye text-sky-600"></i>
                                    <span>View</span>
                                </a>
                                @if(!in_array($claim->status, ['approved', 'paid']) || auth()->user()->isSuperior())
                                <button type="button" onclick="document.getElementById('replaceVoucherModal').classList.remove('hidden')" class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 border border-amber-300 rounded-lg text-amber-800 text-xs font-bold transition flex items-center space-x-1 cursor-pointer">
                                    <i class="fa-solid fa-arrows-rotate text-amber-600"></i>
                                    <span>Replace</span>
                                </button>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center space-x-1.5">
                                <span class="text-slate-400 text-[10px]">None</span>
                                <button type="button" onclick="document.getElementById('replaceVoucherModal').classList.remove('hidden')" class="px-2 py-0.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 rounded text-emerald-800 text-[10px] font-bold transition">
                                    + Upload
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-file-signature text-sky-500 text-lg"></i>
                            <div>
                                <div class="font-semibold text-slate-800">Service Report Proof</div>
                                <div class="text-[10px] text-slate-500">Branch Handover Slip</div>
                            </div>
                        </div>
                        @if($claim->supporting_doc)
                            <a href="{{ asset('storage/' . $claim->supporting_doc) }}" target="_blank" class="px-2 py-1 bg-white border border-slate-300 hover:border-slate-400 rounded text-slate-700 text-xs font-medium">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        @else
                            <span class="text-slate-400 text-[10px]">None</span>
                        @endif
                    </div>
                </div>

                @if($claim->admin_notes)
                    <div class="mt-3 p-3 bg-slate-100 rounded-lg text-xs text-slate-700 border border-slate-200">
                        <strong class="text-slate-900 block mb-0.5">Admin Audit Notes / Feedback:</strong>
                        <p class="italic">"{{ $claim->admin_notes }}"</p>
                    </div>
                @endif
            </div>

            <!-- Re-Submission Form (Enabled when status is 'rejected') -->
            @if($claim->status === 'rejected')
                <div class="bg-rose-50 border-2 border-rose-300 rounded-xl p-5 space-y-4">
                    <div class="flex items-center space-x-2 text-rose-900 font-bold text-xs uppercase tracking-wider">
                        <i class="fa-solid fa-rotate-right text-rose-600"></i>
                        <span>Re-Submit Corrected Claim (Engineer Action)</span>
                    </div>
                    <p class="text-xs text-rose-700">
                        This claim was rejected by admin. Please correct the claimed amount or attach clearer voucher documents and re-submit.
                    </p>

                    <form action="{{ route('expenses.resubmit', $claim) }}" method="POST" enctype="multipart/form-data" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Corrected Claim Amount (PKR) *</label>
                            <input type="number" step="0.01" name="claimed_amount" required value="{{ $claim->claimed_amount }}" class="w-full border border-slate-300 rounded-lg p-2 text-xs font-bold text-slate-800">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Upload New Voucher PDF / Image</label>
                            <input type="file" name="voucher_file" class="w-full text-xs text-slate-500">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Explanation Notes</label>
                            <input type="text" name="notes" placeholder="Notes explaining correction to admin..." class="w-full border border-slate-300 rounded-lg p-2 text-xs">
                        </div>
                        <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2 rounded-lg text-xs shadow-sm transition">
                            <i class="fa-solid fa-paper-plane mr-1"></i> Re-Submit for Verification
                        </button>
                    </form>
                </div>
            @endif

        </div>

        <!-- Right 1 Col: Admin Approval, Rejection, and Payment Processing -->
        <div class="space-y-6">

            @if(!auth()->user()->isEngineer())

                <!-- ADMIN AUDIT & APPROVAL PANEL -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider pb-2 border-b border-slate-100">
                        Admin Audit Actions
                    </h2>

                    @if($claim->status === 'submitted' || $claim->status === 'rejected')
                        @if($claim->status === 'rejected')
                            <div class="bg-rose-50 border border-rose-200 p-2.5 rounded-lg text-rose-800 text-[11px] mb-2 flex items-center justify-between">
                                <span><i class="fa-solid fa-triangle-exclamation mr-1 text-rose-600"></i> Status is <strong>Rejected</strong>. You can approve once corrected or update notes.</span>
                            </div>
                        @endif

                        <!-- APPROVE BUTTON -->
                        <form action="{{ route('expenses.approve', $claim) }}" method="POST">
                            @csrf
                            <input type="hidden" name="admin_notes" value="{{ $claim->status === 'rejected' ? 'Verified corrected voucher/mileage after rejection. Approved.' : 'Verified mileage and receipts. Approved.' }}">
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-xs shadow transition flex items-center justify-center space-x-1.5 cursor-pointer">
                                <i class="fa-solid fa-check-circle"></i>
                                <span>{{ $claim->status === 'rejected' ? 'Approve Corrected Expense Claim' : 'Approve Expense Claim' }}</span>
                            </button>
                        </form>

                        <!-- REJECT FORM -->
                        <div class="pt-3 border-t border-slate-100">
                            <label class="block text-xs font-bold text-rose-700 mb-1">{{ $claim->status === 'rejected' ? 'Update Rejection Reason / Request Further Correction:' : 'Reject with Notes (Allow Re-submission):' }}</label>
                            <form action="{{ route('expenses.reject', $claim) }}" method="POST" class="space-y-2 text-xs">
                                @csrf
                                <textarea name="rejection_reason" required rows="2" placeholder="Explain why voucher or amount is rejected..." class="w-full border border-rose-300 rounded-lg p-2 text-xs">{{ $claim->status === 'rejected' ? $claim->admin_notes : '' }}</textarea>
                                <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-semibold py-1.5 rounded-lg text-xs shadow-sm transition cursor-pointer">
                                    <i class="fa-solid fa-ban mr-1"></i> {{ $claim->status === 'rejected' ? 'Update Rejection Notes' : 'Reject Claim' }}
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($claim->status === 'approved')
                        <div class="bg-blue-50 p-3 rounded-lg border border-blue-200 text-xs text-blue-900">
                            <i class="fa-solid fa-circle-check text-blue-600 mr-1"></i>
                            Claim is <strong>Approved</strong>. Ready for individual or bulk disbursement.
                        </div>

                        <!-- INDIVIDUAL PAY FORM -->
                        <div class="pt-2">
                            <h3 class="text-xs font-bold text-slate-700 mb-2">Disburse Payment Individually:</h3>
                            <form action="{{ route('expenses.pay', $claim) }}" method="POST" class="space-y-2 text-xs">
                                @csrf
                                <div>
                                    <label class="block text-slate-600 mb-0.5">Payment Method *</label>
                                    <select name="payment_method" required class="w-full border border-slate-300 rounded p-1.5 text-xs">
                                        <option value="bank_transfer">Online Bank Transfer</option>
                                        <option value="cash">Cash Voucher</option>
                                        <option value="cheque">Cross Cheque</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-slate-600 mb-0.5">Transaction Reference # *</label>
                                    <input type="text" name="payment_reference" required placeholder="FT-991203 / Chq # 44012" class="w-full border border-slate-300 rounded p-1.5 text-xs">
                                </div>
                                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded-lg text-xs shadow-sm transition">
                                    <i class="fa-solid fa-money-bill-check mr-1"></i> Mark as Paid (PKR {{ number_format($claim->claimed_amount, 2) }})
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($claim->status === 'paid')
                        <div class="bg-emerald-50 p-4 rounded-lg border border-emerald-200 text-xs text-emerald-950 space-y-1">
                            <div class="font-bold text-emerald-800 flex items-center space-x-1">
                                <i class="fa-solid fa-badge-check text-emerald-600"></i>
                                <span>Disbursement Complete</span>
                            </div>
                            <div>Method: <strong>{{ strtoupper(str_replace('_', ' ', $claim->payment_method)) }}</strong></div>
                            <div>Ref: <code class="font-mono">{{ $claim->payment_reference }}</code></div>
                            <div>Disbursed On: {{ $claim->paid_at?->format('d M Y, h:i A') }}</div>
                            <div>Disbursed By: {{ $claim->paidBy?->name ?? 'Admin' }}</div>
                        </div>
                    @endif
                </div>

            @else
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 text-xs space-y-2">
                    <h2 class="font-bold text-slate-800">Claim Status</h2>
                    <p class="text-slate-600">
                        Your claim is currently: <strong class="uppercase text-slate-800">{{ $claim->status }}</strong>
                    </p>
                    @if($claim->status === 'paid')
                        <div class="bg-emerald-50 text-emerald-800 p-3 rounded border border-emerald-200">
                            Payment disbursed via {{ $claim->payment_method }} (Ref: {{ $claim->payment_reference }}).
                        </div>
                    @endif
                </div>
            @endif

        </div>

    </div>

</div>

<!-- Replace Voucher Modal -->
<div id="replaceVoucherModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden">
        <div class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between">
            <div class="font-bold text-xs flex items-center space-x-2">
                <i class="fa-solid fa-receipt text-emerald-400"></i>
                <span>Upload / Replace Receipt Voucher</span>
            </div>
            <button type="button" onclick="document.getElementById('replaceVoucherModal').classList.add('hidden')" class="text-slate-400 hover:text-white text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="{{ route('expenses.update-voucher', $claim) }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">Select Receipt Voucher File</label>
                <input type="file" name="voucher_file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 border border-slate-200 rounded-xl p-2 bg-slate-50">
                <p class="text-[10px] text-slate-400 mt-1">Accepts images (PNG, JPG, WEBP) or PDF toll/fuel receipts up to 10MB.</p>
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('replaceVoucherModal').classList.add('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold shadow-sm">
                    <i class="fa-solid fa-upload mr-1"></i> Upload Voucher
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
