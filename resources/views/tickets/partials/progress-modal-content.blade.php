<div class="space-y-6">
    <!-- Header Summary -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono font-black text-lg text-slate-900 tracking-tight">
                    #{{ $ticket->ticket_no }}
                </span>
                @if($ticket->isInstallation())
                    <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-xs font-black bg-purple-100 text-purple-900 border border-purple-300">
                        <i class="fa-solid fa-screwdriver-wrench text-[10px] text-purple-700"></i>
                        <span>New Machine Installation</span>
                    </span>
                @else
                    <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        <i class="fa-solid fa-triangle-exclamation text-[10px] text-amber-700"></i>
                        <span>Complaint / Repair</span>
                    </span>
                @endif
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                    {{ in_array($ticket->status, ['resolved', 'closed', 'workshop_repaired']) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : '' }}
                    {{ in_array($ticket->status, ['awaiting_approval']) ? 'bg-amber-100 text-amber-900 border border-amber-400' : '' }}
                    {{ in_array($ticket->status, ['in_progress', 'assigned']) ? 'bg-sky-100 text-sky-800 border border-sky-300' : '' }}
                    {{ in_array($ticket->status, ['awaiting_workshop', 'in_workshop_repair', 'return_transit']) ? 'bg-purple-100 text-purple-800 border border-purple-300' : '' }}
                    {{ in_array($ticket->status, ['open', 'escalated']) ? 'bg-rose-100 text-rose-800 border border-rose-300' : '' }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
            </div>
            <div class="text-xs text-slate-600 mt-1 flex flex-wrap items-center gap-2">
                <span class="font-black text-slate-900">{{ $ticket->bank_name }}</span>
                <span>&bull;</span>
                <span>{{ $ticket->branch_name ?? 'Branch' }} (<strong>{{ $ticket->branch_location }}</strong>)</span>
                @if($ticket->machine_model || $ticket->machine_serial_no)
                    <span>&bull;</span>
                    <span class="font-mono text-slate-700">
                        {{ $ticket->machine_model ?? 'Machine' }} (S/N: {{ $ticket->machine_serial_no ?? 'N/A' }})
                    </span>
                @endif
            </div>
        </div>

        <div class="text-right shrink-0">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Logged On</span>
            <span class="text-xs font-bold text-slate-800">{{ $ticket->created_at->format('d M Y, h:i A') }}</span>
        </div>
    </div>

    <!-- NAKED EYE KPI CARDS: Process Durations in Hours -->
    <div>
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2 flex items-center justify-between">
            <span>⏱️ Process Stage Durations (Hours &amp; Days)</span>
            <span class="text-[10px] font-semibold text-slate-400">Judged at a glance &bull; No mental math needed</span>
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            <!-- 1. Total Elapsed Time -->
            <div class="bg-slate-900 text-white p-3 rounded-xl shadow-xs">
                <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block">Total Age</span>
                <div class="text-xl font-black mt-0.5 font-mono text-amber-400">
                    {{ $totalElapsedHours }}h
                </div>
                <div class="text-[10px] text-slate-300 mt-0.5">
                    {{ $totalDays > 0 ? "{$totalDays}d {$totalRemHours}h" : 'Same day' }}
                </div>
            </div>

            <!-- 2. Time to Alignment -->
            <div class="bg-sky-50 border border-sky-200 p-3 rounded-xl shadow-xs">
                <span class="text-[10px] uppercase tracking-wider font-bold text-sky-800 block">1. Alignment</span>
                <div class="text-xl font-black mt-0.5 font-mono text-sky-900">
                    {{ $assignmentHours !== null ? "{$assignmentHours}h" : 'Pending' }}
                </div>
                <div class="text-[10px] text-sky-700 truncate mt-0.5" title="{{ $ticket->engineer?->name ?? 'Unassigned' }}">
                    {{ $ticket->engineer ? $ticket->engineer->name : 'Unassigned' }}
                </div>
            </div>

            <!-- 3. Waiting for Bank Approval -->
            <div class="p-3 rounded-xl shadow-xs border {{ $approvalWaitHours >= 48 ? 'bg-red-50 border-red-300 text-red-900 ring-2 ring-red-400 animate-pulse' : ($approvalWaitHours > 0 ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-500') }}">
                <span class="text-[10px] uppercase tracking-wider font-bold block {{ $approvalWaitHours >= 48 ? 'text-red-700' : ($approvalWaitHours > 0 ? 'text-amber-800' : 'text-slate-400') }}">
                    2. Approval Freeze
                </span>
                <div class="text-xl font-black mt-0.5 font-mono {{ $approvalWaitHours >= 48 ? 'text-red-700' : ($approvalWaitHours > 0 ? 'text-amber-900' : 'text-slate-400') }}">
                    {{ $approvalWaitHours > 0 ? "{$approvalWaitHours}h" : '0h' }}
                </div>
                <div class="text-[10px] truncate mt-0.5 {{ $approvalWaitHours >= 48 ? 'text-red-600 font-extrabold' : '' }}" title="{{ $ticket->approval_request_reason ?? 'No pause' }}">
                    {{ $isAwaitingApprovalNow ? '⚠️ Still Paused' : ($approvalWaitHours > 0 ? 'Resumed' : 'No hold') }}
                </div>
            </div>

            <!-- 4. Workshop Cargo Transit -->
            <div class="p-3 rounded-xl shadow-xs border {{ $workshopTransitHours !== null ? 'bg-purple-50 border-purple-200 text-purple-900' : 'bg-slate-50 border-slate-200 text-slate-400' }}">
                <span class="text-[10px] uppercase tracking-wider font-bold block text-purple-800">
                    3. Cargo To Lab
                </span>
                <div class="text-xl font-black mt-0.5 font-mono text-purple-900">
                    {{ $workshopTransitHours !== null ? "{$workshopTransitHours}h" : 'N/A' }}
                </div>
                <div class="text-[10px] text-purple-700 truncate mt-0.5" title="{{ $ticket->workshop_dispatch_courier ?? 'Direct/Local' }}">
                    {{ $ticket->workshop_dispatch_courier ? ($ticket->workshop_dispatch_courier . ' ' . $ticket->workshop_dispatch_tracking) : ($workshopTransitHours !== null ? 'In Lab Transit' : 'No Lab Move') }}
                </div>
            </div>

            <!-- 5. Workshop Bench Repair -->
            <div class="p-3 rounded-xl shadow-xs border {{ $benchRepairHours !== null ? 'bg-indigo-50 border-indigo-200 text-indigo-900' : 'bg-slate-50 border-slate-200 text-slate-400' }}">
                <span class="text-[10px] uppercase tracking-wider font-bold block text-indigo-800">
                    4. Bench Repair
                </span>
                <div class="text-xl font-black mt-0.5 font-mono text-indigo-900">
                    {{ $benchRepairHours !== null ? "{$benchRepairHours}h" : 'N/A' }}
                </div>
                <div class="text-[10px] text-indigo-700 truncate mt-0.5" title="{{ $ticket->workshopEngineer?->name ?? 'Bench Tech' }}">
                    {{ $ticket->workshopEngineer ? $ticket->workshopEngineer->name : ($benchRepairHours !== null ? 'Under Repair' : 'Not In Lab') }}
                </div>
            </div>

            <!-- 6. Net Active Working Resolution -->
            <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-xl shadow-xs">
                <span class="text-[10px] uppercase tracking-wider font-bold text-emerald-800 block">Net Work Time</span>
                <div class="text-xl font-black mt-0.5 font-mono text-emerald-900">
                    {{ $netActiveHours }}h
                </div>
                <div class="text-[10px] text-emerald-700 mt-0.5">
                    Excl. paused approval
                </div>
            </div>
        </div>
    </div>

    <!-- LIFECYCLE PROGRESS PIPELINE -->
    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-3">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center justify-between">
            <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-timeline text-sky-600"></i>
                <span>Complete Lifecycle Timeline &amp; Responsibility</span>
            </span>
            <span class="text-[11px] text-slate-500 font-semibold font-mono">
                Current Status: {{ strtoupper(str_replace('_', ' ', $ticket->status)) }}
            </span>
        </h3>

        <div class="relative pl-6 space-y-4 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 text-xs">
            <!-- 1. Received & Logged -->
            <div class="relative">
                <div class="absolute -left-6 top-0.5 w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[9px] shadow-xs">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-1 font-bold text-slate-800">
                    <span>Complaint Received &amp; Logged</span>
                    <span class="text-slate-500 font-mono text-[11px]">{{ $ticket->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <p class="text-slate-600 text-[11px] mt-0.5">
                    Source: <strong class="text-slate-800">{{ $ticket->ticket_no_source === 'from_email' ? 'Bank Email Ingestion' : 'Operations Desk' }}</strong>
                    &bull; Urgency: <strong>{{ strtoupper($ticket->urgency) }}</strong>
                    @if($ticket->sla_tat) &bull; Turnaround Commitment: <strong>{{ $ticket->sla_tat }}</strong> @endif
                </p>
            </div>

            <!-- 2. Engineer Assigned -->
            <div class="relative">
                <div class="absolute -left-6 top-0.5 w-4 h-4 rounded-full {{ $ticket->assigned_engineer_id ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-white' }} flex items-center justify-center text-[9px] shadow-xs">
                    <i class="fa-solid {{ $ticket->assigned_engineer_id ? 'fa-check' : 'fa-hourglass-half' }}"></i>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-1 font-bold text-slate-800">
                    <span>Field Engineer Assignment</span>
                    @if($ticket->assigned_at)
                        <span class="text-slate-500 font-mono text-[11px]">{{ $ticket->assigned_at->format('d M Y, h:i A') }} ({{ $assignmentHours }}h from log)</span>
                    @else
                        <span class="text-amber-600 font-bold text-[11px]">Pending Alignment</span>
                    @endif
                </div>
                <p class="text-slate-600 text-[11px] mt-0.5">
                    @if($ticket->engineer)
                        Assigned to: <strong class="text-slate-900">{{ $ticket->engineer->name }}</strong> (Base: {{ $ticket->engineer->base_city }})
                        @if($ticket->assignedBy) by {{ $ticket->assignedBy->name }} @endif
                    @else
                        No field technician currently dispatched.
                    @endif
                </p>
            </div>

            <!-- 3. Waiting for Approval (If applicable) -->
            @if($ticket->approval_requested_at || $ticket->sla_paused_at || $approvalWaitHours > 0)
                <div class="relative">
                    <div class="absolute -left-6 top-0.5 w-4 h-4 rounded-full {{ $approvalWaitHours >= 48 ? 'bg-red-600 text-white animate-pulse' : ($ticket->approval_arrived_at ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white animate-pulse') }} flex items-center justify-center text-[9px] shadow-xs">
                        <i class="fa-solid fa-pause"></i>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-1 font-bold {{ $approvalWaitHours >= 48 ? 'text-red-700' : 'text-amber-900' }}">
                        <span>
                            Waiting for Client / Bank Approval
                            @if($approvalWaitHours >= 48)
                                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-red-600 text-white animate-pulse">
                                    ⚠️ Critical: Contact Bank (&gt;48h)
                                </span>
                            @endif
                        </span>
                        <span class="font-mono text-[11px] text-amber-800">Duration: {{ $approvalWaitHours }} Hours</span>
                    </div>
                    <div class="text-[11px] text-slate-700 mt-1 bg-amber-50/80 p-2.5 rounded-lg border border-amber-200">
                        <div>Reason: <strong class="text-amber-950">"{{ $ticket->approval_request_reason }}"</strong></div>
                        <div class="text-[10px] text-slate-500 mt-1 flex flex-wrap gap-2">
                            <span>Paused at: {{ $ticket->sla_paused_at?->format('d M Y, h:i A') ?? $ticket->approval_requested_at?->format('d M Y, h:i A') }}</span>
                            @if($ticket->approval_arrived_at)
                                <span>&bull;</span>
                                <span class="text-emerald-700 font-bold">Approved at: {{ $ticket->approval_arrived_at->format('d M Y, h:i A') }}</span>
                            @else
                                <span>&bull;</span>
                                <span class="text-red-600 font-extrabold animate-pulse">Actively waiting for bank clearance right now</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- 4. Central Workshop Stage (If applicable) -->
            @if($ticket->workshop_dispatched_at || $ticket->workshop_location || $ticket->workshop_engineer_id)
                <div class="relative">
                    <div class="absolute -left-6 top-0.5 w-4 h-4 rounded-full {{ in_array($ticket->status, ['workshop_repaired', 'return_transit', 'resolved', 'closed']) ? 'bg-emerald-500 text-white' : 'bg-purple-600 text-white' }} flex items-center justify-center text-[9px] shadow-xs">
                        <i class="fa-solid fa-warehouse"></i>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-1 font-bold text-purple-950">
                        <span>Central Workshop Repair &amp; Calibration</span>
                        <span class="font-mono text-[11px] text-purple-800">
                            Bench Time: {{ $benchRepairHours !== null ? "{$benchRepairHours}h" : 'Pending Intake' }}
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-700 mt-1 bg-purple-50/70 p-2.5 rounded-lg border border-purple-200 space-y-1">
                        <div>
                            Location: <strong class="text-purple-900">{{ $ticket->workshop_location ?? 'Central Technical Lab' }}</strong>
                            &bull; Bench Engineer: <strong class="text-purple-900">{{ $ticket->workshopEngineer?->name ?? 'Workshop Tech' }}</strong>
                        </div>
                        <div class="text-[10px] text-slate-500 flex flex-wrap gap-2">
                            @if($ticket->workshop_dispatched_at)
                                <span>Dispatched: {{ $ticket->workshop_dispatched_at->format('d M Y, h:i A') }}</span>
                            @endif
                            @if($ticket->workshop_received_at)
                                <span>&bull; Received on Bench: {{ $ticket->workshop_received_at->format('d M Y, h:i A') }}</span>
                            @endif
                            @if($ticket->workshop_repaired_at)
                                <span>&bull; Repaired: {{ $ticket->workshop_repaired_at->format('d M Y, h:i A') }}</span>
                            @endif
                        </div>
                        @if($ticket->workshop_repair_summary)
                            <div class="text-[10px] text-slate-600 italic border-t border-purple-100 pt-1 mt-1">
                                Summary: "{{ $ticket->workshop_repair_summary }}"
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- 5. Resolution & Closure -->
            <div class="relative">
                <div class="absolute -left-6 top-0.5 w-4 h-4 rounded-full {{ in_array($ticket->status, ['resolved', 'closed']) ? 'bg-emerald-500 text-white' : 'bg-slate-300 text-white' }} flex items-center justify-center text-[9px] shadow-xs">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-1 font-bold {{ in_array($ticket->status, ['resolved', 'closed']) ? 'text-emerald-800' : 'text-slate-500' }}">
                    <span>Resolution &amp; Bank Delivery</span>
                    @if($ticket->resolved_at)
                        <span class="text-emerald-700 font-mono text-[11px]">{{ $ticket->resolved_at->format('d M Y, h:i A') }} (Total: {{ $totalElapsedHours }}h)</span>
                    @else
                        <span class="text-slate-400 font-normal text-[11px]">Work in progress</span>
                    @endif
                </div>
                @if($ticket->resolution_summary)
                    <p class="text-slate-600 text-[11px] mt-0.5 bg-emerald-50/60 p-2 rounded border border-emerald-100 italic">
                        "{{ $ticket->resolution_summary }}"
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- DAY-WISE DAILY SLA FEEDBACK & AUDIT TRAIL -->
    <div>
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 mb-2 flex items-center justify-between">
            <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-calendar-days text-teal-600"></i>
                <span>Day-Wise SLA Field Feedback &amp; Updates ({{ $feedbacks->count() }})</span>
            </span>
            <span class="text-[10px] text-slate-400">Chronological daily log</span>
        </h3>

        @if($feedbacks->isEmpty())
            <div class="bg-slate-50 rounded-xl p-4 text-center text-xs text-slate-400 border border-slate-200">
                <i class="fa-regular fa-comment-dots text-slate-300 text-lg mb-1 block"></i>
                No day-wise field feedback submitted yet for this complaint.
            </div>
        @else
            <div class="space-y-2.5">
                @foreach($feedbacks as $fb)
                    @php
                        $fbDate = $fb->submitted_at ?? $fb->created_at;
                    @endphp
                    <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-2xs hover:border-teal-300 transition text-xs">
                        <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 mb-2">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-teal-100 text-teal-800">
                                    Day {{ $fb->day_number }} Update
                                </span>
                                <span class="font-bold text-slate-800">
                                    {{ $fb->engineer?->name ?? 'Field Engineer' }}
                                </span>
                            </div>
                            <span class="text-[10px] font-mono text-slate-400">
                                {{ $fbDate->format('d M Y, h:i A') }}
                            </span>
                        </div>
                        <p class="text-slate-700 leading-relaxed">
                            {{ $fb->feedback_text }}
                        </p>
                        @if($fb->action_taken || $fb->parts_required || $fb->eta_completion)
                            <div class="mt-2 pt-2 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                                @if($fb->action_taken)
                                    <div>
                                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Action Taken</span>
                                        <span class="text-slate-700 font-medium">{{ $fb->action_taken }}</span>
                                    </div>
                                @endif
                                @if($fb->parts_required)
                                    <div>
                                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Parts Required</span>
                                        <span class="text-slate-700 font-medium">{{ $fb->parts_required }}</span>
                                    </div>
                                @endif
                                @if($fb->eta_completion)
                                    <div>
                                        <span class="text-slate-400 block text-[9px] uppercase font-bold">Next ETA</span>
                                        <span class="text-teal-700 font-bold">{{ $fb->eta_completion }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
