@extends('layouts.app')

@section('title', 'Field Engineers Directory & Roster - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="p-2 bg-indigo-100 text-indigo-700 rounded-xl text-sm">
                    <i class="fa-solid fa-user-gear"></i>
                </span>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">
                    Field Engineers Directory &amp; Roster
                </h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-10">
                Master technician database for manual complaint alignment, real-time workload monitoring, availability status, and SLA compliance.
            </p>
        </div>
        <div class="text-xs text-slate-500 bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl">
            Total Technicians in DB: <strong class="text-slate-900 font-extrabold">{{ $engineers->total() }}</strong>
        </div>
    </div>

    <!-- Data Table Container with Filters & "Show By" Selector -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

        <!-- Top Table Controls: Search, Filters, and "Show by" entries -->
        <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50/75 space-y-3">
            <form action="{{ route('engineers.index') }}" method="GET" class="space-y-3">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs">
                    
                    <!-- Search (5 cols) -->
                    <div class="lg:col-span-5">
                        <label class="block font-semibold text-slate-700 mb-1">Search Engineers</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}" 
                                placeholder="Search Name, Base City, Phone, or Specialization..." 
                                class="w-full pl-9 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 transition">
                        </div>
                    </div>

                    <!-- City Filter (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Base City</label>
                        <input type="text" name="city" value="{{ request('city') }}" placeholder="e.g. Lahore, Karachi" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Availability Status Filter (2 cols) -->
                    <div class="lg:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Availability</label>
                        <select name="status" class="w-full bg-white border border-slate-300 rounded-xl py-2 px-2.5 text-xs focus:ring-2 focus:ring-indigo-500" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Available for Assignment</option>
                            <option value="on_leave" {{ request('status') === 'on_leave' ? 'selected' : '' }}>On Leave / Unavailable</option>
                        </select>
                    </div>

                    <!-- SHOW BY PER PAGE SELECTOR (3 cols) -->
                    <div class="lg:col-span-3">
                        <label class="block font-semibold text-slate-700 mb-1">
                            <i class="fa-solid fa-table-cells mr-1 text-slate-400"></i> Show by:
                        </label>
                        <select name="per_page" class="w-full bg-white border-2 border-slate-300 rounded-xl py-2 px-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500" onchange="this.form.submit()">
                            <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10 engineers per page</option>
                            <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 engineers per page</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 engineers per page</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 engineers per page</option>
                            <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>Show All</option>
                        </select>
                    </div>

                </div>

                <div class="flex items-center justify-between pt-1 text-xs">
                    <div class="flex items-center space-x-2">
                        <button type="submit" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-lg text-xs transition">
                            <i class="fa-solid fa-filter mr-1"></i> Apply Filters
                        </button>
                        <a href="{{ route('engineers.index') }}" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-lg text-xs transition">
                            Reset
                        </a>
                    </div>
                </div>

            </form>
        </div>

        <!-- High-Capacity Engineers Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 uppercase font-bold tracking-wider text-[11px]">
                        <th class="py-3 px-4">Technician Name</th>
                        <th class="py-3 px-4">Base City</th>
                        <th class="py-3 px-4">Hardware Specialization</th>
                        <th class="py-3 px-4">WhatsApp Contact</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-center whitespace-nowrap">Active Load</th>
                        <th class="py-3 px-3 text-center whitespace-nowrap">Resolved</th>
                        <th class="py-3 px-3 text-center whitespace-nowrap">Escalated</th>
                        <th class="py-3 px-4 text-right">Performance Report</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($engineers as $eng)
                        <tr class="hover:bg-slate-50 transition">
                            
                            <!-- Name & Avatar -->
                            <td class="py-3.5 px-4 font-bold text-slate-900 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-xl bg-slate-800 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                                        {{ substr($eng->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('engineers.show', $eng) }}" class="text-slate-900 hover:text-indigo-600 font-extrabold text-xs">
                                            {{ $eng->name }}
                                        </a>
                                        <div class="text-[10px] text-slate-400 font-normal mt-0.5">{{ $eng->email }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Base City -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-bold text-slate-800">{{ $eng->base_city }}</div>
                                <div class="text-[10px] text-slate-400">Pakistan</div>
                            </td>

                            <!-- Specialization -->
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded font-semibold text-[11px] bg-slate-100 text-slate-800 inline-block border border-slate-200">
                                    {{ $eng->specialization ?? 'General Hardware' }}
                                </span>
                            </td>

                            <!-- WhatsApp Contact -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center space-x-1.5 font-mono text-xs">
                                    <i class="fa-brands fa-whatsapp text-emerald-600 text-sm"></i>
                                    <strong class="text-emerald-800">{{ $eng->phone_whatsapp }}</strong>
                                </div>
                            </td>

                            <!-- Availability Toggle -->
                            <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                <form action="{{ route('engineers.toggle-availability', $eng) }}" method="POST">
                                    @csrf
                                    <button type="submit" title="Click to toggle availability" class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase transition {{ $eng->is_available ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-rose-100 text-rose-800 hover:bg-rose-200' }}">
                                        <i class="fa-solid fa-circle text-[7px] mr-1"></i> {{ $eng->is_available ? 'Available' : 'On Leave' }}
                                    </button>
                                </form>
                            </td>

                            <!-- Active Load -->
                            <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-xs font-extrabold {{ $eng->active_count > 0 ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $eng->active_count }}
                                </span>
                            </td>

                            <!-- Resolved -->
                            <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-xs font-extrabold text-emerald-700 bg-emerald-50">
                                    {{ $eng->resolved_count }}
                                </span>
                            </td>

                            <!-- Escalated -->
                            <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-xs font-extrabold {{ $eng->escalated_count > 0 ? 'bg-rose-100 text-rose-800' : 'text-slate-400 bg-slate-50' }}">
                                    {{ $eng->escalated_count }}
                                </span>
                            </td>

                            <!-- Performance Report Action -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <a href="{{ route('engineers.show', $eng) }}" class="inline-flex items-center px-3 py-1.5 bg-slate-900 hover:bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                    <span>Dossier</span>
                                    <i class="fa-solid fa-arrow-right ml-1.5 text-[9px]"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400 text-xs">
                                <i class="fa-solid fa-user-xmark text-3xl text-slate-300 mb-2 block"></i>
                                No field technicians found matching search criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Footer -->
        <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
            <div>
                Showing <strong class="text-slate-900">{{ $engineers->firstItem() ?? 0 }}</strong> to <strong class="text-slate-900">{{ $engineers->lastItem() ?? 0 }}</strong> of <strong class="text-slate-900">{{ $engineers->total() }}</strong> technicians
            </div>
            <div>
                {{ $engineers->links() }}
            </div>
        </div>

    </div>

</div>
@endsection
