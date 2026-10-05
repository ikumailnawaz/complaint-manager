@extends('layouts.app')

@section('title', 'Machine Models - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Top Ribbon -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-server text-cyan-600"></i>
                <span>Machine Models Catalog</span>
            </h1>
            <p class="text-xs text-slate-500">Supported equipment and banknote processing hardware models and their compatible components</p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg border border-slate-200 font-semibold">
                {{ $models->count() }} Models Configured
            </span>
        </div>
    </div>

    <!-- 2 Column Layout: Models List (Left) + Add New Form (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Models Table & Parts Accordion -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Registered Machine Models</h2>
                    <span class="text-[11px] text-slate-400">Click a model to inspect compatible parts</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($models as $model)
                        <div class="p-4 hover:bg-slate-50/50 transition">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 shrink-0">
                                        <i class="fa-solid {{ $model->machine_type_icon }} text-base text-sky-600"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <h3 class="text-sm font-bold text-slate-900">{{ $model->name }}</h3>
                                            @if(!empty($model->machine_type) && strtolower($model->machine_type) !== 'atm')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                                    {{ $model->machine_type === 'cdm' ? 'bg-purple-100 text-purple-800' : '' }}
                                                    {{ $model->machine_type === 'pos' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                                    {{ $model->machine_type === 'kiosk' ? 'bg-amber-100 text-amber-800' : '' }}
                                                    {{ $model->machine_type === 'other' ? 'bg-slate-100 text-slate-800' : '' }}">
                                                    {{ strtoupper($model->machine_type) }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            {{ $model->manufacturer ?? 'Generic' }} &bull; {{ $model->description ?? 'No extra specs' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Right stats & Action -->
                                <div class="flex items-center space-x-3 text-right shrink-0">
                                    <div class="text-xs">
                                        <span class="px-2 py-1 rounded bg-sky-50 text-sky-700 font-bold border border-sky-200">
                                            {{ $model->parts_count }} Parts
                                        </span>
                                    </div>
                                    <div class="text-xs">
                                        <span class="px-2 py-1 rounded bg-slate-100 text-slate-600 font-bold border border-slate-200">
                                            {{ $model->part_requests_count }} Requisitions
                                        </span>
                                    </div>

                                    @if(auth()->user()->isAdmin())
                                    <!-- Delete Model (if no requests) -->
                                    @if($model->part_requests_count == 0)
                                    <form action="{{ route('parts.master.models.destroy', $model) }}" method="POST" onsubmit="return confirm('Delete machine model {{ $model->name }}?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition" title="Delete Model">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                    @endif
                                    @endif
                                </div>
                            </div>

                            <!-- Attached Parts Collapsible -->
                            <details class="mt-3 group">
                                <summary class="cursor-pointer text-xs font-semibold text-sky-600 hover:text-sky-800 flex items-center space-x-1 outline-hidden">
                                    <i class="fa-solid fa-chevron-right text-[10px] transition group-open:rotate-90"></i>
                                    <span>Manage Compatible Parts ({{ $model->parts->count() }})</span>
                                </summary>
                                <div class="mt-3 pl-4 border-l-2 border-sky-100 space-y-3">
                                    <!-- List of parts attached -->
                                    <div class="flex flex-wrap gap-2">
                                        @forelse($model->parts as $part)
                                            <div class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs text-slate-700 shadow-2xs">
                                                <span class="font-mono text-slate-400 text-[10px]">{{ $part->part_number }}</span>
                                                <span class="font-medium">{{ $part->name }}</span>
                                                @if(auth()->user()->isAdmin())
                                                <form action="{{ route('parts.master.models.detach-part', [$model, $part]) }}" method="POST" class="inline ml-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-slate-400 hover:text-rose-600" title="Detach Part">
                                                        <i class="fa-solid fa-xmark text-[10px]"></i>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-xs text-slate-400 italic">No specific parts attached yet.</p>
                                        @endforelse
                                    </div>

                                    @if(auth()->user()->isAdmin())
                                    <!-- Quick Attach Part Form -->
                                    <form action="{{ route('parts.master.models.attach-part', $model) }}" method="POST" class="flex items-center space-x-2 pt-2">
                                        @csrf
                                        <select name="part_id" required class="text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 py-1.5">
                                            <option value="">-- Attach a Part from Catalog --</option>
                                            @foreach(\App\Models\Part::where('is_active', true)->whereNotIn('id', $model->parts->pluck('id'))->orderBy('name')->get() as $p)
                                                <option value="{{ $p->id }}">{{ $p->part_number }} - {{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-2xs transition">
                                            <i class="fa-solid fa-plus mr-1"></i> Attach
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </details>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 text-xs">
                            No machine models found. Add one from the form on the right.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Add New Model Card -->
        <div class="space-y-4">
            @if(auth()->user()->isAdmin())
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h2 class="text-sm font-bold text-slate-900 mb-1 flex items-center space-x-1.5">
                    <i class="fa-solid fa-plus-circle text-emerald-600"></i>
                    <span>Register New Machine Model</span>
                </h2>
                <p class="text-xs text-slate-500 mb-4">Define a new machine brand or series</p>

                <form action="{{ route('parts.master.models.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Model Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="name" required placeholder="e.g. NCR SelfServ 87"
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Manufacturer
                        </label>
                        <input type="text" name="manufacturer" placeholder="e.g. NCR, Wincor, Diebold, VeriFone"
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Machine Type <span class="text-rose-500">*</span>
                        </label>
                        <select name="machine_type" required class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm">
                            <option value="atm">ATM (Automated Teller Machine)</option>
                            <option value="cdm">CDM (Cash Deposit Machine)</option>
                            <option value="pos">POS (Point of Sale Terminal)</option>
                            <option value="kiosk">Self-Service Kiosk</option>
                            <option value="other">Other Terminal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Description / Notes
                        </label>
                        <textarea name="description" rows="2" placeholder="Optional notes regarding form factor, power supply..."
                            class="w-full text-xs rounded-lg border-slate-300 focus:border-sky-500 focus:ring-sky-500 shadow-sm"></textarea>
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-md transition flex items-center justify-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-save"></i>
                        <span>Save Machine Model</span>
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
