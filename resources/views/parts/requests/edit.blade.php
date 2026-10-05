@extends('layouts.app')

@section('title', 'Edit Part Request #' . $partRequest->request_number)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">

    {{-- Back Link --}}
    <a href="{{ route('parts.requests.show', $partRequest) }}"
       class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-sky-600 mb-4 transition font-medium">
        <i class="fas fa-arrow-left"></i> Back to Request #{{ $partRequest->request_number }}
    </a>

    {{-- Page Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Edit Part Request</h1>
            <p class="text-xs text-slate-500 mt-0.5">Modifying <span class="font-mono font-bold text-sky-600">{{ $partRequest->request_number }}</span> (Status: {{ $partRequest->status_label }})</p>
        </div>
        <span class="px-3 py-1 text-xs font-bold rounded-full bg-amber-50 text-amber-700 border border-amber-200">
            Editable Before Approval
        </span>
    </div>

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="mb-5 bg-rose-50 border border-rose-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation text-rose-600 mt-0.5"></i>
                <div>
                    <h3 class="text-xs font-bold text-rose-800">Please correct the following:</h3>
                    <ul class="list-disc list-inside text-xs text-rose-700 mt-1 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('parts.requests.update', $partRequest) }}" id="partRequestForm">
        @csrf
        @method('PUT')

        {{-- Section 1: Ticket & Machine --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-5">
            <h2 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-sky-600 text-white flex items-center justify-center text-xs font-bold">1</span>
                Ticket &amp; Machine Details
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Ticket Select --}}
                <div class="md:col-span-2">
                    <label class="text-xs font-semibold text-slate-700">Related Ticket <span class="text-rose-500">*</span></label>
                    <select name="ticket_id" id="ticketSelect" required
                            class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400 @error('ticket_id') border-rose-400 @enderror">
                        <option value="">-- Select Active Ticket --</option>
                        @foreach($tickets as $t)
                            @php
                                $isSel = (old('ticket_id', $partRequest->ticket_id) == $t->id);
                            @endphp
                            <option value="{{ $t->id }}"
                                data-model="{{ $t->machine_model ?? '' }}"
                                data-serial="{{ $t->machine_serial_no ?? '' }}"
                                {{ $isSel ? 'selected' : '' }}>
                                #{{ $t->ticket_no }} — {{ $t->bank_name ?? 'N/A' }} ({{ $t->branch_location ?? 'No branch' }})
                            </option>
                        @endforeach
                    </select>
                    @error('ticket_id')
                        <p class="text-[10px] text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Machine Model Select --}}
                <div>
                    <label class="text-xs font-semibold text-slate-700">Machine Model <span class="text-rose-500">*</span></label>
                    <select name="machine_model_id" id="modelSelect" required
                            class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400 @error('machine_model_id') border-rose-400 @enderror">
                        <option value="">-- Choose Machine Model --</option>
                        @foreach($models as $m)
                            <option value="{{ $m->id }}"
                                data-name="{{ strtolower($m->name) }}"
                                {{ (old('machine_model_id', $partRequest->machine_model_id) == $m->id) ? 'selected' : '' }}>
                                {{ $m->name }} ({{ strtoupper($m->machine_type) }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Select machine model to view catalogued parts</p>
                    @error('machine_model_id')
                        <p class="text-[10px] text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Machine Serial Number (Manual Input) --}}
                <div>
                    <label class="text-xs font-semibold text-slate-700 flex items-center justify-between">
                        <span>Machine Serial Number</span>
                        <span class="text-[11px] text-slate-400 font-normal">Enter manually</span>
                    </label>
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-barcode text-xs"></i>
                        </div>
                        <input type="text" name="machine_serial_no" id="serialNoInput"
                               value="{{ old('machine_serial_no', $partRequest->machine_serial_no) }}"
                               placeholder="e.g. SN-45210, 2025-X01"
                               class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-xs font-mono text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-400">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Add machine serial from physical tag/chassis</p>
                    @error('machine_serial_no')
                        <p class="text-[10px] text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Section 2: Parts Selection --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-5">
            <h2 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold">2</span>
                Select Required Spare Parts
            </h2>

            <div id="partsSection" class="space-y-3">
                {{-- Live Search Filter Inside Section --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-filter text-sky-600"></i>
                            Search Inside Model Parts:
                        </span>
                        <span id="filteredMatchBadge" class="text-[11px] text-slate-500 font-medium"></span>
                    </div>
                    <div class="relative w-full sm:w-80">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                        <input type="text" id="partSearchFilter"
                               oninput="filterPartsTable()"
                               placeholder="Search part code or name (e.g. Belt, Cutter)..."
                               class="w-full pl-8 pr-8 py-1.5 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 bg-white shadow-2xs">
                        <button type="button" onclick="clearPartFilter()" id="clearFilterBtn" class="hidden absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </button>
                    </div>
                </div>

                <div id="partsLoading" class="hidden text-center py-6 text-xs text-slate-500">
                    <i class="fas fa-spinner fa-spin text-sky-600 text-base mb-1"></i>
                    <p>Loading catalogued spare parts for selected model...</p>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-lg max-h-96 overflow-y-auto">
                    <table class="w-full text-xs" id="partsTable">
                        <thead class="sticky top-0 bg-slate-100 border-b border-slate-200 shadow-sm z-10">
                            <tr>
                                <th class="text-center px-3 py-2.5 font-bold text-slate-700 w-12">Select</th>
                                <th class="text-left px-3 py-2.5 font-bold text-slate-700 w-36">Part Code</th>
                                <th class="text-left px-3 py-2.5 font-bold text-slate-700">Part Description</th>
                                <th class="text-center px-3 py-2.5 font-bold text-slate-700 w-16">Unit</th>
                                <th class="text-center px-3 py-2.5 font-bold text-slate-700 w-24">Qty Req.</th>
                                <th class="text-left px-3 py-2.5 font-bold text-slate-700 w-44">Remarks / Note</th>
                            </tr>
                        </thead>
                        <tbody id="partsBody" class="divide-y divide-slate-100">
                            {{-- Populated dynamically by JS --}}
                        </tbody>
                    </table>
                </div>

                <div class="text-xs text-slate-600 mt-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200">
                            <i class="fa-solid fa-circle-check text-xs"></i>
                            <span id="selectedCount">0</span> selected
                        </span>
                        <span class="text-slate-400">|</span>
                        <span class="text-slate-500" id="totalPartsCount">0 total parts</span>
                    </div>
                    <span class="text-[11px] text-slate-400">Only checked parts will be included in the request.</span>
                </div>
            </div>

            @error('items')
                <p class="text-xs text-rose-600 font-semibold mt-2">{{ $message }}</p>
            @enderror
        </div>

        {{-- Section 3: Fault Description --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-5">
            <h2 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold">3</span>
                Fault Description &amp; Reason for Replacement
            </h2>

            <div>
                <label class="text-xs font-semibold text-slate-700">
                    Fault Description <span class="text-rose-500">*</span>
                </label>
                <textarea name="fault_description" required rows="3"
                          class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-sky-400 @error('fault_description') border-rose-400 @enderror"
                          placeholder="Describe the machine defect, error code, or reason why these parts are needed...">{{ old('fault_description', $partRequest->fault_description) }}</textarea>
                @error('fault_description')
                    <p class="text-[10px] text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('parts.requests.show', $partRequest) }}"
               class="text-xs text-slate-500 hover:text-slate-800 transition font-medium">
                <i class="fas fa-times mr-1"></i> Cancel
            </a>
            <button type="submit"
                    id="submitBtn"
                    class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white px-6 py-2.5 rounded-lg font-bold text-xs shadow-md hover:shadow-lg transition">
                <i class="fas fa-save"></i> Save Changes to Part Request
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// Pre-existing selected items from database
const initialSelectedItems = @json($initialSelectedItems);

// Pass engineer advance envelope float stock into JS
const engineerEnvelopeStock = @json($envelopeStockMap);

// Ticket Selection Auto-Fill
document.getElementById('ticketSelect').addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const serial = selectedOption.getAttribute('data-serial') || '';
    const ticketModel = (selectedOption.getAttribute('data-model') || '').toLowerCase().trim();

    if (serial && !document.getElementById('serialNoInput').value) {
        document.getElementById('serialNoInput').value = serial;
    }

    if (ticketModel) {
        const modelSelect = document.getElementById('modelSelect');
        for (let i = 0; i < modelSelect.options.length; i++) {
            const opt = modelSelect.options[i];
            const optName = (opt.getAttribute('data-name') || '').toLowerCase().trim();
            if (optName && (ticketModel === optName || ticketModel.includes(optName) || optName.includes(ticketModel))) {
                modelSelect.selectedIndex = i;
                modelSelect.dispatchEvent(new Event('change'));
                break;
            }
        }
    }
});

// Load parts when Machine Model changes
document.getElementById('modelSelect').addEventListener('change', function () {
    const modelId = this.value;
    const partsLoading = document.getElementById('partsLoading');

    if (!modelId) {
        document.getElementById('partsBody').innerHTML = '';
        document.getElementById('selectedCount').textContent = '0';
        return;
    }

    partsLoading.classList.remove('hidden');
    document.getElementById('partsBody').innerHTML = '';
    document.getElementById('selectedCount').textContent = '0';
    const filterInput = document.getElementById('partSearchFilter');
    if (filterInput) filterInput.value = '';

    fetch(`/parts/api/model-parts/${modelId}`)
        .then(r => r.json())
        .then(parts => {
            partsLoading.classList.add('hidden');
            document.getElementById('totalPartsCount').textContent = `${parts.length} total parts`;

            if (parts.length === 0) {
                document.getElementById('partsBody').innerHTML =
                    `<tr><td colspan="6" class="px-3 py-6 text-center text-slate-400 text-xs">
                        <i class="fas fa-box-open mr-1 text-slate-300 text-lg block mb-1"></i> No spare parts linked to this machine model.
                    </td></tr>`;
                return;
            }

            let html = '';
            parts.forEach((p, i) => {
                const existing = initialSelectedItems[p.id];
                const isChecked = existing ? true : false;
                const qtyVal = existing ? existing.qty : 1;
                const noteVal = existing ? (existing.note || '') : '';
                const inEnv = engineerEnvelopeStock && engineerEnvelopeStock[p.id] ? engineerEnvelopeStock[p.id] : 0;
                const envBadge = inEnv > 0
                    ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 shadow-2xs" title="Carried in your advance envelope float">
                        <i class="fa-solid fa-briefcase text-[9px]"></i> Envelope: ${inEnv} on hand
                       </span>`
                    : '';

                html += `<tr class="hover:bg-slate-50 transition part-row ${isChecked ? 'bg-sky-50/60' : (inEnv > 0 ? 'bg-amber-50/40' : '')}" id="part_row_${i}" data-search="${(p.part_number + ' ' + p.name).toLowerCase()}">
                    <td class="px-3 py-2 text-center">
                        <input type="checkbox" name="items[${i}][part_id]" value="${p.id}"
                               id="chk_${i}"
                               ${isChecked ? 'checked' : ''}
                               onchange="togglePartRow(this, ${i})"
                               class="w-4 h-4 accent-sky-600 rounded cursor-pointer">
                    </td>
                    <td class="px-3 py-2 font-mono text-xs font-bold text-slate-700">${p.part_number ?? '—'}</td>
                    <td class="px-3 py-2">
                        <div class="font-medium text-slate-800 flex items-center flex-wrap gap-1.5">
                            <span>${p.name}</span>
                            ${envBadge}
                        </div>
                    </td>
                    <td class="px-3 py-2 text-center text-slate-500">${p.unit ?? 'pcs'}</td>
                    <td class="px-3 py-2 text-center">
                        <input type="number" name="items[${i}][qty]" id="qty_${i}" min="1" value="${qtyVal}" ${isChecked ? '' : 'disabled'}
                               class="border border-slate-300 rounded px-2 py-1 w-16 text-xs text-center font-bold focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400">
                    </td>
                    <td class="px-3 py-2">
                        <input type="text" name="items[${i}][note]" id="note_${i}" value="${noteVal}" ${isChecked ? '' : 'disabled'}
                               class="border border-slate-300 rounded px-2 py-1 w-full text-xs focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400"
                               placeholder="Note (optional)">
                    </td>
                </tr>`;
            });
            document.getElementById('partsBody').innerHTML = html;
            updateCount();
        })
        .catch(() => {
            partsLoading.classList.add('hidden');
            document.getElementById('partsBody').innerHTML =
                `<tr><td colspan="6" class="px-3 py-4 text-center text-rose-500 text-xs">
                    <i class="fas fa-triangle-exclamation mr-1"></i> Failed to load parts. Please refresh and try again.
                </td></tr>`;
        });
});

function togglePartRow(checkbox, index) {
    const qtyInput = document.getElementById(`qty_${index}`);
    const noteInput = document.getElementById(`note_${index}`);
    const row = document.getElementById(`part_row_${index}`);

    if (checkbox.checked) {
        if (qtyInput) qtyInput.removeAttribute('disabled');
        if (noteInput) noteInput.removeAttribute('disabled');
        if (row) row.classList.add('bg-sky-50/60');
    } else {
        if (qtyInput) qtyInput.setAttribute('disabled', 'disabled');
        if (noteInput) noteInput.setAttribute('disabled', 'disabled');
        if (row) row.classList.remove('bg-sky-50/60');
    }
    updateCount();
}

function updateCount() {
    const checked = document.querySelectorAll('input[type="checkbox"][name*="[part_id]"]:checked').length;
    document.getElementById('selectedCount').textContent = checked;
}

function filterPartsTable() {
    const q = (document.getElementById('partSearchFilter').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#partsBody tr.part-row');
    const clearBtn = document.getElementById('clearFilterBtn');
    const badge = document.getElementById('filteredMatchBadge');

    if (clearBtn) clearBtn.classList.toggle('hidden', !q);

    let matchCount = 0;
    rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
            r.style.display = '';
            matchCount++;
        } else {
            r.style.display = 'none';
        }
    });

    if (badge) {
        badge.textContent = q ? `(Showing ${matchCount} of ${rows.length})` : `(${rows.length} total)`;
    }
}

function clearPartFilter() {
    const input = document.getElementById('partSearchFilter');
    if (input) {
        input.value = '';
        filterPartsTable();
        input.focus();
    }
}

document.getElementById('partRequestForm').addEventListener('submit', function (e) {
    const checked = document.querySelectorAll('input[type="checkbox"][name*="[part_id]"]:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Please select at least one spare part from the list before saving.');
        return false;
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const modelSelect = document.getElementById('modelSelect');
    if (modelSelect && modelSelect.value) {
        modelSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
@endsection
