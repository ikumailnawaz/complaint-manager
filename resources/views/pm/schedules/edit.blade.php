@extends('layouts.app')

@section('title', 'Edit Schedule')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <div class="flex items-center space-x-4">
        <a href="{{ route('pm.schedules.index') }}" class="text-slate-400 hover:text-slate-700 transition">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-black text-slate-900">Edit Schedule</h1>
            <p class="text-slate-500 text-sm">{{ $schedule->title }} — {{ $schedule->machine->serial_number ?? '' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('pm.schedules.update', $schedule) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
        @csrf @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm space-y-1">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
        @endif

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Machine <span class="text-red-500">*</span></label>
            <select name="pm_machine_id" required class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                @foreach($machines as $machine)
                    <option value="{{ $machine->id }}" @selected(old('pm_machine_id', $schedule->pm_machine_id) == $machine->id)>
                        {{ $machine->serial_number }} — {{ $machine->machineModel->name ?? '' }} ({{ $machine->location }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Task Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $schedule->title) }}" required
                class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Frequency <span class="text-red-500">*</span></label>
            <select name="frequency_days" required class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                <option value="30"  @selected(old('frequency_days', $schedule->frequency_days) == 30)>Monthly (every 30 days)</option>
                <option value="60"  @selected(old('frequency_days', $schedule->frequency_days) == 60)>Bi-Monthly (every 60 days)</option>
                <option value="90"  @selected(old('frequency_days', $schedule->frequency_days) == 90)>Quarterly (every 90 days)</option>
                <option value="180" @selected(old('frequency_days', $schedule->frequency_days) == 180)>Semi-Annual (every 180 days)</option>
                <option value="365" @selected(old('frequency_days', $schedule->frequency_days) == 365)>Annual (every 365 days)</option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Next Due Date <span class="text-red-500">*</span></label>
                <input type="date" name="next_due_date" value="{{ old('next_due_date', $schedule->next_due_date?->format('Y-m-d')) }}" required
                    class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Assigned Engineer <span class="text-xs text-slate-400">(override)</span></label>
                <select name="assigned_engineer_id" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                    <option value="">Machine default</option>
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}" @selected(old('assigned_engineer_id', $schedule->assigned_engineer_id) == $eng->id)>{{ $eng->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $schedule->is_active)) class="rounded">
            <label for="is_active" class="text-sm font-semibold text-slate-700">Schedule is Active</label>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-2 border-t border-slate-100">
            <a href="{{ route('pm.schedules.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow transition">
                <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
