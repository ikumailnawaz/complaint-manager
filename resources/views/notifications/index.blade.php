@extends('layouts.app')

@section('title', 'Notifications Center - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <span class="p-2.5 bg-sky-100 text-sky-700 rounded-xl text-base">
                <i class="fa-solid fa-bell"></i>
            </span>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Notifications Center</span>
                    @if($unreadCount > 0)
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-700 border border-rose-200">
                            {{ $unreadCount }} Unread
                        </span>
                    @endif
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Live operational updates, ticket assignments, spare part requisitions, and maintenance alerts.
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <button type="button" onclick="window.testNotificationSound()" class="inline-flex items-center space-x-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer">
                <i class="fa-solid fa-volume-high text-sky-600"></i>
                <span>Test Alert Sound</span>
            </button>
            <button type="button" onclick="window.markAllNotificationsRead()" class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer">
                <i class="fa-solid fa-check-double"></i>
                <span>Mark All as Read</span>
            </button>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-slate-500 font-bold mr-1">Filter:</span>
            <a href="{{ route('notifications.index') }}" 
               class="px-3 py-1.5 rounded-lg font-bold transition {{ !request('status') && !request('type') ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                All Alerts
            </a>
            <a href="{{ route('notifications.index', ['status' => 'unread']) }}" 
               class="px-3 py-1.5 rounded-lg font-bold transition {{ request('status') === 'unread' ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Unread Only
            </a>
            <a href="{{ route('notifications.index', ['type' => 'ticket_assigned']) }}" 
               class="px-3 py-1.5 rounded-lg font-bold transition {{ request('type') === 'ticket_assigned' ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Ticket Assignments
            </a>
            <a href="{{ route('notifications.index', ['type' => 'part_request_created']) }}" 
               class="px-3 py-1.5 rounded-lg font-bold transition {{ in_array(request('type'), ['part_request_created', 'part_request_approved']) ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Part Requests
            </a>
            <a href="{{ route('notifications.index', ['type' => 'pm_due']) }}" 
               class="px-3 py-1.5 rounded-lg font-bold transition {{ request('type') === 'pm_due' ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Preventive Maintenance
            </a>
        </div>
        <div class="text-slate-400 text-[11px]">
            Showing {{ $notifications->total() }} notifications
        </div>
    </div>

    <!-- Notifications List Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse($notifications as $notif)
                <div class="p-4 flex items-start justify-between gap-4 transition hover:bg-slate-50/80 {{ $notif->is_read ? 'bg-white' : 'bg-sky-50/40 border-l-4 border-l-sky-500' }}" id="notif-row-{{ $notif->id }}">
                    <div class="flex items-start space-x-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-base shadow-xs
                                    {{ $notif->type === 'ticket_assigned' ? 'bg-indigo-100 text-indigo-700' : '' }}
                                    {{ str_starts_with($notif->type, 'part_request') ? 'bg-amber-100 text-amber-700' : '' }}
                                    {{ $notif->type === 'pm_due' ? 'bg-rose-100 text-rose-700' : '' }}
                                    {{ $notif->type === 'ticket_escalated' ? 'bg-red-100 text-red-700' : '' }}">
                            <i class="{{ $notif->icon }}"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-xs font-extrabold text-slate-900 tracking-tight">{{ $notif->title }}</h3>
                                @if(!$notif->is_read)
                                    <span class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></span>
                                @endif
                                <span class="text-[10px] text-slate-400 font-medium">
                                    {{ $notif->created_at->diffForHumans() }} ({{ $notif->created_at->format('d M H:i') }})
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                                {{ $notif->message }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 shrink-0">
                        @if($notif->link)
                            <a href="{{ $notif->link }}" onclick="window.markNotificationRead({{ $notif->id }})" class="px-3 py-1.5 bg-slate-100 hover:bg-sky-50 hover:text-sky-700 text-slate-700 text-xs font-bold rounded-lg border border-slate-200 transition">
                                <span>Inspect</span>
                                <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-[10px]"></i>
                            </a>
                        @endif

                        @if(!$notif->is_read)
                            <button type="button" onclick="window.markNotificationRead({{ $notif->id }})" class="p-1.5 text-slate-400 hover:text-sky-600 rounded-lg hover:bg-slate-100 transition" title="Mark as Read">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-slate-400">
                    <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-slate-100 flex items-center justify-center text-2xl text-slate-300">
                        <i class="fa-regular fa-bell-slash"></i>
                    </div>
                    <div class="font-bold text-sm text-slate-600">No Notifications</div>
                    <div class="text-xs mt-1">You are all caught up! When tickets or parts require attention, alerts will appear here.</div>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
