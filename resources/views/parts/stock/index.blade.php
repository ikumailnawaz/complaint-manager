@extends('layouts.app')
@section('title', 'Parts Stock Levels')

@section('content')
<style>
    @media print { nav, header, .no-print { display:none !important; } }
</style>

<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 flex items-center gap-2 no-print">
            <i class="fas fa-check-circle text-emerald-500"></i>
            <span class="text-xs font-medium">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 flex items-center gap-2 no-print">
            <i class="fas fa-exclamation-circle text-rose-500"></i>
            <span class="text-xs font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Parts Stock Levels</h1>
            <p class="text-xs text-slate-500 mt-0.5">Real-time inventory across all locations</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 no-print">
            <a href="{{ route('parts.stock.movements') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg transition">
                <i class="fas fa-history"></i> View Movements
            </a>
            <button id="openAdjustment"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg transition">
                <i class="fas fa-sliders-h"></i> Manual Adjustment
            </button>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Parts --}}
        <div class="bg-white rounded-xl shadow border border-slate-200 p-4 flex items-center gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                <i class="fas fa-boxes text-slate-600 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-medium">Total Parts</p>
                <p class="text-2xl font-bold text-slate-800">{{ $totalParts }}</p>
            </div>
        </div>

        {{-- Out of Stock --}}
        <div class="bg-rose-50 rounded-xl shadow border border-rose-200 p-4 flex items-center gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-rose-100 flex items-center justify-center">
                <i class="fas fa-times-circle text-rose-600 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-rose-600 font-medium">Out of Stock</p>
                <p class="text-2xl font-bold text-rose-700">{{ $outOfStock }}</p>
            </div>
        </div>

        {{-- Low Stock Alerts --}}
        <div class="bg-amber-50 rounded-xl shadow border border-amber-200 p-4 flex items-center gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-amber-600 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-amber-600 font-medium">Low Stock Alerts</p>
                <p class="text-2xl font-bold text-amber-700">{{ $lowStock }}</p>
            </div>
        </div>

        {{-- Total Inventory Value --}}
        <div class="bg-emerald-50 rounded-xl shadow border border-emerald-200 p-4 flex items-center gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                <i class="fas fa-coins text-emerald-600 text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-emerald-600 font-medium">Total Inventory Value</p>
                <p class="text-lg font-bold text-emerald-700">PKR {{ number_format($totalValue) }}</p>
            </div>
        </div>
    </div>

    {{-- Stock Grid Table --}}
    <div class="bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center text-xs font-bold">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Multi-Location Stock Grid</h2>
                    <p id="stockFilterBadge" class="text-[11px] text-slate-500 font-medium">Showing all {{ count($parts) }} catalogued parts</p>
                </div>
            </div>

            {{-- Live Search & Filters --}}
            <div class="flex flex-wrap items-center gap-2.5">
                {{-- Machine Model Filter --}}
                <div class="relative">
                    <select id="modelFilterSelect" onchange="filterStockTable()"
                            class="text-xs bg-white border border-slate-300 rounded-lg px-3 py-1.5 font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 shadow-2xs">
                        <option value="">-- All Machine Models ({{ count($models) }}) --</option>
                        @foreach($models as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} ({{ strtoupper($m->machine_type) }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Stock Status Filter --}}
                <div class="relative">
                    <select id="stockStatusSelect" onchange="filterStockTable()"
                            class="text-xs bg-white border border-slate-300 rounded-lg px-3 py-1.5 font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 shadow-2xs">
                        <option value="all">All Quantities</option>
                        <option value="in_stock">In Stock (> 0)</option>
                        <option value="out_of_stock">Out of Stock (0)</option>
                        <option value="low_stock">Low Stock (≤ Reorder)</option>
                    </select>
                </div>

                {{-- Live Search Input --}}
                <div class="relative w-full sm:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                    <input type="text" id="stockTableSearch" oninput="filterStockTable()"
                           placeholder="Search part code or name..."
                           class="w-full pl-8 pr-8 py-1.5 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 shadow-2xs">
                    <button type="button" onclick="clearStockSearch()" id="clearStockSearchBtn" class="hidden absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">Part No.</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">Part Name</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">Machine Model</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Unit</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">Reorder Lvl</th>
                        @foreach($locations as $loc)
                            <th class="text-center px-4 py-3 font-semibold text-slate-600 whitespace-nowrap">{{ $loc->name }}</th>
                        @endforeach
                        <th class="text-center px-4 py-3 font-semibold text-slate-800 whitespace-nowrap">Grand Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($parts as $part)
                        <tr class="hover:bg-slate-50 transition stock-row"
                            data-search="{{ strtolower($part->part_number . ' ' . $part->name . ' ' . $part->machineModels->pluck('name')->join(' ')) }}"
                            data-models="{{ $part->machineModels->pluck('id')->join(',') }}"
                            data-total="{{ $part->totalStock() }}"
                            data-reorder="{{ $part->reorder_level ?? 0 }}">
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-700 font-bold whitespace-nowrap">
                                {{ $part->part_number }}
                            </td>
                            <td class="px-4 py-2.5 text-slate-800 font-medium whitespace-nowrap">{{ $part->name }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                @if($part->machineModels->isNotEmpty())
                                    @foreach($part->machineModels as $mm)
                                        <span class="inline-block bg-slate-100 text-slate-700 font-semibold px-2 py-0.5 rounded text-[10px] mr-1">{{ $mm->name }}</span>
                                    @endforeach
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $part->unit }}</td>
                            <td class="px-4 py-2.5 text-center text-slate-500">{{ $part->reorder_level ?? '—' }}</td>
                            @foreach($locations as $loc)
                                @php
                                    $ledger   = $grid[$part->id][$loc->id] ?? null;
                                    $qty      = $ledger ? $ledger->qty_on_hand : 0;
                                    $cellClass = $qty <= 0
                                        ? 'bg-rose-50 text-rose-700 font-bold'
                                        : ($qty <= $part->reorder_level && $part->reorder_level > 0
                                            ? 'bg-amber-50 text-amber-700 font-bold'
                                            : 'text-slate-700');
                                @endphp
                                <td class="{{ $cellClass }} text-center px-2 py-2 whitespace-nowrap">
                                    {{ $qty }}
                                    @if($qty <= 0)
                                        <span title="Out of stock">🔴</span>
                                    @elseif($qty <= $part->reorder_level && $part->reorder_level > 0)
                                        <span title="Low stock">⚠️</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center px-4 py-2.5 font-bold text-slate-800">
                                {{ $part->totalStock() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + count($locations) + 1 }}" class="text-center py-10 text-slate-400">
                                <i class="fas fa-box-open text-2xl mb-2 block"></i>
                                No parts found in inventory.
                            </td>
                        </tr>
                    @endforelse
                    <tr id="stockNoMatchRow" class="hidden">
                        <td colspan="{{ 5 + count($locations) + 1 }}" class="text-center py-10 text-slate-400 bg-slate-50/50">
                            <i class="fas fa-search text-2xl mb-2 text-slate-300 block"></i>
                            <p class="text-xs font-semibold text-slate-600">No spare parts match your filter criteria.</p>
                            <button type="button" onclick="resetStockFilters()" class="mt-2 text-xs text-sky-600 hover:underline font-medium">Reset all filters</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Manual Adjustment Modal --}}
<div id="adjustmentModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center no-print">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-slate-800">
                <i class="fas fa-sliders-h text-amber-500 mr-2"></i>Manual Stock Adjustment
            </h3>
            <button type="button"
                    onclick="document.getElementById('adjustmentModal').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('parts.stock.adjustment') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Part <span class="text-rose-500">*</span></label>
                <input type="hidden" name="part_id" id="adj_part_id" required>

                {{-- Trigger Button / Selected display --}}
                <div class="relative">
                    <button type="button" id="adjPartTrigger" onclick="toggleAdjDropdown(event)"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs text-left bg-white hover:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-400 flex items-center justify-between shadow-2xs">
                        <span id="adjPartLabel" class="text-slate-400 truncate">-- Search &amp; Select Part from Catalog --</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 ml-2 shrink-0"></i>
                    </button>

                    {{-- Searchable Dropdown Menu --}}
                    <div id="adjPartDropdown" class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl z-50 p-2 text-xs">
                        <div class="relative mb-2">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
                            <input type="text" id="adjPartSearch" oninput="filterAdjPartOptions()"
                                   placeholder="Type code or name to search..."
                                   class="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 bg-slate-50">
                        </div>
                        <div id="adjPartsList" class="max-h-52 overflow-y-auto divide-y divide-slate-100">
                            @foreach($parts as $p)
                                <div class="adj-part-opt px-3 py-2 hover:bg-amber-50 cursor-pointer rounded flex items-center justify-between transition"
                                     data-id="{{ $p->id }}"
                                     data-label="{{ $p->part_number }} — {{ $p->name }}"
                                     data-search="{{ strtolower($p->part_number . ' ' . $p->name . ' ' . ($p->unit ?? '')) }}"
                                     onclick="selectAdjPart('{{ $p->id }}', '{{ addslashes($p->part_number) }} — {{ addslashes($p->name) }}')">
                                    <div class="truncate mr-2">
                                        <span class="font-mono font-bold text-slate-800">{{ $p->part_number }}</span>
                                        <span class="text-slate-600 ml-1.5">{{ $p->name }}</span>
                                    </div>
                                    <span class="text-[10px] bg-slate-100 text-slate-500 font-mono px-1.5 py-0.5 rounded shrink-0">{{ $p->unit ?? 'pcs' }}</span>
                                </div>
                            @endforeach
                            <div id="adjNoMatch" class="hidden px-3 py-4 text-center text-slate-400 text-xs">
                                No parts found matching search
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Location <span class="text-rose-500">*</span></label>
                <select name="location_id" required
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none">
                    <option value="">Select a location...</option>
                    @foreach($locations as $l)
                        <option value="{{ $l->id }}">{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">New Quantity <span class="text-rose-500">*</span></label>
                <input type="number" name="qty_new" min="0" required
                       placeholder="Enter new on-hand quantity"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Reason / Note <span class="text-rose-500">*</span></label>
                <input type="text" name="note" required
                       placeholder="e.g. Physical count discrepancy"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs px-4 py-2.5 rounded-lg transition">
                    <i class="fas fa-check mr-1.5"></i>Apply Adjustment
                </button>
                <button type="button"
                        onclick="document.getElementById('adjustmentModal').classList.add('hidden')"
                        class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs px-4 py-2.5 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('openAdjustment').addEventListener('click', () => {
        document.getElementById('adjustmentModal').classList.remove('hidden');
    });

    // Close modal on backdrop click
    document.getElementById('adjustmentModal').addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });

    function toggleAdjDropdown(e) {
        if (e) e.stopPropagation();
        const dropdown = document.getElementById('adjPartDropdown');
        const isHidden = dropdown.classList.contains('hidden');
        dropdown.classList.toggle('hidden', !isHidden);
        if (isHidden) {
            const searchInput = document.getElementById('adjPartSearch');
            searchInput.value = '';
            filterAdjPartOptions();
            setTimeout(() => searchInput.focus(), 50);
        }
    }

    function filterAdjPartOptions() {
        const q = (document.getElementById('adjPartSearch').value || '').toLowerCase().trim();
        const items = document.querySelectorAll('#adjPartsList .adj-part-opt');
        let visibleCount = 0;
        items.forEach(el => {
            const searchStr = el.getAttribute('data-search') || '';
            if (!q || searchStr.includes(q)) {
                el.style.display = '';
                visibleCount++;
            } else {
                el.style.display = 'none';
            }
        });
        document.getElementById('adjNoMatch').classList.toggle('hidden', visibleCount > 0);
    }

    function selectAdjPart(id, label) {
        document.getElementById('adj_part_id').value = id;
        const labelSpan = document.getElementById('adjPartLabel');
        labelSpan.textContent = label;
        labelSpan.classList.remove('text-slate-400');
        labelSpan.classList.add('text-slate-800', 'font-bold');
        document.getElementById('adjPartDropdown').classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('adjPartDropdown');
        const trigger = document.getElementById('adjPartTrigger');
        if (dropdown && !dropdown.contains(e.target) && !trigger.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Multi-Location Table Live Filters
    function filterStockTable() {
        const q = (document.getElementById('stockTableSearch').value || '').toLowerCase().trim();
        const selectedModel = document.getElementById('modelFilterSelect').value;
        const stockStatus = document.getElementById('stockStatusSelect').value;
        const clearBtn = document.getElementById('clearStockSearchBtn');
        if (clearBtn) clearBtn.classList.toggle('hidden', !q);

        const rows = document.querySelectorAll('tbody tr.stock-row');
        let visibleCount = 0;

        rows.forEach(r => {
            const text = (r.getAttribute('data-search') || '').toLowerCase();
            const models = (r.getAttribute('data-models') || '').split(',');
            const total = parseInt(r.getAttribute('data-total') || '0', 10);
            const reorder = parseInt(r.getAttribute('data-reorder') || '0', 10);

            const matchesSearch = !q || text.includes(q);
            const matchesModel = !selectedModel || models.includes(selectedModel);
            let matchesStatus = true;

            if (stockStatus === 'in_stock') {
                matchesStatus = total > 0;
            } else if (stockStatus === 'out_of_stock') {
                matchesStatus = total <= 0;
            } else if (stockStatus === 'low_stock') {
                matchesStatus = reorder > 0 && total <= reorder;
            }

            if (matchesSearch && matchesModel && matchesStatus) {
                r.style.display = '';
                visibleCount++;
            } else {
                r.style.display = 'none';
            }
        });

        const badge = document.getElementById('stockFilterBadge');
        if (badge) {
            badge.textContent = `Showing ${visibleCount} of ${rows.length} parts`;
        }

        const noMatchRow = document.getElementById('stockNoMatchRow');
        if (noMatchRow) {
            noMatchRow.classList.toggle('hidden', visibleCount > 0);
        }
    }

    function clearStockSearch() {
        const input = document.getElementById('stockTableSearch');
        if (input) {
            input.value = '';
            filterStockTable();
            input.focus();
        }
    }

    function resetStockFilters() {
        document.getElementById('stockTableSearch').value = '';
        document.getElementById('modelFilterSelect').value = '';
        document.getElementById('stockStatusSelect').value = 'all';
        filterStockTable();
    }
</script>
@endsection
