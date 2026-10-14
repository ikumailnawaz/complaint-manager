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

<div class="space-y-4">
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
                    <div class="p-3 rounded-xl border {{ $bgCard }} transition hover:shadow-xs cursor-pointer" onclick="jumpToSegment({{ $idx }}, {{ $seg->lat }}, {{ $seg->lng }})">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[10px] font-mono font-bold text-slate-400">
                                {{ Carbon\Carbon::parse($seg->started_at)->format('h:i A') }} - {{ Carbon\Carbon::parse($seg->ended_at)->format('h:i A') }}
                            </span>
                            @if($isStop)
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold {{ $kind === 'office' ? 'bg-indigo-100 text-indigo-700' : ($kind === 'ticket_site' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700') }}">
                                    Stop: {{ round($seg->duration_sec / 60) }}m
                                </span>
                            @else
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-slate-200 text-slate-700">
                                    Transit: {{ round($seg->distance_m / 1000, 1) }} km
                                </span>
                            @endif
                        </div>
                        <div class="font-bold text-xs text-slate-900 flex items-center space-x-1.5">
                            <i class="fa-solid {{ $isStop ? ($kind === 'office' ? 'fa-building text-indigo-600' : ($kind === 'ticket_site' ? 'fa-building-columns text-sky-600' : 'fa-clock text-amber-600')) : 'fa-motorcycle text-slate-600' }}"></i>
                            <span>{{ $seg->label }}</span>
                        </div>
                        @if($seg->address)
                            <div class="text-[11px] text-slate-500 mt-1 line-clamp-1">{{ $seg->address }}</div>
                        @endif
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

let polyline;
let bikeMarker;
let stopMarkers = [];

let currentIndex = 0;
let isPlaying = false;
let playInterval = null;
let speedMultiplier = 1;

document.addEventListener('DOMContentLoaded', function() {
    initPlaybackMap();
});

function initPlaybackMap() {
    const startLat = routePoints.length > 0 ? routePoints[0].lat : 31.5397357;
    const startLng = routePoints.length > 0 ? routePoints[0].lng : 74.3306892;

    map = L.map('playback_map').setView([startLat, startLng], 13);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(map);

    // Draw Geofences
    geofences.forEach(gf => {
        L.circle([gf.lat, gf.lng], {
            color: '#6366f1',
            fillColor: '#818cf8',
            fillOpacity: 0.15,
            radius: gf.radius_m
        }).addTo(map).bindPopup("<b>" + gf.name + "</b> (Attendance Geofence)");
    });

    if (routePoints.length === 0) {
        return;
    }

    // Draw Complete Travel Polyline (Vibrant Indigo Route)
    const latlngs = routePoints.map(p => [p.lat, p.lng]);
    polyline = L.polyline(latlngs, {
        color: '#4f46e5',
        weight: 5,
        opacity: 0.85,
        lineJoin: 'round'
    }).addTo(map);

    map.fitBounds(polyline.getBounds(), { padding: [40, 40] });

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
        <div id="bike_pin" style="transition: transform 0.2s ease-out;" class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center shadow-2xl border-2 border-amber-400 text-sm">
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

function togglePlayback() {
    if (isPlaying) {
        pausePlayback();
    } else {
        startPlayback();
    }
}

function startPlayback() {
    if (routePoints.length === 0) return;
    isPlaying = true;
    document.getElementById('play_icon').className = 'fa-solid fa-pause';

    clearInterval(playInterval);
    const frameRate = Math.max(15, Math.floor(250 / speedMultiplier));

    playInterval = setInterval(() => {
        if (currentIndex >= routePoints.length - 1) {
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
    const idx = Math.floor((val / 1000) * (routePoints.length - 1));
    currentIndex = idx;
    stepTo(idx);
}

function stepTo(idx) {
    if (!routePoints[idx]) return;

    const pt = routePoints[idx];
    bikeMarker.setLatLng([pt.lat, pt.lng]);

    // Calculate heading rotation towards next point
    if (idx < routePoints.length - 1) {
        const next = routePoints[idx + 1];
        const heading = calculateBearing(pt.lat, pt.lng, next.lat, next.lng);
        const pin = document.getElementById('bike_pin');
        if (pin) pin.style.transform = `rotate(${heading}deg)`;
    }

    updatePlaybackUI(idx);
}

function updatePlaybackUI(idx) {
    if (!routePoints[idx]) return;
    const pt = routePoints[idx];

    // Slider
    const slider = document.getElementById('timeline_slider');
    slider.value = Math.floor((idx / (routePoints.length - 1)) * 1000);

    // Clock
    document.getElementById('current_time_display').textContent = pt.time_full || pt.time;

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
    pausePlayback();
    if (lat && lng) {
        map.flyTo([lat, lng], 15, { duration: 1 });
    }
}
</script>
@endsection
