@extends('layouts.app')

@section('header_title', 'Tracking & Geofence Settings')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-4 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- System Configuration Form -->
    <form method="POST" action="{{ route('tracking.settings.save') }}" class="space-y-6">
        @csrf

        <!-- Security & Access -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2 mb-4">
                <i class="fa-solid fa-lock text-amber-500"></i> Module Lock Password
            </h3>
            <div class="max-w-md">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Access PIN / Password</label>
                <input type="text" name="module_password" value="{{ $settings['module_password'] ?? 'admin' }}" required class="w-full text-xs rounded-xl border-slate-300 font-mono">
                <p class="text-[11px] text-slate-500 mt-1">Prompted whenever user clicks the locked Fleet &amp; Attendance Tracking sidebar menu. Re-locks immediately upon leaving the module.</p>
            </div>
        </div>

        <!-- Duty Hours & Attendance Rules -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2 mb-4">
                <i class="fa-solid fa-business-time text-emerald-600"></i> Duty Hours &amp; Attendance Rules
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Duty Window Start</label>
                    <input type="time" name="duty_start_time" value="{{ $settings['duty_start_time'] ?? '08:00' }}" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">App tracks from this hour</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Duty Window End</label>
                    <input type="time" name="duty_end_time" value="{{ $settings['duty_end_time'] ?? '20:00' }}" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">App stops tracking after this</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Office Start Time</label>
                    <input type="time" name="attendance_start_time" value="{{ $settings['attendance_start_time'] ?? '09:00' }}" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">Expected morning arrival</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Late Grace Period (Mins)</label>
                    <input type="number" name="attendance_grace_minutes" value="{{ $settings['attendance_grace_minutes'] ?? 30 }}" min="0" max="120" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">Late marked if after 09:30 AM</p>
                </div>
            </div>
        </div>

        <!-- Telemetry & Stoppage Algorithm -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2 mb-4">
                <i class="fa-solid fa-satellite-dish text-sky-600"></i> GPS Telemetry &amp; Stop Detection Logic
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Moving Poll Interval (Sec)</label>
                    <input type="number" name="moving_interval_sec" value="{{ $settings['moving_interval_sec'] ?? 15 }}" min="5" max="120" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">GPS fix rate while moving</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stationary Poll (Sec)</label>
                    <input type="number" name="stationary_interval_sec" value="{{ $settings['stationary_interval_sec'] ?? 120 }}" min="30" max="600" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">Saves battery when stopped</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Upload Batch Interval (Sec)</label>
                    <input type="number" name="upload_interval_sec" value="{{ $settings['upload_interval_sec'] ?? 60 }}" min="15" max="300" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">Sync to server frequency</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stay Radius (Meters)</label>
                    <input type="number" name="stay_radius_meters" value="{{ $settings['stay_radius_meters'] ?? 80 }}" min="20" max="300" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">Max drift within stoppage</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Min Stop Duration (Mins)</label>
                    <input type="number" name="stay_min_minutes" value="{{ $settings['stay_min_minutes'] ?? 5 }}" min="2" max="60" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">Halt must exceed this to count as stop (avoids traffic lights)</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Exit Threshold (Meters)</label>
                    <input type="number" name="exit_radius_meters" value="{{ $settings['exit_radius_meters'] ?? 150 }}" min="50" max="500" required class="w-full text-xs rounded-xl border-slate-300">
                    <p class="text-[10px] text-slate-400 mt-0.5">Distance required to confirm departure from stop</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Save Settings
                </button>
            </div>
        </div>
    </form>

    <!-- Geofences Management (Offices & Hubs) -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-draw-polygon text-indigo-600"></i> Office &amp; Hub Geofences
                </h3>
                <p class="text-xs text-slate-500">Engineers entering these areas trigger automatic time-in attendance and office stay detection</p>
            </div>
            <button type="button" onclick="toggleGeofenceForm()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fa-solid fa-plus"></i> Add Geofence
            </button>
        </div>

        <!-- Add Geofence Form (Hidden by default) -->
        <div id="geofenceForm" class="hidden bg-slate-50 rounded-xl p-4 border border-slate-200">
            <h4 class="text-xs font-bold text-slate-800 mb-3">Create New Office / Hub Geofence</h4>
            <form method="POST" action="{{ route('tracking.settings.geofence.save') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @csrf
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Lahore Head Office" class="w-full text-xs rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Type</label>
                    <select name="type" class="w-full text-xs rounded-xl border-slate-300">
                        <option value="office">Main Office</option>
                        <option value="workshop">Central Workshop</option>
                        <option value="hub">Inventory Hub</option>
                        <option value="customer">Major Customer Branch</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Radius (Meters) *</label>
                    <input type="number" name="radius_meters" value="75" min="25" max="1000" required class="w-full text-xs rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Latitude *</label>
                    <input type="number" step="0.0000001" name="latitude" required placeholder="31.5397357" class="w-full text-xs rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Longitude *</label>
                    <input type="number" step="0.0000001" name="longitude" required placeholder="74.3306892" class="w-full text-xs rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">City</label>
                    <input type="text" name="city" placeholder="Lahore" class="w-full text-xs rounded-xl border-slate-300">
                </div>
                <div class="sm:col-span-2 lg:col-span-3 flex items-center justify-between pt-2">
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="is_attendance" value="1" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Use for Automated Daily Attendance (Time In &amp; Time Out)</span>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" onclick="toggleGeofenceForm()" class="px-3 py-1.5 bg-slate-200 text-slate-700 rounded-lg text-xs font-medium">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold">Save Geofence</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Existing Geofences List -->
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="text-[11px] font-semibold text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-3">Geofence Name</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3">Coordinates</th>
                        <th class="py-3 px-3">Radius</th>
                        <th class="py-3 px-3">Attendance</th>
                        <th class="py-3 px-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($geofences as $geo)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="py-3 px-3 font-semibold text-slate-900">
                            {{ $geo->name }}
                            @if($geo->city)
                                <span class="text-[10px] text-slate-400 font-normal block">{{ $geo->city }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 uppercase text-[10px] font-bold text-slate-600">
                            {{ $geo->type }}
                        </td>
                        <td class="py-3 px-3 font-mono text-slate-600">
                            {{ number_format($geo->latitude, 6) }}, {{ number_format($geo->longitude, 6) }}
                        </td>
                        <td class="py-3 px-3 font-semibold text-slate-800">
                            {{ $geo->radius_meters }}m (~{{ round($geo->radius_meters * 3.28084) }} ft)
                        </td>
                        <td class="py-3 px-3">
                            @if($geo->is_attendance)
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded text-[10px] font-bold">Enabled</span>
                            @else
                                <span class="text-slate-400 text-[10px]">Disabled</span>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            <form method="POST" action="{{ route('tracking.settings.geofence.delete', $geo->id) }}" onsubmit="return confirm('Delete this geofence?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 font-semibold text-xs">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-xs text-slate-400">No geofences configured yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gemini AI Parameter Self-Tuning Assistant -->
    <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-950 text-white rounded-2xl p-6 border border-indigo-800/40 shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-indigo-400 text-lg">
                    <i class="fa-solid fa-brain"></i>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-indigo-200">Gemini AI Parameter Self-Tuning Assistant</h3>
                    <p class="text-xs text-slate-400">Evaluates noise levels, GPS drift, and traffic delays to suggest optimal stay &amp; exit thresholds</p>
                </div>
            </div>
            <form method="POST" action="{{ route('tracking.ai-review') }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Run AI Diagnostics
                </button>
            </form>
        </div>

        @if(count($aiReviews) > 0)
            <div class="space-y-3 pt-2">
                <h4 class="text-xs font-bold text-indigo-300">Recent AI Reviews</h4>
                @foreach($aiReviews as $rev)
                <div class="bg-slate-950/60 rounded-xl p-4 border border-slate-800/80 text-xs space-y-2">
                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                        <span>{{ $rev->created_at->diffForHumans() }}</span>
                        <span class="font-mono text-emerald-400">Stay: {{ $rev->suggested_stay_radius }}m • Min: {{ $rev->suggested_stay_min }}m</span>
                    </div>
                    <div class="text-slate-300 whitespace-pre-line leading-relaxed">
                        {{ $rev->recommendations }}
                    </div>
                </div>
                @endforeach
            </div>
        @else
            <div class="bg-slate-950/40 rounded-xl p-4 border border-slate-800/80 text-xs text-slate-400 text-center">
                Click "Run AI Diagnostics" to let Gemini analyze your recent telemetry fixes and suggest the most accurate stay &amp; stoppage parameters.
            </div>
        @endif
    </div>
</div>

<script>
function toggleGeofenceForm() {
    const el = document.getElementById('geofenceForm');
    el.classList.toggle('hidden');
}
</script>
@endsection
