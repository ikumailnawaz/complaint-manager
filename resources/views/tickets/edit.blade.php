@extends('layouts.app')

@section('title', 'Manage Ticket ' . $ticket->ticket_no . ' - Bank Complaint Manager')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-xl font-bold text-slate-800">Manage Ticket: {{ $ticket->ticket_no }}</h1>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-indigo-100 text-indigo-800">
                    Full Operator Edit
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Review, correct, or update all ticket attributes, hardware serials, location details, and SLA targets.</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('tickets.show', $ticket) }}" class="text-xs text-slate-600 hover:text-slate-900 bg-white border border-slate-300 px-3 py-1.5 rounded-lg shadow-sm">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Ticket
            </a>
        </div>
    </div>

    <form action="{{ route('tickets.update', $ticket) }}" method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
        @csrf
        @method('PUT')

        <!-- SECTION 1: TICKET IDENTIFICATION -->
        <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
            <h2 class="text-xs font-bold text-slate-600 uppercase tracking-wider">Ticket Identification & Source</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">System Ticket Number *</label>
                    <input type="text" name="ticket_no" value="{{ old('ticket_no', $ticket->ticket_no) }}" required class="w-full border border-slate-300 rounded-lg p-2 text-xs font-mono font-bold focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Bank Internal Ref # (e.g. 118929)</label>
                    <input type="text" name="customer_ref_no" value="{{ old('customer_ref_no', $ticket->customer_ref_no) }}" placeholder="Optional bank complaint reference..." class="w-full border border-slate-300 rounded-lg p-2 text-xs font-mono focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Current Ticket Status *</label>
                    <select name="status" required class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                        <option value="open" {{ old('status', $ticket->status) === 'open' ? 'selected' : '' }}>Open (Unassigned)</option>
                        <option value="assigned" {{ old('status', $ticket->status) === 'assigned' ? 'selected' : '' }}>Assigned to Engineer</option>
                        <option value="in_progress" {{ old('status', $ticket->status) === 'in_progress' ? 'selected' : '' }}>In Progress (Field Work)</option>
                        <option value="awaiting_workshop" {{ old('status', $ticket->status) === 'awaiting_workshop' ? 'selected' : '' }}>Awaiting Workshop Repair</option>
                        <option value="escalated" {{ old('status', $ticket->status) === 'escalated' ? 'selected' : '' }}>Escalated to Superior</option>
                        <option value="resolved" {{ old('status', $ticket->status) === 'resolved' ? 'selected' : '' }}>Resolved (Work Done)</option>
                        <option value="closed" {{ old('status', $ticket->status) === 'closed' ? 'selected' : '' }}>Closed (Confirmed)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SECTION 2: BANK & LOCATION INFORMATION -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">1. Bank & Branch Location</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Bank Name *</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $ticket->bank_name) }}" required placeholder="e.g. United Bank Limited (UBL)" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Branch Name & Code</label>
                    <input type="text" name="branch_name" value="{{ old('branch_name', $ticket->branch_name) }}" placeholder="e.g. RAILWAY ROAD FAISALABAD (472)" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">City / Location *</label>
                    <input type="text" name="branch_location" value="{{ old('branch_location', $ticket->branch_location) }}" required placeholder="e.g. Faisalabad, Lahore" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
            </div>
            <div class="mt-3 text-xs">
                <label class="block font-medium text-slate-700 mb-1">Branch Physical Street Address</label>
                <input type="text" name="branch_address" value="{{ old('branch_address', $ticket->branch_address) }}" placeholder="e.g. Plot 12, Main Railway Road, Faisalabad" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
            </div>
        </div>

        <!-- SECTION 3: BANK CONTACT PERSON -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">2. Bank Officer & Contacts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Contact Person Name</label>
                    <input type="text" name="customer_name" value="{{ old('customer_name', $ticket->customer_name) }}" placeholder="e.g. Mr. Shoaib (BOM) or Branch Manager" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Branch Mobile Number</label>
                    <input type="text" name="customer_mobile" value="{{ old('customer_mobile', $ticket->customer_mobile) }}" placeholder="0314-3914289" class="w-full border border-slate-300 rounded-lg p-2 text-xs font-mono focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Bank Sender Email (Receives Assignment Reply)</label>
                    <input type="email" name="customer_email" value="{{ old('customer_email', $ticket->customer_email) }}" placeholder="branch@bank.com" class="w-full border border-slate-300 rounded-lg p-2 text-xs font-mono focus:ring-1 focus:ring-sky-500">
                </div>
            </div>
        </div>

        <!-- SECTION 4: MACHINE HARDWARE & WARRANTY -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">3. Machine Hardware & Warranty</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Machine Type</label>
                    <select name="machine_type" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                        <option value="">-- Unknown / Unspecified --</option>
                        <option value="Cash Sorting Machine" {{ old('machine_type', $ticket->machine_type) === 'Cash Sorting Machine' ? 'selected' : '' }}>Cash Sorting Machine</option>
                        <option value="Counting Machine" {{ old('machine_type', $ticket->machine_type) === 'Counting Machine' ? 'selected' : '' }}>Counting Machine</option>
                        <option value="Binding Machine" {{ old('machine_type', $ticket->machine_type) === 'Binding Machine' ? 'selected' : '' }}>Binding Machine</option>
                        <option value="ATM" {{ old('machine_type', $ticket->machine_type) === 'ATM' ? 'selected' : '' }}>ATM (Cash Dispenser)</option>
                        <option value="CDM" {{ old('machine_type', $ticket->machine_type) === 'CDM' ? 'selected' : '' }}>CDM (Cash Deposit)</option>
                        <option value="POS" {{ old('machine_type', $ticket->machine_type) === 'POS' ? 'selected' : '' }}>POS Terminal</option>
                        <option value="Kiosk" {{ old('machine_type', $ticket->machine_type) === 'Kiosk' ? 'selected' : '' }}>Cheque / Statement Kiosk</option>
                        <option value="Other" {{ old('machine_type', $ticket->machine_type) === 'Other' ? 'selected' : '' }}>Other Equipment</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Model Number</label>
                    <input type="text" name="machine_model" value="{{ old('machine_model', $ticket->machine_model) }}" placeholder="e.g. cm30mm, Glory GFS-120" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Serial Number / TID</label>
                    <input type="text" name="machine_serial_no" value="{{ old('machine_serial_no', $ticket->machine_serial_no) }}" placeholder="e.g. cms316827" class="w-full border border-slate-300 rounded-lg p-2 text-xs font-mono font-bold focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Warranty Status *</label>
                    <select name="warranty_status" required class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                        <option value="in_warranty" {{ old('warranty_status', $ticket->warranty_status) === 'in_warranty' ? 'selected' : '' }}>In Warranty / SLA</option>
                        <option value="out_of_warranty" {{ old('warranty_status', $ticket->warranty_status) === 'out_of_warranty' ? 'selected' : '' }}>Out of Warranty (Billable)</option>
                        <option value="unknown" {{ old('warranty_status', $ticket->warranty_status) === 'unknown' ? 'selected' : '' }}>Unknown / To Be Verified</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SECTION 5: SLA TARGETS & ASSIGNED ENGINEER -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">4. SLA Targets & Engineer Alignment</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Urgency Priority *</label>
                    <select name="urgency" required class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                        <option value="high" {{ old('urgency', $ticket->urgency) === 'high' ? 'selected' : '' }}>High Urgency (Machine Down)</option>
                        <option value="medium" {{ old('urgency', $ticket->urgency) === 'medium' ? 'selected' : '' }}>Medium Urgency (Standard)</option>
                        <option value="low" {{ old('urgency', $ticket->urgency) === 'low' ? 'selected' : '' }}>Low Urgency (Routine)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Operator Defined TAT (SLA)</label>
                    <select name="sla_tat" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                        <option value="">Keep Current Deadline</option>
                        <option value="1 day">1 Day SLA</option>
                        <option value="2 days">2 Days SLA</option>
                        <option value="3 days">3 Days SLA</option>
                        <option value="4 days">4 Days SLA</option>
                        <option value="4 hours">4 Hours TAT</option>
                        <option value="8 hours">8 Hours TAT</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Or Specific Custom SLA Deadline</label>
                    <input type="datetime-local" name="custom_sla_deadline" value="{{ $ticket->sla_deadline ? $ticket->sla_deadline->format('Y-m-d\TH:i') : '' }}" class="w-full border border-slate-300 rounded-lg p-1.5 text-xs text-slate-700 bg-white">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Assigned Field Engineer</label>
                    <select name="assigned_engineer_id" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                        <option value="">-- Unassigned (Open) --</option>
                        @foreach($engineers as $eng)
                            <option value="{{ $eng->id }}" {{ old('assigned_engineer_id', $ticket->assigned_engineer_id) == $eng->id ? 'selected' : '' }}>
                                {{ $eng->name }} ({{ $eng->base_city }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- SECTION 6: FAULT DETAILS -->
        <div>
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">5. Fault Summary & Raw Logs</h2>
            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">One-line Issue Summary *</label>
                    <input type="text" name="issue_summary" value="{{ old('issue_summary', $ticket->issue_summary) }}" required placeholder="e.g. Binding machine heating element defect" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Full Email Content / Technical Details</label>
                    <textarea name="issue_description" rows="5" class="w-full border border-slate-300 rounded-lg p-2 text-xs font-mono focus:ring-1 focus:ring-sky-500">{{ old('issue_description', $ticket->issue_description) }}</textarea>
                </div>
            </div>
        </div>

        <!-- SUBMIT / CANCEL ACTIONS -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200">
            <a href="{{ route('tickets.show', $ticket) }}" class="px-4 py-2 border border-slate-300 text-slate-700 font-semibold rounded-lg text-xs hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5">
                <i class="fa-solid fa-save mr-1"></i>
                <span>Save Ticket Updates</span>
            </button>
        </div>

    </form>
</div>
@endsection
