@extends('layouts.app')

@section('title', 'Trip Playback & Simulation - ' . $engineer->name)

@section('header_title')
<div class="flex items-center space-x-2">
    <a href="{{ route('tracking.live') }}" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 flex items-center justify-center text-slate-700 transition">
        <i class="fa-solid fa-arrow-left text-xs"></i>
    </a>
    <div>
        <h1 class="text-base font-bold text-slate-900 leading-tight">Trip Simulation &amp; Transit Playback</h1>
        <p class="text-[11px] text-slate-500 font-medium">Uber/Careem Animated Transit • {{ $engineer->name }} • {{ Carbon\Carbon::parse($date)->format('d M Y') }}</p>
    </div>
</div>
@endsection

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-polylinedecorator@1.6.0/dist/leaflet.polylineDecorator.js"></script>

<div class="space-y-4">
    @include('tracking.partials.tabs')

    <!-- Top Filter Bar -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <form action="{{ url('/tracking/engineer/' . $engineer->id) }}" method="GET" class="flex flex-wrap items-center gap-3">
            <!-- Engineer Select -->
            <div class="flex items-center space-x-2">
                <label class="text-xs font-bold text-slate-600">Engineer:</label>
                <select name="engineer_id" onchange="window.location.href='{{ url('/tracking/engineer') }}/' + this.value + '?date={{ $date }}'" class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 font-bold focus:ring-1 focus:ring-indigo-500">
                    @foreach($allEngineers as $eng)
                        <option value="{{ $eng->id }}" {{ $eng->id == $engineer->id ? 'selected' : '' }}>
                            {{ $eng->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Date Picker -->
            <div class="flex items-center space-x-2">
                <label class="text-xs font-bold text-slate-600">Date:</label>
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 font-bold focus:ring-1 focus:ring-indigo-500">
            </div>

            <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition">
                Load Itinerary
            </button>
        </form>

        <!-- Day Summary Stats -->
        <div class="flex items-center space-x-3 text-xs font-semibold">
            <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700">
                <i class="fa-solid fa-route mr-1"></i> {{ $summary ? round($summary->distance_m / 1000, 1) : 0 }} km
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-sky-50 text-sky-700">
                <i class="fa-solid fa-motorcycle mr-1"></i> {{ $summary ? round($summary->drive_sec / 60) : 0 }}m drive
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700">
                <i class="fa-solid fa-location-dot mr-1"></i> {{ $summary ? $summary->stops_count : 0 }} stops
            </span>
            @if($summary && $summary->efficiency_score !== null)
            <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700">
                <i class="fa-solid fa-circle-check mr-1"></i> {{ $summary->efficiency_score }}% Efficiency
            </span>
            @endif
        </div>
    </div>

    <!-- Main Workspace Grid: Map + Timeline -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 h-[75vh]">
        <!-- Right: Map Canvas with Interactive Controls -->
        <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden relative flex flex-col">
            <div id="playback_map" class="w-full flex-1 z-10"></div>

            <!-- Floating Snapped Route & Mode Toggle Pill -->
            <div id="road_snap_badge" class="absolute top-3 left-14 z-[400] bg-slate-900/90 backdrop-blur-md px-3 py-1.5 rounded-xl shadow-lg border border-slate-700/80 text-xs font-semibold text-white flex items-center space-x-2 transition-all">
                <span id="road_snap_dot" class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span id="road_snap_text" class="text-[11px] font-medium text-slate-200">Matching route to street network...</span>
                <button type="button" id="snap_toggle_btn" onclick="toggleRoadSnappingMode()" class="ml-2 px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-indigo-300 hover:text-white text-[10px] font-bold border border-slate-600 transition flex items-center gap-1 cursor-pointer" title="Toggle between Street Network and Raw Telemetry">
                    <i class="fa-solid fa-code-compare text-[9px]"></i> <span id="snap_toggle_text">View Raw</span>
                </button>
            </div>

            <!-- Floating Selected Trip Isolation Banner -->
            <div id="selected_trip_banner" class="hidden absolute top-12 left-14 z-[400] bg-slate-950/95 backdrop-blur-md px-3 py-1.5 rounded-xl shadow-2xl border border-indigo-500/70 text-xs font-bold text-white flex items-center space-x-2.5 transition-all">
                <span id="selected_trip_banner_dot" class="w-2.5 h-2.5 rounded-full bg-indigo-500 ring-2 ring-white/50"></span>
                <div>
                    <span id="selected_trip_banner_name" class="text-white font-extrabold">Trip #1</span>
                    <span id="selected_trip_banner_stats" class="text-[11px] font-normal text-slate-300 ml-1.5">3.2 km • 18m</span>
                </div>
                <button onclick="resetTripSelection()" class="ml-2 text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-200 px-2 py-0.5 rounded-lg border border-slate-600 transition flex items-center gap-1 cursor-pointer">
                    <i class="fa-solid fa-xmark text-[9px]"></i> Show All
                </button>
            </div>

            <!-- Floating Uber-Style Playback Control Console -->
            <div class="p-3 bg-slate-900/95 backdrop-blur-md text-white border-t border-slate-800 z-20 flex flex-col space-y-2.5 shadow-2xl">
                <!-- Scrubber Slider & Timestamp -->
                <div class="flex items-center space-x-3">
                    <span id="current_time_display" class="font-mono text-xs font-bold text-indigo-300 w-20 text-center bg-slate-800/80 py-1 rounded-lg border border-slate-700">
                        08:00 AM
                    </span>
                    <input type="range" id="timeline_slider" min="0" max="1000" value="0" step="1" oninput="onScrubberSeek(this.value)" class="w-full h-1.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-indigo-400">
                    <span id="status_pill" class="text-[11px] px-2 py-0.5 rounded-full font-bold bg-slate-800 text-slate-300 whitespace-nowrap">
                        Ready
                    </span>
                </div>

                <!-- Controls row -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <!-- Play/Pause Button -->
                        <button id="play_btn" onclick="togglePlayback()" class="w-10 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white flex items-center justify-center text-sm shadow-md transition">
                            <i id="play_icon" class="fa-solid fa-play"></i>
                        </button>
                        <!-- Reset Button -->
                        <button onclick="resetPlayback()" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center text-xs transition" title="Rewind to start">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                    </div>

                    <!-- Speed Multipliers (Including 0.5x Slow-Motion) -->
                    <div class="flex items-center space-x-1 bg-slate-800/80 p-1 rounded-xl border border-slate-700 text-[11px] font-bold">
                        <span class="text-slate-400 text-[10px] uppercase px-1.5">Speed:</span>
                        <button onclick="setSpeed(0.5)" class="speed-btn px-2 py-1 rounded-lg hover:bg-slate-700 text-slate-300 transition" data-speed="0.5">0.5x</button>
                        <button onclick="setSpeed(1)" class="speed-btn px-2 py-1 rounded-lg bg-indigo-600 text-white transition" data-speed="1">1x</button>
                        <button onclick="setSpeed(10)" class="speed-btn px-2 py-1 rounded-lg hover:bg-slate-700 text-slate-300 transition" data-speed="10">10x</button>
                        <button onclick="setSpeed(30)" class="speed-btn px-2 py-1 rounded-lg hover:bg-slate-700 text-slate-300 transition" data-speed="30">30x</button>
                        <button onclick="setSpeed(60)" class="speed-btn px-2 py-1 rounded-lg hover:bg-slate-700 text-slate-300 transition" data-speed="60">60x</button>
                        <button onclick="setSpeed(120)" class="speed-btn px-2 py-1 rounded-lg hover:bg-slate-700 text-slate-300 transition" data-speed="120">120x</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Left: Chronological Trip Itinerary Timeline -->
        <div class="lg:col-span-1 bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col h-full overflow-hidden">
            <div class="p-3 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
                <span class="font-extrabold text-xs text-slate-900 uppercase tracking-wider">Itinerary Timeline</span>
                <div class="flex items-center space-x-1.5">
                    <button id="show_all_trips_btn" onclick="resetTripSelection()" class="hidden text-[10px] px-2 py-0.5 rounded-full bg-slate-800 hover:bg-slate-900 text-white font-bold transition shadow-xs">
                        <i class="fa-solid fa-layer-group text-[9px] mr-1"></i> All Trips
                    </button>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-200 font-bold text-slate-700">{{ count($segments) }} Events</span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-3">
                @php
                    $tripCounter = 0;
                    $palette = [
                        ['stroke' => '#4f46e5', 'badge' => 'bg-indigo-100 text-indigo-700 border-indigo-200'],
                        ['stroke' => '#059669', 'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
                        ['stroke' => '#d97706', 'badge' => 'bg-amber-100 text-amber-800 border-amber-200'],
                        ['stroke' => '#e11d48', 'badge' => 'bg-rose-100 text-rose-800 border-rose-200'],
                        ['stroke' => '#0284c7', 'badge' => 'bg-sky-100 text-sky-800 border-sky-200'],
                        ['stroke' => '#9333ea', 'badge' => 'bg-purple-100 text-purple-800 border-purple-200'],
                        ['stroke' => '#0d9488', 'badge' => 'bg-teal-100 text-teal-800 border-teal-200'],
                        ['stroke' => '#ea580c', 'badge' => 'bg-orange-100 text-orange-800 border-orange-200'],
                    ];
                @endphp
                @forelse($segments as $idx => $seg)
                    @php
                        $isStop = $seg->type === 'stop';
                        $kind = $seg->kind;
                        $tripIdx = null;
                        if (!$isStop) {
                            $tripIdx = $tripCounter;
                            $tripCounter++;
                            $colorInfo = $palette[$tripIdx % count($palette)];
                        }
                        $bgCard = $isStop 
                            ? ($kind === 'office' ? 'border-indigo-200 bg-indigo-50/30' : ($kind === 'ticket_site' ? 'border-sky-200 bg-sky-50/30' : 'border-amber-200 bg-amber-50/30'))
                            : 'border-slate-200 bg-slate-50/40';
                    @endphp
                    <div id="itinerary-card-{{ $idx }}" 
                         data-segment-idx="{{ $idx }}"
                         data-trip-idx="{{ $tripIdx !== null ? $tripIdx : '' }}"
                         class="itinerary-card p-3 rounded-xl border {{ $bgCard }} transition duration-200 hover:shadow-xs cursor-pointer group" 
                         onclick="onItineraryCardClick({{ $idx }}, {{ $tripIdx !== null ? $tripIdx : 'null' }}, {{ $seg->lat }}, {{ $seg->lng }})">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-mono font-bold text-slate-400">
                                {{ Carbon\Carbon::parse($seg->started_at)->format('h:i A') }} - {{ Carbon\Carbon::parse($seg->ended_at)->format('h:i A') }}
                            </span>
                            @if($isStop)
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold {{ $kind === 'office' ? 'bg-indigo-100 text-indigo-700' : ($kind === 'ticket_site' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-700') }}">
                                    Stop: {{ round($seg->duration_sec / 60) }}m
                                </span>
                            @else
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold border {{ $colorInfo['badge'] }}">
                                    Trip #{{ $tripIdx + 1 }}: {{ round($seg->distance_m / 1000, 1) }} km
                                </span>
                            @endif
                        </div>
                        <div class="font-bold text-xs text-slate-900 flex items-center space-x-1.5">
                            @if($isStop)
                                <i class="fa-solid {{ $kind === 'office' ? 'fa-building text-indigo-600' : ($kind === 'ticket_site' ? 'fa-building-columns text-emerald-600' : 'fa-clock text-amber-600') }}"></i>
                            @else
                                <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: {{ $colorInfo['stroke'] }};"></span>
                            @endif
                            @php
                                $cardLabel = $seg->label;
                                if ($isStop && $kind === 'office' && !str_contains(strtolower($cardLabel), 'office') && !str_contains(strtolower($cardLabel), 'workshop')) {
                                    $cardLabel .= ' Office';
                                }
                            @endphp
                            <span>{{ $cardLabel }}</span>
                        </div>
                        @if($seg->address)
                            @php
                                $addr = $seg->address;
                                if ($isStop && $kind === 'office' && !str_contains(strtolower($addr), 'office')) {
                                    $addr = str_replace('Near ' . $seg->label, 'At ' . $seg->label . ' Office', $addr) . ' • Attendance Geofence';
                                }
                            @endphp
                            <div class="text-[11px] text-slate-500 mt-1 line-clamp-1">{{ $addr }}</div>
                        @endif
                        <div class="mt-2 pt-1.5 border-t border-slate-200/60 flex items-center justify-between text-[11px] font-bold group-hover:opacity-100" style="color: {{ !$isStop ? $colorInfo['stroke'] : '#4f46e5' }};">
                            <span class="flex items-center gap-1">
                                <i class="fa-solid fa-play text-[9px]"></i> {{ !$isStop ? 'Isolate & Play Trip #' . ($tripIdx + 1) : 'Jump to this stop' }}
                            </span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-300 group-hover:translate-x-0.5 transition"></i>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        No telemetry segments recorded on this date.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
let map;
let routePoints = @json($rawPoints);
let segments = @json($segments);
let geofences = @json($geofences);
let bankBranches = @json($bankBranches ?? []);

const TRIP_PALETTE = [
    { stroke: '#4f46e5', name: 'Indigo',   hex: '#4f46e5', light: 'bg-indigo-50 border-indigo-200 text-indigo-700' },
    { stroke: '#059669', name: 'Emerald',  hex: '#059669', light: 'bg-emerald-50 border-emerald-200 text-emerald-800' },
    { stroke: '#d97706', name: 'Amber',    hex: '#d97706', light: 'bg-amber-50 border-amber-200 text-amber-800' },
    { stroke: '#e11d48', name: 'Crimson',  hex: '#e11d48', light: 'bg-rose-50 border-rose-200 text-rose-800' },
    { stroke: '#0284c7', name: 'Sky Cyan', hex: '#0284c7', light: 'bg-sky-50 border-sky-200 text-sky-800' },
    { stroke: '#9333ea', name: 'Purple',   hex: '#9333ea', light: 'bg-purple-50 border-purple-200 text-purple-800' },
    { stroke: '#0d9488', name: 'Teal',     hex: '#0d9488', light: 'bg-teal-50 border-teal-200 text-teal-800' },
    { stroke: '#ea580c', name: 'Orange',   hex: '#ea580c', light: 'bg-orange-50 border-orange-200 text-orange-800' },
];

let tripLayers = [];
let selectedTripIndex = null;
let stopMarkers = [];
let bikeMarker = null;

let fullRouteCoords = [];
let fullSimulationPoints = (routePoints && routePoints.length > 0) ? routePoints.slice() : [];
let activeSimulationPoints = fullSimulationPoints.slice();

let currentIndex = 0;
let isPlaying = false;
let playInterval = null;
let speedMultiplier = 1;

document.addEventListener('DOMContentLoaded', function() {
    initPlaybackMap();
    if (routePoints.length > 1) {
        loadAndSnapRoute();
    } else {
        updateSnapBadge('idle', 'No transit telemetry recorded');
    }
});

function initPlaybackMap() {
    const startLat = routePoints.length > 0 ? routePoints[0].lat : 31.5397357;
    const startLng = routePoints.length > 0 ? routePoints[0].lng : 74.3306892;

    // Initialize Map with Center View
    map = L.map('playback_map').setView([startLat, startLng], 13);

    // 1. Google Maps Road & Bank POI Layer
    const googleStreets = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        attribution: '&copy; Google Maps'
    });

    // 2. Google Hybrid (Satellite + Road Names)
    const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        attribution: '&copy; Google Maps Satellite'
    });

    // 3. Esri World Street Map
    const esriStreets = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri World Street Map'
    });

    googleStreets.addTo(map);

    L.control.layers({
        "Google Maps (Banks & Streets)": googleStreets,
        "Google Satellite": googleHybrid,
        "Esri English Streets": esriStreets
    }, null, { position: 'topright' }).addTo(map);

    // Draw Geofences
    geofences.forEach(gf => {
        L.circle([gf.lat, gf.lng], {
            color: '#6366f1',
            fillColor: '#818cf8',
            fillOpacity: 0.15,
            radius: gf.radius_m
        }).addTo(map).bindPopup("<b>" + gf.name + "</b> (Attendance Geofence)");
    });

    // Plot Bank Branches from Active Complaints
    bankBranches.forEach(b => {
        const bankIcon = L.divIcon({
            html: `
                <div style="background-color: #059669;" class="w-8 h-8 rounded-xl text-white flex items-center justify-center shadow-lg border-2 border-white hover:scale-110 transition cursor-pointer" title="${b.bank_name} - ${b.branch_name}">
                    <i class="fa-solid fa-building-columns text-sm"></i>
                </div>
            `,
            className: 'bank-pin-marker',
            iconSize: [32, 32],
            iconAnchor: [16, 16],
            popupAnchor: [0, -16]
        });

        L.marker([b.lat, b.lng], { icon: bankIcon })
            .addTo(map)
            .bindPopup(`
                <div class="p-2 space-y-1 text-xs font-sans min-w-[200px]">
                    <div class="font-bold text-emerald-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-building-columns"></i> <span>${b.bank_name}</span>
                    </div>
                    <div class="font-semibold text-slate-800">${b.branch_name}</div>
                    <div class="text-[11px] text-slate-500">${b.address || ''}</div>
                    <div class="pt-1.5 mt-1 border-t border-slate-100 flex items-center justify-between">
                        <span class="font-mono text-[10px] text-indigo-600 font-bold">#${b.ticket_no}</span>
                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase">Complaint Bank</span>
                    </div>
                </div>
            `);
    });

    if (routePoints.length === 0) {
        return;
    }

    fullRouteCoords = routePoints.map(p => [p.lat, p.lng]);

    // Build and render initial trip routes
    buildAndRenderTrips(fullRouteCoords);

    // Place Stop Markers with permanent visible stop times
    renderStopMarkers();

    // Create Uber/Careem Animated Moving Motorcycle Marker
    const bikeHtml = `
        <div id="bike_pin" style="transition: transform 0.15s ease-out;" class="w-10 h-10 rounded-full bg-slate-950 text-white flex items-center justify-center shadow-2xl border-2 border-amber-400 text-sm">
            <i class="fa-solid fa-motorcycle text-amber-300"></i>
        </div>
    `;

    const bikeIcon = L.divIcon({
        html: bikeHtml,
        className: 'bike-marker-icon',
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });

    bikeMarker = L.marker([routePoints[0].lat, routePoints[0].lng], { icon: bikeIcon, zIndexOffset: 1000 }).addTo(map);

    updatePlaybackUI(0);
}

/**
 * Render stop markers on map with permanent callout bubbles showing stop duration & arrival/departure times
 */
function renderStopMarkers() {
    stopMarkers.forEach(m => map.removeLayer(m));
    stopMarkers = [];

    // Gather all stops from segments
    let stops = segments.filter(s => s.type === 'stop');

    // Fallback: If segments table has no stops, synthesize origin, dwell points, and destination
    if (stops.length === 0 && routePoints.length > 0) {
        // Origin Stop
        stops.push({
            type: 'stop',
            kind: 'office',
            label: 'Start / Departure',
            started_at: routePoints[0].time_full || routePoints[0].time,
            ended_at: routePoints[0].time_full || routePoints[0].time,
            started_at_formatted: routePoints[0].time_full || routePoints[0].time,
            ended_at_formatted: routePoints[0].time_full || routePoints[0].time,
            duration_sec: 0,
            lat: routePoints[0].lat,
            lng: routePoints[0].lng
        });

        // Detect stationary clusters (speed <= 1.5 km/h for >= 2 consecutive points)
        let dwellPoints = [];
        for (let i = 0; i < routePoints.length; i++) {
            const p = routePoints[i];
            if ((p.speed || 0) <= 1.5) {
                dwellPoints.push(p);
            } else {
                if (dwellPoints.length >= 2) {
                    const firstP = dwellPoints[0];
                    const lastP = dwellPoints[dwellPoints.length - 1];
                    const dur = (lastP.timestamp && firstP.timestamp) ? (lastP.timestamp - firstP.timestamp) : (dwellPoints.length * 45);
                    if (dur >= 90) {
                        stops.push({
                            type: 'stop',
                            kind: 'unknown',
                            label: 'Field Stop',
                            started_at: firstP.time_full || firstP.time,
                            ended_at: lastP.time_full || lastP.time,
                            started_at_formatted: firstP.time_full || firstP.time,
                            ended_at_formatted: lastP.time_full || lastP.time,
                            duration_sec: dur,
                            lat: firstP.lat,
                            lng: firstP.lng
                        });
                    }
                }
                dwellPoints = [];
            }
        }

        // Destination Stop
        if (routePoints.length >= 4) {
            const lastP = routePoints[routePoints.length - 1];
            stops.push({
                type: 'stop',
                kind: 'office',
                label: 'Arrival / End Location',
                started_at: lastP.time_full || lastP.time,
                ended_at: lastP.time_full || lastP.time,
                started_at_formatted: lastP.time_full || lastP.time,
                ended_at_formatted: lastP.time_full || lastP.time,
                duration_sec: 0,
                lat: lastP.lat,
                lng: lastP.lng
            });
        }
    }

    let stopIdx = 0;
    const stopLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    stops.forEach((s) => {
        const letter = stopLetters[stopIdx % stopLetters.length];
        stopIdx++;

        const kindColor = s.kind === 'office' ? '#4f46e5' : (s.kind === 'ticket_site' ? '#059669' : '#d97706');
        const durMin = Math.round((s.duration_sec || 0) / 60);

        const startTime = s.started_at_formatted || (s.started_at ? formatTimeStr(s.started_at) : '');
        const endTime = s.ended_at_formatted || (s.ended_at ? formatTimeStr(s.ended_at) : '');

        let timeLabel = '';
        if (durMin > 0) {
            timeLabel = `${durMin}m` + (startTime ? ` (${startTime} - ${endTime})` : '');
        } else if (startTime) {
            timeLabel = startTime;
        }

        const iconHtml = `
            <div class="stop-marker-wrapper group flex flex-col items-center cursor-pointer" style="transform: translate(-50%, -100%);">
                <!-- Permanent Visible Stop Callout Bubble -->
                <div class="stop-time-callout bg-slate-950/90 text-white text-[10px] px-2 py-0.5 rounded-full shadow-lg border border-slate-700 whitespace-nowrap font-bold flex items-center gap-1.5 mb-1 backdrop-blur-md transition group-hover:scale-105">
                    <span class="w-2 h-2 rounded-full" style="background-color: ${kindColor};"></span>
                    <span class="text-slate-100 font-extrabold">${letter}. ${s.label}</span>
                    ${timeLabel ? `<span class="text-amber-300 font-mono pl-1 border-l border-slate-700">⏱️ ${timeLabel}</span>` : ''}
                </div>
                <!-- Pin with Letter Badge -->
                <div style="background-color: ${kindColor};" class="w-7 h-7 rounded-full text-white font-black text-xs flex items-center justify-center shadow-xl border-2 border-white ring-2 ring-slate-900/30">
                    ${letter}
                </div>
                <!-- Arrow pointer -->
                <div class="w-0 h-0 border-l-[4px] border-l-transparent border-r-[4px] border-r-transparent border-t-[5px]" style="border-t-color: ${kindColor};"></div>
            </div>
        `;

        const icon = L.divIcon({
            html: iconHtml,
            className: 'custom-stop-div-icon',
            iconSize: [0, 0],
            iconAnchor: [0, 0],
            popupAnchor: [0, -35]
        });

        const marker = L.marker([s.lat, s.lng], { icon: icon, zIndexOffset: 500 })
            .addTo(map)
            .bindPopup(`
                <div class="text-xs p-1.5 font-sans min-w-[210px]">
                    <div class="font-extrabold text-sm text-slate-900 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full text-white text-[10px] font-black flex items-center justify-center" style="background-color: ${kindColor};">${letter}</span>
                        <span>${s.label}</span>
                    </div>
                    ${durMin > 0 ? `<div class="text-indigo-600 font-bold mt-1.5 flex items-center gap-1"><i class="fa-solid fa-clock text-amber-500"></i> Stop Duration: ${durMin} minutes</div>` : ''}
                    ${startTime ? `<div class="text-slate-600 font-mono text-[11px] mt-0.5"><i class="fa-solid fa-hourglass-start text-slate-400"></i> ${startTime} &rarr; ${endTime}</div>` : ''}
                    <div class="text-slate-500 text-[11px] mt-1 pt-1 border-t border-slate-100">${s.address || 'Field Location'}</div>
                </div>
            `);

        marker.stopData = s;
        stopMarkers.push(marker);
    });
}

/**
 * Partition route into distinct trips and render each with its own vibrant color and directional arrows
 */
function buildAndRenderTrips(coords) {
    // Clear existing trip layers
    tripLayers.forEach(tl => {
        if (tl.casing) map.removeLayer(tl.casing);
        if (tl.line) map.removeLayer(tl.line);
        if (tl.decorator) map.removeLayer(tl.decorator);
    });
    tripLayers = [];

    if (!coords || coords.length < 2) return;

    const driveSegments = segments.filter(s => s.type === 'drive');
    let tripPartitions = [];

    if (driveSegments.length > 0) {
        // Partition coords according to drive segments
        let remainingCoords = coords.slice();
        driveSegments.forEach((seg, sIdx) => {
            const segDistKm = Math.round((seg.distance_m || 0) / 100) / 10;
            const segDurMin = Math.round((seg.duration_sec || 0) / 60);

            // Find closest starting and ending coords
            let startIdx = 0;
            let endIdx = remainingCoords.length - 1;

            if (seg.lat && seg.lng && seg.end_lat && seg.end_lng) {
                let minStart = Infinity;
                let minEnd = Infinity;
                remainingCoords.forEach((c, cIdx) => {
                    const dS = Math.hypot(c[0] - seg.lat, c[1] - seg.lng);
                    const dE = Math.hypot(c[0] - seg.end_lat, c[1] - seg.end_lng);
                    if (dS < minStart) { minStart = dS; startIdx = cIdx; }
                    if (dE < minEnd && cIdx >= startIdx) { minEnd = dE; endIdx = cIdx; }
                });
            }

            if (endIdx <= startIdx) {
                endIdx = Math.min(startIdx + Math.floor(remainingCoords.length / driveSegments.length), remainingCoords.length - 1);
            }

            const tripSlice = remainingCoords.slice(startIdx, endIdx + 1);
            if (tripSlice.length >= 2) {
                tripPartitions.push({
                    tripIndex: sIdx,
                    name: `Trip #${sIdx + 1}`,
                    distanceKm: segDistKm || (Math.round(calcPathDistance(tripSlice) / 100) / 10),
                    timeSpan: `${seg.started_at_formatted || ''} - ${seg.ended_at_formatted || ''}`,
                    coords: tripSlice,
                    segment: seg
                });
            }
        });
    }

    // Fallback: If no drive segments were matched, treat the whole path as Trip 1
    if (tripPartitions.length === 0) {
        tripPartitions.push({
            tripIndex: 0,
            name: 'Trip #1',
            distanceKm: Math.round(calcPathDistance(coords) / 100) / 10,
            timeSpan: 'Full Route',
            coords: coords,
            segment: null
        });
    }

    // Now render each trip with its distinct color and directional arrow decorator
    tripPartitions.forEach((trip, idx) => {
        const color = TRIP_PALETTE[idx % TRIP_PALETTE.length];

        const casing = L.polyline(trip.coords, {
            color: '#0f172a',
            weight: 8,
            opacity: 0.35,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(map);

        const line = L.polyline(trip.coords, {
            color: color.stroke,
            weight: 5.5,
            opacity: 0.95,
            lineCap: 'round',
            lineJoin: 'round'
        }).addTo(map);

        // Direction indicators
        let decorator = null;
        if (typeof L.polylineDecorator === 'function' && trip.coords.length > 1) {
            try {
                decorator = L.polylineDecorator(line, {
                    patterns: [{
                        offset: 20,
                        repeat: 65,
                        symbol: L.Symbol.arrowHead({
                            pixelSize: 10,
                            headAngle: 45,
                            polygon: true,
                            pathOptions: {
                                stroke: true,
                                weight: 1.5,
                                color: '#0f172a',
                                fillColor: '#ffffff',
                                fillOpacity: 1
                            }
                        })
                    }]
                }).addTo(map);
            } catch (err) {
                console.warn('PolylineDecorator note:', err);
            }
        }

        // Click to isolate this trip
        line.on('click', () => selectTrip(idx));
        casing.on('click', () => selectTrip(idx));

        line.bindTooltip(`<strong>${trip.name}</strong> • ${trip.distanceKm} km<br><span style="font-size:10px; color:${color.stroke}; font-weight:bold;">Click to isolate this trip</span>`, { sticky: true });

        tripLayers.push({
            index: idx,
            trip: trip,
            color: color,
            casing: casing,
            line: line,
            decorator: decorator
        });
    });

    if (tripLayers.length > 0) {
        const bounds = L.latLngBounds(coords);
        map.fitBounds(bounds, { padding: [40, 40] });
    }
}

/**
 * Select and isolate a specific trip:
 * - Selected trip is shown in vivid full color with prominent direction arrows
 * - ALL other routes become blurred and dimmed into the background
 */
function selectTrip(tripIdx) {
    if (selectedTripIndex === tripIdx) {
        // Clicking already selected trip toggles back to all trips
        resetTripSelection();
        return;
    }

    selectedTripIndex = tripIdx;

    document.getElementById('show_all_trips_btn')?.classList.remove('hidden');

    const activeLayer = tripLayers[tripIdx];
    if (!activeLayer) return;

    // Show floating banner on map
    const banner = document.getElementById('selected_trip_banner');
    if (banner) {
        banner.classList.remove('hidden');
        document.getElementById('selected_trip_banner_name').textContent = activeLayer.trip.name;
        document.getElementById('selected_trip_banner_stats').textContent = `${activeLayer.trip.distanceKm} km • ${activeLayer.trip.timeSpan}`;
        document.getElementById('selected_trip_banner_dot').style.backgroundColor = activeLayer.color.stroke;
    }

    // Apply Highlight to selected trip & Blur / Dimming to all other routes
    tripLayers.forEach((tl, idx) => {
        const isSelected = (idx === tripIdx);
        const lineEl = tl.line.getElement();
        const casingEl = tl.casing.getElement();

        if (isSelected) {
            // Selected Trip: Vivid, thick, top z-index, crisp
            tl.line.setStyle({
                color: tl.color.stroke,
                weight: 7,
                opacity: 1.0
            });
            tl.casing.setStyle({
                weight: 10,
                opacity: 0.5
            });
            tl.line.bringToFront();

            if (lineEl) {
                lineEl.style.filter = 'none';
                lineEl.style.transition = 'all 0.3s ease';
            }
            if (casingEl) {
                casingEl.style.filter = 'none';
                casingEl.style.transition = 'all 0.3s ease';
            }

            if (tl.decorator && !map.hasLayer(tl.decorator)) {
                map.addLayer(tl.decorator);
            }
        } else {
            // Other Trips: BLURRED & DIMMED into background
            tl.line.setStyle({
                color: '#94a3b8',
                weight: 3.5,
                opacity: 0.22
            });
            tl.casing.setStyle({
                opacity: 0
            });

            if (lineEl) {
                lineEl.style.filter = 'blur(2px)';
                lineEl.style.transition = 'all 0.3s ease';
            }

            // Remove arrow decorators on background trips to keep map clean
            if (tl.decorator && map.hasLayer(tl.decorator)) {
                map.removeLayer(tl.decorator);
            }
        }
    });

    // Zoom nicely to the isolated trip
    map.fitBounds(activeLayer.line.getBounds(), { padding: [50, 50], maxZoom: 16 });

    // Focus simulation and scrubber on this selected trip
    focusSimulationOnTrip(activeLayer.trip.coords);

    // Highlight timeline card
    highlightTimelineForTrip(tripIdx);
}

/**
 * Reset isolation and display all trips in full vibrant colors without blur
 */
function resetTripSelection() {
    selectedTripIndex = null;

    document.getElementById('show_all_trips_btn')?.classList.add('hidden');
    document.getElementById('selected_trip_banner')?.classList.add('hidden');

    tripLayers.forEach((tl) => {
        tl.line.setStyle({
            color: tl.color.stroke,
            weight: 5.5,
            opacity: 0.95
        });
        tl.casing.setStyle({
            weight: 8,
            opacity: 0.35
        });

        const lineEl = tl.line.getElement();
        if (lineEl) {
            lineEl.style.filter = 'none';
        }

        if (tl.decorator && !map.hasLayer(tl.decorator)) {
            map.addLayer(tl.decorator);
        }
    });

    // Reset timeline highlight
    document.querySelectorAll('.itinerary-card').forEach(c => {
        c.classList.remove('ring-2', 'ring-indigo-500', 'shadow-md', 'opacity-40');
    });

    // Restore full day simulation points
    activeSimulationPoints = fullSimulationPoints.slice();
    const slider = document.getElementById('timeline_slider');
    slider.max = Math.max(1, activeSimulationPoints.length - 1);
    stepTo(0);

    if (fullRouteCoords.length > 0) {
        map.fitBounds(L.latLngBounds(fullRouteCoords), { padding: [40, 40] });
    }
}

function onItineraryCardClick(segmentIdx, tripIdx, lat, lng) {
    if (tripIdx !== null && tripIdx !== undefined && tripLayers[tripIdx]) {
        selectTrip(tripIdx);
    } else {
        // Jump to stop
        jumpToSegment(segmentIdx, lat, lng);
    }
}

function highlightTimelineForTrip(tripIdx) {
    document.querySelectorAll('.itinerary-card').forEach(card => {
        const cTripIdx = card.getAttribute('data-trip-idx');
        if (cTripIdx !== null && cTripIdx !== '' && parseInt(cTripIdx) === tripIdx) {
            card.classList.add('ring-2', 'ring-indigo-500', 'shadow-md');
            card.classList.remove('opacity-40');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            card.classList.remove('ring-2', 'ring-indigo-500', 'shadow-md');
            card.classList.add('opacity-40');
        }
    });
}

function focusSimulationOnTrip(tripCoords) {
    if (!tripCoords || tripCoords.length === 0) return;

    activeSimulationPoints = tripCoords.map(coord => {
        const lat = coord[0];
        const lng = coord[1];
        let nearestRaw = routePoints[0];
        let minDist = Infinity;
        for (let i = 0; i < routePoints.length; i++) {
            const d = Math.hypot(routePoints[i].lat - lat, routePoints[i].lng - lng);
            if (d < minDist) {
                minDist = d;
                nearestRaw = routePoints[i];
            }
        }
        return {
            lat: lat,
            lng: lng,
            speed: nearestRaw ? (nearestRaw.speed || 0) : 0,
            time: nearestRaw ? nearestRaw.time : '--:--',
            time_full: nearestRaw ? nearestRaw.time_full : '--:--'
        };
    });

    const slider = document.getElementById('timeline_slider');
    slider.max = Math.max(1, activeSimulationPoints.length - 1);
    currentIndex = 0;
    stepTo(0);
}

/**
 * Snaps route to actual street network via backend API with client OSRM fallback
 */
async function loadAndSnapRoute() {
    if (routePoints.length < 2) {
        updateSnapBadge('idle', 'No transit telemetry recorded');
        return;
    }

    updateSnapBadge('loading', 'Matching GPS trace to street network...');

    try {
        const snapUrl = `{{ url('/tracking/engineer') }}/{{ $engineer->id }}/snapped-route?date={{ $date }}`;
        const response = await fetch(snapUrl);
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();

        if (data.success && data.coordinates && data.coordinates.length > 1) {
            applySnappedRoadNetwork(data.coordinates);
            updateSnapBadge('success', 'Snapped to Street Network • ➔ Direction Flow');
            return;
        }
    } catch (err) {
        console.warn('Backend route snapping failed, trying direct OSRM...', err);
    }

    // Direct client OSRM fallback
    try {
        const clientSnapped = await snapRouteViaClientOsrm(routePoints);
        if (clientSnapped && clientSnapped.length > 1) {
            applySnappedRoadNetwork(clientSnapped);
            updateSnapBadge('success', 'Snapped to Streets (OSRM) • ➔ Direction Flow');
            return;
        }
    } catch (err2) {
        console.warn('Client OSRM fallback failed:', err2);
    }

    // Fallback: Keep raw points with directional arrows
    updateSnapBadge('fallback', 'Raw Telemetry Trace • ➔ Direction Flow');
}

let isSnappedMode = true;
let rawRouteCoords = (routePoints && routePoints.length > 0) ? routePoints.map(p => [p.lat, p.lng]) : [];
let snappedRoadCoords = [];

function toggleRoadSnappingMode() {
    isSnappedMode = !isSnappedMode;
    const btnText = document.getElementById('snap_toggle_text');
    const badgeText = document.getElementById('road_snap_text');
    const dot = document.getElementById('road_snap_dot');

    if (isSnappedMode) {
        if (snappedRoadCoords.length > 0) {
            applySnappedRoadNetwork(snappedRoadCoords);
        }
        if (btnText) btnText.textContent = 'View Raw';
        if (badgeText) badgeText.textContent = 'Snapped to Street Network • ➔ Direction Flow';
        if (dot) dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-400';
    } else {
        if (rawRouteCoords.length > 0) {
            fullRouteCoords = rawRouteCoords;
            fullSimulationPoints = routePoints.slice();
            if (selectedTripIndex === null) {
                activeSimulationPoints = fullSimulationPoints.slice();
            }
            buildAndRenderTrips(rawRouteCoords);
            if (selectedTripIndex !== null && tripLayers[selectedTripIndex]) {
                selectTrip(selectedTripIndex);
            }
        }
        if (btnText) btnText.textContent = 'Snap Streets';
        if (badgeText) badgeText.textContent = 'Raw Telemetry GPS Trace (Actual GPS Points)';
        if (dot) dot.className = 'w-2.5 h-2.5 rounded-full bg-sky-400';
    }
}

/**
 * Apply snapped street coordinates to all trips and simulation points
 */
function applySnappedRoadNetwork(snappedCoords) {
    if (!snappedCoords || snappedCoords.length < 2) return;

    snappedRoadCoords = snappedCoords;
    fullRouteCoords = snappedCoords;

    // Rebuild full simulation points along snapped street geometry
    fullSimulationPoints = snappedCoords.map((coord) => {
        const lat = coord[0];
        const lng = coord[1];
        let nearestRaw = routePoints[0];
        let minDist = Infinity;
        for (let i = 0; i < routePoints.length; i++) {
            const d = Math.hypot(routePoints[i].lat - lat, routePoints[i].lng - lng);
            if (d < minDist) {
                minDist = d;
                nearestRaw = routePoints[i];
            }
        }
        return {
            lat: lat,
            lng: lng,
            speed: nearestRaw ? (nearestRaw.speed || 0) : 0,
            time: nearestRaw ? nearestRaw.time : '--:--',
            time_full: nearestRaw ? nearestRaw.time_full : '--:--'
        };
    });

    if (selectedTripIndex === null) {
        activeSimulationPoints = fullSimulationPoints.slice();
    }

    // Rebuild and re-render trips with the snapped coordinates
    buildAndRenderTrips(snappedCoords);

    // If a trip was already selected, re-apply the isolation
    if (selectedTripIndex !== null && tripLayers[selectedTripIndex]) {
        selectTrip(selectedTripIndex);
    }
}

/**
 * Client-side OSRM routing fallback
 */
async function snapRouteViaClientOsrm(pts) {
    const filtered = [];
    for (let i = 0; i < pts.length; i++) {
        if (filtered.length === 0) {
            filtered.push(pts[i]);
            continue;
        }
        const prev = filtered[filtered.length - 1];
        const dist = Math.hypot(pts[i].lat - prev.lat, pts[i].lng - prev.lng) * 111000;
        if (dist >= 12 || i === pts.length - 1) {
            filtered.push(pts[i]);
        }
    }

    if (filtered.length < 2) return null;

    const chunkCoords = [];
    const chunkSize = 25;

    for (let i = 0; i < filtered.length - 1; i += (chunkSize - 1)) {
        const chunk = filtered.slice(i, i + chunkSize);
        if (chunk.length < 2) break;

        const coordsStr = chunk.map(p => `${p.lng.toFixed(6)},${p.lat.toFixed(6)}`).join(';');
        const url = `https://router.project-osrm.org/route/v1/driving/${coordsStr}?overview=full&geometries=geojson`;

        const resp = await fetch(url);
        if (!resp.ok) continue;
        const json = await resp.json();

        if (json.code === 'Ok' && json.routes && json.routes[0]) {
            const geom = json.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
            if (chunkCoords.length > 0) geom.shift();
            chunkCoords.push(...geom);
        } else {
            const rawChunk = chunk.map(p => [p.lat, p.lng]);
            if (chunkCoords.length > 0) rawChunk.shift();
            chunkCoords.push(...rawChunk);
        }
    }

    return chunkCoords.length > 1 ? chunkCoords : null;
}

function updateSnapBadge(state, message) {
    const badge = document.getElementById('road_snap_badge');
    const dot = document.getElementById('road_snap_dot');
    const text = document.getElementById('road_snap_text');
    if (!badge || !dot || !text) return;

    text.textContent = message;

    if (state === 'loading') {
        dot.className = 'w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse';
    } else if (state === 'success') {
        dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-400';
    } else if (state === 'fallback') {
        dot.className = 'w-2.5 h-2.5 rounded-full bg-sky-400';
    } else {
        dot.className = 'w-2.5 h-2.5 rounded-full bg-slate-400';
    }
}

function togglePlayback() {
    if (isPlaying) {
        pausePlayback();
    } else {
        startPlayback();
    }
}

function startPlayback() {
    if (activeSimulationPoints.length === 0) return;
    isPlaying = true;
    document.getElementById('play_icon').className = 'fa-solid fa-pause';

    clearInterval(playInterval);
    // 0.5x moves at 400ms per frame, 1x at 200ms, 10x at 20ms, etc.
    const frameRate = Math.max(15, Math.floor(200 / speedMultiplier));

    playInterval = setInterval(() => {
        if (currentIndex >= activeSimulationPoints.length - 1) {
            pausePlayback();
            return;
        }
        currentIndex++;
        stepTo(currentIndex);
    }, frameRate);
}

function pausePlayback() {
    isPlaying = false;
    clearInterval(playInterval);
    document.getElementById('play_icon').className = 'fa-solid fa-play';
}

function resetPlayback() {
    pausePlayback();
    currentIndex = 0;
    stepTo(0);
}

function setSpeed(mult) {
    speedMultiplier = parseFloat(mult);
    document.querySelectorAll('.speed-btn').forEach(b => {
        if (parseFloat(b.getAttribute('data-speed')) === speedMultiplier) {
            b.className = 'speed-btn px-2 py-1 rounded-lg bg-indigo-600 text-white transition';
        } else {
            b.className = 'speed-btn px-2 py-1 rounded-lg hover:bg-slate-700 text-slate-300 transition';
        }
    });

    if (isPlaying) {
        pausePlayback();
        startPlayback();
    }
}

function onScrubberSeek(val) {
    pausePlayback();
    if (activeSimulationPoints.length === 0) return;
    const maxVal = parseInt(document.getElementById('timeline_slider').max) || (activeSimulationPoints.length - 1);
    const idx = Math.min(Math.round((val / maxVal) * (activeSimulationPoints.length - 1)), activeSimulationPoints.length - 1);
    currentIndex = Math.max(0, idx);
    stepTo(currentIndex);
}

function stepTo(idx) {
    if (!activeSimulationPoints || !activeSimulationPoints[idx]) return;

    const pt = activeSimulationPoints[idx];
    if (bikeMarker) {
        bikeMarker.setLatLng([pt.lat, pt.lng]);
    }

    if (idx < activeSimulationPoints.length - 1) {
        const next = activeSimulationPoints[idx + 1];
        const heading = calculateBearing(pt.lat, pt.lng, next.lat, next.lng);
        const pin = document.getElementById('bike_pin');
        if (pin) pin.style.transform = `rotate(${heading}deg)`;
    }

    updatePlaybackUI(idx);
}

function updatePlaybackUI(idx) {
    if (!activeSimulationPoints || !activeSimulationPoints[idx]) return;
    const pt = activeSimulationPoints[idx];

    const slider = document.getElementById('timeline_slider');
    const maxVal = parseInt(slider.max) || (activeSimulationPoints.length - 1);
    slider.value = Math.round((idx / (activeSimulationPoints.length - 1)) * maxVal);

    document.getElementById('current_time_display').textContent = pt.time_full || pt.time || '--:--';

    const speed = pt.speed || 0;
    const pill = document.getElementById('status_pill');
    if (speed > 2) {
        pill.textContent = `Transit: ${speed} km/h`;
        pill.className = 'text-[11px] px-2 py-0.5 rounded-full font-bold bg-sky-900 text-sky-200';
    } else {
        pill.textContent = 'At Stop / Idle';
        pill.className = 'text-[11px] px-2 py-0.5 rounded-full font-bold bg-amber-900 text-amber-200';
    }
}

function calculateBearing(lat1, lon1, lat2, lon2) {
    const dLon = (lon2 - lon1) * (Math.PI / 180);
    const y = Math.sin(dLon) * Math.cos(lat2 * (Math.PI / 180));
    const x = Math.cos(lat1 * (Math.PI / 180)) * Math.sin(lat2 * (Math.PI / 180)) -
              Math.sin(lat1 * (Math.PI / 180)) * Math.cos(lat2 * (Math.PI / 180)) * Math.cos(dLon);
    const brng = Math.atan2(y, x) * (180 / Math.PI);
    return (brng + 360) % 360;
}

function calcPathDistance(coords) {
    let d = 0;
    for (let i = 0; i < coords.length - 1; i++) {
        d += Math.hypot(coords[i+1][0] - coords[i][0], coords[i+1][1] - coords[i][1]) * 111000;
    }
    return d;
}

function formatTimeStr(isoOrTime) {
    if (!isoOrTime) return '';
    try {
        const d = new Date(isoOrTime);
        if (!isNaN(d.getTime())) {
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    } catch(e) {}
    return String(isoOrTime).slice(0, 5);
}

function jumpToSegment(idx, lat, lng) {
    if (idx < 0 || idx >= segments.length) return;
    const seg = segments[idx];

    let bestIdx = 0;
    let minDiff = Infinity;

    if (activeSimulationPoints && activeSimulationPoints.length > 0) {
        activeSimulationPoints.forEach((pt, pIdx) => {
            if (pt.lat && pt.lng && lat && lng) {
                const d = Math.hypot(pt.lat - lat, pt.lng - lng);
                if (d < minDiff) {
                    minDiff = d;
                    bestIdx = pIdx;
                }
            }
        });
    }

    currentIndex = bestIdx;
    stepTo(currentIndex);

    if (lat && lng) {
        map.flyTo([lat, lng], 16, { duration: 0.6 });
    }

    document.querySelectorAll('.itinerary-card').forEach((card, i) => {
        if (i === idx) {
            card.classList.add('ring-2', 'ring-indigo-500', 'shadow-md');
        } else {
            card.classList.remove('ring-2', 'ring-indigo-500', 'shadow-md');
        }
    });

    startPlayback();
}
</script>
@endsection
