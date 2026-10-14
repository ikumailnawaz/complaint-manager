@extends('layouts.app')

@section('title', 'Root Level Access')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 text-center text-white relative">
            <div class="w-16 h-16 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center mx-auto mb-3 shadow-inner">
                <i class="fa-solid fa-shield-halved text-2xl text-indigo-400"></i>
            </div>
            <h2 class="text-xl font-extrabold tracking-tight">Root Level Access</h2>
            <p class="text-xs text-indigo-200/80 mt-1">Elevated System Security Authorization</p>
            <div class="absolute top-3 right-3 text-[10px] px-2.5 py-0.5 rounded-full bg-indigo-500/30 text-indigo-200 font-mono tracking-wider">
                ROOT ACCESS
            </div>
        </div>

        <!-- Form Body -->
        <div class="p-6">
            @if(session('error'))
                <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-center space-x-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <form action="{{ route('tracking.unlock') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="redirect" value="{{ $redirect }}">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                        Root Access Password
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <i class="fa-solid fa-key text-xs"></i>
                        </span>
                        <input type="password" 
                               name="password" 
                               id="tracking_password_input" 
                               required 
                               autofocus
                               placeholder="Enter password (default: admin)..."
                               class="w-full pl-9 pr-10 py-2.5 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition font-mono">
                        <button type="button" 
                                onclick="togglePasswordVisibility()" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                            <i id="eye_icon" class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                    <p class="text-[11px] text-emerald-600 font-medium mt-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>100% Free OpenStreetMap tracking • No API key or fee required</span>
                    </p>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-unlock-keyhole"></i>
                        <span>Authenticate &amp; Unlock</span>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span class="flex items-center space-x-1.5">
                    <i class="fa-solid fa-lock text-slate-400"></i>
                    <span>Auto-locks upon leaving</span>
                </span>
                <a href="{{ route('dashboard') }}" class="text-indigo-600 hover:text-indigo-800 font-semibold hover:underline">
                    Back to Dashboard &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('tracking_password_input');
    const eye = document.getElementById('eye_icon');
    if (input.type === 'password') {
        input.type = 'text';
        eye.classList.remove('fa-eye');
        eye.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        eye.classList.remove('fa-eye-slash');
        eye.classList.add('fa-eye');
    }
}
</script>
@endsection
