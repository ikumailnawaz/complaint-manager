@extends('layouts.app')

@section('title', 'Initiate Part Transfer - Bank Complaint Manager')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Top Ribbon -->
    <div class="flex items-center justify-between bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('parts.transfers.index') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900">Initiate Inter-Location Part Transfer</h1>
                <p class="text-xs text-slate-500">Relocate spare inventory between offices or from central warehouse</p>
            </div>
        </div>
        <a href="{{ route('parts.transfers.index') }}" class="text-xs text-slate-600 hover:text-slate-900 font-semibold px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 transition">
            Cancel
        </a>
    </div>

    <form action="{{ route('parts.transfers.store') }}" method="POST" class="space-y-6">
        @csrf

        <!-- Locations Selection Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2">
                1. Transfer Route & Logistics
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Source Location -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Source Office (Dispatching From) <span class="text-rose-500">*</span>
                    </label>
                    <select name="from_location_id" required class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                        <option value="">-- Select Source Office --</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('from_location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ $loc->city }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Inventory will be deducted from here upon dispatch</p>
                    @error('from_location_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Destination Location -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Destination Office (Receiving At) <span class="text-rose-500">*</span>
                    </label>
                    <select name="to_location_id" required class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                        <option value="">-- Select Destination Office --</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('to_location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ $loc->city }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Inventory will be credited here upon confirmation of receipt</p>
                    @error('to_location_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remarks -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Transfer Reason / Courier Instructions
                    </label>
                    <input type="text" name="remarks" value="{{ old('remarks') }}" placeholder="e.g. Urgent ATM dispenser module replenishment for Lahore South branch"
                        class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                    2. Spare Components to Transfer
                </h2>
                <button type="button" onclick="addTransferRow()" class="px-3 py-1 bg-sky-600 hover:bg-sky-700 text-white rounded text-xs font-bold shadow-2xs transition flex items-center space-x-1 cursor-pointer">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Item</span>
                </button>
            </div>

            <div class="overflow-x-visible pb-16">
                <table class="w-full text-xs text-left" id="transfer-table">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3 w-8/12">Part (from Catalog) *</th>
                            <th class="py-2.5 px-3 w-3/12 text-center">Transfer Quantity *</th>
                            <th class="py-2.5 px-2 w-1/12 text-center">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="transfer-items-body" class="divide-y divide-slate-100">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end space-x-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
            <a href="{{ route('parts.transfers.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-md transition flex items-center space-x-2 cursor-pointer">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Initiate Transfer Order</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const availableParts = @json($parts);
    let rowCount = 0;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function addTransferRow(defaultPartId = '', defaultQty = 1) {
        const tbody = document.getElementById('transfer-items-body');
        const rowId = 'trow-' + rowCount;
        const currentIdx = rowCount;

        const defaultPart = defaultPartId ? availableParts.find(p => p.id == defaultPartId) : null;
        const defaultLabel = defaultPart ? `${defaultPart.part_number} — ${defaultPart.name}` : '-- Choose Component --';

        let optionsListHtml = '';
        availableParts.forEach(p => {
            optionsListHtml += `
                <div class="transfer-opt-${rowId} px-3 py-2 hover:bg-sky-50 cursor-pointer rounded flex items-center justify-between transition text-xs"
                     data-search="${(p.part_number + ' ' + p.name + ' ' + (p.unit || '')).toLowerCase()}"
                     onclick="selectTransferPart('${rowId}', ${p.id}, '${escapeHtml(p.part_number)} — ${escapeHtml(p.name)}')">
                    <div class="truncate mr-2">
                        <span class="font-mono font-bold text-slate-800">${escapeHtml(p.part_number)}</span>
                        <span class="text-slate-600 ml-1.5">${escapeHtml(p.name)}</span>
                    </div>
                    <span class="text-[10px] bg-slate-100 text-slate-500 font-mono px-1.5 py-0.5 rounded shrink-0">${escapeHtml(p.unit || 'pcs')}</span>
                </div>
            `;
        });

        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.className = 'hover:bg-slate-50/50';
        tr.innerHTML = `
            <td class="py-2.5 px-3 relative">
                <input type="hidden" name="items[${currentIdx}][part_id]" id="input-${rowId}" value="${defaultPartId}" required>

                <div id="btn-${rowId}" onclick="toggleTransferPartDropdown('${rowId}', event)"
                     class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs text-left bg-white hover:border-sky-500 focus:ring-2 focus:ring-sky-500 flex items-center justify-between cursor-pointer shadow-2xs">
                    <span id="label-${rowId}" class="${defaultPart ? 'text-slate-800 font-bold' : 'text-slate-400'} truncate">
                        ${defaultLabel}
                    </span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 ml-2 shrink-0"></i>
                </div>

                <!-- Dropdown Search Menu -->
                <div id="dropdown-${rowId}" class="transfer-dropdown hidden absolute left-3 right-3 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl z-50 p-2 text-xs">
                    <div class="relative mb-2">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                        <input type="text" id="search-${rowId}" oninput="filterTransferPartOptions('${rowId}')"
                               placeholder="Type part code or name to search..."
                               class="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 bg-slate-50">
                    </div>
                    <div id="list-${rowId}" class="max-h-52 overflow-y-auto divide-y divide-slate-100">
                        ${optionsListHtml}
                        <div id="nomatch-${rowId}" class="hidden px-3 py-4 text-center text-slate-400 text-xs">
                            No matching components found
                        </div>
                    </div>
                </div>
            </td>
            <td class="py-2.5 px-3 text-center">
                <input type="number" min="1" name="items[${currentIdx}][qty]" value="${defaultQty}" required
                       class="w-24 text-center text-xs rounded-lg border-slate-300 py-2 font-bold focus:ring-sky-500 focus:border-sky-500">
            </td>
            <td class="py-2.5 px-2 text-center">
                <button type="button" onclick="document.getElementById('${rowId}').remove()"
                        class="text-slate-400 hover:text-rose-600 transition p-1.5" title="Remove line">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        rowCount++;
    }

    function toggleTransferPartDropdown(rowId, e) {
        if (e) e.stopPropagation();
        const dropdown = document.getElementById(`dropdown-${rowId}`);
        const isHidden = dropdown.classList.contains('hidden');

        document.querySelectorAll('.transfer-dropdown').forEach(d => d.classList.add('hidden'));

        if (isHidden) {
            dropdown.classList.remove('hidden');
            const searchInput = document.getElementById(`search-${rowId}`);
            searchInput.value = '';
            filterTransferPartOptions(rowId);
            setTimeout(() => searchInput.focus(), 50);
        }
    }

    function filterTransferPartOptions(rowId) {
        const q = (document.getElementById(`search-${rowId}`).value || '').toLowerCase().trim();
        const items = document.querySelectorAll(`#list-${rowId} .transfer-opt-${rowId}`);
        let matchCount = 0;
        items.forEach(el => {
            const text = el.getAttribute('data-search') || '';
            if (!q || text.includes(q)) {
                el.style.display = '';
                matchCount++;
            } else {
                el.style.display = 'none';
            }
        });
        const noMatch = document.getElementById(`nomatch-${rowId}`);
        if (noMatch) noMatch.classList.toggle('hidden', matchCount > 0);
    }

    function selectTransferPart(rowId, id, label) {
        document.getElementById(`input-${rowId}`).value = id;
        const labelSpan = document.getElementById(`label-${rowId}`);
        labelSpan.textContent = label;
        labelSpan.classList.remove('text-slate-400');
        labelSpan.classList.add('text-slate-800', 'font-bold');
        document.getElementById(`dropdown-${rowId}`).classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.transfer-dropdown') && !e.target.closest('[id^="btn-trow-"]')) {
            document.querySelectorAll('.transfer-dropdown').forEach(d => d.classList.add('hidden'));
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        addTransferRow();
    });
</script>
@endpush
@endsection
