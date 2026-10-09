<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bank Complaint Manager ERP')</title>
    <!-- Tailwind CSS CDN (GoDaddy shared hosting friendly - no node build needed) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom scrollbar for sidebar */
        #app-sidebar::-webkit-scrollbar {
            width: 4px;
        }
        #app-sidebar::-webkit-scrollbar-track {
            background: #0f172a;
        }
        #app-sidebar::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        #app-sidebar::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex font-sans antialiased">

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-950/70 z-40 hidden md:hidden transition-opacity"></div>

    <!-- LEFT SIDEBAR -->
    <aside id="app-sidebar" class="fixed md:sticky top-0 left-0 h-screen w-64 bg-slate-900 text-slate-100 flex flex-col justify-between shrink-0 z-50 border-r border-slate-800 shadow-2xl transition-transform duration-300 -translate-x-full md:translate-x-0 overflow-y-auto">
        <div>
            <!-- Sidebar Header / Logo -->
            <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800 bg-slate-950">
                <a href="{{ auth()->user()->isOfficeStaff() ? route('tickets.open') : route('dashboard') }}" class="flex items-center space-x-2.5 text-white font-bold tracking-wide hover:text-sky-400 transition">
                    <span class="bg-gradient-to-tr from-sky-600 to-cyan-500 text-white p-2 rounded-xl flex items-center justify-center shadow-md shadow-sky-950">
                        <i class="fa-solid fa-building-columns text-sm"></i>
                    </span>
                    <div class="leading-tight">
                        <div class="text-sm font-extrabold tracking-tight">Bank Complaints</div>
                        <div class="text-[9px] text-sky-400 font-bold uppercase tracking-wider">Field Operations ERP</div>
                    </div>
                </a>

                <!-- Mobile Close Button -->
                <button type="button" onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white p-1 rounded-lg">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- User Info Card (Sidebar Top) -->
            <div class="p-3 mx-3 my-3 bg-slate-800/90 rounded-xl border border-slate-700/80 flex items-center space-x-2.5 shadow-inner">
                <div class="w-9 h-9 rounded-lg bg-sky-600/40 text-sky-300 border border-sky-400/50 flex items-center justify-center font-bold text-xs shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-white truncate">{{ auth()->user()->name }}</div>
                    <div class="flex items-center space-x-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span class="text-[10px] text-slate-300 uppercase font-semibold font-mono tracking-wider">
                            {{ str_replace('_', ' ', auth()->user()->role) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="px-3 space-y-4 text-xs">

                <!-- 1. DASHBOARD -->
                @if(!auth()->user()->isOfficeStaff())
                <div class="space-y-0.5">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2.5 px-3 py-2 rounded-xl font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-sky-600 text-white shadow-md shadow-sky-900/40' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fa-solid fa-gauge-high w-4 text-center {{ request()->routeIs('dashboard') ? 'text-white' : 'text-sky-400' }}"></i>
                        <span class="flex-1">Dashboard</span>
                    </a>
                </div>
                @endif

                <!-- 2. COMPLAINTS (DROPDOWN) -->
                @php
                    $isEngineerUser = auth()->user()->isEngineer();
                    $isOfficeStaffUser = auth()->user()->isOfficeStaff();

                    if ($isEngineerUser) {
                        $myComplaintsCount = \App\Models\Ticket::where('assigned_engineer_id', auth()->id())
                            ->whereNotIn('status', ['closed', 'resolved'])
                            ->count();
                        $complaintsBadgeCount = $myComplaintsCount;
                    } else {
                        $openCount = \App\Models\Ticket::whereNotIn('status', ['closed', 'resolved'])->count();
                        $escCount = \App\Models\Ticket::where('status', 'escalated')
                            ->orWhere(function($q) {
                                $q->whereNotNull('sla_deadline')->where('sla_deadline', '<', now())->whereNotIn('status', ['closed', 'resolved']);
                            })->count();
                        $unassignedEmailCount = \App\Models\InboxEmail::whereNull('ticket_id')->count();
                        $complaintsBadgeCount = $openCount + $escCount;
                    }
                    $complaintsActive = request()->routeIs('tickets.*') || request()->routeIs('settings.email*');
                @endphp
                <details class="group" {{ $complaintsActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $complaintsActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-indigo-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-ticket w-4 text-center text-indigo-400"></i>
                            <span class="text-white">Complaints</span>
                        </span>
                        <div class="flex items-center space-x-1.5">
                            @if($complaintsBadgeCount > 0)
                                <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-black text-[10px] rounded-full">{{ $complaintsBadgeCount }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                        </div>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        @if(request()->routeIs('tickets.show') && isset($ticket))
                        <!-- Current Open Ticket Context Pill -->
                        <div class="px-2.5 py-1.5 text-xs font-bold text-sky-300 bg-sky-950/70 rounded-lg border border-sky-800/80 flex items-center gap-1.5 my-1 shadow-inner">
                            <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                            <span class="truncate">Ticket: #{{ $ticket->ticket_no }}</span>
                        </div>
                        @endif

                        @if(!$isOfficeStaffUser)
                        <!-- Complain Registry (Visible to Super Admin, Admin, Engineer) -->
                        <a href="{{ route('tickets.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('tickets.index') || (request()->routeIs('tickets.show') && !request()->routeIs('tickets.open')) ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-list-check mr-1.5 text-[10px]"></i> Complain Registry</span>
                            @if($isEngineerUser && $myComplaintsCount > 0)
                                <span class="px-1.5 py-0.2 bg-sky-500 text-white font-bold text-[9px] rounded-full">{{ $myComplaintsCount }}</span>
                            @endif
                        </a>
                        @endif

                        @if(!$isEngineerUser)
                        <!-- Open Ticket (Visible to Super Admin, Admin, Office Staff) -->
                        <a href="{{ route('tickets.open') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('tickets.open') ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-clock-rotate-left mr-1.5 text-[10px]"></i> Open Tickets</span>
                            @if(isset($openCount) && $openCount > 0)
                                <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-black text-[9px] rounded-full">{{ $openCount }}</span>
                            @endif
                        </a>
                        @endif

                        @if(!$isEngineerUser && !$isOfficeStaffUser)
                        <!-- Escalated Ticket (Visible to Super Admin, Admin) -->
                        <a href="{{ route('tickets.escalations') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('tickets.escalations') ? 'bg-slate-800 text-rose-400 font-bold border-l-2 border-rose-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-triangle-exclamation mr-1.5 text-[10px]"></i> Escalated Tickets</span>
                            @if(isset($escCount) && $escCount > 0)
                                <span class="px-1.5 py-0.2 bg-rose-500 text-white font-black text-[9px] rounded-full animate-pulse">{{ $escCount }}</span>
                            @endif
                        </a>

                        <!-- Mail Sync (Webmail & Ingest) (Visible to Super Admin, Admin) -->
                        <a href="{{ route('settings.email') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('settings.*') ? 'bg-slate-800 text-cyan-400 font-bold border-l-2 border-cyan-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-inbox mr-1.5 text-[10px]"></i> Mail Sync (Webmail &amp; Ingest)</span>
                            @if(isset($unassignedEmailCount) && $unassignedEmailCount > 0)
                                <span class="px-1.5 py-0.2 bg-sky-500 text-white font-bold text-[9px] rounded-full">{{ $unassignedEmailCount }}</span>
                            @endif
                        </a>
                        @endif
                    </div>
                </details>

                <!-- 3. WAITED APPROVAL (DROPDOWN) -->
                @if(!auth()->user()->isEngineer())
                @php
                    $approvalCount = \App\Models\Ticket::where('status', 'awaiting_approval')->count();
                    $approvalsActive = request()->routeIs('approvals.*');
                @endphp
                <details class="group" {{ $approvalsActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $approvalsActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-amber-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-hourglass-half w-4 text-center text-amber-400"></i>
                            <span class="text-white">Waited Approval</span>
                        </span>
                        <div class="flex items-center space-x-1.5">
                            @if($approvalCount > 0)
                                <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-black text-[10px] rounded-full animate-pulse">{{ $approvalCount }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                        </div>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        <a href="{{ route('approvals.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('approvals.*') ? 'bg-slate-800 text-amber-400 font-bold border-l-2 border-amber-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-clock-rotate-left mr-1.5 text-[10px]"></i> Pending Approvals</span>
                            @if($approvalCount > 0)
                                <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-black text-[9px] rounded-full">{{ $approvalCount }}</span>
                            @endif
                        </a>
                    </div>
                </details>

                <!-- 4. WORKSHOP MANAGEMENT (DROPDOWN) -->
                @php
                    $workshopCount = \App\Models\Ticket::whereIn('status', ['awaiting_workshop', 'in_workshop_repair', 'workshop_repaired', 'return_transit'])->count();
                    $workshopActive = request()->routeIs('workshop.*');
                @endphp
                <details class="group" {{ $workshopActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $workshopActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-purple-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-truck-ramp-box w-4 text-center text-purple-400"></i>
                            <span class="text-white">Workshop Management</span>
                        </span>
                        <div class="flex items-center space-x-1.5">
                            @if($workshopCount > 0)
                                <span class="px-1.5 py-0.2 bg-purple-500 text-white font-black text-[10px] rounded-full">{{ $workshopCount }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                        </div>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        <a href="{{ route('workshop.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('workshop.*') ? 'bg-slate-800 text-purple-400 font-bold border-l-2 border-purple-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-wrench mr-1.5 text-[10px]"></i> Central Workshop</span>
                            @if($workshopCount > 0)
                                <span class="px-1.5 py-0.2 bg-purple-500 text-white font-black text-[9px] rounded-full">{{ $workshopCount }}</span>
                            @endif
                        </a>
                    </div>
                </details>
                @endif

                <!-- 5. FIELD & DISBURSEMENTS (DROPDOWN) -->
                @if(!auth()->user()->isOfficeStaff())
                @php
                    $fieldActive = request()->routeIs('expenses.*') || request()->routeIs('engineers.*');
                @endphp
                <details class="group" {{ $fieldActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $fieldActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-emerald-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-money-bill-transfer w-4 text-center text-emerald-400"></i>
                            <span class="text-white">Field &amp; Disbursements</span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        <!-- Tour Expenses -->
                        <a href="{{ route('expenses.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('expenses.*') ? 'bg-slate-800 text-emerald-400 font-bold border-l-2 border-emerald-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-receipt mr-1.5 text-[10px]"></i> Tour Expenses</span>
                        </a>

                        @if(!auth()->user()->isEngineer())
                        <!-- Engineers Directory -->
                        <a href="{{ route('engineers.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('engineers.*') ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-user-gear mr-1.5 text-[10px]"></i> Engineer Directory</span>
                        </a>
                        @endif
                    </div>
                </details>
                @endif

                <!-- 6. PREVENTIVE MAINTENANCE (DROPDOWN) -->
                @if(!auth()->user()->isOfficeStaff())
                @php
                    $pmUser = auth()->user();
                    $pmOverdueCount = \App\Models\PmSchedule::where('is_active', true)
                        ->where('next_due_date', '<', now()->toDateString())
                        ->when($pmUser->isEngineer(), fn($q) =>
                            $q->where(function($q2) use ($pmUser) {
                                $q2->where('assigned_engineer_id', $pmUser->id)
                                   ->orWhere(fn($q3) => $q3->whereNull('assigned_engineer_id')
                                       ->whereHas('machine', fn($m) => $m->where('assigned_engineer_id', $pmUser->id)));
                            })
                        )->count();
                    $pmDueSoonCount = \App\Models\PmSchedule::where('is_active', true)
                        ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                        ->when($pmUser->isEngineer(), fn($q) =>
                            $q->where(function($q2) use ($pmUser) {
                                $q2->where('assigned_engineer_id', $pmUser->id)
                                   ->orWhere(fn($q3) => $q3->whereNull('assigned_engineer_id')
                                       ->whereHas('machine', fn($m) => $m->where('assigned_engineer_id', $pmUser->id)));
                            })
                        )->count();
                    $pmAlertCount = $pmOverdueCount + $pmDueSoonCount;
                    $pmActive = request()->routeIs('pm.*');
                @endphp
                <details class="group" {{ $pmActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $pmActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-teal-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-screwdriver-wrench w-4 text-center text-teal-400"></i>
                            <span class="text-white">Preventive Maintenance</span>
                        </span>
                        <div class="flex items-center space-x-1.5">
                            @if($pmAlertCount > 0)
                                <span class="px-1.5 py-0.2 font-black text-[10px] rounded-full {{ $pmOverdueCount > 0 ? 'bg-red-500 text-white animate-pulse' : 'bg-teal-500 text-white' }}">{{ $pmAlertCount }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                        </div>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        @if(auth()->user()->isEngineer())
                        <a href="{{ route('pm.tasks.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('pm.tasks.*') ? 'bg-slate-800 text-teal-400 font-bold border-l-2 border-teal-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-wrench mr-1.5 text-[10px]"></i> My PM Tasks</span>
                            @if($pmAlertCount > 0)
                                <span class="px-1.5 py-0.2 font-black text-[9px] rounded-full {{ $pmOverdueCount > 0 ? 'bg-red-500 text-white animate-pulse' : 'bg-teal-500 text-white' }}">{{ $pmAlertCount }}</span>
                            @endif
                        </a>
                        @else
                        <a href="{{ route('pm.machines.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('pm.machines.*') ? 'bg-slate-800 text-teal-400 font-bold border-l-2 border-teal-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-desktop mr-1.5 text-[10px]"></i> Machine Registry</span>
                        </a>
                        <a href="{{ route('pm.schedules.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('pm.schedules.*') ? 'bg-slate-800 text-teal-400 font-bold border-l-2 border-teal-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-calendar-check mr-1.5 text-[10px]"></i> PM Schedules</span>
                            @if($pmAlertCount > 0)
                                <span class="px-1.5 py-0.2 font-black text-[9px] rounded-full {{ $pmOverdueCount > 0 ? 'bg-red-500 text-white animate-pulse' : 'bg-teal-500 text-white' }}">{{ $pmAlertCount }}</span>
                            @endif
                        </a>
                        @endif
                    </div>
                </details>
                @endif

                <!-- 7. PARTS & INVENTORY (DROPDOWN) -->
                @php
                    $pendingPrCount = auth()->user()->isEngineer()
                        ? \App\Models\PartRequest::where('engineer_id', auth()->id())->whereIn('status', ['pending_stock_check', 'pending_approval'])->count()
                        : \App\Models\PartRequest::whereIn('status', ['pending_stock_check', 'pending_approval'])->count();
                    $envStockCount = auth()->user()->isEngineer()
                        ? \App\Models\EngineerInventory::where('engineer_id', auth()->id())->where('qty_on_hand', '>', 0)->count()
                        : \App\Models\EngineerInventory::where('qty_on_hand', '>', 0)->distinct('engineer_id')->count('engineer_id');
                    $partsActive = request()->routeIs('parts.*');
                @endphp
                <details class="group" {{ $partsActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $partsActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-amber-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-boxes-stacked w-4 text-center text-amber-400"></i>
                            <span class="text-white">Parts &amp; Inventory</span>
                        </span>
                        <div class="flex items-center space-x-1.5">
                            @if($pendingPrCount > 0)
                                <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-black text-[10px] rounded-full">{{ $pendingPrCount }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                        </div>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        <!-- Part Requests -->
                        <a href="{{ route('parts.requests.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.requests.*') ? 'bg-slate-800 text-amber-400 font-bold border-l-2 border-amber-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-clipboard-list mr-1.5 text-[10px]"></i> Part Requests</span>
                            @if($pendingPrCount > 0)
                                <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 font-black text-[9px] rounded-full">{{ $pendingPrCount }}</span>
                            @endif
                        </a>

                        <!-- Advance Envelopes -->
                        <a href="{{ route('parts.envelopes.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.envelopes.*') ? 'bg-slate-800 text-teal-400 font-bold border-l-2 border-teal-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-briefcase mr-1.5 text-[10px]"></i> {{ auth()->user()->isEngineer() ? 'My Parts Envelope' : 'Advance Envelopes' }}</span>
                            @if($envStockCount > 0)
                                <span class="px-1.5 py-0.2 bg-teal-500 text-slate-950 font-bold text-[9px] rounded-full">{{ $envStockCount }}</span>
                            @endif
                        </a>

                        @if(!auth()->user()->isEngineer())
                        <!-- Stock Levels -->
                        <a href="{{ route('parts.stock.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.stock.*') ? 'bg-slate-800 text-emerald-400 font-bold border-l-2 border-emerald-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-boxes-stacked mr-1.5 text-[10px]"></i> Stock Levels</span>
                        </a>

                        <!-- Goods Received (GRN) -->
                        <a href="{{ route('parts.grn.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.grn.*') ? 'bg-slate-800 text-blue-400 font-bold border-l-2 border-blue-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-file-invoice mr-1.5 text-[10px]"></i> Goods Received (GRN)</span>
                        </a>

                        <!-- Location Transfers -->
                        <a href="{{ route('parts.transfers.index') }}" class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.transfers.*') ? 'bg-slate-800 text-purple-400 font-bold border-l-2 border-purple-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <span><i class="fa-solid fa-truck-ramp-box mr-1.5 text-[10px]"></i> Location Transfers</span>
                        </a>

                        @if(auth()->user()->isAdmin())
                        <!-- Master Catalog -->
                        <div class="pt-1 mt-1 border-t border-slate-700">
                            <div class="px-2.5 py-1 text-[10px] font-bold text-slate-300 uppercase tracking-wider">Master Catalog</div>
                            <a href="{{ route('parts.master.parts') }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.master.parts*') ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                                <i class="fa-solid fa-barcode mr-1.5 text-[10px]"></i> Parts Catalog
                            </a>
                            <a href="{{ route('parts.master.models') }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.master.models*') ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                                <i class="fa-solid fa-server mr-1.5 text-[10px]"></i> Machine Models
                            </a>
                            <a href="{{ route('parts.master.locations') }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('parts.master.locations*') ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                                <i class="fa-solid fa-location-dot mr-1.5 text-[10px]"></i> Office Hubs
                            </a>
                        </div>
                        @endif
                        @endif
                    </div>
                </details>

                <!-- 8. REPORTS (DROPDOWN) -->
                @if(!auth()->user()->isEngineer())
                @php
                    $reportsActive = request()->routeIs('reports.*');
                @endphp
                <details class="group" {{ $reportsActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $reportsActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-sky-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-chart-pie w-4 text-center text-sky-400"></i>
                            <span class="text-white">Reports</span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        @if(auth()->user()->isOfficeStaff())
                        <!-- Office Staff only gets Machine Faults Report -->
                        <a href="{{ route('reports.index', ['tab' => 'machine_faults']) }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('reports.*') ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <i class="fa-solid fa-video mr-1.5 text-[10px]"></i> Machine Faults Report
                        </a>
                        @else
                        <!-- Reports & Analytics for Admins/Superiors -->
                        <a href="{{ route('reports.index') }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('reports.*') ? 'bg-slate-800 text-sky-400 font-bold border-l-2 border-sky-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <i class="fa-solid fa-chart-line mr-1.5 text-[10px]"></i> Reports &amp; Analytics
                        </a>
                        @endif
                    </div>
                </details>
                @endif

                <!-- 9. ADMINISTRATION (DROPDOWN - ADMIN ONLY, NO NOTIFICATION HUB) -->
                @if(auth()->user()->isAdmin())
                @php
                    $adminActive = request()->routeIs('users.*');
                @endphp
                <details class="group" {{ $adminActive ? 'open' : '' }}>
                    <summary class="cursor-pointer flex items-center justify-between px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800 hover:text-white transition font-semibold list-none select-none {{ $adminActive ? 'bg-slate-800/90 text-white font-bold border-l-2 border-amber-400 pl-2.5' : '' }}">
                        <span class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-users-gear w-4 text-center text-amber-400"></i>
                            <span class="text-white">Administration</span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition group-open:rotate-90"></i>
                    </summary>
                    <div class="pl-7 pr-2 py-1 space-y-1 border-l-2 border-slate-700 ml-5 mt-1">
                        <a href="{{ route('users.index') }}" class="flex items-center px-2.5 py-1.5 rounded-lg text-xs {{ request()->routeIs('users.*') ? 'bg-slate-800 text-amber-400 font-bold border-l-2 border-amber-400 pl-2' : 'text-slate-200 hover:text-white hover:bg-slate-800/80 font-medium' }} transition">
                            <i class="fa-solid fa-user-shield mr-1.5 text-[10px]"></i> User Management
                        </a>
                    </div>
                </details>
                @endif
            </nav>
        </div>

        <!-- Sidebar Bottom: Role Switcher & System Indicators -->
        <div class="p-3 border-t border-slate-800 bg-slate-950/70 space-y-2">
            <!-- Logout Button -->
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center space-x-2 px-3 py-1.5 bg-slate-800 hover:bg-rose-900/60 hover:text-rose-100 text-slate-200 rounded-lg text-xs font-semibold transition border border-slate-700/80">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                    <span>Sign Out</span>
                </button>
            </form>

            <div class="pt-1 flex items-center justify-between text-[10px] text-slate-400 px-1">
                <span title="WhatsApp Cloud Integration"><i class="fa-brands fa-whatsapp text-emerald-400"></i> Active</span>
                <span title="Gemini AI Auto-Triage"><i class="fa-solid fa-robot text-sky-400"></i> AI Ready</span>
            </div>
        </div>
    </aside>

    <!-- RIGHT MAIN CONTENT WORKSPACE -->
    <div class="flex-1 min-w-0 flex flex-col min-h-screen bg-slate-100">

        <!-- Top Header Ribbon (Sticky) -->
        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 lg:px-8 flex items-center justify-between shadow-xs">
            <div class="flex items-center space-x-3">
                <!-- Mobile Sidebar Toggle -->
                <button type="button" onclick="toggleSidebar()" class="md:hidden p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-hidden transition">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>

                <!-- Breadcrumb or App Title -->
                <div class="flex items-center space-x-2">
                    <span class="font-bold text-slate-900 text-sm sm:text-base tracking-tight">
                        @yield('header_title', 'Bank Complaint Management System')
                    </span>
                </div>
            </div>

            <!-- Header Right: Quick Switcher & User Profile -->
            <div class="flex items-center space-x-3">
                <!-- Active User Role Badge -->
                <div class="hidden sm:flex items-center space-x-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-slate-500 font-normal">Role:</span>
                    <span class="text-sky-700 uppercase font-mono tracking-wider">{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                </div>

                <!-- Notification Bell & Interactive Dropdown -->
                @php
                    $navUnreadCount = \App\Models\AppNotification::unread()->forUser(auth()->user())->count();
                @endphp
                <div class="relative" id="notifContainer">
                    <button id="notifBellBtn" type="button" class="relative p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition focus:outline-hidden" title="System Notifications">
                        <i class="fa-solid fa-bell text-lg"></i>
                        <span id="notifBadge" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-rose-600 text-white text-[10px] font-black rounded-full flex items-center justify-center {{ $navUnreadCount > 0 ? '' : 'hidden' }}">
                            {{ $navUnreadCount > 99 ? '99+' : $navUnreadCount }}
                        </span>
                    </button>

                    <!-- Dropdown Panel -->
                    <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden text-slate-800 animate-in fade-in duration-100">
                        <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-bell text-sky-400 text-sm"></i>
                                <span class="font-bold text-xs uppercase tracking-wider">Alerts</span>
                                <span id="notifDropdownCount" class="text-[10px] bg-rose-600 px-1.5 py-0.5 rounded-full font-bold {{ $navUnreadCount > 0 ? '' : 'hidden' }}">
                                    {{ $navUnreadCount }} new
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" id="notifTestChimeBtn" title="Test Notification Chime Sound" class="text-slate-400 hover:text-amber-400 text-xs px-1.5 py-1 rounded transition">
                                    <i class="fa-solid fa-volume-high"></i>
                                </button>
                                <button type="button" id="notifMuteToggleBtn" title="Toggle Sound Chime" class="text-slate-400 hover:text-white text-xs px-1.5 py-1 rounded transition">
                                    <i id="notifMuteIcon" class="fa-solid fa-bell"></i>
                                </button>
                                <button type="button" id="notifMarkAllReadBtn" title="Mark all as read" class="text-slate-400 hover:text-sky-400 text-xs px-1.5 py-1 rounded transition">
                                    <i class="fa-solid fa-check-double"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Notification items container -->
                        <div id="notifItemsContainer" class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                            <div class="p-6 text-center text-slate-400 text-xs">
                                <i class="fa-solid fa-spinner fa-spin text-sky-500 mb-2 text-lg"></i>
                                <p>Loading alerts...</p>
                            </div>
                        </div>

                        <!-- Dropdown Footer -->
                        <div class="p-2.5 bg-slate-50 border-t border-slate-100 text-center flex items-center justify-between px-4">
                            <span class="text-[10px] text-slate-400">Audio Synth: Active</span>
                            <a href="{{ route('notifications.index') }}" class="text-xs font-bold text-sky-600 hover:text-sky-800 transition">
                                Full Notifications Center &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Current User Avatar -->
                <div class="flex items-center space-x-2 pl-2 border-l border-slate-200">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-slate-800 leading-tight">{{ auth()->user()->name }}</div>
                        <div class="text-[10px] text-slate-400 capitalize">{{ str_replace('_', ' ', auth()->user()->role) }}</div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" title="Logout" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-slate-100 rounded-lg transition">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Flash Messages -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full mt-4">
            @if(session('success'))
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-xs mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-start space-x-3">
                        <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5 text-base shrink-0"></i>
                        <div class="text-sm text-emerald-800 font-medium">{{ session('success') }}</div>
                    </div>
                    @if(session('undo_ticket_id'))
                        @php
                            $undoTicket = \App\Models\Ticket::find(session('undo_ticket_id'));
                        @endphp
                        @if($undoTicket && !$undoTicket->hasClaimedExpenses())
                        <form action="{{ route('tickets.undo-resolve', session('undo_ticket_id')) }}" method="POST" class="inline shrink-0">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold shadow-xs transition flex items-center space-x-1.5 cursor-pointer active:scale-95">
                                <i class="fa-solid fa-rotate-left"></i>
                                <span>Undo Mark as Done</span>
                            </button>
                        </form>
                        @endif
                    @endif
                </div>
            @endif

            @if(session('warning'))
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl shadow-xs mb-4 flex items-start space-x-3">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 text-base"></i>
                    <div class="text-sm text-amber-800 font-medium">{{ session('warning') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-xs mb-4 flex items-start space-x-3">
                    <i class="fa-solid fa-circle-xmark text-rose-600 mt-0.5 text-base"></i>
                    <div class="text-sm text-rose-800 font-medium">{{ session('error') }}</div>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-xs mb-4">
                    <div class="flex items-center space-x-2 text-rose-800 font-semibold text-sm mb-1">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Please correct the errors below:</span>
                    </div>
                    <ul class="list-disc list-inside text-xs text-rose-700 space-y-0.5 pl-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Main Workspace Content -->
        <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-4">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200 mt-auto py-4 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
                <div>Bank Complaint Manager ERP &bull; Laravel 10 Engine &bull; GoDaddy Compatible</div>
                <div class="flex items-center space-x-4">
                    <span class="flex items-center space-x-1 text-slate-600">
                        <i class="fa-brands fa-whatsapp text-emerald-500"></i>
                        <span>WhatsApp Ready</span>
                    </span>
                    <span class="flex items-center space-x-1 text-slate-600">
                        <i class="fa-solid fa-robot text-sky-500"></i>
                        <span>Gemini AI Ready</span>
                    </span>
                </div>
            </div>
        </footer>
    </div>

    <!-- Vanilla JS Sidebar Toggle -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('app-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }
    </script>

    <!-- Notification & Web Audio Chime System -->
    <script>
        (function() {
            let audioCtx = null;
            let lastUnreadCount = {{ $navUnreadCount ?? 0 }};
            let isMuted = localStorage.getItem('appNotificationMuted') === 'true';

            function updateMuteButtonUI() {
                const icon = document.getElementById('notifMuteIcon');
                const btn = document.getElementById('notifMuteToggleBtn');
                if (!icon || !btn) return;
                if (isMuted) {
                    icon.className = 'fa-solid fa-bell-slash text-rose-400';
                    btn.title = 'Sound Muted (Click to Unmute)';
                } else {
                    icon.className = 'fa-solid fa-bell text-emerald-400';
                    btn.title = 'Sound Enabled (Click to Mute)';
                }
            }

            function getAudioContext() {
                if (!audioCtx) {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (AudioContextClass) {
                        audioCtx = new AudioContextClass();
                    }
                }
                if (audioCtx && audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                return audioCtx;
            }

            // Web Audio Synthesized Chime (Two-tone melodic ping)
            function playNotificationChime() {
                if (isMuted) return;
                try {
                    const ctx = getAudioContext();
                    if (!ctx) return;

                    const now = ctx.currentTime;
                    // Tone 1: D5 (587.33 Hz)
                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(587.33, now);
                    gain1.gain.setValueAtTime(0.18, now);
                    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.22);
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.start(now);
                    osc1.stop(now + 0.22);

                    // Tone 2: A5 (880 Hz) - crisp alert note
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(880, now + 0.12);
                    gain2.gain.setValueAtTime(0.24, now + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start(now + 0.12);
                    osc2.stop(now + 0.55);
                } catch (e) {
                    console.warn('Notification audio chime error:', e);
                }
            }

            // Fetch unread notifications
            async function fetchNotifications(playSoundIfNew = false) {
                try {
                    const res = await fetch("{{ route('notifications.unread') }}", {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    
                    const count = data.count || 0;
                    const items = data.notifications || [];

                    // Badge update
                    const badge = document.getElementById('notifBadge');
                    const dropdownCount = document.getElementById('notifDropdownCount');
                    if (badge) {
                        badge.textContent = count > 99 ? '99+' : count;
                        if (count > 0) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                    if (dropdownCount) {
                        dropdownCount.textContent = count + ' new';
                        if (count > 0) {
                            dropdownCount.classList.remove('hidden');
                        } else {
                            dropdownCount.classList.add('hidden');
                        }
                    }

                    // Check if new notifications arrived to play sound
                    if (playSoundIfNew && count > lastUnreadCount) {
                        playNotificationChime();
                    }
                    lastUnreadCount = count;

                    // Render items
                    renderDropdownItems(items);
                } catch (err) {
                    console.warn('Error fetching notifications:', err);
                }
            }

            function renderDropdownItems(items) {
                const container = document.getElementById('notifItemsContainer');
                if (!container) return;

                if (items.length === 0) {
                    container.innerHTML = `
                        <div class="p-6 text-center text-slate-400">
                            <i class="fa-regular fa-bell-slash text-2xl text-slate-300 mb-2"></i>
                            <p class="text-xs font-semibold text-slate-500">No new notifications</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">You are all caught up!</p>
                        </div>
                    `;
                    return;
                }

                let html = '';
                items.forEach(item => {
                    const iconClass = item.icon_class || 'fa-solid fa-bell text-sky-500';
                    const createdTime = item.time_ago || 'Recently';
                    const actionUrl = item.action_url ? item.action_url : '#';
                    
                    html += `
                        <div class="p-3 hover:bg-slate-50 transition flex items-start space-x-3 group relative border-b border-slate-100 last:border-b-0">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="${iconClass} text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0 pr-6">
                                <a href="${actionUrl}" class="block text-xs font-bold text-slate-800 hover:text-sky-600 transition truncate">
                                    ${escapeHtml(item.title)}
                                </a>
                                <p class="text-[11px] text-slate-600 line-clamp-2 mt-0.5 leading-relaxed">
                                    ${escapeHtml(item.message)}
                                </p>
                                <span class="text-[9px] text-slate-400 font-mono mt-1 block">
                                    <i class="fa-regular fa-clock mr-0.5"></i> ${createdTime}
                                </span>
                            </div>
                            <button type="button" onclick="markNotificationRead(${item.id}, event)" title="Mark as read" class="absolute right-2.5 top-3 text-slate-300 hover:text-emerald-600 p-1 rounded transition opacity-80 group-hover:opacity-100">
                                <i class="fa-solid fa-check text-xs"></i>
                            </button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            window.markNotificationRead = async function(id, e) {
                if (e) e.stopPropagation();
                try {
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    await fetch(`/notifications/${id}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        }
                    });
                    fetchNotifications(false);
                } catch (err) {
                    console.error('Mark read error:', err);
                }
            };

            // DOM Ready bindings
            document.addEventListener('DOMContentLoaded', () => {
                updateMuteButtonUI();

                const bellBtn = document.getElementById('notifBellBtn');
                const dropdown = document.getElementById('notifDropdown');
                const testChimeBtn = document.getElementById('notifTestChimeBtn');
                const muteToggleBtn = document.getElementById('notifMuteToggleBtn');
                const markAllBtn = document.getElementById('notifMarkAllReadBtn');

                if (bellBtn && dropdown) {
                    bellBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const isHidden = dropdown.classList.contains('hidden');
                        if (isHidden) {
                            dropdown.classList.remove('hidden');
                            fetchNotifications(false);
                        } else {
                            dropdown.classList.add('hidden');
                        }
                    });

                    document.addEventListener('click', (e) => {
                        if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
                            dropdown.classList.add('hidden');
                        }
                    });
                }

                if (testChimeBtn) {
                    testChimeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        getAudioContext();
                        playNotificationChime();
                    });
                }

                if (muteToggleBtn) {
                    muteToggleBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        isMuted = !isMuted;
                        localStorage.setItem('appNotificationMuted', isMuted ? 'true' : 'false');
                        updateMuteButtonUI();
                    });
                }

                if (markAllBtn) {
                    markAllBtn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        try {
                            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                            await fetch("{{ route('notifications.markAllRead') }}", {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': token,
                                    'Accept': 'application/json'
                                }
                            });
                            fetchNotifications(false);
                        } catch (err) {
                            console.error('Mark all read error:', err);
                        }
                    });
                }

                // Initial fetch to populate dropdown
                fetchNotifications(false);

                // Background polling every 20 seconds
                setInterval(() => {
                    fetchNotifications(true);
                }, 20000);
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
