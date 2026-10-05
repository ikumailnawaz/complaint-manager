@extends('layouts.app')

@section('title', 'Edit Machine — ' . $machine->serial_number)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <div class="flex items-center space-x-4">
        <a href="{{ route('pm.machines.show', $machine) }}" class="text-slate-400 hover:text-slate-700 transition">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-black text-slate-900">Edit Machine</h1>
            <p class="text-slate-500 text-sm font-mono">{{ $machine->serial_number }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('pm.machines.update', $machine) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
        @csrf @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm space-y-1">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
        @endif

        <div class="grid grid-cols-2 gap-5">
            <div class="col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Machine Model <span class="text-red-500">*</span></label>
                <select name="machine_model_id" required class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                    @foreach($machineModels as $model)
                        <option value="{{ $model->id }}" @selected(old('machine_model_id', $machine->machine_model_id) == $model->id)>
                            {{ $model->name }} ({{ $model->manufacturer }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Serial Number <span class="text-red-500">*</span></label>
                <input type="text" name="serial_number" value="{{ old('serial_number', $machine->serial_number) }}" required
                    class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Asset Tag</label>
                <input type="text" name="asset_tag" value="{{ old('asset_tag', $machine->asset_tag) }}"
                    class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Location / Branch <span class="text-red-500">*</span></label>
                <input type="text" name="location" value="{{ old('location', $machine->location) }}" required
                    class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Bank Name</label>
                <input type="text" name="bank_name" value="{{ old('bank_name', $machine->bank_name) }}"
                    class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Installation Date</label>
                <input type="date" name="installed_at" value="{{ old('installed_at', $machine->installed_at?->format('Y-m-d')) }}"
                    class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Assigned Engineer</label>
                <select name="assigned_engineer_id" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                    <option value="">-- Unassigned --</option>
                    @foreach($engineers as $eng)
                        <option value="{{ $eng->id }}" @selected(old('assigned_engineer_id', $machine->assigned_engineer_id) == $eng->id)>{{ $eng->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                    class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500">{{ old('notes', $machine->notes) }}</textarea>
            </div>

            <div class="col-span-2 flex items-center space-x-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $machine->is_active)) class="rounded">
                <label for="is_active" class="text-sm font-semibold text-slate-700">Machine is Active</label>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-2 border-t border-slate-100">
            <a href="{{ route('pm.machines.show', $machine) }}" class="px-5 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow transition">
                <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
