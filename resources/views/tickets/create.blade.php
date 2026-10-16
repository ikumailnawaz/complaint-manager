@extends('layouts.app')

@section('title', 'Register Complaint Ticket - Bank Complaint Manager')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Register New Complaint Ticket</h1>
            <p class="text-xs text-slate-500 mt-1">Manual entry or simulate bank support email ingestion</p>
        </div>
        <a href="{{ route('tickets.index') }}" class="text-xs text-slate-600 hover:text-slate-900 bg-white border border-slate-300 px-3 py-1.5 rounded-lg shadow-sm">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to Complaints
        </a>
    </div>

    <form action="{{ route('tickets.store') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
        @csrf

        <!-- Ticket Number Type Section -->
        <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Ticket Number Format</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="flex items-center space-x-2 text-xs text-slate-800 cursor-pointer">
                    <input type="radio" name="ticket_no_type" value="auto" checked class="text-sky-600" onchange="toggleCustomTicket(false)">
                    <span><strong>Auto-Generate</strong> (e.g. CMP-{{ date('Y') }}-0000X)</span>
                </label>
                <label class="flex items-center space-x-2 text-xs text-slate-800 cursor-pointer">
                    <input type="radio" name="ticket_no_type" value="manual" class="text-sky-600" onchange="toggleCustomTicket(true)">
                    <span><strong>Use Bank's Email Ref #</strong> (e.g. HBL-REF-8891)</span>
                </label>
            </div>
            <div id="custom_ticket_field" class="hidden mt-3">
                <input type="text" name="custom_ticket_no" placeholder="Enter bank reference number from their email..." class="w-full border border-slate-300 rounded-lg p-2 text-xs">
            </div>
        </div>

        <!-- Ticket Nature: Complaint vs New Machine Installation -->
        <div class="p-4 bg-indigo-50/60 rounded-xl border border-indigo-200">
            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                <i class="fa-solid fa-layer-group text-indigo-600 mr-1"></i> Work Order / Ticket Nature *
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <label class="flex items-start space-x-2.5 p-3 bg-white rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition shadow-2xs">
                    <input type="radio" name="ticket_type" value="complaint" checked class="mt-0.5 text-sky-600 focus:ring-sky-500">
                    <div>
                        <span class="font-extrabold text-slate-900 flex items-center">
                            <i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1.5 text-xs"></i> Standard Complaint / Fault Repair (Default)
                        </span>
                        <p class="text-[11px] text-slate-500 mt-0.5">Machine breakdown, hardware jam, sensor fault, or on-site service call</p>
                    </div>
                </label>
                <label class="flex items-start space-x-2.5 p-3 bg-white rounded-lg border border-purple-200 cursor-pointer hover:bg-purple-50 transition shadow-2xs">
                    <input type="radio" name="ticket_type" value="installation" class="mt-0.5 text-purple-600 focus:ring-purple-500">
                    <div>
                        <span class="font-extrabold text-purple-900 flex items-center">
                            <i class="fa-solid fa-screwdriver-wrench text-purple-600 mr-1.5 text-xs"></i> 🔧 New Machine Installation
                        </span>
                        <p class="text-[11px] text-slate-500 mt-0.5">Brand new machine deployment, on-site setup, testing, and branch handover</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Bank & Branch Section -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">1. Bank & Location Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Bank Name *</label>
                    <input type="text" name="bank_name" required placeholder="e.g. MCB, HBL, UBL, Meezan" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Branch Name</label>
                    <input type="text" name="branch_name" placeholder="e.g. Gulberg Main Branch" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">City / Location *</label>
                    <input type="text" name="branch_location" required placeholder="e.g. Lahore, Karachi, Multan" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
            </div>
            <div class="mt-3 text-xs">
                <label class="block font-medium text-slate-700 mb-1">Branch Street Address</label>
                <input type="text" name="branch_address" placeholder="Full branch physical address..." class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
            </div>
        </div>

        <!-- Customer / Branch Contact -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">2. Bank Contact Person</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Contact Name</label>
                    <input type="text" name="customer_name" placeholder="Branch Manager / Contact" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Mobile / WhatsApp Number</label>
                    <input type="text" name="customer_mobile" placeholder="03001234567" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Bank Sender Email (For Replies)</label>
                    <input type="email" name="customer_email" placeholder="branch.manager@bank.com" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
            </div>
        </div>

        <!-- Machine Hardware & Warranty -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">3. Machine Hardware & Warranty</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Machine Type</label>
                    <select name="machine_type" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                        <option value="">-- Unknown / Not Specified --</option>
                        @if(isset($machineTypes))
                            @foreach($machineTypes as $mType)
                                <option value="{{ $mType }}" {{ old('machine_type') === $mType ? 'selected' : '' }}>{{ $mType }}</option>
                            @endforeach
                        @else
                            <option value="Cash Sorting Machine">Cash Sorting Machine</option>
                            <option value="Cash Counting Machine">Cash Counting Machine</option>
                            <option value="Cash Binding Machine">Cash Binding Machine</option>
                            <option value="Shrink Wrapping Machine">Shrink Wrapping Machine</option>
                            <option value="ATM">ATM (Cash Dispenser)</option>
                            <option value="CDM">CDM (Cash Deposit)</option>
                            <option value="POS">POS Terminal</option>
                            <option value="Kiosk">Cheque / Statement Kiosk</option>
                            <option value="Other Equipment">Other Equipment</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Machine Model</label>
                    <input type="text" name="machine_model" placeholder="e.g. Glory GFS-120 / Magner 150 / NCR 6634" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Serial Number</label>
                    <input type="text" name="machine_serial_no" placeholder="e.g. CSM-99210-LH" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Warranty Status *</label>
                    <select name="warranty_status" required class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                        <option value="in_warranty">In Warranty (Free Service)</option>
                        <option value="out_of_warranty">Out of Warranty (Billable)</option>
                        <option value="unknown">Unknown / Needs Check</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Issue Details & Urgency -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">4. Fault Description & Urgency SLA</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                <div class="sm:col-span-2">
                    <label class="block font-medium text-slate-700 mb-1">Issue Summary (One Line) *</label>
                    <input type="text" name="issue_summary" required placeholder="e.g. Note rejection sensor fault / cash feeder jam" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Urgency Priority *</label>
                    <select name="urgency" required class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                        <option value="high">High Urgency</option>
                        <option value="medium" selected>Medium Urgency</option>
                        <option value="low">Low Urgency</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">SLA Turnaround (TAT) *</label>
                    <select name="sla_tat" required class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                        <option value="1d" selected>1 Day (24h SLA)</option>
                        <option value="2d">2 Days (48h SLA)</option>
                        <option value="3d">3 Days (72h SLA)</option>
                        <option value="4d">4 Days (96h SLA)</option>
                        <option value="4h">4 Hours (Emergency SLA)</option>
                        <option value="8h">8 Hours (Same-Day SLA)</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 text-xs">
                <label class="block font-medium text-slate-700 mb-1">Detailed Description (Bank Email Body)</label>
                <textarea name="issue_description" rows="3" placeholder="Full complaint text sent by bank..." class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500"></textarea>
            </div>
        </div>

        <!-- Optional Manual Alignment During Creation -->
        <div class="p-4 bg-sky-50 rounded-lg border border-sky-200">
            <h2 class="text-xs font-bold text-sky-900 uppercase tracking-wider mb-2">
                <i class="fa-solid fa-user-check mr-1"></i> Optional: Assign Field Engineer Now
            </h2>
            <p class="text-[11px] text-sky-700 mb-3">
                You can leave this unassigned to pick an engineer later from the ticket detail view, or select an available engineer right now.
            </p>
            <div class="text-xs">
                <select name="assigned_engineer_id" class="w-full border border-sky-300 rounded-lg p-2 text-xs bg-white">
                    <option value="">-- Leave Unassigned (Will Assign Later) --</option>
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}">
                            {{ $eng->name }} &bull; {{ $eng->base_city }} &bull; {{ $eng->specialization }} &bull; (WA: {{ $eng->phone_whatsapp }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
            <a href="{{ route('tickets.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow hover:shadow-md transition">
                <i class="fa-solid fa-check mr-1"></i> Create Complaint Ticket
            </button>
        </div>
    </form>

</div>

<script>
    function toggleCustomTicket(show) {
        document.getElementById('custom_ticket_field').classList.toggle('hidden', !show);
    }
</script>
@endsection
