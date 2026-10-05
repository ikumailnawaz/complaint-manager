@extends('layouts.app')

@section('title', 'Submit Tour Expense Claim - Bank Complaint Manager')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Submit Tour Expense Claim</h1>
            <p class="text-xs text-slate-500 mt-1">Claim travel, fuel, toll, and accommodation expenses for branch complaint visits</p>
        </div>
        <a href="{{ route('expenses.index') }}" class="text-xs text-slate-600 hover:text-slate-900 bg-white border border-slate-300 px-3 py-1.5 rounded-lg shadow-sm">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to Expenses
        </a>
    </div>

    <form action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
        @csrf

        <!-- Linked Complaint Ticket -->
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">1. Select Linked Complaint Ticket *</label>
            <select name="ticket_id" required class="w-full border border-slate-300 rounded-lg p-2.5 text-xs bg-white focus:ring-1 focus:ring-emerald-500" onchange="autoFillCities(this)">
                <option value="">-- Choose Ticket You Completed --</option>
                @foreach($tickets as $t)
                    <option value="{{ $t->id }}" 
                        data-branch-city="{{ $t->branch_location }}" 
                        data-branch-address="{{ $t->branch_address }}"
                        data-engineer-city="{{ $t->engineer?->base_city ?? auth()->user()->base_city }}"
                        data-engineer-home="{{ $t->engineer?->home_coordinates ?? auth()->user()->home_coordinates }}"
                        {{ (isset($ticket) && $ticket->id == $t->id) ? 'selected' : '' }}>
                        {{ $t->ticket_no }} &bull; {{ $t->bank_name }} - {{ $t->branch_location }} &bull; (Status: {{ strtoupper($t->status) }})
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Travel Route & Trip Type -->
        <div>
            <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">2. Route &amp; Travel Details</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Engineer Starting Point (City / Home) *</label>
                    <input type="text" id="from_city" name="from_city" required value="{{ old('from_city', auth()->user()->home_coordinates ? auth()->user()->base_city . ' (' . auth()->user()->home_coordinates . ')' : (auth()->user()->base_city ?? 'Lahore')) }}" placeholder="e.g. Lahore or GPS coordinates" class="w-full border border-slate-300 rounded-lg p-2 text-xs">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Bank Branch Destination (Address / City) *</label>
                    <input type="text" id="to_city" name="to_city" required value="{{ old('to_city', isset($ticket) ? ($ticket->branch_address ?: $ticket->branch_location) : '') }}" placeholder="e.g. Branch street address or City" class="w-full border border-slate-300 rounded-lg p-2 text-xs">
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Trip Type *</label>
                    <select name="trip_type" required class="w-full border border-slate-300 rounded-lg p-2 text-xs">
                        <option value="round_trip" selected>Round Trip (Return)</option>
                        <option value="one_way">One Way</option>
                    </select>
                </div>
            </div>
            <div class="mt-2 text-[11px] text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <i class="fa-solid fa-route text-sky-600 mr-1"></i>
                    <strong>AI Tentative Distance Engine:</strong> Distance is measured from Engineer's Home Coordinates to Branch Physical Street Address (fallback to Bank City / Region).
                </div>
                <div class="flex items-center gap-2">
                    @if(auth()->user()->home_coordinates)
                        <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            <i class="fa-solid fa-location-dot"></i> Home GPS: {{ auth()->user()->home_coordinates }}
                        </span>
                    @endif
                    <button type="button" onclick="openGoogleMapsCheck()" class="inline-flex items-center gap-1 px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white rounded font-bold text-[10px] shadow-sm transition">
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Check on Google Maps</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Claim Category & Description -->
        <div>
            <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">3. Expense Classification</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Expense Category *</label>
                    <select name="category" required class="w-full border border-slate-300 rounded-lg p-2 text-xs bg-white">
                        <option value="travel" selected>🚗 Inter-City Travel &amp; Fuel</option>
                        <option value="fuel">⛽ Local Branch Fuel / Patrol</option>
                        <option value="accommodation">🏨 Hotel &amp; Lodging</option>
                        <option value="parts">🔩 Emergency Parts / Hardware</option>
                        <option value="food">🍱 Daily Meals / Per Diem</option>
                        <option value="misc">🧾 Tolls &amp; Miscellaneous</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Total Claim Amount (PKR) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-bold text-slate-400">PKR</span>
                        <input type="number" step="0.01" name="claimed_amount" required placeholder="e.g. 7500.00" class="w-full pl-12 pr-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-900 focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
            </div>
            <div class="mt-3 text-xs">
                <label class="block font-medium text-slate-700 mb-1">Expense Justification / Notes</label>
                <textarea name="description" rows="2" placeholder="Briefly describe what this expense covers (e.g. Round-trip fuel to Sahiwal branch, toll plaza slips attached)..." class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-emerald-500"></textarea>
            </div>
        </div>

        <!-- Document & Voucher Uploads (Smooth uploads) -->
        <div>
            <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">4. Supporting Evidence & Vouchers</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="p-4 border-2 border-dashed border-slate-300 rounded-xl hover:border-emerald-500 transition bg-slate-50/50">
                    <label class="block font-bold text-slate-700 mb-1">
                        <i class="fa-solid fa-receipt text-emerald-600 mr-1"></i> Expense Voucher (PDF or Image)
                    </label>
                    <p class="text-[11px] text-slate-500 mb-2">Toll receipts, fuel slips, hotel bills</p>
                    <input type="file" name="voucher_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200">
                </div>

                <div class="p-4 border-2 border-dashed border-slate-300 rounded-xl hover:border-sky-500 transition bg-slate-50/50">
                    <label class="block font-bold text-slate-700 mb-1">
                        <i class="fa-solid fa-file-signature text-sky-600 mr-1"></i> Service Report / Supporting Proof
                    </label>
                    <p class="text-[11px] text-slate-500 mb-2">Branch signed completion slip or photo</p>
                    <input type="file" name="supporting_doc" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-sky-100 file:text-sky-800 hover:file:bg-sky-200">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
            <a href="{{ route('expenses.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow hover:shadow-md transition">
                <i class="fa-solid fa-check mr-1"></i> Submit Tour Claim for Verification
            </button>
        </div>
    </form>

</div>

<script>
    function autoFillCities(select) {
        const option = select.options[select.selectedIndex];
        if (option) {
            if (option.dataset.branchAddress && option.dataset.branchAddress.trim()) {
                document.getElementById('to_city').value = option.dataset.branchAddress + (option.dataset.branchCity ? ' (' + option.dataset.branchCity + ')' : '');
            } else if (option.dataset.branchCity) {
                document.getElementById('to_city').value = option.dataset.branchCity;
            }

            if (option.dataset.engineerHome && option.dataset.engineerHome.trim()) {
                document.getElementById('from_city').value = (option.dataset.engineerCity ? option.dataset.engineerCity + ' ' : '') + '(' + option.dataset.engineerHome + ')';
            } else if (option.dataset.engineerCity) {
                document.getElementById('from_city').value = option.dataset.engineerCity;
            }
        }
    }

    function openGoogleMapsCheck() {
        const from = document.getElementById('from_city').value.trim();
        const to = document.getElementById('to_city').value.trim();
        if (!to) {
            alert('Please select or specify the Bank Branch Destination address first.');
            return;
        }
        const url = 'https://www.google.com/maps/dir/?api=1&origin=' + encodeURIComponent(from || 'Lahore') + '&destination=' + encodeURIComponent(to);
        window.open(url, '_blank');
    }
</script>
@endsection
