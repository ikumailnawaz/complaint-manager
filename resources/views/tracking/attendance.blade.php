@extends('layouts.app')

@section('header_title', 'Automated Geofence Attendance Register')

@section('content')
<div class="space-y-4 max-w-7xl mx-auto">
    @include('tracking.partials.tabs')

    <!-- Filter Header -->
    <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200">
        <form method="GET" action="{{ route('tracking.attendance') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
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
                <label class="block text-xs font-semibold text-slate-600 mb-1">From Date</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="w-full text-xs rounded-xl border-slate-300 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">To Date</label>
                <input type="date" name="to_date" value="{{ $toDate }}" class="w-full text-xs rounded-xl border-slate-300 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <a href="{{ route('tracking.attendance') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition flex items-center justify-center" title="Reset Filters">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
            <div class="text-right text-[11px] text-slate-500 self-center hidden lg:block">
                <span>Office Geofence: </span>
                <span class="font-bold text-slate-800">Lahore Central (75m radius)</span>
                <span class="block text-emerald-600 font-medium">Auto-marks on first GPS entry &amp; last exit</span>
            </div>
        </form>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-user text-emerald-600"></i> Attendance Ledger
                </h3>
                <p class="text-xs text-slate-500">Showing records from <span class="font-semibold text-slate-700">{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }}</span> to <span class="font-semibold text-slate-700">{{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</span> ({{ count($attendanceDays) }} days logged)</p>
            </div>
            <span class="text-xs text-slate-500">Grace period: 30 mins after 09:00 AM (Late after 09:30 AM)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="text-[11px] font-semibold text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Engineer</th>
                        <th class="py-3 px-3">First In</th>
                        <th class="py-3 px-3">Last Out</th>
                        <th class="py-3 px-3">Office Time</th>
                        <th class="py-3 px-3">Field Transit</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attendanceDays as $day)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="py-3 px-3 font-semibold text-slate-900">
                            {{ $day->date }}
                            <span class="block text-[10px] text-slate-400 font-normal">
                                {{ \Carbon\Carbon::parse($day->date)->format('l') }}
                            </span>
                        </td>
                        <td class="py-3 px-3 font-medium text-slate-800">
                            {{ $day->engineer->name ?? 'N/A' }}
                        </td>
                        <td class="py-3 px-3 font-medium">
                            @if($day->first_in_at)
                                <span class="text-slate-900">{{ \Carbon\Carbon::parse($day->first_in_at)->timezone('Asia/Karachi')->format('h:i A') }}</span>
                                @if($day->is_late)
                                    <span class="block text-[10px] font-semibold text-rose-600">Late by {{ $day->late_minutes }}m</span>
                                @else
                                    <span class="block text-[10px] font-semibold text-emerald-600">On Time</span>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 font-medium">
                            @if($day->last_out_at)
                                <span class="text-slate-900">{{ \Carbon\Carbon::parse($day->last_out_at)->timezone('Asia/Karachi')->format('h:i A') }}</span>
                            @else
                                <span class="text-slate-400">Inside Office</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-slate-700">
                            {{ round($day->office_minutes / 60, 1) }} hrs
                            <span class="text-[10px] text-slate-400 block">({{ $day->entries_count }} entries)</span>
                        </td>
                        <td class="py-3 px-3 text-slate-700">
                            {{ round($day->field_minutes / 60, 1) }} hrs
                        </td>
                        <td class="py-3 px-3">
                            @if($day->status === 'present')
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[10px] font-bold">Present</span>
                            @elseif($day->status === 'late')
                                <span class="px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded text-[10px] font-bold">Late</span>
                            @elseif($day->status === 'on_field')
                                <span class="px-2 py-0.5 bg-sky-50 text-sky-700 border border-sky-200 rounded text-[10px] font-bold">On Field</span>
                            @elseif($day->status === 'half_day')
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[10px] font-bold">Half Day</span>
                            @elseif($day->status === 'excused')
                                <span class="px-2 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 rounded text-[10px] font-bold">Excused</span>
                            @else
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-bold">Absent</span>
                            @endif

                            @if($day->is_manual_override)
                                <span class="block text-[9px] text-amber-600 font-mono mt-0.5" title="Overridden by {{ $day->overriddenBy->name ?? 'Admin' }}: {{ $day->override_reason }}">
                                    <i class="fa-solid fa-user-pen"></i> Overridden
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            <button type="button" 
                                    onclick="openOverrideModal({{ json_encode($day) }})"
                                    class="text-xs text-sky-600 hover:text-sky-800 font-semibold inline-flex items-center gap-1">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-xs text-slate-400">
                            No attendance records found for this period.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manual Override Modal -->
<div id="overrideModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-amber-500"></i> Override Attendance
            </h4>
            <button type="button" onclick="closeOverrideModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('tracking.attendance.override') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="attendance_id" id="overrideAttendanceId">

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                <select name="status" id="overrideStatus" class="w-full text-xs rounded-xl border-slate-300">
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="on_field">On Field (Client direct)</option>
                    <option value="half_day">Half Day</option>
                    <option value="absent">Absent</option>
                    <option value="excused">Excused Leave</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">First In Time</label>
                    <input type="time" name="first_in_at" id="overrideFirstIn" class="w-full text-xs rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Last Out Time</label>
                    <input type="time" name="last_out_at" id="overrideLastOut" class="w-full text-xs rounded-xl border-slate-300">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Reason for Override *</label>
                <textarea name="override_reason" rows="2" required placeholder="e.g. Engineer went directly to client branch in morning per supervisor instructions" class="w-full text-xs rounded-xl border-slate-300"></textarea>
            </div>

            <div class="flex gap-2 justify-end pt-2">
                <button type="button" onclick="closeOverrideModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-xs">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openOverrideModal(day) {
    document.getElementById('overrideAttendanceId').value = day.id;
    document.getElementById('overrideStatus').value = day.status;
    if (day.first_in_at) {
        const d = new Date(day.first_in_at);
        document.getElementById('overrideFirstIn').value = d.toTimeString().substring(0, 5);
    } else {
        document.getElementById('overrideFirstIn').value = '';
    }
    if (day.last_out_at) {
        const d = new Date(day.last_out_at);
        document.getElementById('overrideLastOut').value = d.toTimeString().substring(0, 5);
    } else {
        document.getElementById('overrideLastOut').value = '';
    }
    const modal = document.getElementById('overrideModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeOverrideModal() {
    const modal = document.getElementById('overrideModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
@endsection
