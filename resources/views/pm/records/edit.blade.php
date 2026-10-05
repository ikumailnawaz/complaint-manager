@extends('layouts.app')

@section('title', 'Edit Service Record — ' . $record->machine->serial_number)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center space-x-4">
        <a href="{{ auth()->user()->isEngineer() ? route('pm.tasks.index') : route('pm.machines.show', $record->pm_machine_id) }}" class="text-slate-400 hover:text-slate-700 transition text-xl">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-black text-slate-900">Edit PM Service Record</h1>
            <p class="text-slate-500 text-sm">Update task notes or replace supporting document</p>
        </div>
    </div>

    {{-- Machine & Task Info Banner --}}
    <div class="bg-teal-50 border border-teal-200 rounded-2xl p-5 space-y-2">
        <div class="flex items-center justify-between">
            <span class="font-black text-teal-900 text-lg">{{ $record->schedule?->title ?? 'Maintenance Service' }}</span>
            <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">
                Status: {{ ucfirst($record->status) }}
            </span>
        </div>
        <div class="grid grid-cols-2 gap-2 text-xs text-teal-800">
            <div><span class="text-teal-600 font-semibold">Machine:</span> <span class="font-mono font-bold">{{ $record->machine->serial_number }}</span> ({{ $record->machine->machineModel->name ?? '' }})</div>
            <div><span class="text-teal-600 font-semibold">Location:</span> {{ $record->machine->location }}</div>
            <div><span class="text-teal-600 font-semibold">Due Date:</span> {{ $record->due_date->format('d M Y') }}</div>
            <div><span class="text-teal-600 font-semibold">Engineer:</span> {{ $record->performedBy?->name ?? 'Unassigned' }}</div>
        </div>
    </div>

    {{-- Edit Form --}}
    <form method="POST" action="{{ route('pm.records.update', $record) }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
        @csrf
        @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm space-y-1">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
        @endif

        {{-- Performed Date --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Service Performed Date</label>
            <input type="datetime-local" name="performed_at" 
                   value="{{ old('performed_at', $record->performed_at ? $record->performed_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}"
                   class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm">
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Observations / Service Notes</label>
            <textarea name="notes" rows="4" placeholder="Describe work done, parts replaced, machine condition…"
                      class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm">{{ old('notes', $record->notes) }}</textarea>
        </div>

        {{-- Current Supporting Document --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Current Supporting Document</label>
            @if($record->hasDocument())
                <div class="border border-slate-200 rounded-xl p-3 bg-slate-50 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        @if($record->isImageDocument())
                            <a href="{{ route('pm.records.view', $record) }}" target="_blank" class="block shrink-0">
                                <img src="{{ route('pm.records.view', $record) }}" alt="Document" class="w-12 h-12 object-cover rounded-lg border border-slate-200 shadow-sm hover:opacity-90">
                            </a>
                        @else
                            <div class="w-12 h-12 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center text-xl">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>
                        @endif
                        <div>
                            <div class="text-sm font-bold text-slate-800 break-all">{{ $record->document_original_name ?? 'Uploaded Document' }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">Uploaded on {{ $record->updated_at->format('d M Y, h:i A') }}</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 shrink-0">
                        <a href="{{ route('pm.records.view', $record) }}" target="_blank" class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold transition shadow-sm">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>View
                        </a>
                        <a href="{{ route('pm.records.download', $record) }}" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition">
                            <i class="fa-solid fa-download mr-1"></i>Download
                        </a>
                    </div>
                </div>
            @else
                <div class="border border-dashed border-amber-300 rounded-xl p-3 bg-amber-50 text-amber-800 text-xs flex items-center space-x-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm"></i>
                    <span>No document uploaded for this service record yet. Please attach one below.</span>
                </div>
            @endif
        </div>

        {{-- Upload New / Replacement Document --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">
                {{ $record->hasDocument() ? 'Replace Supporting Document (Optional)' : 'Upload Supporting Document' }}
            </label>
            <p class="text-xs text-slate-400 mb-2">Upload photo, report, or signed receipt (PDF, JPG, PNG, WEBP, DOCX — max 15MB)</p>

            <div class="space-y-3">
                <label for="document-input" id="dropzone" class="border-2 border-dashed border-slate-300 hover:border-teal-500 rounded-xl p-6 text-center cursor-pointer transition bg-slate-50/50 block">
                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                    <p class="text-sm font-semibold text-slate-700">Click to choose file or drag &amp; drop here</p>
                    <p class="text-xs text-slate-400 mt-1">Supported: PDF, JPG, PNG, WEBP, DOCX up to 15MB</p>
                    <span class="mt-3 inline-flex items-center px-3.5 py-1.5 bg-white border border-slate-300 hover:border-teal-500 rounded-lg text-xs font-bold text-slate-700 shadow-xs">
                        <i class="fa-solid fa-folder-open text-teal-600 mr-1.5"></i>Browse File
                    </span>
                </label>
                <input type="file" id="document-input" name="document" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
            </div>

            {{-- Live File Preview Box --}}
            <div id="file-preview-box" class="hidden mt-3 p-3.5 bg-emerald-50 border border-emerald-300 rounded-xl flex items-center justify-between">
                <div class="flex items-center space-x-3 overflow-hidden">
                    <img id="img-preview" class="w-12 h-12 object-cover rounded-lg border border-emerald-300 hidden" alt="Preview">
                    <div id="doc-icon-preview" class="w-12 h-12 rounded-lg bg-emerald-200 text-emerald-800 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-file"></i>
                    </div>
                    <div class="truncate">
                        <div class="flex items-center space-x-1">
                            <span class="text-[10px] bg-emerald-200 text-emerald-900 px-1.5 py-0.2 rounded font-bold uppercase">Ready</span>
                            <p id="preview-name" class="text-xs font-bold text-emerald-950 truncate"></p>
                        </div>
                        <p id="preview-size" class="text-[11px] text-emerald-700 mt-0.5"></p>
                    </div>
                </div>
                <button type="button" id="remove-file-btn" class="text-xs text-red-600 hover:text-red-800 font-bold px-2 py-1 rounded hover:bg-red-50 shrink-0">
                    <i class="fa-solid fa-xmark mr-1"></i>Remove
                </button>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
            <a href="{{ auth()->user()->isEngineer() ? route('pm.tasks.index') : route('pm.machines.show', $record->pm_machine_id) }}" 
               class="px-5 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 transition text-sm">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow transition text-sm flex items-center space-x-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save Changes</span>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('dropzone');
    const input = document.getElementById('document-input');
    const previewBox = document.getElementById('file-preview-box');
    const previewName = document.getElementById('preview-name');
    const previewSize = document.getElementById('preview-size');
    const imgPreview = document.getElementById('img-preview');
    const docIcon = document.getElementById('doc-icon-preview');
    const removeBtn = document.getElementById('remove-file-btn');

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('border-teal-500', 'bg-teal-50/40');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('border-teal-500', 'bg-teal-50/40');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            handleFileSelect(input.files[0]);
        }
    });

    input.addEventListener('change', () => {
        if (input.files && input.files[0]) {
            handleFileSelect(input.files[0]);
        }
    });

    function handleFileSelect(file) {
        if (!file) return;
        previewName.textContent = file.name;
        previewSize.textContent = (file.size / 1024 > 1024) 
            ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
            : (file.size / 1024).toFixed(1) + ' KB';

        if (file.type && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imgPreview.src = e.target.result;
                imgPreview.classList.remove('hidden');
                docIcon.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            imgPreview.classList.add('hidden');
            docIcon.classList.remove('hidden');
        }

        previewBox.classList.remove('hidden');
    }

    removeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        input.value = '';
        previewBox.classList.add('hidden');
        imgPreview.src = '';
    });
});
</script>
@endsection
