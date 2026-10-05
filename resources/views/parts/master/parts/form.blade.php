@extends('layouts.app')

@section('title', isset($part) ? 'Edit Part: ' . $part->name : 'Add New Part - Bank Complaint Manager')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header & Breadcrumb -->
    <div class="flex items-center justify-between bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('parts.master.parts') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back to Parts">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ isset($part) ? 'Edit Part: ' . $part->name : 'Add New Part' }}</h1>
                <p class="text-xs text-slate-500">Master Catalog for ATM / CDM / POS spare components</p>
            </div>
        </div>
        <a href="{{ route('parts.master.parts') }}" class="text-xs text-slate-600 hover:text-slate-900 font-semibold px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 transition">
            Cancel & Return
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <form action="{{ isset($part) ? route('parts.master.parts.update', $part) : route('parts.master.parts.store') }}" method="POST" class="p-6 space-y-6">
            @csrf
            @if(isset($part))
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Part Number -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Part Number / SKU <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="part_number" value="{{ old('part_number', $part->part_number ?? '') }}" required placeholder="e.g. NCR-CASS-2220"
                        class="w-full text-sm rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm font-mono">
                    <p class="text-[11px] text-slate-400 mt-1">Unique part code or manufacturer reference</p>
                    @error('part_number')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Part Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Part Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $part->name ?? '') }}" required placeholder="e.g. Cash Cassette 2220"
                        class="w-full text-sm rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Unit -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Unit of Measurement <span class="text-rose-500">*</span>
                    </label>
                    <select name="unit" required class="w-full text-sm rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                        @foreach(['PCS' => 'Pieces (PCS)', 'SET' => 'Set (SET)', 'BOX' => 'Box (BOX)', 'ROLL' => 'Roll (ROLL)', 'KG' => 'Kilogram (KG)', 'PAIR' => 'Pair (PAIR)'] as $code => $lbl)
                            <option value="{{ $code }}" {{ old('unit', $part->unit ?? 'PCS') === $code ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @error('unit')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Unit Cost -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Benchmark Unit Cost (PKR)
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400 font-bold">PKR</span>
                        <input type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost', $part->unit_cost ?? '') }}" placeholder="0.00"
                            class="w-full pl-12 text-sm rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>
                    @error('unit_cost')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Reorder Level -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Reorder Threshold Level <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" min="0" name="reorder_level" value="{{ old('reorder_level', $part->reorder_level ?? 0) }}" required placeholder="0"
                        class="w-full text-sm rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    <p class="text-[11px] text-slate-400 mt-1">Triggers low-stock amber warning when stock drops to or below this</p>
                    @error('reorder_level')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status Checkbox (Only on Edit or default active) -->
                <div class="flex items-center pt-6">
                    <label class="relative flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $part->is_active ?? true) ? 'checked' : '' }}
                            class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 rounded">
                        <span class="text-sm font-semibold text-slate-800">Item is Active & Available for Requisition</span>
                    </label>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Technical Specifications / Description
                </label>
                <textarea name="description" rows="3" placeholder="Provide part compatibility, pin configurations or manufacturer details..."
                    class="w-full text-sm rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">{{ old('description', $part->description ?? '') }}</textarea>
                @error('description')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Compatible Machine Models -->
            <div class="border-t border-slate-100 pt-6">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Compatible Machine Models</h3>
                        <p class="text-xs text-slate-500">Select which machine models can accept this part during field technician requests.</p>
                    </div>
                    <span class="text-xs text-sky-600 font-semibold">{{ count($models) }} Models in Database</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 max-h-60 overflow-y-auto">
                    @forelse($models as $model)
                        <label class="flex items-center space-x-2 text-xs text-slate-700 hover:text-slate-900 bg-white p-2.5 rounded-lg border border-slate-200 cursor-pointer shadow-2xs hover:border-sky-400 transition">
                            <input type="checkbox" name="model_ids[]" value="{{ $model->id }}"
                                {{ in_array($model->id, old('model_ids', $selectedModelIds ?? [])) ? 'checked' : '' }}
                                class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 h-4 w-4">
                            <span class="font-medium truncate">{{ $model->name }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 uppercase font-mono ml-auto">{{ $model->machine_type }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-slate-400 col-span-3 py-2 text-center">No machine models registered yet. Create one in Master Models.</p>
                    @endforelse
                </div>
                @error('model_ids')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-slate-100">
                <a href="{{ route('parts.master.parts') }}" class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-md transition flex items-center space-x-2 cursor-pointer">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>{{ isset($part) ? 'Update Part Information' : 'Save Part to Catalog' }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
