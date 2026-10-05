@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <!-- HEADER & STATS -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center space-x-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-xl shadow-md shadow-purple-900/20">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-800 tracking-tight flex items-center gap-2">
                    <span>Central Workshop Hub</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 font-bold border border-purple-200">
                        Multi-Stage Transit &amp; Bench Operations
                    </span>
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Track off-site bank machine logistics, field-to-workshop cargo handovers, bench technician repairs, and return transit.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('tickets.index') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 transition flex items-center space-x-1.5">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Complaints Registry</span>
            </a>
            <a href="{{ route('parts.requests.create') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-sky-600 text-white hover:bg-sky-500 shadow-md shadow-sky-900/20 transition flex items-center space-x-1.5">
                <i class="fa-solid fa-gears"></i>
                <span>Request Spare Parts</span>
            </a>
        </div>
    </div>

    <!-- 5-STAGE METRICS TILES -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- 1. Inbound Receivables -->
        <a href="{{ route('workshop.index', ['tab' => 'inbound']) }}"
           class="p-4 rounded-xl border transition {{ $tab === 'inbound' ? 'bg-amber-500/10 border-amber-500 shadow-sm' : 'bg-white border-slate-200 hover:border-slate-300' }}">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>1. Inbound In-Transit</span>
                <i class="fa-solid fa-truck-fast text-amber-500"></i>
            </div>
            <div class="text-2xl font-black text-slate-800 mt-1.5 font-mono">{{ $counts['inbound'] }}</div>
            <div class="text-[10px] text-amber-700 mt-0.5 font-medium">Awaiting physical intake</div>
        </a>

        <!-- 2. Active Bench Repairs -->
        <a href="{{ route('workshop.index', ['tab' => 'active']) }}"
           class="p-4 rounded-xl border transition {{ $tab === 'active' ? 'bg-purple-500/10 border-purple-500 shadow-sm' : 'bg-white border-slate-200 hover:border-slate-300' }}">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>2. Active on Bench</span>
                <i class="fa-solid fa-screwdriver-wrench text-purple-600"></i>
            </div>
            <div class="text-2xl font-black text-slate-800 mt-1.5 font-mono">{{ $counts['active'] }}</div>
            <div class="text-[10px] text-purple-700 mt-0.5 font-medium">Under bench repair</div>
        </a>

        <!-- 3. Repaired & Ready -->
        <a href="{{ route('workshop.index', ['tab' => 'repaired']) }}"
           class="p-4 rounded-xl border transition {{ $tab === 'repaired' ? 'bg-blue-500/10 border-blue-500 shadow-sm' : 'bg-white border-slate-200 hover:border-slate-300' }}">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>3. Tested OK / Ready</span>
                <i class="fa-solid fa-boxes-packing text-blue-600"></i>
            </div>
            <div class="text-2xl font-black text-slate-800 mt-1.5 font-mono">{{ $counts['repaired'] }}</div>
            <div class="text-[10px] text-blue-700 mt-0.5 font-medium">Ready for bank return</div>
        </a>

        <!-- 4. Return Cargo Transit -->
        <a href="{{ route('workshop.index', ['tab' => 'return_transit']) }}"
           class="p-4 rounded-xl border transition {{ $tab === 'return_transit' ? 'bg-indigo-500/10 border-indigo-500 shadow-sm' : 'bg-white border-slate-200 hover:border-slate-300' }}">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>4. Return Transit</span>
                <i class="fa-solid fa-plane-departure text-indigo-600"></i>
            </div>
            <div class="text-2xl font-black text-slate-800 mt-1.5 font-mono">{{ $counts['return_transit'] }}</div>
            <div class="text-[10px] text-indigo-700 mt-0.5 font-medium">En route to branch</div>
        </a>

        <!-- 5. Completed & Closed -->
        <a href="{{ route('workshop.index', ['tab' => 'completed']) }}"
           class="p-4 rounded-xl border transition {{ $tab === 'completed' ? 'bg-emerald-500/10 border-emerald-500 shadow-sm' : 'bg-white border-slate-200 hover:border-slate-300' }}">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>5. Returned &amp; Closed</span>
                <i class="fa-solid fa-circle-check text-emerald-600"></i>
            </div>
            <div class="text-2xl font-black text-slate-800 mt-1.5 font-mono">{{ $counts['completed'] }}</div>
            <div class="text-[10px] text-emerald-700 mt-0.5 font-medium">Turnaround complete</div>
        </a>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
        <form method="GET" action="{{ route('workshop.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="sm:col-span-6 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search Ticket #, Bank, Branch, Model, Serial, Tracking No..."
                       class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:outline-none">
            </div>

            <div class="sm:col-span-4">
                <select name="location" class="w-full border border-slate-300 rounded-xl p-2 focus:ring-2 focus:ring-purple-500 focus:outline-none bg-white">
                    <option value="">All Workshop Facilities</option>
                    @foreach($workshopLocations as $loc)
                        <option value="{{ $loc }}" {{ request('location') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <button type="submit" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl transition shadow-sm">
                    Filter
                </button>
                @if(request('search') || request('location'))
                    <a href="{{ route('workshop.index', ['tab' => $tab]) }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- WORKSHOP TICKETS TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-slate-200">
                <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="px-4 py-3">Ticket &amp; Bank Details</th>
                        <th class="px-4 py-3">Machine Equipment</th>
                        <th class="px-4 py-3">Original Field Engineer</th>
                        <th class="px-4 py-3">Logistics &amp; Tracking</th>
                        <th class="px-4 py-3">Workshop Handover</th>
                        <th class="px-4 py-3">Smart Day SLA</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tickets as $t)
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Ticket & Bank -->
                            <td class="px-4 py-3.5">
                                <a href="{{ route('tickets.show', $t) }}" class="font-mono font-bold text-purple-700 hover:underline">
                                    #{{ $t->ticket_no }}
                                </a>
                                <div class="font-bold text-slate-800 text-xs mt-0.5">{{ $t->bank_name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $t->branch_name ?? $t->branch_location }}</div>
                            </td>

                            <!-- Machine Equipment -->
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-slate-800">{{ $t->machine_model ?? $t->machine_type }}</div>
                                <div class="text-[11px] font-mono text-slate-500">S/N: {{ $t->machine_serial_no ?? 'N/A' }}</div>
                                <div class="mt-1">
                                    @if($t->status === 'awaiting_workshop')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            🚚 In Transit to Workshop
                                        </span>
                                    @elseif($t->status === 'in_workshop_repair')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                            🔬 On Bench Repair
                                        </span>
                                    @elseif($t->status === 'workshop_repaired')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                            📦 Repaired (Ready for Bank)
                                        </span>
                                    @elseif($t->status === 'return_transit')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                            🚚 Return Transit to Bank
                                        </span>
                                    @elseif($t->status === 'closed')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            ✅ Closed &amp; Delivered
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Original Field Engineer -->
                            <td class="px-4 py-3.5">
                                @php
                                    $fieldEng = $t->originalFieldEngineer ?? $t->engineer;
                                @endphp
                                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-user-gear text-slate-400"></i>
                                    <span>{{ $fieldEng?->name ?? 'Unassigned' }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    On-site time: <span class="font-mono font-bold text-slate-700">{{ $t->field_duration_text }}</span>
                                </div>
                            </td>

                            <!-- Logistics & Tracking -->
                            <td class="px-4 py-3.5">
                                @if($t->workshop_dispatched_at)
                                    <div class="text-xs font-semibold text-slate-800 flex items-center gap-1">
                                        <i class="fa-solid fa-box text-amber-500"></i>
                                        <span>{{ $t->workshop_dispatch_courier ?? 'Courier' }}</span>
                                    </div>
                                    <div class="font-mono text-[11px] text-purple-700 font-bold">
                                        {{ $t->workshop_dispatch_tracking ?? 'In Person' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        Sent: {{ $t->workshop_dispatched_at->format('d M, h:i A') }}
                                    </div>
                                @else
                                    <span class="text-slate-400 text-xs">—</span>
                                @endif

                                @if($t->return_dispatched_at)
                                    <div class="mt-2 pt-1.5 border-t border-slate-100">
                                        <div class="text-[10px] font-bold text-indigo-600 uppercase">Return Tracking:</div>
                                        <div class="text-xs font-semibold text-slate-800">{{ $t->return_courier }}</div>
                                        <div class="font-mono text-[11px] text-indigo-700 font-bold">{{ $t->return_tracking_number }}</div>
                                    </div>
                                @endif
                            </td>

                            <!-- Workshop Handover -->
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-800 flex items-center gap-1">
                                    <i class="fa-solid fa-warehouse text-purple-500"></i>
                                    <span>{{ $t->workshop_location ?? 'Central Workshop' }}</span>
                                </div>
                                @if($t->workshop_received_at)
                                    <div class="text-[11px] text-slate-700 font-medium mt-0.5">
                                        Bench Handler: <strong class="text-purple-800 font-bold">{{ $t->workshopEngineer?->name ?? 'Workshop Tech' }}</strong>
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        Intake: {{ $t->workshop_received_at->format('d M, h:i A') }} (Transit: {{ $t->inbound_transit_duration_text }})
                                    </div>
                                @else
                                    <div class="text-[11px] text-amber-700 font-medium mt-0.5 flex items-center gap-1">
                                        <i class="fa-solid fa-clock"></i>
                                        <span>Awaiting workshop intake</span>
                                    </div>
                                @endif
                            </td>

                            <!-- Smart Day SLA -->
                            <td class="px-4 py-3.5">
                                <div class="inline-flex flex-col gap-1">
                                    <span class="px-2 py-0.5 rounded font-mono font-bold text-[10px] bg-slate-100 text-slate-700 border border-slate-200">
                                        Overall Age: Day {{ $t->overall_ticket_day }}
                                    </span>
                                    @if($t->status === 'awaiting_workshop')
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] bg-amber-50 text-amber-800 border border-amber-200">
                                            In Transit: {{ $t->inbound_transit_duration_text }}
                                        </span>
                                    @elseif($t->status === 'in_workshop_repair')
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] bg-purple-100 text-purple-900 border border-purple-200">
                                            Bench Day {{ $t->workshop_engineer_day }} (Workshop Tech)
                                        </span>
                                    @elseif($t->status === 'workshop_repaired')
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] bg-blue-100 text-blue-900 border border-blue-200">
                                            Repaired in {{ $t->workshop_repair_duration_text }}
                                        </span>
                                    @elseif($t->status === 'return_transit')
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] bg-indigo-100 text-indigo-900 border border-indigo-200">
                                            Return: {{ $t->return_transit_duration_text }}
                                        </span>
                                    @elseif($t->status === 'closed')
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] bg-emerald-100 text-emerald-900 border border-emerald-200">
                                            Total SLA: {{ $t->total_resolution_duration_text }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3.5 text-right space-x-1.5 whitespace-nowrap">
                                @if($t->status === 'awaiting_workshop' && auth()->user()->isSuperior())
                                    <button type="button"
                                            onclick="openWorkshopReceiveModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}', '{{ addslashes($t->workshop_location ?? '') }}')"
                                            class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg text-xs shadow-xs transition inline-flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-box-open"></i>
                                        <span>Receive at Workshop</span>
                                    </button>
                                @elseif($t->status === 'in_workshop_repair' && (auth()->user()->isSuperior() || auth()->id() === $t->assigned_engineer_id))
                                    <button type="button"
                                            onclick="openWorkshopResolveModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}')"
                                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-xs transition inline-flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-check-double"></i>
                                        <span>Mark Bench Done</span>
                                    </button>
                                @elseif(($t->status === 'workshop_repaired' || ($t->status === 'resolved' && $t->workshop_received_at && !$t->bank_received_at)) && auth()->user()->isSuperior())
                                    <button type="button"
                                            onclick="openWorkshopReturnModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}')"
                                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow-xs transition inline-flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-truck"></i>
                                        <span>Dispatch Back to Bank</span>
                                    </button>
                                @elseif($t->status === 'return_transit' && auth()->user()->isSuperior())
                                    <button type="button"
                                            onclick="openWorkshopCloseModal({{ $t->id }}, '{{ $t->ticket_no }}', '{{ addslashes($t->bank_name) }}')"
                                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-xs transition inline-flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span>Confirm Bank Receipt &amp; Close</span>
                                    </button>
                                @endif

                                <a href="{{ route('tickets.show', $t) }}" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition inline-flex items-center gap-1">
                                    <i class="fa-solid fa-eye"></i>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-boxes-packing text-4xl mb-2 text-slate-300"></i>
                                <p class="text-sm font-semibold">No tickets found in this workshop stage.</p>
                                <p class="text-xs text-slate-400 mt-1">Select another tab above or check back when machines are sent off-site.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

</div>

@include('tickets.partials.workshop-lifecycle-modals')
@endsection

