@extends('layouts.app')

@section('title', 'Engineer Live Fleet Tracking & Transit')

@section('header_title')
<div class="flex items-center space-x-2">
    <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600">
        <i class="fa-solid fa-satellite-dish text-sm"></i>
    </div>
    <div>
        <h1 class="text-base font-bold text-slate-900 leading-tight">Live Field Engineer Tracking</h1>
        <p class="text-[11px] text-slate-500 font-medium">Real-time GPS transit monitoring, vehicle speeds &amp; stoppages</p>
    </div>
</div>
@endsection

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="space-y-4">
    @include('tracking.partials.tabs')

    <!-- Top Action Bar -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <!-- Quick Stats -->
        <div class="flex items-center space-x-4 sm:space-x-6 text-xs font-semibold">
            <div class="flex items-center space-x-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-slate-600">On Duty:</span>
                <span id="stat_active" class="font-bold text-slate-900 text-sm">0</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="w-3 h-3 rounded-full bg-sky-500"></span>
                <span class="text-slate-600">In Transit:</span>
                <span id="stat_transit" class="font-bold text-slate-900 text-sm">0</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                <span class="text-slate-600">Stationary:</span>
                <span id="stat_stationary" class="font-bold text-slate-900 text-sm">0</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="w-3 h-3 rounded-full bg-slate-400"></span>
                <span class="text-slate-600">Offline:</span>
                <span id="stat_offline" class="font-bold text-slate-900 text-sm">0</span>
            </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="flex items-center space-x-2">
            <span id="poll_indicator" class="text-[11px] text-slate-400 font-mono hidden sm:inline">
                Auto-refresh in <span id="countdown">15</span>s
            </span>
            <button onclick="fetchLiveData()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center space-x-1.5">
                <i class="fa-solid fa-arrows-rotate text-[11px]" id="refresh_icon"></i>
                <span>Refresh</span>
            </button>
            <a href="{{ route('tracking.reports') }}" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl transition flex items-center space-x-1.5">
                <i class="fa-solid fa-chart-line text-[11px]"></i>
                <span>Reports</span>
            </a>
            <a href="{{ route('tracking.attendance') }}" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl transition flex items-center space-x-1.5">
                <i class="fa-solid fa-calendar-check text-[11px]"></i>
                <span>Attendance</span>
            </a>
            <a href="{{ route('tracking.settings') }}" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition flex items-center space-x-1.5">
                <i class="fa-solid fa-sliders text-[11px]"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <!-- Map & Fleet Panel Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 h-[75vh]">
        <!-- Left: Engineers Fleet List -->
        <div class="lg:col-span-1 bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col h-full overflow-hidden">
            <!-- Search header -->
            <div class="p-3 border-b border-slate-200 bg-slate-50/50">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" 
                           id="engineer_search" 
                           oninput="filterEngineers()"
                           placeholder="Search engineers..." 
                           class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <!-- List of Engineers -->
            <div id="engineers_list" class="flex-1 overflow-y-auto divide-y divide-slate-100 p-1">
                <div class="p-6 text-center text-xs text-slate-400">Loading fleet positions...</div>
            </div>
        </div>

        <!-- Right: Interactive Leaflet Map -->
        <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden relative">
            <div id="live_map" class="w-full h-full z-10"></div>
            
            <!-- Map Overlay Legend -->
            <div class="absolute bottom-4 right-4 z-20 bg-white/90 backdrop-blur-sm p-3 rounded-xl border border-slate-200 shadow-lg text-[11px] font-semibold space-y-1.5 pointer-events-auto">
                <div class="text-[10px] text-slate-400 uppercase tracking-wider font-bold mb-1">Fleet Markers</div>
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full bg-sky-500 flex items-center justify-center text-[8px] text-white font-bold"><i class="fa-solid fa-motorcycle"></i></span>
                    <span class="text-slate-700">Moving Motorcycle</span>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                    <span class="text-slate-700">Stationary / Site Stop</span>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
                    <span class="text-slate-700">Office Geofence</span>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="w-3.5 h-3.5 rounded-md bg-emerald-600 flex items-center justify-center text-[8px] text-white font-bold"><i class="fa-solid fa-building-columns"></i></span>
                    <span class="text-slate-700">Complaint Bank Branch</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let map;
let markers = {};
let geofenceCircles = [];
let bankMarkers = [];
let engineersData = [];
let bankBranches = @json($bankBranches ?? []);
let countdown = 15;
let timer;

const defaultLat = 31.5397357;
const defaultLng = 74.3306892;

document.addEventListener('DOMContentLoaded', function() {
    initMap();
    fetchLiveData();
    startCountdown();
});

function initMap() {
    map = L.map('live_map', { zoomControl: true }).setView([defaultLat, defaultLng], 12);

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

    googleStreets.addTo(map);

    L.control.layers({
        "Google Maps (Banks & Streets)": googleStreets,
        "Google Satellite": googleHybrid,
        "Esri English Streets": esriStreets
    }, null, { position: 'topright' }).addTo(map);

    // Plot initial Geofences
    @foreach($geofences as $gf)
        const circle_{{ $gf->id }} = L.circle([{{ $gf->lat }}, {{ $gf->lng }}], {
            color: '#6366f1',
            fillColor: '#818cf8',
            fillOpacity: 0.2,
            radius: {{ $gf->radius_m }}
        }).addTo(map).bindPopup("<b>{{ $gf->name }}</b><br>Attendance Geofence (Radius: {{ $gf->radius_m }}m)");
        geofenceCircles.push(circle_{{ $gf->id }});
    @endforeach

    // Plot Bank Branches from Active Complaints (Banks similar to Google Maps)
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

        const m = L.marker([b.lat, b.lng], { icon: bankIcon })
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
        bankMarkers.push(m);
    });
}

function fetchLiveData() {
    const icon = document.getElementById('refresh_icon');
    if (icon) icon.classList.add('fa-spin');

    fetch("{{ route('tracking.live-data') }}")
        .then(res => res.json())
        .then(data => {
            if (icon) icon.classList.remove('fa-spin');
            if (data.success) {
                engineersData = data.engineers;
                renderFleetList(engineersData);
                updateMapMarkers(engineersData);
                updateStats(engineersData);
                countdown = 15;
            }
        })
        .catch(err => {
            if (icon) icon.classList.remove('fa-spin');
            console.error(err);
        });
}

function updateStats(engineers) {
    let active = 0, transit = 0, stationary = 0, offline = 0;
    engineers.forEach(e => {
        if (e.status_color === 'slate') offline++;
        else {
            active++;
            if (e.status_color === 'sky') transit++;
            else stationary++;
        }
    });

    document.getElementById('stat_active').textContent = active;
    document.getElementById('stat_transit').textContent = transit;
    document.getElementById('stat_stationary').textContent = stationary;
    document.getElementById('stat_offline').textContent = offline;
}

function renderFleetList(engineers) {
    const container = document.getElementById('engineers_list');
    const query = (document.getElementById('engineer_search').value || '').toLowerCase();

    const filtered = engineers.filter(e => e.name.toLowerCase().includes(query));

    if (filtered.length === 0) {
        container.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">No matching engineers found.</div>';
        return;
    }

    let html = '';
    filtered.forEach(e => {
        const bgBadge = e.status_color === 'sky' 
            ? 'bg-sky-100 text-sky-800' 
            : (e.status_color === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600');

        const playbackUrl = "{{ url('/tracking/engineer') }}/" + e.engineer_id;

        html += `
            <div class="p-3 hover:bg-slate-50 transition cursor-pointer flex flex-col space-y-2 rounded-xl m-1" onclick="focusEngineer(${e.engineer_id}, ${e.lat}, ${e.lng})">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs">
                            ${e.name.substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <div class="font-bold text-xs text-slate-900">${e.name}</div>
                            <div class="text-[10px] text-slate-400">${e.current_geofence ? 'At ' + e.current_geofence : e.last_updated}</div>
                        </div>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold ${bgBadge}">
                        ${e.status_text}
                    </span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-100">
                    <span class="flex items-center space-x-1">
                        <i class="fa-solid fa-gauge text-slate-400"></i>
                        <span>${e.speed_kmh} km/h</span>
                    </span>
                    <span class="flex items-center space-x-1">
                        <i class="fa-solid fa-battery-three-quarters text-slate-400"></i>
                        <span>${e.battery !== null ? e.battery + '%' : '—'}</span>
                    </span>
                    <a href="${playbackUrl}" onclick="event.stopPropagation()" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-1">
                        <i class="fa-solid fa-play text-[10px]"></i>
                        <span>Simulate Trip</span>
                    </a>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function updateMapMarkers(engineers) {
    const seen = {};

    engineers.forEach(e => {
        if (!e.lat || !e.lng) return;
        seen[e.engineer_id] = true;

        const iconHtml = `
            <div style="transform: rotate(${e.bearing || 0}deg);" class="w-8 h-8 rounded-full ${e.status_color === 'sky' ? 'bg-sky-600' : (e.status_color === 'amber' ? 'bg-amber-500' : 'bg-slate-600')} text-white flex items-center justify-center shadow-lg border-2 border-white text-xs">
                <i class="fa-solid ${e.status_color === 'sky' ? 'fa-motorcycle' : 'fa-user-check'}"></i>
            </div>
        `;

        const customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-fleet-pin',
            iconSize: [32, 32],
            iconAnchor: [16, 16],
            popupAnchor: [0, -16]
        });

        const popupContent = `
            <div class="p-2 space-y-1.5 text-xs font-sans">
                <div class="font-extrabold text-sm text-slate-900 flex items-center justify-between">
                    <span>${e.name}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-700">${e.battery !== null ? e.battery + '%' : ''}</span>
                </div>
                <div class="text-slate-600">Status: <b>${e.status_text}</b></div>
                <div class="text-slate-600">Speed: <b>${e.speed_kmh} km/h</b></div>
                <div class="text-slate-500 text-[11px]">Last Ping: ${e.recorded_at_time || e.last_updated}</div>
                <div class="pt-2">
                    <a href="{{ url('/tracking/engineer') }}/${e.engineer_id}" class="w-full block text-center py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow-xs transition">
                        <i class="fa-solid fa-play mr-1"></i> Play Trip Animation
                    </a>
                </div>
            </div>
        `;

        if (markers[e.engineer_id]) {
            markers[e.engineer_id].setLatLng([e.lat, e.lng]);
            markers[e.engineer_id].setIcon(customIcon);
            markers[e.engineer_id].setPopupContent(popupContent);
        } else {
            markers[e.engineer_id] = L.marker([e.lat, e.lng], { icon: customIcon })
                .addTo(map)
                .bindPopup(popupContent);
        }
    });

    // Remove old markers
    for (const id in markers) {
        if (!seen[id]) {
            map.removeLayer(markers[id]);
            delete markers[id];
        }
    }
}

function focusEngineer(id, lat, lng) {
    if (lat && lng) {
        map.flyTo([lat, lng], 15, { duration: 1 });
        if (markers[id]) {
            markers[id].openPopup();
        }
    }
}

function filterEngineers() {
    renderFleetList(engineersData);
}

function startCountdown() {
    clearInterval(timer);
    timer = setInterval(() => {
        countdown--;
        const el = document.getElementById('countdown');
        if (el) el.textContent = countdown;
        if (countdown <= 0) {
            fetchLiveData();
        }
    }, 1000);
}
</script>
@endsection
