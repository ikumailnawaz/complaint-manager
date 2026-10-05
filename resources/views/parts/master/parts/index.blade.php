@extends('layouts.app')

@section('title', 'Parts Catalog')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i class="fa-solid fa-circle-check text-emerald-500"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Parts Catalog</h1>
            <p class="mt-0.5 text-xs text-slate-500">Manage all spare parts, inventory levels and compatibility.</p>
        </div>
        <a href="{{ route('parts.master.parts.create') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700 transition-colors">
            <i class="fa-solid fa-plus"></i>
            Add New Part
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        {{-- Total Parts --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total Parts</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100">
                    <i class="fa-solid fa-boxes-stacked text-slate-600 text-sm"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-slate-800">{{ $parts->total() }}</p>
            <p class="mt-0.5 text-xs text-slate-400">across all categories</p>
        </div>

        {{-- Active Parts --}}
        <div class="rounded-xl border border-emerald-100 bg-white p-4 shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Active</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $activeParts ?? '—' }}</p>
            <p class="mt-0.5 text-xs text-slate-400">currently active</p>
        </div>

        {{-- Low Stock --}}
        <div class="rounded-xl border border-amber-100 bg-white p-4 shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Low Stock</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-amber-700">{{ $lowStockCount ?? '—' }}</p>
            <p class="mt-0.5 text-xs text-slate-400">below reorder level</p>
        </div>

        {{-- Out of Stock --}}
        <div class="rounded-xl border border-rose-100 bg-white p-4 shadow">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Out of Stock</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100">
                    <i class="fa-solid fa-circle-xmark text-rose-600 text-sm"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-rose-700">{{ $outOfStockCount ?? '—' }}</p>
            <p class="mt-0.5 text-xs text-slate-400">zero inventory</p>
        </div>
    </div>

    {{-- Search & Filter --}}
    <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow">
        <form method="GET" action="{{ route('parts.master.parts') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-48">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search part no. or name…"
                       class="w-full rounded-lg border border-slate-200 py-2 pl-8 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400">
            </div>
            <select name="active"
                    class="rounded-lg border border-slate-200 py-2 pl-3 pr-8 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400">
                <option value="">All Status</option>
                <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active Only</option>
                <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-700 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition-colors">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <a href="{{ route('parts.master.parts') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </a>
        </form>
    </div>

    {{-- Parts Table --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow overflow-hidden">
        @if($parts->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <i class="fa-solid fa-box-open text-4xl text-slate-300 mb-3"></i>
                <p class="text-sm font-medium text-slate-500">No parts found.</p>
                <p class="mt-1 text-xs text-slate-400">Try adjusting your search or add a new part.</p>
                <a href="{{ route('parts.master.parts.create') }}"
                   class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors">
                    <i class="fa-solid fa-plus"></i> Add New Part
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 uppercase tracking-wide">Part No.</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 uppercase tracking-wide">Name</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 uppercase tracking-wide">Unit</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600 uppercase tracking-wide">Unit Cost (PKR)</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600 uppercase tracking-wide">Reorder Lvl</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600 uppercase tracking-wide">Total Stock</th>
                            <th class="px-4 py-3 text-center font-semibold text-slate-600 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-center font-semibold text-slate-600 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($parts as $part)
                            @php $stock = $part->totalStock(); @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                {{-- Part Number --}}
                                <td class="px-4 py-3">
                                    <span class="font-mono font-semibold text-slate-700">{{ $part->part_number }}</span>
                                </td>

                                {{-- Name --}}
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800">{{ $part->name }}</div>
                                    @if($part->description)
                                        <div class="text-slate-400 truncate max-w-48" title="{{ $part->description }}">{{ $part->description }}</div>
                                    @endif
                                </td>

                                {{-- Unit --}}
                                <td class="px-4 py-3">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-600 font-medium">{{ $part->unit }}</span>
                                </td>

                                {{-- Unit Cost --}}
                                <td class="px-4 py-3 text-right font-medium text-slate-700">
                                    PKR {{ number_format($part->unit_cost, 2) }}
                                </td>

                                {{-- Reorder Level --}}
                                <td class="px-4 py-3 text-right text-slate-600">
                                    {{ $part->reorder_level ?? '—' }}
                                </td>

                                {{-- Total Stock --}}
                                <td class="px-4 py-3 text-right">
                                    @if($stock == 0)
                                        <span class="inline-block rounded-md bg-rose-100 px-2 py-0.5 font-semibold text-rose-700">{{ $stock }}</span>
                                    @elseif($part->reorder_level && $stock <= $part->reorder_level)
                                        <span class="inline-block rounded-md bg-amber-100 px-2 py-0.5 font-semibold text-amber-700">{{ $stock }}</span>
                                    @else
                                        <span class="font-medium text-slate-700">{{ $stock }}</span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3 text-center">
                                    @if($part->is_active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-emerald-700 font-medium">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-600 font-medium">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Inactive
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        {{-- Edit --}}
                                        <a href="{{ route('parts.master.parts.edit', $part) }}"
                                           class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors"
                                           title="Edit Part">
                                            <i class="fa-solid fa-pencil text-xs"></i>
                                        </a>

                                        {{-- Toggle Active --}}
                                        <form method="POST" action="{{ route('parts.master.parts.toggle', $part) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex h-7 items-center gap-1 rounded-lg px-2 {{ $part->is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }} transition-colors"
                                                    title="{{ $part->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="fa-solid {{ $part->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }} text-xs"></i>
                                                <span class="text-xs font-medium">{{ $part->is_active ? 'Deactivate' : 'Activate' }}</span>
                                            </button>
                                        </form>

                                        {{-- Delete --}}
                                        <form method="POST" action="{{ route('parts.master.parts.destroy', $part) }}"
                                              onsubmit="return confirm('Are you sure you want to delete part \'{{ addslashes($part->name) }}\'? This action cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors"
                                                    title="Delete Part">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($parts->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">
                    {{ $parts->appends(request()->query())->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Showing count --}}
    @if(!$parts->isEmpty())
        <p class="mt-3 text-xs text-slate-400 text-right">
            Showing {{ $parts->firstItem() }}–{{ $parts->lastItem() }} of {{ $parts->total() }} parts
        </p>
    @endif

</div>
@endsection
