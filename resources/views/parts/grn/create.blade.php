@extends('layouts.app')

@section('title', 'Create GRN - Receive Spare Parts - Bank Complaint Manager')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top Ribbon -->
    <div class="flex items-center justify-between bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('parts.grn.index') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900">Goods Received Note (GRN)</h1>
                <p class="text-xs text-slate-500">Record incoming shipment of spare components into an office or warehouse</p>
            </div>
        </div>
        <a href="{{ route('parts.grn.index') }}" class="text-xs text-slate-600 hover:text-slate-900 font-semibold px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 transition">
            Cancel
        </a>
    </div>

    <form id="grn-form" action="{{ route('parts.grn.store') }}" method="POST" class="space-y-6">
        @csrf

        <!-- Shipment Header Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2">
                1. Shipment & Vendor Details
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Receiving Location -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Receiving Location <span class="text-rose-500">*</span>
                    </label>
                    <select name="location_id" required class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                        <option value="">-- Select Hub --</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }} ({{ $loc->city }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Supplier Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Supplier / Vendor Name
                    </label>
                    <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" placeholder="e.g. NCR OEM Parts Direct"
                        class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                </div>

                <!-- Received Date -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Received Date <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="received_at" value="{{ old('received_at', date('Y-m-d')) }}" required
                        class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                </div>

                <!-- Invoice Number -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Vendor Invoice Number
                    </label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" placeholder="e.g. INV-9842"
                        class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm font-mono">
                </div>

                <!-- Invoice Date -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Invoice Date
                    </label>
                    <input type="date" name="invoice_date" value="{{ old('invoice_date') }}"
                        class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                </div>

                <!-- Remarks -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Delivery Remarks / Notes
                    </label>
                    <input type="text" name="remarks" value="{{ old('remarks') }}" placeholder="Courier airway bill, packing condition..."
                        class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                    2. Received Parts Inventory Items
                </h2>
                <button type="button" onclick="addGrnRow()" class="px-3 py-1 bg-sky-600 hover:bg-sky-700 text-white rounded text-xs font-bold shadow-2xs transition flex items-center space-x-1 cursor-pointer">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add Item Row</span>
                </button>
            </div>

            <div class="overflow-x-visible pb-16">
                <table class="w-full text-xs text-left" id="grn-items-table">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3 w-5/12">Part (from Catalog) *</th>
                            <th class="py-2.5 px-3 w-2/12 text-center">Qty Received *</th>
                            <th class="py-2.5 px-3 w-2/12 text-right">Unit Cost (PKR)</th>
                            <th class="py-2.5 px-3 w-2/12">Condition</th>
                            <th class="py-2.5 px-2 w-1/12 text-center">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="grn-items-body" class="divide-y divide-slate-100">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end space-x-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
            <a href="{{ route('parts.grn.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-md transition flex items-center space-x-2 cursor-pointer">
                <i class="fa-solid fa-file-circle-check"></i>
                <span>Save GRN as Draft</span>
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

    function addGrnRow(defaultPartId = '', defaultQty = 1, defaultCost = '') {
        const tbody = document.getElementById('grn-items-body');
        const rowId = 'row-' + rowCount;
        const currentIdx = rowCount;

        const defaultPart = defaultPartId ? availableParts.find(p => p.id == defaultPartId) : null;
        const defaultLabel = defaultPart ? `${defaultPart.part_number} — ${defaultPart.name}` : '-- Choose Part --';

        let optionsListHtml = '';
        availableParts.forEach(p => {
            optionsListHtml += `
                <div class="grn-opt-${rowId} px-3 py-2 hover:bg-sky-50 cursor-pointer rounded flex items-center justify-between transition text-xs"
                     data-search="${(p.part_number + ' ' + p.name + ' ' + (p.unit || '')).toLowerCase()}"
                     onclick="selectGrnPart('${rowId}', ${p.id}, '${escapeHtml(p.part_number)} — ${escapeHtml(p.name)}', '${p.unit_cost || ''}', ${currentIdx})">
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

                <div id="btn-${rowId}" onclick="toggleGrnPartDropdown('${rowId}', event)"
                     class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs text-left bg-white hover:border-sky-500 focus:ring-2 focus:ring-sky-500 flex items-center justify-between cursor-pointer shadow-2xs">
                    <span id="label-${rowId}" class="${defaultPart ? 'text-slate-800 font-bold' : 'text-slate-400'} truncate">
                        ${defaultLabel}
                    </span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 ml-2 shrink-0"></i>
                </div>

                <!-- Dropdown Search Menu -->
                <div id="dropdown-${rowId}" class="grn-dropdown hidden absolute left-3 right-3 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl z-50 p-2 text-xs">
                    <div class="relative mb-2">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                        <input type="text" id="search-${rowId}" oninput="filterGrnPartOptions('${rowId}')"
                               placeholder="Type part code or name to search..."
                               class="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 bg-slate-50">
                    </div>
                    <div id="list-${rowId}" class="max-h-52 overflow-y-auto divide-y divide-slate-100">
                        ${optionsListHtml}
                        <div id="nomatch-${rowId}" class="hidden px-3 py-4 text-center text-slate-400 text-xs">
                            No matching parts found
                        </div>
                    </div>
                </div>
            </td>
            <td class="py-2.5 px-3 text-center">
                <input type="number" min="1" name="items[${currentIdx}][qty_received]" value="${defaultQty}" required
                       class="w-24 text-center text-xs rounded-lg border-slate-300 py-2 font-bold focus:ring-sky-500 focus:border-sky-500">
            </td>
            <td class="py-2.5 px-3 text-right">
                <input type="number" step="0.01" min="0" name="items[${currentIdx}][unit_cost]" id="cost-${currentIdx}" value="${defaultCost}"
                       placeholder="0.00" class="w-32 text-right text-xs rounded-lg border-slate-300 py-2 font-mono focus:ring-sky-500 focus:border-sky-500">
            </td>
            <td class="py-2.5 px-3">
                <select name="items[${currentIdx}][condition]" class="w-full text-xs rounded-lg border-slate-300 py-2">
                    <option value="new">Brand New (OEM)</option>
                    <option value="refurbished">Refurbished / Tested</option>
                    <option value="used">Used / Functional</option>
                </select>
            </td>
            <td class="py-2.5 px-2 text-center">
                <button type="button" onclick="removeRow('${rowId}')" class="text-slate-400 hover:text-rose-600 transition p-1.5" title="Remove row">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        rowCount++;
    }

    function toggleGrnPartDropdown(rowId, e) {
        if (e) e.stopPropagation();
        const dropdown = document.getElementById(`dropdown-${rowId}`);
        const isHidden = dropdown.classList.contains('hidden');

        document.querySelectorAll('.grn-dropdown').forEach(d => d.classList.add('hidden'));

        if (isHidden) {
            dropdown.classList.remove('hidden');
            const searchInput = document.getElementById(`search-${rowId}`);
            searchInput.value = '';
            filterGrnPartOptions(rowId);
            setTimeout(() => searchInput.focus(), 50);
        }
    }

    function filterGrnPartOptions(rowId) {
        const q = (document.getElementById(`search-${rowId}`).value || '').toLowerCase().trim();
        const items = document.querySelectorAll(`#list-${rowId} .grn-opt-${rowId}`);
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

    function selectGrnPart(rowId, id, label, unitCost, idx) {
        document.getElementById(`input-${rowId}`).value = id;
        const labelSpan = document.getElementById(`label-${rowId}`);
        labelSpan.textContent = label;
        labelSpan.classList.remove('text-slate-400');
        labelSpan.classList.add('text-slate-800', 'font-bold');
        document.getElementById(`dropdown-${rowId}`).classList.add('hidden');

        const costInput = document.getElementById('cost-' + idx);
        if (unitCost && costInput && !costInput.value) {
            costInput.value = unitCost;
        }
    }

    function removeRow(rowId) {
        const row = document.getElementById(rowId);
        if (row) {
            row.remove();
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.grn-dropdown') && !e.target.closest('[id^="btn-row-"]')) {
            document.querySelectorAll('.grn-dropdown').forEach(d => d.classList.add('hidden'));
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        addGrnRow();
    });
</script>
@endpush
@endsection
