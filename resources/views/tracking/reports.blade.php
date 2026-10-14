@extends('layouts.app')

@section('header_title', 'Engineer Travel & Stoppage Reports')

@section('content')
<div class="space-y-4 max-w-7xl mx-auto">
    @include('tracking.partials.tabs')

    <!-- Top Filter Bar -->
    <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200">
        <form method="GET" action="{{ route('tracking.reports') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Field Engineer</label>
                <select name="engineer_id" class="w-full text-xs rounded-xl border-slate-300 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Field Engineers</option>
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}" {{ $selectedEngineerId == $eng->id ? 'selected' : '' }}>
                            {{ $eng->name }} ({{ $eng->city ?? 'Field' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Date</label>
                <input type="date" name="date" value="{{ $selectedDate }}" class="w-full text-xs rounded-xl border-slate-300 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">View Mode</label>
                <select name="range" class="w-full text-xs rounded-xl border-slate-300 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="daily" {{ $range == 'daily' ? 'selected' : '' }}>Daily Detailed</option>
                    <option value="monthly" {{ $range == 'monthly' ? 'selected' : '' }}>Monthly Overview</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
                <a href="{{ route('tracking.reports.export-csv', ['engineer_id' => $selectedEngineerId, 'date' => $selectedDate, 'range' => $range]) }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center justify-center gap-1" title="Download CSV">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </a>
            </div>
            @if($selectedEngineerId)
            <div class="flex gap-2">
                <form method="POST" action="{{ route('tracking.reprocess') }}" class="inline">
                    @csrf
                    <input type="hidden" name="engineer_id" value="{{ $selectedEngineerId }}">
                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                    <button type="submit" class="w-full px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-medium border border-slate-300 transition flex items-center justify-center gap-1.5" title="Recalculate trips & attendance from raw points">
                        <i class="fa-solid fa-arrows-rotate text-emerald-600"></i> Reprocess Day
                    </button>
                </form>
            </div>
            @endif
        </form>
    </div>

    @if($range === 'daily')
        @if($summary)
            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Distance</div>
                    <div class="mt-1 text-2xl font-black text-slate-900">{{ number_format($summary->total_distance_km, 1) }} <span class="text-xs font-normal text-slate-500">km</span></div>
                    <div class="mt-1 text-[10px] text-emerald-600 font-medium"><i class="fa-solid fa-road"></i> On-road verified</div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Travel Time</div>
                    <div class="mt-1 text-2xl font-black text-slate-900">{{ round($summary->transit_minutes / 60, 1) }} <span class="text-xs font-normal text-slate-500">hrs</span></div>
                    <div class="mt-1 text-[10px] text-slate-500 font-medium">{{ $summary->transit_minutes }} active mins</div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Customer Time</div>
                    <div class="mt-1 text-2xl font-black text-slate-900">{{ round($summary->stop_minutes / 60, 1) }} <span class="text-xs font-normal text-slate-500">hrs</span></div>
                    <div class="mt-1 text-[10px] text-indigo-600 font-medium">{{ $summary->stop_count }} verified stops</div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Office Time</div>
                    <div class="mt-1 text-2xl font-black text-slate-900">{{ round($summary->office_minutes / 60, 1) }} <span class="text-xs font-normal text-slate-500">hrs</span></div>
                    <div class="mt-1 text-[10px] text-amber-600 font-medium">Inside geofence</div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Attendance</div>
                    @if($attendance)
                        <div class="mt-1 text-base font-bold text-slate-900">
                            {{ \Carbon\Carbon::parse($attendance->first_in_at)->timezone('Asia/Karachi')->format('h:i A') }}
                        </div>
                        <div class="mt-1 text-[10px] font-semibold {{ $attendance->is_late ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $attendance->is_late ? 'Late by ' . $attendance->late_minutes . 'm' : 'On Time' }}
                        </div>
                    @else
                        <div class="mt-1 text-base font-bold text-slate-400">Absent</div>
                        <div class="mt-1 text-[10px] text-slate-400">No office check-in</div>
                    @endif
                </div>

                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Efficiency</div>
                    <div class="mt-1 text-2xl font-black {{ $summary->efficiency_score >= 75 ? 'text-emerald-600' : ($summary->efficiency_score >= 50 ? 'text-amber-500' : 'text-rose-500') }}">
                        {{ $summary->efficiency_score }}<span class="text-xs font-normal text-slate-500">/100</span>
                    </div>
                    <div class="mt-1 text-[10px] text-slate-500 font-medium">Auto-scored by AI</div>
                </div>
            </div>

            <!-- Gemini AI Appraisal Card -->
            @if($summary->ai_narrative)
            <div class="bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 text-white rounded-2xl p-5 border border-indigo-800/40 shadow-lg">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-indigo-400">
                            <i class="fa-solid fa-robot"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-indigo-200">Gemini AI Field Appraisal &amp; Route Analysis</h3>
                            <p class="text-[11px] text-slate-400">Automated performance summary based on travel telemetry &amp; stop durations</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 rounded-lg text-[10px] font-mono">
                        Score: {{ $summary->efficiency_score }}/100
                    </span>
                </div>
                <div class="text-xs text-slate-200 leading-relaxed bg-slate-950/40 rounded-xl p-4 border border-slate-800/80 font-sans">
                    {!! nl2br(e($summary->ai_narrative)) !!}
                </div>
            </div>
            @endif

            <!-- Detailed Stoppages Table -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span> Verified Field Stoppages
                        </h3>
                        <p class="text-xs text-slate-500">Accurately detected when vehicle is stationary for &ge; 5 minutes within 80m</p>
                    </div>
                    <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-lg">
                        {{ count($stops) }} Total Stops
                    </span>
                </div>

                @if(count($stops) === 0)
                    <div class="text-center py-8 text-xs text-slate-400">No stops recorded for this day.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="text-[11px] font-semibold text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-3">#</th>
                                    <th class="py-3 px-3">Arrival</th>
                                    <th class="py-3 px-3">Departure</th>
                                    <th class="py-3 px-3">Duration</th>
                                    <th class="py-3 px-3">Type</th>
                                    <th class="py-3 px-3">Location / Address</th>
                                    <th class="py-3 px-3 text-right">Map</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($stops as $idx => $stop)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="py-3 px-3 font-mono font-bold text-slate-900">
                                        <span class="w-6 h-6 rounded-full inline-flex items-center justify-center text-[10px] {{ $stop->classification === 'office' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800' }}">
                                            {{ chr(65 + ($idx % 26)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-medium text-slate-700">
                                        {{ \Carbon\Carbon::parse($stop->start_time)->timezone('Asia/Karachi')->format('h:i A') }}
                                    </td>
                                    <td class="py-3 px-3 font-medium text-slate-700">
                                        {{ $stop->end_time ? \Carbon\Carbon::parse($stop->end_time)->timezone('Asia/Karachi')->format('h:i A') : 'Ongoing' }}
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-slate-900">
                                        {{ $stop->duration_minutes }} min
                                    </td>
                                    <td class="py-3 px-3">
                                        @if($stop->classification === 'office')
                                            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 rounded text-[10px] font-semibold">Office Base</span>
                                        @elseif($stop->classification === 'customer')
                                            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded text-[10px] font-semibold">Customer / Branch</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-semibold">Transit Halt</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-slate-600 max-w-xs truncate">
                                        {{ $stop->end_address ?? ($stop->start_lat . ', ' . $stop->start_lng) }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="https://www.google.com/maps?q={{ $stop->start_lat }},{{ $stop->start_lng }}" target="_blank" class="text-sky-600 hover:text-sky-800 font-medium inline-flex items-center gap-1">
                                            <span>Open</span> <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Detailed Drive Segments Table -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span> Vehicle Transit Segments
                        </h3>
                        <p class="text-xs text-slate-500">Actual road movement trips between consecutive stoppages</p>
                    </div>
                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-lg">
                        {{ count($trips) }} Transit Trips
                    </span>
                </div>

                @if(count($trips) === 0)
                    <div class="text-center py-8 text-xs text-slate-400">No transit trips recorded.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="text-[11px] font-semibold text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-3">Trip</th>
                                    <th class="py-3 px-3">Departure</th>
                                    <th class="py-3 px-3">Arrival</th>
                                    <th class="py-3 px-3">Transit Time</th>
                                    <th class="py-3 px-3">Distance</th>
                                    <th class="py-3 px-3">Avg Speed</th>
                                    <th class="py-3 px-3">Max Speed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($trips as $idx => $trip)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="py-3 px-3 font-semibold text-slate-900">
                                        Trip #{{ $idx + 1 }}
                                    </td>
                                    <td class="py-3 px-3 text-slate-700">
                                        {{ \Carbon\Carbon::parse($trip->start_time)->timezone('Asia/Karachi')->format('h:i A') }}
                                    </td>
                                    <td class="py-3 px-3 text-slate-700">
                                        {{ $trip->end_time ? \Carbon\Carbon::parse($trip->end_time)->timezone('Asia/Karachi')->format('h:i A') : 'In Transit' }}
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-slate-900">
                                        {{ $trip->duration_minutes }} min
                                    </td>
                                    <td class="py-3 px-3 font-bold text-emerald-600">
                                        {{ number_format($trip->distance_km, 2) }} km
                                    </td>
                                    <td class="py-3 px-3 text-slate-600">
                                        {{ number_format($trip->avg_speed_kmh, 1) }} km/h
                                    </td>
                                    <td class="py-3 px-3 text-slate-600">
                                        {{ number_format($trip->max_speed_kmh, 1) }} km/h
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        @else
            <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 shadow-xs">
                <i class="fa-solid fa-route text-4xl text-slate-300 mb-3"></i>
                <h4 class="text-sm font-bold text-slate-800">No Telemetry Summary For Selected Date</h4>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">Select a different date or engineer above, or run the live tracking simulator to generate day movement data.</p>
            </div>
        @endif

    @else
        <!-- Monthly Overview Table -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 mb-3">Monthly Fleet Performance Overview ({{ \Carbon\Carbon::parse($selectedDate)->format('F Y') }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="text-[11px] font-semibold text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">Date</th>
                            <th class="py-3 px-3">Engineer</th>
                            <th class="py-3 px-3">Distance</th>
                            <th class="py-3 px-3">Transit Time</th>
                            <th class="py-3 px-3">Stops</th>
                            <th class="py-3 px-3">Customer Time</th>
                            <th class="py-3 px-3">Attendance</th>
                            <th class="py-3 px-3">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($monthlySummaries as $row)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3 px-3 font-semibold text-slate-900">{{ $row->date }}</td>
                            <td class="py-3 px-3 font-medium text-slate-800">{{ $row->engineer->name ?? 'N/A' }}</td>
                            <td class="py-3 px-3 font-bold text-emerald-600">{{ number_format($row->total_distance_km, 1) }} km</td>
                            <td class="py-3 px-3 text-slate-700">{{ $row->transit_minutes }} min</td>
                            <td class="py-3 px-3 text-slate-700">{{ $row->stop_count }}</td>
                            <td class="py-3 px-3 text-slate-700">{{ $row->stop_minutes }} min</td>
                            <td class="py-3 px-3">
                                @if($row->attendance)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $row->attendance->is_late ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $row->attendance->is_late ? 'Late' : 'Present' }}
                                    </span>
                                @else
                                    <span class="text-slate-400">Absent</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-black {{ $row->efficiency_score >= 75 ? 'text-emerald-600' : ($row->efficiency_score >= 50 ? 'text-amber-500' : 'text-rose-500') }}">
                                {{ $row->efficiency_score }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-xs text-slate-400">No telemetry recorded for this month.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
