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

            <!-- Floating Snapped Route & Direction Indicator Pill -->
            <div id="road_snap_badge" class="absolute top-3 left-14 z-[400] bg-slate-900/90 backdrop-blur-md px-3 py-1.5 rounded-xl shadow-lg border border-slate-700/80 text-xs font-semibold text-white flex items-center space-x-2 pointer-events-none transition-all">
                <span id="road_snap_dot" class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span id="road_snap_text" class="text-[11px] font-medium text-slate-200">Matching route to street network...</span>
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

                    <!-- Speed Multipliers -->
                    <div class="flex items-center space-x-1.5 bg-slate-800/80 p-1 rounded-xl border border-slate-700 text-[11px] font-bold">
                        <span class="text-slate-400 text-[10px] uppercase px-1.5">Speed:</span>
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
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-200 font-bold text-slate-700">{{ count($segments) }} Events</span>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-3">
                @forelse($segments as $idx => $seg)
                    @php
                        $isStop = $seg->type === 'stop';
                        $kind = $seg->kind;
                        $bgCard = $isStop 
                            ? ($kind === 'office' ? 'border-indigo-200 bg-indigo-50/30' : ($kind === 'ticket_site' ? 'border-sky-200 bg-sky-50/30' : 'border-amber-200 bg-amber-50/30'))
                            : 'border-slate-200 bg-slate-50/40';
                    @endphp
                    <div id="itinerary-card-{{ $idx }}" class="itinerary-card p-3 rounded-xl border {{ $bgCard }} transition hover:shadow-xs cursor-pointer group" onclick="jumpToSegment({{ $idx }}, {{ $seg->lat }}, {{ $seg->lng }})">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-mono font-bold text-slate-400">
                                {{ Carbon\Carbon::parse($seg->started_at)->format('h:i A') }} - {{ Carbon\Carbon::parse($seg->ended_at)->format('h:i A') }}
                            </span>
                            @if($isStop)
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold {{ $kind === 'office' ? 'bg-indigo-100 text-indigo-700' : ($kind === 'ticket_site' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-700') }}">
                                    Stop: {{ round($seg->duration_sec / 60) }}m
                                </span>
                            @else
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-slate-200 text-slate-700">
                                    Transit: {{ round($seg->distance_m / 1000, 1) }} km
                                </span>
                            @endif
                        </div>
                        <div class="font-bold text-xs text-slate-900 flex items-center space-x-1.5">
                            <i class="fa-solid {{ $isStop ? ($kind === 'office' ? 'fa-building text-indigo-600' : ($kind === 'ticket_site' ? 'fa-building-columns text-emerald-600' : 'fa-clock text-amber-600')) : 'fa-motorcycle text-slate-600' }}"></i>
                            <span>{{ $seg->label }}</span>
                        </div>
                        @if($seg->address)
                            <div class="text-[11px] text-slate-500 mt-1 line-clamp-1">{{ $seg->address }}</div>
                        @endif
                        <div class="mt-2 pt-1.5 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-indigo-600 font-bold group-hover:text-indigo-700">
                            <span class="flex items-center gap-1">
                                <i class="fa-solid fa-play text-[9px]"></i> Simulate this leg
                            </span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-300 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition"></i>
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

let polyline = null;
let routeCasing = null;
let routeDecorator = null;
let bikeMarker = null;
let stopMarkers = [];

let activeSimulationPoints = (routePoints && routePoints.length > 0) ? routePoints.slice() : [];
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

    // 1. Google Maps Road & Bank POI Layer (Shows all Banks, ATMs, and English street names)
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

    // Default to Google Maps with Banks
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

    // Initial Path (Raw Telemetry) - Immediately render so map isn't blank
    const latlngs = routePoints.map(p => [p.lat, p.lng]);

    routeCasing = L.polyline(latlngs, {
        color: '#1e1b4b',
        weight: 8,
        opacity: 0.35,
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);

    polyline = L.polyline(latlngs, {
        color: '#4f46e5',
        weight: 5.5,
        opacity: 0.95,
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(map);

    map.fitBounds(polyline.getBounds(), { padding: [40, 40] });

    // Apply Direction Indicator Arrows on initial path
    applyDirectionDecorator(polyline);

    // Place Stop Markers (A, B, C...) with Duration & Arrival Tags
    let stopIdx = 0;
    const stopLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    segments.forEach((s) => {
        if (s.type === 'stop') {
            const letter = stopLetters[stopIdx % stopLetters.length];
            stopIdx++;

            const kindColor = s.kind === 'office' ? '#4f46e5' : (s.kind === 'ticket_site' ? '#0284c7' : '#d97706');
            const iconHtml = `
                <div style="background-color: ${kindColor};" class="w-7 h-7 rounded-full text-white font-black text-xs flex items-center justify-center shadow-lg border-2 border-white">
                    ${letter}
                </div>
            `;

            const icon = L.divIcon({
                html: iconHtml,
                className: 'stop-badge-marker',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -14]
            });

            const durMin = Math.round(s.duration_sec / 60);
            const marker = L.marker([s.lat, s.lng], { icon: icon })
                .addTo(map)
                .bindPopup(`
                    <div class="text-xs p-1 font-sans">
                        <div class="font-extrabold text-sm text-slate-900">${letter}. ${s.label}</div>
                        <div class="text-indigo-600 font-bold mt-0.5">Stop Duration: ${durMin} minutes</div>
                        <div class="text-slate-500 text-[11px] mt-0.5">${s.address || ''}</div>
                    </div>
                `);

            stopMarkers.push(marker);
        }
    });

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
 * Decorate polyline with crisp, high-visibility directional arrows
 */
function applyDirectionDecorator(poly) {
    if (routeDecorator) {
        map.removeLayer(routeDecorator);
        routeDecorator = null;
    }
    if (!poly || typeof L.polylineDecorator !== 'function') return;

    try {
        routeDecorator = L.polylineDecorator(poly, {
            patterns: [
                {
                    offset: 25,
                    repeat: 70,
                    symbol: L.Symbol.arrowHead({
                        pixelSize: 11,
                        headAngle: 45,
                        polygon: true,
                        pathOptions: {
                            stroke: true,
                            weight: 1.5,
                            color: '#1e1b4b',
                            fillColor: '#ffffff',
                            fillOpacity: 1
                        }
                    })
                }
            ]
        }).addTo(map);
    } catch (e) {
        console.warn('PolylineDecorator note:', e);
    }
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
            applySnappedRoute(data.coordinates);
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
            applySnappedRoute(clientSnapped);
            updateSnapBadge('success', 'Snapped to Streets (OSRM) • ➔ Direction Flow');
            return;
        }
    } catch (err2) {
        console.warn('Client OSRM fallback failed:', err2);
    }

    // Fallback: Keep raw points with directional arrows
    updateSnapBadge('fallback', 'Raw Telemetry Trace • ➔ Direction Flow');
    applyDirectionDecorator(polyline);
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

/**
 * Apply snapped street coordinates to polyline, casing, and decorator
 */
function applySnappedRoute(coords) {
    if (!polyline || !coords || coords.length < 2) return;

    if (routeCasing) {
        routeCasing.setLatLngs(coords);
    }
    polyline.setLatLngs(coords);
    polyline.setStyle({
        color: '#4f46e5',
        weight: 5.5,
        opacity: 0.95
    });

    // Reapply directional arrowheads along the actual street polyline
    applyDirectionDecorator(polyline);

    // Build active simulation points for smooth street riding
    rebuildSimulationPoints(coords);
}

/**
 * Rebuild simulation points along snapped street geometry with interpolated speeds & timestamps
 */
function rebuildSimulationPoints(snappedCoords) {
    if (!snappedCoords || snappedCoords.length === 0) return;

    activeSimulationPoints = snappedCoords.map((coord) => {
        const lat = coord[0];
        const lng = coord[1];

        // Match with nearest recorded telemetry point for time & speed metadata
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

    // Re-sync timeline slider
    const slider = document.getElementById('timeline_slider');
    slider.max = Math.max(1, activeSimulationPoints.length - 1);
    if (currentIndex >= activeSimulationPoints.length) {
        currentIndex = activeSimulationPoints.length - 1;
    }
    stepTo(currentIndex);
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
    speedMultiplier = mult;
    document.querySelectorAll('.speed-btn').forEach(b => {
        if (parseInt(b.getAttribute('data-speed')) === mult) {
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

    // Calculate heading rotation towards next point
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

    // Slider
    const slider = document.getElementById('timeline_slider');
    const maxVal = parseInt(slider.max) || (activeSimulationPoints.length - 1);
    slider.value = Math.round((idx / (activeSimulationPoints.length - 1)) * maxVal);

    // Clock
    document.getElementById('current_time_display').textContent = pt.time_full || pt.time || '--:--';

    // Status pill
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

function jumpToSegment(idx, lat, lng) {
    if (idx < 0 || idx >= segments.length) return;
    const seg = segments[idx];

    // Find the closest point in activeSimulationPoints
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

    // Set animation to that point
    currentIndex = bestIdx;
    stepTo(currentIndex);

    if (lat && lng) {
        map.flyTo([lat, lng], 16, { duration: 0.6 });
    }

    // Highlight timeline card
    document.querySelectorAll('.itinerary-card').forEach((card, i) => {
        if (i === idx) {
            card.classList.add('ring-2', 'ring-indigo-500', 'shadow-md');
        } else {
            card.classList.remove('ring-2', 'ring-indigo-500', 'shadow-md');
        }
    });

    // Start simulating automatically from this leg
    startPlayback();
}
</script>
@endsection
