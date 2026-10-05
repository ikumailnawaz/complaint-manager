@extends('layouts.app')

@section('title', 'Offices & Warehouses - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Top Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-location-dot text-rose-500"></i>
                <span>Offices, Warehouses & Regional Hubs</span>
            </h1>
            <p class="text-xs text-slate-500">Physical inventory hubs where spare parts are stocked and dispatched</p>
        </div>
        <div>
            <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg border border-slate-200 font-semibold">
                {{ $locations->count() }} Locations Configured
            </span>
        </div>
    </div>

    <!-- 2 Column Layout: Locations List (Left) + Add Location (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Locations Table -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Inventory Locations</h2>
                    <span class="text-[11px] text-slate-400">Stock transfers and GRNs are logged per location</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($locations as $loc)
                        <div class="p-4 hover:bg-slate-50/50 transition">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 shrink-0">
                                        <i class="fa-solid {{ $loc->type_icon }} text-base text-rose-500"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <h3 class="text-sm font-bold text-slate-900">{{ $loc->name }}</h3>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                                {{ $loc->type === 'warehouse' ? 'bg-amber-100 text-amber-800' : '' }}
                                                {{ $loc->type === 'hub' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                                {{ $loc->type === 'office' ? 'bg-emerald-100 text-emerald-800' : '' }}">
                                                {{ strtoupper($loc->type) }}
                                            </span>
                                            @if($loc->is_active)
                                                <span class="px-1.5 py-0.2 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded">Active</span>
                                            @else
                                                <span class="px-1.5 py-0.2 bg-slate-100 text-slate-500 text-[10px] font-bold rounded">Inactive</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            <i class="fa-solid fa-city mr-1 text-slate-400"></i> {{ $loc->city ?? 'No city set' }}
                                            @if($loc->address)
                                                &bull; {{ Str::limit($loc->address, 50) }}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2 text-right shrink-0">
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold border border-slate-200">
                                        {{ $loc->grns_count }} GRN Inflows
                                    </span>

                                    <!-- Toggle Edit Form -->
                                    <button onclick="document.getElementById('edit-loc-{{ $loc->id }}').classList.toggle('hidden');" class="p-1.5 text-slate-400 hover:text-sky-600 transition" title="Edit Location">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Hidden Edit Form Drawer -->
                            <div id="edit-loc-{{ $loc->id }}" class="hidden mt-4 pt-3 border-t border-slate-100 bg-slate-50 p-4 rounded-xl">
                                <form action="{{ route('parts.master.locations.update', $loc) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Location Name</label>
                                        <input type="text" name="name" value="{{ $loc->name }}" required class="w-full text-xs rounded-lg border-slate-300">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">City</label>
                                        <input type="text" name="city" value="{{ $loc->city }}" class="w-full text-xs rounded-lg border-slate-300">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Facility Type</label>
                                        <select name="type" class="w-full text-xs rounded-lg border-slate-300">
                                            <option value="office" {{ $loc->type === 'office' ? 'selected' : '' }}>Regional Office</option>
                                            <option value="warehouse" {{ $loc->type === 'warehouse' ? 'selected' : '' }}>Central Warehouse</option>
                                            <option value="hub" {{ $loc->type === 'hub' ? 'selected' : '' }}>Transit Hub</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center pt-5">
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="is_active" value="1" {{ $loc->is_active ? 'checked' : '' }} class="rounded text-emerald-600 h-4 w-4">
                                            <span class="font-semibold text-slate-700">Operational Active</span>
                                        </label>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block font-bold text-slate-700 mb-1">Street Address</label>
                                        <input type="text" name="address" value="{{ $loc->address }}" class="w-full text-xs rounded-lg border-slate-300">
                                    </div>
                                    <div class="sm:col-span-2 flex justify-end space-x-2 pt-2">
                                        <button type="button" onclick="document.getElementById('edit-loc-{{ $loc->id }}').classList.add('hidden')" class="px-3 py-1.5 text-slate-600 hover:bg-slate-200 rounded-lg">Cancel</button>
                                        <button type="submit" class="px-4 py-1.5 bg-sky-600 text-white font-bold rounded-lg hover:bg-sky-700">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 text-xs">
                            No locations registered yet. Use the form on the right to add the first office.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Add Location Card -->
        <div class="space-y-4">
            @if(auth()->user()->isAdmin())
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h2 class="text-sm font-bold text-slate-900 mb-1 flex items-center space-x-1.5">
                    <i class="fa-solid fa-plus-circle text-emerald-600"></i>
                    <span>Register New Location</span>
                </h2>
                <p class="text-xs text-slate-500 mb-4">Add a new regional office or warehouse hub</p>

                <form action="{{ route('parts.master.locations.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Location Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="name" required placeholder="e.g. Multan Regional Hub"
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            City
                        </label>
                        <input type="text" name="city" placeholder="e.g. Multan, Peshawar, Quetta"
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Facility Type <span class="text-rose-500">*</span>
                        </label>
                        <select name="type" required class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                            <option value="office">Regional Office</option>
                            <option value="warehouse">Central Warehouse</option>
                            <option value="hub">Transit Hub</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Physical Address
                        </label>
                        <textarea name="address" rows="2" placeholder="Street, Plaza, City details..."
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm"></textarea>
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-md transition flex items-center justify-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-save"></i>
                        <span>Save Location</span>
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
