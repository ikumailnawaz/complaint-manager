@extends('layouts.app')

@section('title', 'Complete Task — ' . $schedule->title)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Back --}}
    <div class="flex items-center space-x-4">
        <a href="{{ route('pm.tasks.index') }}" class="text-slate-400 hover:text-slate-700 transition">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-black text-slate-900">Complete PM Task</h1>
            <p class="text-slate-500 text-sm">Upload proof and mark as done</p>
        </div>
    </div>

    {{-- Machine Info Card --}}
    <div class="bg-teal-50 border border-teal-200 rounded-2xl p-5 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <div class="font-black text-teal-800 text-lg">{{ $schedule->title }}</div>
                <div class="flex items-center space-x-2 text-sm text-teal-700 mt-0.5">
                    <span class="font-mono font-bold">{{ $schedule->machine->serial_number }}</span>
                    <span>·</span>
                    <span>{{ $schedule->machine->machineModel->name ?? '—' }}</span>
                    <span>·</span>
                    <span>{{ $schedule->machine->machineModel->machine_type ?? '' }}</span>
                </div>
            </div>
            @if($schedule->is_overdue)
                <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold animate-pulse">🔴 OVERDUE</span>
            @elseif($schedule->is_due_soon)
                <span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">🟡 Due Soon</span>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-teal-600 font-semibold">Bank:</span> <span class="text-slate-800">{{ $schedule->machine->bank_name ?? '—' }}</span></div>
            <div><span class="text-teal-600 font-semibold">Location:</span> <span class="text-slate-800">{{ $schedule->machine->location }}</span></div>
            <div><span class="text-teal-600 font-semibold">Due Date:</span>
                <span class="font-bold {{ $schedule->is_overdue ? 'text-red-600' : 'text-slate-800' }}">
                    {{ $schedule->next_due_date?->format('d M Y') ?? '—' }}
                    @if($schedule->is_overdue) ({{ abs($schedule->days_until_due) }} days overdue) @endif
                </span>
            </div>
            <div><span class="text-teal-600 font-semibold">Frequency:</span> <span class="text-slate-800">{{ $schedule->frequency_label }}</span></div>
            @if($schedule->last_performed_at)
            <div class="col-span-2"><span class="text-teal-600 font-semibold">Last Performed:</span> <span class="text-slate-800">{{ $schedule->last_performed_at->format('d M Y') }}</span></div>
            @endif
        </div>
    </div>

    {{-- Previous Records (last 5) --}}
    @if($schedule->records->isNotEmpty())
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 text-sm font-bold text-slate-700">Previous Records</div>
        <table class="w-full text-xs">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left px-4 py-2 font-semibold text-slate-500">Date</th>
                    <th class="text-left px-4 py-2 font-semibold text-slate-500">Status</th>
                    <th class="text-left px-4 py-2 font-semibold text-slate-500">Notes</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($schedule->records as $rec)
                <tr>
                    <td class="px-4 py-2 text-slate-700">{{ $rec->performed_at?->format('d M Y') ?? $rec->due_date->format('d M Y') }}</td>
                    <td class="px-4 py-2">
                        @if($rec->status === 'completed' && !$rec->is_overdue)
                            <span class="text-emerald-600 font-bold">✅ On Time</span>
                        @elseif($rec->status === 'completed')
                            <span class="text-amber-600 font-bold">⚠ Late</span>
                        @else
                            <span class="text-red-600 font-bold">✗ Missed</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-slate-500 max-w-xs truncate">{{ $rec->notes ?? '—' }}</td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex items-center justify-end space-x-2">
                            @if($rec->document_path)
                                <a href="{{ route('pm.records.view', $rec) }}" target="_blank" class="text-teal-600 hover:text-teal-800 font-bold" title="View Document">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                                <a href="{{ route('pm.records.download', $rec) }}" class="text-slate-500 hover:text-slate-700" title="Download">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </a>
                            @endif
                            <a href="{{ route('pm.records.edit', $rec) }}" class="text-indigo-600 hover:text-indigo-800 font-bold text-xs" title="Edit this record">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @php
        $existingCompleted = $schedule->records->where('status', 'completed')->sortByDesc('performed_at')->first();
    @endphp

    @if($existingCompleted)
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div class="font-bold text-emerald-950 text-sm">
                    Previously Completed: {{ $existingCompleted->performed_at?->format('d M Y, h:i A') }}
                </div>
                <div class="text-xs text-emerald-700">
                    By: {{ $existingCompleted->performedBy?->name ?? 'Engineer' }}
                    @if($existingCompleted->hasDocument())
                        • Attached: <span class="font-mono font-semibold">{{ $existingCompleted->document_original_name }}</span>
                    @endif
                </div>
            </div>
        </div>
        @if($existingCompleted->hasDocument())
        <a href="{{ route('pm.records.view', $existingCompleted) }}" target="_blank"
           class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-xs flex items-center space-x-1">
            <i class="fa-solid fa-file-lines"></i>
            <span>View Current Doc</span>
        </a>
        @endif
    </div>
    @endif

    {{-- Completion Form --}}
    <form method="POST" action="{{ route('pm.tasks.complete', $schedule) }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm space-y-1">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
        @endif

        {{-- Performed Date --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Service Performed Date</label>
            <input type="datetime-local" name="performed_at" 
                   value="{{ old('performed_at', $existingCompleted ? $existingCompleted->performed_at?->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}"
                   class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Observations / Service Notes</label>
            <textarea name="notes" rows="4" placeholder="Describe what was done, any observations, parts inspected, issues resolved…"
                class="w-full border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm">{{ old('notes', $existingCompleted?->notes) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">
                {{ $existingCompleted && $existingCompleted->hasDocument() ? 'Replace Supporting Document (Optional)' : 'Supporting Document' }}
            </label>
            <p class="text-xs text-slate-400 mb-2">Upload photo, service report, signed checklist (PDF, JPG, PNG, WEBP, DOCX — max 15MB)</p>
            
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

        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
            <a href="{{ route('pm.tasks.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 transition text-sm">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl shadow transition flex items-center space-x-2 text-sm">
                <i class="fa-solid fa-circle-check"></i>
                <span>{{ $existingCompleted ? 'Save & Update Record' : 'Mark as Completed' }}</span>
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
