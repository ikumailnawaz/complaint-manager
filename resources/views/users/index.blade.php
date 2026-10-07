@extends('layouts.app')

@section('title', 'User Management & Role Assignment - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <span class="p-2.5 bg-blue-100 text-blue-700 rounded-xl text-base">
                <i class="fa-solid fa-users-gear"></i>
            </span>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>User Management &amp; Role Assignment</span>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-full font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">
                        Admin Portal
                    </span>
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Create users, allocate system privileges, reassign operational roles, and manage field availability.
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <a href="{{ route('users.create') }}" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition">
                <i class="fa-solid fa-user-plus mr-1"></i>
                <span>Add New User</span>
            </a>
        </div>
    </div>

    <!-- Role Summary Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <a href="{{ route('users.index') }}" class="bg-white p-3.5 rounded-xl shadow-xs border border-slate-200 transition hover:border-slate-400">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">All Users</div>
            <div class="text-xl font-black text-slate-800 mt-0.5">{{ $roleCounts['total'] }}</div>
        </a>
        <a href="{{ route('users.index', ['role' => 'super_admin']) }}" class="bg-white p-3.5 rounded-xl shadow-xs border border-amber-200 transition hover:border-amber-400">
            <div class="text-[11px] font-bold text-amber-600 uppercase tracking-wider flex items-center gap-1">
                <i class="fa-solid fa-crown text-[10px]"></i> Super Admin
            </div>
            <div class="text-xl font-black text-amber-800 mt-0.5">{{ $roleCounts['super_admin'] }}</div>
        </a>
        <a href="{{ route('users.index', ['role' => 'admin']) }}" class="bg-white p-3.5 rounded-xl shadow-xs border border-blue-200 transition hover:border-blue-400">
            <div class="text-[11px] font-bold text-blue-600 uppercase tracking-wider flex items-center gap-1">
                <i class="fa-solid fa-user-tie text-[10px]"></i> Ops Admin
            </div>
            <div class="text-xl font-black text-blue-800 mt-0.5">{{ $roleCounts['admin'] }}</div>
        </a>
        <a href="{{ route('users.index', ['role' => 'office_staff']) }}" class="bg-white p-3.5 rounded-xl shadow-xs border border-teal-200 transition hover:border-teal-400">
            <div class="text-[11px] font-bold text-teal-600 uppercase tracking-wider flex items-center gap-1">
                <i class="fa-solid fa-boxes-packing text-[10px]"></i> Office Staff
            </div>
            <div class="text-xl font-black text-teal-800 mt-0.5">{{ $roleCounts['office_staff'] }}</div>
        </a>
        <a href="{{ route('users.index', ['role' => 'engineer']) }}" class="bg-white p-3.5 rounded-xl shadow-xs border border-emerald-200 transition hover:border-emerald-400">
            <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider flex items-center gap-1">
                <i class="fa-solid fa-wrench text-[10px]"></i> Engineers
            </div>
            <div class="text-xl font-black text-emerald-800 mt-0.5">{{ $roleCounts['engineer'] }}</div>
        </a>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-xl shadow-xs border border-slate-200">
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <div class="flex-1 w-full flex items-center gap-2">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Search by name, email, phone, city, or specialization..." 
                           class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs focus:ring-1 focus:ring-blue-400 focus:bg-white">
                </div>

                <select name="role" class="bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-blue-400">
                    <option value="">All Roles</option>
                    <option value="super_admin" {{ request('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Operations Admin</option>
                    <option value="superior" {{ request('role') === 'superior' ? 'selected' : '' }}>Superior Manager</option>
                    <option value="office_staff" {{ request('role') === 'office_staff' ? 'selected' : '' }}>Office Staff</option>
                    <option value="engineer" {{ request('role') === 'engineer' ? 'selected' : '' }}>Field Engineer</option>
                </select>

                <button type="submit" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg transition">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'role', 'city']))
                    <a href="{{ route('users.index') }}" class="px-2.5 py-2 text-slate-500 hover:text-slate-800 text-xs font-semibold">
                        Reset
                    </a>
                @endif
            </div>

            <div class="text-[11px] text-slate-400 shrink-0">
                Found {{ $users->total() }} user(s)
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold">
                        <th class="px-4 py-3">User / Identity</th>
                        <th class="px-4 py-3">Assigned Role</th>
                        <th class="px-4 py-3">Contact / WhatsApp</th>
                        <th class="px-4 py-3">Base City</th>
                        <th class="px-4 py-3">Specialization</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $u)
                        <tr class="hover:bg-slate-50/70 transition">
                            <!-- Name / Email -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs uppercase
                                                {{ $u->role === 'super_admin' ? 'bg-amber-100 text-amber-800' : '' }}
                                                {{ $u->role === 'admin' ? 'bg-blue-100 text-blue-800' : '' }}
                                                {{ $u->role === 'superior' ? 'bg-purple-100 text-purple-800' : '' }}
                                                {{ in_array($u->role, ['office_staff', 'staff']) ? 'bg-teal-100 text-teal-800' : '' }}
                                                {{ $u->role === 'engineer' ? 'bg-emerald-100 text-emerald-800' : '' }}">
                                        {{ strtoupper(substr($u->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-slate-800">{{ $u->name }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Role Badge -->
                            <td class="px-4 py-3.5">
                                @if($u->role === 'super_admin')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                        <i class="fa-solid fa-crown text-[9px]"></i> Super Admin
                                    </span>
                                @elseif($u->role === 'admin')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-900 border border-blue-200">
                                        <i class="fa-solid fa-user-tie text-[9px]"></i> Operations Admin
                                    </span>
                                @elseif($u->role === 'superior')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 text-purple-900 border border-purple-200">
                                        <i class="fa-solid fa-shield-halved text-[9px]"></i> Superior Manager
                                    </span>
                                @elseif(in_array($u->role, ['office_staff', 'staff']))
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-teal-100 text-teal-900 border border-teal-200">
                                        <i class="fa-solid fa-boxes-packing text-[9px]"></i> Office Staff
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-200">
                                        <i class="fa-solid fa-wrench text-[9px]"></i> Field Engineer
                                    </span>
                                @endif
                            </td>

                            <!-- Contact -->
                            <td class="px-4 py-3.5 font-mono text-slate-600">
                                {{ $u->phone_whatsapp ?: '—' }}
                            </td>

                            <!-- City -->
                            <td class="px-4 py-3.5 font-medium text-slate-700">
                                {{ $u->base_city ?: '—' }}
                            </td>

                            <!-- Specialization -->
                            <td class="px-4 py-3.5 text-slate-600">
                                {{ $u->specialization ?: 'General' }}
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3.5 text-center">
                                @if($u->is_available)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive / Leave
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3.5 text-right space-x-1.5">
                                @if(!empty($u->fcm_token))
                                    <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('users.test-push') ? route('users.test-push', $u) : url('/users/' . $u->id . '/test-push') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-bold rounded-lg border border-amber-200 transition" title="Send live test push notification to {{ $u->name }}'s phone">
                                            <i class="fa-solid fa-bell mr-1 text-[10px] text-amber-600 animate-pulse"></i> Test Push
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 bg-slate-100 text-slate-400 text-[10px] font-medium rounded-lg border border-slate-200" title="Engineer has not opened app to sync device yet">
                                        <i class="fa-solid fa-mobile-screen-button mr-1 text-[9px]"></i> No Device
                                    </span>
                                @endif

                                <a href="{{ route('users.edit', $u) }}" class="inline-flex items-center px-2.5 py-1 bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-700 text-xs font-bold rounded-lg border border-slate-200 transition">
                                    <i class="fa-solid fa-pen-to-square mr-1 text-[10px]"></i> Edit
                                </a>

                                @if($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete user \'{{ $u->name }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 rounded hover:bg-slate-100 transition" title="Delete User">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No users match the given filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
