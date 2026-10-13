<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($ticket) ? "Tracking #{$ticket->ticket_no} - Complaint Status Portal" : "Complaint & Service Tracking Portal" }}</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
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
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .animate-subtle {
            animation: pulse-subtle 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans min-h-screen flex flex-col antialiased">

    <!-- Top Global Header -->
    <header class="bg-[#0f172a] text-white border-b border-slate-800 sticky top-0 z-50 shadow-md">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('track.index') }}" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 to-indigo-600 flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-satellite-dish text-lg"></i>
                </div>
                <div>
                    <span class="font-bold text-base tracking-tight text-white flex items-center space-x-1.5">
                        <span>QMS TRADERS</span>
                        <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-300 border border-sky-400/30">Live Tracking</span>
                    </span>
                    <p class="text-[11px] text-slate-400">Bank Equipment Service &amp; Complaint Portal</p>
                </div>
            </a>

            <div class="flex items-center space-x-3">
                <a href="tel:+9221111000000" class="hidden sm:inline-flex items-center space-x-1.5 text-xs text-slate-300 hover:text-white px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800/60 hover:bg-slate-800 transition">
                    <i class="fa-solid fa-headset text-sky-400"></i>
                    <span>Helpdesk Support</span>
                </a>
                <a href="{{ route('login') }}" class="inline-flex items-center space-x-1.5 text-xs font-semibold text-slate-200 hover:text-white px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 transition">
                    <i class="fa-solid fa-lock text-xs text-slate-400"></i>
                    <span>Staff Login</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow">
        
        <!-- Search & Hero Banner -->
        <section class="bg-gradient-to-b from-[#0f172a] via-[#1e293b] to-slate-100 pt-8 pb-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-400 text-xs font-semibold mb-4">
                    <i class="fa-solid fa-truck-fast"></i>
                    <span>Real-Time SLA &amp; Field Progress Courier Tracking</span>
                </div>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight">
                    Track Complaint Status &amp; Service History
                </h1>
                <p class="mt-2 text-xs sm:text-sm text-slate-300 max-w-2xl mx-auto">
                    Instant real-time visibility into your ticket lifecycle, technician on-site updates, parts requisition, workshop repairs, and SLA timelines.
                </p>

                <!-- Search Input Form -->
                <div class="mt-6 max-w-2xl mx-auto">
                    <form action="{{ route('track.search') }}" method="POST" class="relative shadow-2xl rounded-2xl overflow-hidden bg-white p-1.5 border border-slate-200 flex flex-col sm:flex-row gap-1.5">
                        @csrf
                        <div class="relative flex-grow flex items-center">
                            <span class="pl-4 text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                            </span>
                            <input 
                                type="text" 
                                name="ticket_no" 
                                value="{{ old('ticket_no', $searchedQuery ?? '') }}"
                                placeholder="Enter Complaint # or Bank Reference" 
                                required
                                autofocus
                                class="w-full pl-3 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none font-medium"
                            >
                        </div>
                        <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white font-bold px-6 py-3 rounded-xl text-sm transition flex items-center justify-center space-x-2 shrink-0 shadow-sm">
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Track Status</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Search Error Notification -->
        @if(!empty($error))
            <div class="max-w-4xl mx-auto px-4 -mt-4 mb-8">
                <div class="bg-white rounded-2xl border-l-4 border-rose-500 shadow-md p-5 flex items-start space-x-4">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 text-lg">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-sm font-bold text-slate-900">Complaint Record Not Found</h3>
                        <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $error }}</p>
                        <div class="mt-3 flex items-center space-x-3 text-xs text-slate-500">
                            <span><i class="fa-regular fa-lightbulb text-amber-500 mr-1"></i> Tip: Please check for typographical errors or enter the numerical part of your ticket reference.</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Ticket Found View: Full Courier Tracking Experience -->
        @if(isset($ticket))
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 mb-16 space-y-6">

                <!-- 1. Header Particulars & Status Card -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-5 sm:p-6 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-sky-400">Service Ticket</span>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-white/10 text-white border border-white/20">
                                    {{ $ticket->bank_name }}
                                </span>
                                @if($ticket->urgency)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $ticket->urgency === 'urgent' ? 'bg-rose-500/20 text-rose-300 border border-rose-400/30' : ($ticket->urgency === 'high' ? 'bg-amber-500/20 text-amber-300 border border-amber-400/30' : 'bg-slate-700 text-slate-300') }}">
                                        {{ $ticket->urgency }} PRIORITY
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2 flex items-center space-x-3">
                                <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight font-mono">
                                    {{ $ticket->ticket_no }}
                                </h2>
                                <button 
                                    type="button" 
                                    onclick="navigator.clipboard.writeText('{{ $ticket->ticket_no }}'); alert('Ticket # copied to clipboard!');" 
                                    class="text-xs text-slate-300 hover:text-white px-2 py-1 rounded bg-white/10 hover:bg-white/20 transition flex items-center space-x-1"
                                    title="Copy Ticket Reference"
                                >
                                    <i class="fa-regular fa-copy"></i>
                                    <span>Copy</span>
                                </button>
                            </div>

                            @if($ticket->customer_ref_no)
                                <p class="text-xs text-slate-300 mt-1 flex items-center space-x-1.5">
                                    <span class="text-slate-400">Bank Ref:</span>
                                    <span class="font-semibold text-sky-300 font-mono">#{{ $ticket->customer_ref_no }}</span>
                                </p>
                            @endif
                        </div>

                        <!-- Status Badge -->
                        <div class="flex flex-col md:items-end">
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Current Lifecycle Status</div>
                            <div class="mt-1 flex items-center space-x-2">
                                @php
                                    $statusConfig = match($ticket->status) {
                                        'open' => ['bg' => 'bg-amber-500/20 border-amber-400/40 text-amber-300', 'dot' => 'bg-amber-400', 'label' => 'Open / Logged', 'icon' => 'fa-clock'],
                                        'in_progress' => ['bg' => 'bg-sky-500/20 border-sky-400/40 text-sky-300', 'dot' => 'bg-sky-400', 'label' => 'In Progress (Field Support)', 'icon' => 'fa-wrench'],
                                        'awaiting_approval' => ['bg' => 'bg-amber-500/20 border-amber-400/40 text-amber-300', 'dot' => 'bg-amber-400', 'label' => 'Awaiting Bank Approval (SLA Paused)', 'icon' => 'fa-hourglass-half'],
                                        'awaiting_workshop' => ['bg' => 'bg-purple-500/20 border-purple-400/40 text-purple-300', 'dot' => 'bg-purple-400', 'label' => 'Central Workshop Repair', 'icon' => 'fa-truck-fast'],
                                        'resolved' => ['bg' => 'bg-emerald-500/20 border-emerald-400/40 text-emerald-300', 'dot' => 'bg-emerald-400', 'label' => 'Resolved / Work Restored', 'icon' => 'fa-circle-check'],
                                        'closed' => ['bg' => 'bg-slate-700/60 border-slate-600 text-slate-300', 'dot' => 'bg-slate-400', 'label' => 'Closed & Archived', 'icon' => 'fa-square-check'],
                                        default => ['bg' => 'bg-slate-700/60 border-slate-600 text-slate-300', 'dot' => 'bg-slate-400', 'label' => ucfirst($ticket->status), 'icon' => 'fa-circle-info'],
                                    };
                                @endphp
                                <span class="px-3 py-1.5 rounded-xl border text-xs font-bold inline-flex items-center space-x-2 {{ $statusConfig['bg'] }}">
                                    <span class="w-2 h-2 rounded-full {{ $statusConfig['dot'] }} animate-subtle"></span>
                                    <i class="fa-solid {{ $statusConfig['icon'] }}"></i>
                                    <span>{{ $statusConfig['label'] }}</span>
                                </span>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-400">
                                Logged: {{ $ticket->created_at ? $ticket->created_at->format('d M Y, h:i A') : 'N/A' }}
                            </div>
                        </div>
                    </div>

                    <!-- 2. Courier Stepper Timeline (5 Stages) -->
                    <div class="p-6 bg-slate-50 border-b border-slate-200">
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-6 flex items-center justify-between">
                            <span>Service Progression Milestones</span>
                            <span class="text-slate-400 font-normal">Step-by-step courier fulfillment timeline</span>
                        </div>

                        <!-- Stepper Container -->
                        <div class="relative">
                            <!-- Horizontal Line for Desktop -->
                            <div class="hidden md:block absolute top-1/2 left-6 right-6 -translate-y-1/2 h-1 bg-slate-200 z-0">
                                @php
                                    $completedCount = collect($milestones)->filter(fn($m) => $m['completed'])->count();
                                    $progressPercent = max(0, min(100, (($completedCount - 1) / 4) * 100));
                                @endphp
                                <div class="h-full bg-gradient-to-r from-sky-500 to-emerald-500 transition-all duration-500" style="width: {{ $progressPercent }}%;"></div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 relative z-10">
                                @foreach($milestones as $m)
                                    <div class="bg-white md:bg-transparent p-3 md:p-0 rounded-xl border md:border-0 border-slate-200 flex md:flex-col items-center md:text-center space-x-3 md:space-x-0 space-y-0 md:space-y-2">
                                        <!-- Step Icon Circle -->
                                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center text-sm md:text-base font-bold transition-all shadow-sm shrink-0
                                            {{ $m['completed'] ? 'bg-emerald-600 text-white shadow-emerald-200' : ($m['current'] ? 'bg-sky-600 text-white ring-4 ring-sky-100 shadow-sky-200' : 'bg-slate-200 text-slate-500') }}">
                                            @if($m['completed'])
                                                <i class="fa-solid fa-check"></i>
                                            @else
                                                <i class="{{ $m['icon'] }}"></i>
                                            @endif
                                        </div>

                                        <!-- Step Labels -->
                                        <div>
                                            <div class="text-xs font-bold {{ $m['completed'] || $m['current'] ? 'text-slate-900' : 'text-slate-500' }}">
                                                {{ $m['label'] }}
                                            </div>
                                            <div class="text-[11px] {{ $m['current'] ? 'text-sky-600 font-semibold' : 'text-slate-500' }} truncate max-w-[130px] md:mx-auto">
                                                {{ $m['sublabel'] }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- 3. Overview Particulars Grid -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 bg-white">
                        
                        <!-- Col 1: Bank & Branch -->
                        <div class="space-y-1.5 border-b md:border-b-0 md:border-r border-slate-100 pb-4 md:pb-0 md:pr-4">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-solid fa-building-columns text-sky-600"></i>
                                <span>Bank &amp; Branch</span>
                            </div>
                            <div class="text-sm font-bold text-slate-900">{{ $ticket->bank_name }}</div>
                            <div class="text-xs text-slate-700 font-medium">
                                {{ $ticket->branch_name }}
                                @if($ticket->branch_code)
                                    <span class="text-slate-500 font-normal">({{ $ticket->branch_code }})</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-500 leading-tight">
                                {{ $ticket->branch_location ?: 'Location on record' }}
                            </div>
                            @if($ticket->customer_name)
                                <div class="text-[11px] text-slate-500 pt-1">
                                    <span class="text-slate-400">Contact:</span> {{ $ticket->customer_name }}
                                    @if($ticket->customer_mobile)
                                        <span class="font-mono text-slate-700">| {{ $ticket->customer_mobile }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Col 2: Machine Details -->
                        <div class="space-y-1.5 border-b md:border-b-0 md:border-r border-slate-100 pb-4 md:pb-0 md:pr-4">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-solid fa-calculator text-indigo-600"></i>
                                <span>Equipment Details</span>
                            </div>
                            <div class="text-sm font-bold text-slate-900">{{ $ticket->machine_type ?: 'Cash Handling Unit' }}</div>
                            <div class="text-xs text-slate-700">
                                Model: <span class="font-semibold">{{ $ticket->machine_model ?: 'Standard' }}</span>
                            </div>
                            <div class="text-xs text-slate-600 font-mono">
                                S/N: <span class="font-bold text-slate-900">{{ $ticket->machine_serial_no ?: 'On-Site Inspection' }}</span>
                            </div>
                            @if($ticket->warranty_status)
                                <div class="pt-1">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $ticket->warranty_status === 'under_warranty' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ str_replace('_', ' ', $ticket->warranty_status) }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <!-- Col 3: Issue & SLA -->
                        <div class="space-y-1.5 border-b md:border-b-0 md:border-r border-slate-100 pb-4 md:pb-0 md:pr-4">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                                <span>Fault / Problem Summary</span>
                            </div>
                            <div class="text-xs text-slate-800 font-medium leading-relaxed bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                                {{ $ticket->issue_summary ?: 'Service call requested for diagnostic inspection.' }}
                            </div>
                            <div class="text-[11px] text-slate-500 pt-1 flex items-center justify-between">
                                <span>SLA Target:</span>
                                <span class="font-semibold text-slate-800">
                                    {{ $ticket->sla_deadline ? $ticket->sla_deadline->format('d M, h:i A') : 'Standard SLA Turnaround' }}
                                </span>
                            </div>
                        </div>

                        <!-- Col 4: Field Engineer -->
                        <div class="space-y-1.5">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-solid fa-user-gear text-teal-600"></i>
                                <span>Assigned Lead Engineer</span>
                            </div>
                            @if($ticket->engineer)
                                <div class="text-sm font-bold text-slate-900">{{ $ticket->engineer->name }}</div>
                                <div class="text-xs text-slate-600">
                                    Base City: <span class="font-semibold text-slate-800">{{ $ticket->engineer->base_city }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Specialization: {{ $ticket->engineer->specialization ?? 'Field Sorter & Hardware Specialist' }}
                                </div>
                                @if($ticket->assigned_at)
                                    <div class="text-[10px] text-slate-400 pt-1">
                                        Assigned On: {{ $ticket->assigned_at->format('d M Y, h:i A') }}
                                    </div>
                                @endif
                            @else
                                <div class="text-xs text-amber-700 bg-amber-50 p-2 rounded border border-amber-200">
                                    <i class="fa-solid fa-user-clock mr-1"></i> Awaiting field engineer assignment by regional dispatch desk.
                                </div>
                            @endif
                        </div>

                    </div>

                    <!-- Special Alerts: Workshop or Awaiting Approval -->
                    @if($ticket->status === 'awaiting_approval' && $ticket->approval_requested_at)
                        <div class="p-4 bg-amber-50 border-t border-amber-200 flex items-start space-x-3 text-amber-900">
                            <i class="fa-solid fa-pause-circle text-amber-600 text-lg mt-0.5 shrink-0"></i>
                            <div class="text-xs">
                                <strong class="font-bold">SLA Turnaround Clock Paused:</strong>
                                This ticket is currently paused awaiting formal authorization / quotation approval from bank management.
                                @if($ticket->approval_request_reason)
                                    <div class="mt-1 text-amber-800 font-medium">Reason: {{ $ticket->approval_request_reason }}</div>
                                @endif
                                <div class="text-[11px] text-amber-700 mt-0.5">Paused since: {{ $ticket->approval_requested_at->format('d M Y, h:i A') }}</div>
                            </div>
                        </div>
                    @endif

                    @if($ticket->workshop_dispatched_at)
                        <div class="p-4 bg-purple-50 border-t border-purple-200 flex items-start space-x-3 text-purple-900">
                            <i class="fa-solid fa-truck-fast text-purple-600 text-lg mt-0.5 shrink-0"></i>
                            <div class="text-xs flex-grow">
                                <div class="flex items-center justify-between">
                                    <strong class="font-bold">Central Workshop Repair Cycle Active</strong>
                                    <span class="text-[11px] font-semibold text-purple-700">Dispatched: {{ $ticket->workshop_dispatched_at->format('d M Y') }}</span>
                                </div>
                                <div class="mt-1 text-purple-800">
                                    @if($ticket->workshop_dispatch_courier)
                                        Courier: <span class="font-bold">{{ $ticket->workshop_dispatch_courier }}</span>
                                    @endif
                                    @if($ticket->workshop_dispatch_tracking)
                                        &bull; Slip / Waybill: <span class="font-mono font-bold">{{ $ticket->workshop_dispatch_tracking }}</span>
                                    @endif
                                </div>
                                @if($ticket->workshop_repaired_at)
                                    <div class="mt-1 text-emerald-800 font-semibold">
                                        <i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Workshop repair completed on {{ $ticket->workshop_repaired_at->format('d M Y, h:i A') }}.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>

                <!-- 4. Chronological Courier Tracking Timeline (Latest First) -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-6">
                        <div>
                            <h3 class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight flex items-center space-x-2">
                                <i class="fa-solid fa-timeline text-sky-600"></i>
                                <span>Detailed Ticket Activity &amp; Field Timeline</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Chronological log of technician visits, daily progress feedback, and resolution milestones</p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                            {{ $events->count() }} Recorded Event{{ $events->count() === 1 ? '' : 's' }}
                        </span>
                    </div>

                    @if($events->isEmpty())
                        <div class="text-center py-10 text-slate-400">
                            <i class="fa-solid fa-clipboard-list text-3xl mb-2 text-slate-300"></i>
                            <p class="text-xs">No activity logged yet. Tracking updates will appear here automatically.</p>
                        </div>
                    @else
                        <!-- Vertical Timeline -->
                        <div class="relative pl-6 sm:pl-8 space-y-8 before:absolute before:left-3 sm:before:left-4 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
                            @foreach($events as $index => $event)
                                @php
                                    $isLatest = $index === 0;
                                    $colorMap = [
                                        'sky' => ['circle' => 'bg-sky-600 text-white ring-4 ring-sky-100', 'badge' => 'bg-sky-50 text-sky-700 border-sky-200'],
                                        'indigo' => ['circle' => 'bg-indigo-600 text-white ring-4 ring-indigo-100', 'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                                        'teal' => ['circle' => 'bg-teal-600 text-white ring-4 ring-teal-100', 'badge' => 'bg-teal-50 text-teal-700 border-teal-200'],
                                        'amber' => ['circle' => 'bg-amber-600 text-white ring-4 ring-amber-100', 'badge' => 'bg-amber-50 text-amber-800 border-amber-200'],
                                        'purple' => ['circle' => 'bg-purple-600 text-white ring-4 ring-purple-100', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200'],
                                        'emerald' => ['circle' => 'bg-emerald-600 text-white ring-4 ring-emerald-100', 'badge' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                                        'rose' => ['circle' => 'bg-rose-600 text-white ring-4 ring-rose-100', 'badge' => 'bg-rose-50 text-rose-800 border-rose-200'],
                                        'slate' => ['circle' => 'bg-slate-600 text-white ring-4 ring-slate-100', 'badge' => 'bg-slate-50 text-slate-700 border-slate-200'],
                                        'blue' => ['circle' => 'bg-blue-600 text-white ring-4 ring-blue-100', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200'],
                                    ];
                                    $theme = $colorMap[$event['badge_color'] ?? 'sky'] ?? $colorMap['sky'];
                                @endphp

                                <div class="relative group">
                                    <!-- Node Marker on Timeline -->
                                    <div class="absolute -left-6 sm:-left-8 top-1 w-6 sm:w-8 h-6 sm:h-8 rounded-full flex items-center justify-center text-xs transition-transform group-hover:scale-110 shadow-sm {{ $theme['circle'] }}">
                                        <i class="{{ $event['icon'] }}"></i>
                                    </div>

                                    <!-- Event Card Content -->
                                    <div class="bg-white rounded-xl border {{ $isLatest ? 'border-sky-300 ring-2 ring-sky-50 shadow-sm' : 'border-slate-200' }} p-4 sm:p-5 transition hover:shadow-md">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 pb-2 border-b border-slate-100">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-sm font-bold text-slate-900">{{ $event['title'] }}</h4>
                                                @if($isLatest)
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-sky-600 text-white shadow-xs">
                                                        Latest Update
                                                    </span>
                                                @endif
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $theme['badge'] }}">
                                                    {{ $event['badge'] }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-500 font-mono flex items-center space-x-1.5 shrink-0">
                                                <i class="fa-regular fa-clock text-slate-400"></i>
                                                <span>{{ $event['timestamp'] instanceof \Carbon\Carbon ? $event['timestamp']->format('d M Y, h:i A') : (is_string($event['timestamp']) ? $event['timestamp'] : '') }}</span>
                                            </div>
                                        </div>

                                        @if(!empty($event['description']))
                                            <div class="mt-2.5 text-xs text-slate-700 leading-relaxed font-normal">
                                                {{ $event['description'] }}
                                            </div>
                                        @endif

                                        @if(!empty($event['details']))
                                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 pt-2 border-t border-slate-100">
                                                @foreach($event['details'] as $key => $val)
                                                    <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                                        <div class="text-[10px] font-semibold text-slate-400 uppercase">{{ $key }}</div>
                                                        <div class="text-xs font-semibold text-slate-800 break-words">{{ $val }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- 5. Print & Direct Link Utilities -->
                <div class="bg-slate-100 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-link text-slate-400"></i>
                        <span>Direct Public Tracking Link:</span>
                        <a href="{{ url('/track/' . rawurlencode($ticket->ticket_no)) }}" class="font-mono text-sky-700 hover:underline font-semibold break-all">
                            {{ url('/track/' . rawurlencode($ticket->ticket_no)) }}
                        </a>
                    </div>
                    <button 
                        type="button" 
                        onclick="window.print();" 
                        class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-semibold transition flex items-center space-x-1.5 shrink-0 shadow-xs"
                    >
                        <i class="fa-solid fa-print"></i>
                        <span>Print Service Record</span>
                    </button>
                </div>

            </div>
        @elseif(empty($error))
            <!-- Landing / Empty State with Portal Features -->
            <div class="max-w-4xl mx-auto px-4 -mt-4 mb-16">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-sky-50 text-sky-600 mx-auto flex items-center justify-center text-2xl mb-4">
                        <i class="fa-solid fa-satellite-dish"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900">How to Track Your Complaint</h2>
                    <p class="text-xs text-slate-600 mt-1 max-w-md mx-auto">
                        Locate the complaint reference number provided in your assignment email, SMS notification, or bank internal reference.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8 text-left">
                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50">
                            <div class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center text-sm font-bold mb-2">
                                <i class="fa-solid fa-hashtag"></i>
                            </div>
                            <h3 class="text-xs font-bold text-slate-900">Flexible Search Support</h3>
                            <p class="text-[11px] text-slate-500 mt-1">
                                Search directly using your complaint ticket number or bank reference.
                            </p>
                        </div>

                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50">
                            <div class="w-8 h-8 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center text-sm font-bold mb-2">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                            <h3 class="text-xs font-bold text-slate-900">Daily SLA Technician Feedbacks</h3>
                            <p class="text-[11px] text-slate-500 mt-1">
                                Read exact field notes submitted by assigned engineers for Day 1, Day 2, and ongoing diagnostics.
                            </p>
                        </div>

                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-sm font-bold mb-2">
                                <i class="fa-solid fa-microchip"></i>
                            </div>
                            <h3 class="text-xs font-bold text-slate-900">Workshop &amp; Lab Visibility</h3>
                            <p class="text-[11px] text-slate-500 mt-1">
                                Complete courier waybill tracking for units sent to Central Workshop for component level repairs.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </main>

    <!-- Global Footer -->
    <footer class="bg-slate-900 text-slate-400 py-8 border-t border-slate-800 text-xs">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="font-bold text-slate-300">QMS Traders Helpdesk Services</span>
                <span>&bull; Nationwide Bank Cash Handling Equipment Maintenance</span>
            </div>
            <div class="text-[11px] text-slate-500">
                &copy; {{ date('Y') }} CMS Company / QMS Traders. All rights reserved.
            </div>
        </div>
    </footer>

</body>
</html>
