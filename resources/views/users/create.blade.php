@extends('layouts.app')

@section('title', 'Add New User - Bank Complaint Manager')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('users.index') }}" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Create System User</h1>
                <p class="text-xs text-slate-500 mt-0.5">Register a new engineer, operations staff member, or administrator.</p>
            </div>
        </div>
    </div>

    <!-- Creation Form Card -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Full Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="e.g. Tariq Mehmood"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Email Address <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           placeholder="user@banksupport.com"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Role Selection -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Assigned Role &amp; Privileges <span class="text-rose-500">*</span>
                    </label>
                    <select name="role" required class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 bg-white focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                        <option value="">Select operational role...</option>
                        @foreach($availableRoles as $roleKey => $roleLabel)
                            <option value="{{ $roleKey }}" {{ old('role') === $roleKey ? 'selected' : '' }}>
                                {{ $roleLabel }} ({{ $roleKey }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Determines authorized modules. Office Staff is restricted to stock, transfers, GRN, and machine faults.
                    </p>
                </div>

                <!-- Phone / WhatsApp -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Phone / WhatsApp</label>
                    <input type="text" name="phone_whatsapp" value="{{ old('phone_whatsapp') }}"
                           placeholder="03001234567"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Base City -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Base Operational City</label>
                    <input type="text" name="base_city" value="{{ old('base_city') }}"
                           placeholder="Lahore, Karachi, Islamabad..."
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Engineer Home Coordinates (GPS / Maps) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        <i class="fa-solid fa-location-crosshairs text-emerald-600 mr-1"></i> Engineer Home Coordinates (GPS Lat, Long)
                    </label>
                    <input type="text" name="home_coordinates" value="{{ old('home_coordinates') }}"
                           placeholder="e.g. 31.5204, 74.3587 (Latitude, Longitude)"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                    <p class="text-[10px] text-slate-400 mt-1">Used by AI to compute exact tour road distances from home to bank branch street address.</p>
                </div>

                <!-- Engineer Home Physical Address -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        <i class="fa-solid fa-house text-sky-600 mr-1"></i> Engineer Home Physical Street Address
                    </label>
                    <input type="text" name="home_address" value="{{ old('home_address') }}"
                           placeholder="e.g. House #24, Street 7, Model Town, Lahore"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Specialization -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1">Hardware / Domain Specialization</label>
                    <input type="text" name="specialization" value="{{ old('specialization') }}"
                           placeholder="e.g. BM-350 Cash Sorter, Glory USF-51, NCR ATM, Electronics"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Password <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" name="password" required minlength="6"
                           placeholder="Min 6 characters"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Confirm Password <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" name="password_confirmation" required minlength="6"
                           placeholder="Repeat password"
                           class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-400 focus:outline-hidden">
                </div>

                <!-- Initial Active Status -->
                <div class="sm:col-span-2 flex items-center space-x-2 pt-2">
                    <input type="checkbox" name="is_available" id="is_available" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-400">
                    <label for="is_available" class="text-xs font-semibold text-slate-700 cursor-pointer">
                        Mark user as immediately Active &amp; Available for ticket / task assignments
                    </label>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                <a href="{{ route('users.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 text-xs font-bold transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition">
                    <i class="fa-solid fa-user-check mr-1.5"></i> Create User
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
