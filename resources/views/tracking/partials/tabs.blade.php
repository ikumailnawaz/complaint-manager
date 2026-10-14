<!-- Root Level Access Navigation Tabs -->
<div class="bg-white rounded-2xl border border-slate-200/80 p-3 shadow-xs mb-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <!-- Tabs Group -->
        <nav class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            <!-- 1. Live Fleet Map -->
            <a href="{{ route('tracking.live') }}" 
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('tracking.live*') ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-900/30' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <i class="fa-solid fa-map-location-dot text-xs {{ request()->routeIs('tracking.live*') ? 'text-white' : 'text-emerald-500' }}"></i>
                <span>Live Fleet Map</span>
            </a>

            <!-- 2. Route Simulation -->
            <a href="{{ route('tracking.playback') }}" 
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('tracking.playback*') || request()->routeIs('tracking.engineer*') ? 'bg-teal-600 text-white shadow-sm shadow-teal-900/30' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <i class="fa-solid fa-play text-xs {{ request()->routeIs('tracking.playback*') ? 'text-white' : 'text-teal-500' }}"></i>
                <span>Route Simulation</span>
            </a>

            <!-- 3. Auto Attendance -->
            <a href="{{ route('tracking.attendance') }}" 
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('tracking.attendance*') ? 'bg-amber-600 text-white shadow-sm shadow-amber-900/30' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <i class="fa-solid fa-clipboard-user text-xs {{ request()->routeIs('tracking.attendance*') ? 'text-white' : 'text-amber-500' }}"></i>
                <span>Auto Attendance</span>
            </a>

            <!-- 4. Travel & Stop Reports -->
            <a href="{{ route('tracking.reports') }}" 
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('tracking.reports*') ? 'bg-sky-600 text-white shadow-sm shadow-sky-900/30' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <i class="fa-solid fa-chart-column text-xs {{ request()->routeIs('tracking.reports*') ? 'text-white' : 'text-sky-500' }}"></i>
                <span>Travel &amp; Stop Reports</span>
            </a>

            <!-- 5. Geofences & Settings -->
            <a href="{{ route('tracking.settings') }}" 
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ request()->routeIs('tracking.settings*') ? 'bg-violet-600 text-white shadow-sm shadow-violet-900/30' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <i class="fa-solid fa-sliders text-xs {{ request()->routeIs('tracking.settings*') ? 'text-white' : 'text-violet-500' }}"></i>
                <span>Geofences &amp; Settings</span>
            </a>
        </nav>

        <!-- Right Side: Status Badge & Explicit Lock Button -->
        <div class="flex items-center gap-2">
            <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-lg text-[10px] font-mono font-bold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>ROOT CLEARANCE</span>
            </span>

            <a href="{{ route('tracking.lock-now') }}" 
               title="Lock Root Level Access immediately"
               class="px-3 py-1.5 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 border border-slate-300 hover:border-rose-300 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fa-solid fa-lock text-[11px] text-rose-500"></i>
                <span>Lock Now</span>
            </a>
        </div>
    </div>
</div>
