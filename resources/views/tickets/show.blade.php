@extends('layouts.app')

@section('title', 'Ticket ' . $ticket->ticket_no . ' - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- TOP BREADCRUMB & STATUS RIBBON -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('tickets.index') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="flex items-center space-x-2">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">{{ $ticket->ticket_no }}</h1>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                        {{ $ticket->urgency === 'high' ? 'bg-rose-100 text-rose-800 border border-rose-200' : '' }}
                        {{ $ticket->urgency === 'medium' ? 'bg-amber-100 text-amber-800 border border-amber-200' : '' }}
                        {{ $ticket->urgency === 'low' ? 'bg-slate-100 text-slate-800 border border-slate-200' : '' }}">
                        {{ $ticket->urgency }} Urgency
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                        {{ $ticket->status === 'open' ? 'bg-amber-100 text-amber-800' : '' }}
                        {{ $ticket->status === 'assigned' ? 'bg-sky-100 text-sky-800' : '' }}
                        {{ $ticket->status === 'in_progress' ? 'bg-indigo-100 text-indigo-800' : '' }}
                        {{ $ticket->status === 'awaiting_workshop' ? 'bg-amber-100 text-amber-800 border border-amber-300' : '' }}
                        {{ $ticket->status === 'in_workshop_repair' ? 'bg-purple-100 text-purple-800 border border-purple-300' : '' }}
                        {{ $ticket->status === 'workshop_repaired' ? 'bg-blue-100 text-blue-800 border border-blue-300' : '' }}
                        {{ $ticket->status === 'return_transit' ? 'bg-indigo-100 text-indigo-800 border border-indigo-300' : '' }}
                        {{ $ticket->status === 'escalated' ? 'bg-rose-100 text-rose-800 animate-pulse' : '' }}
                        {{ $ticket->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : '' }}
                        {{ $ticket->status === 'closed' ? 'bg-slate-200 text-slate-700' : '' }}">
                        @if($ticket->status === 'awaiting_workshop')
                            🚚 In Workshop Transit
                        @elseif($ticket->status === 'in_workshop_repair')
                            🔧 Workshop Bench Repair
                        @elseif($ticket->status === 'workshop_repaired')
                            📦 Workshop Repaired
                        @elseif($ticket->status === 'return_transit')
                            🚚 Return Transit to Bank
                        @else
                            {{ str_replace('_', ' ', $ticket->status) }}
                        @endif
                    </span>
                </div>
                <div class="text-xs text-slate-500 mt-0.5">
                    {{ $ticket->bank_name }} &bull; {{ $ticket->branch_name ?? 'Branch' }} ({{ $ticket->branch_location }}) &bull; Created {{ $ticket->created_at->format('d M Y, h:i A') }}
                </div>
                @php
                    $slaHealth = $ticket->getSlaHealth();
                @endphp
                <div class="mt-2 max-w-xs sm:max-w-sm">
                    <div class="flex items-center justify-between text-[11px] mb-1">
                        <span class="font-bold flex items-center gap-1 {{ $slaHealth['breached'] ? 'text-red-700 font-black' : 'text-slate-700' }}">
                            <i class="fa-solid fa-stopwatch text-slate-400 text-[10px]"></i>
                            <span>{{ $slaHealth['label'] }}</span>
                        </span>
                        <span class="font-mono text-[10px] font-bold {{ $slaHealth['breached'] ? 'text-red-600' : 'text-slate-500' }}">
                            {{ $slaHealth['percent'] }}% Remaining
                        </span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden shadow-inner">
                        <div class="h-1.5 rounded-full transition-all duration-500 {{ $slaHealth['bar'] }}" style="width: {{ max(4, $slaHealth['percent']) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Status Badges & Full Manage Action -->
        <div class="flex items-center space-x-3 text-xs">
            <div class="text-right">
                <div class="text-[10px] text-slate-400 font-semibold uppercase">WhatsApp Group</div>
                @if($ticket->whatsapp_notified)
                    <span class="text-emerald-600 font-bold flex items-center justify-end space-x-1">
                        <i class="fa-solid fa-circle-check"></i> <span>Sent ({{ $ticket->whatsapp_notified_at ? $ticket->whatsapp_notified_at->format('h:i A') : 'Dispatched' }})</span>
                    </span>
                @else
                    <span class="text-slate-400 font-medium">Not Dispatched</span>
                @endif
            </div>

            <div class="text-right border-l border-slate-200 pl-3">
                <div class="text-[10px] text-slate-400 font-semibold uppercase">Bank Email</div>
                @if($ticket->email_assignment_sent)
                    <span class="text-sky-600 font-bold flex items-center justify-end space-x-1">
                        <i class="fa-solid fa-circle-check"></i> <span>Replied ({{ $ticket->email_assignment_sent_at ? $ticket->email_assignment_sent_at->format('h:i A') : 'Sent' }})</span>
                    </span>
                @else
                    <span class="text-slate-400 font-medium">Not Sent</span>
                @endif
            </div>

            @if(!auth()->user()->isEngineer())
            <div class="border-l border-slate-200 pl-3 flex items-center space-x-2">
                <a href="{{ route('tickets.edit', $ticket) }}" class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-lg border border-indigo-200 shadow-sm transition">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>Manage / Edit</span>
                </a>

                @if(!$ticket->is_human_verified)
                <form action="{{ route('tickets.destroy', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('DELETE WRONGLY IDENTIFIED COMPLAINT?\n\nThis ticket #{{ $ticket->ticket_no }} has not yet been human finalized. Deleting will discard this complaint and reset the email back to Needs Triage.\n\nProceed?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-lg border border-rose-300 shadow-sm transition active:scale-95" title="Wrongly marked as complaint? Delete unfinalized ticket">
                        <i class="fa-solid fa-trash-can text-rose-600"></i>
                        <span>Delete Wrong Complaint</span>
                    </button>
                </form>
                @else
                <span class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-lg text-slate-400 bg-slate-100 border border-slate-200 text-xs font-medium cursor-not-allowed" title="Human finalized complaints are protected and cannot be deleted.">
                    <i class="fa-solid fa-lock text-slate-400"></i>
                    <span>Finalized (Delete Locked)</span>
                </span>
                @endif
            </div>
            @endif
        </div>
    </div>

    <!-- HUMAN VERIFICATION STATUS ALERT BANNER -->
    <div class="p-4 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3
        {{ $ticket->is_human_verified ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-300 text-amber-950' }}">
        <div class="flex items-center space-x-3 text-xs">
            <span class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shadow-sm
                {{ $ticket->is_human_verified ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white animate-pulse' }}">
                <i class="fa-solid {{ $ticket->is_human_verified ? 'fa-check' : 'fa-triangle-exclamation' }}"></i>
            </span>
            <div>
                <div class="font-bold text-sm">
                    {{ $ticket->is_human_verified ? 'Ticket Details Finalized & Human Verified' : 'AI-Extracted Data: Needs Human Verification & Finalization' }}
                </div>
                <div class="text-[11px] opacity-90 mt-0.5">
                    @if($ticket->is_human_verified)
                        Verified by <strong>{{ $ticket->verifiedBy?->name ?? 'Operations Desk' }}</strong> on {{ $ticket->verified_at?->format('d M Y, h:i A') }}. All machine specs and branch address confirmed.
                    @elseif(auth()->user()->isEngineer())
                        Service specifications from the original customer email. Perform on-site service and resolve or dispatch to workshop.
                    @else
                        Compare the complete bank email on the left with the extracted fields on the right. Edit any typos or missing serials, then click <strong>"Save &amp; Finalize Ticket Details"</strong>.
                    @endif
                </div>
            </div>
        </div>
        <div class="text-right">
            <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide
                {{ $ticket->is_human_verified ? 'bg-emerald-200 text-emerald-800' : 'bg-amber-200 text-amber-900' }}">
                {{ $ticket->is_human_verified ? 'Finalized & Ready' : 'Review & Finalize Needed' }}
            </span>
        </div>
    </div>

    {{-- AWAITING APPROVAL / SLA PAUSED CALLOUT BANNER --}}
    @if($ticket->status === 'awaiting_approval')
    <div class="bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 text-white rounded-2xl shadow-md p-5 border border-amber-400/40">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start space-x-3.5">
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shrink-0">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-black/20 text-white">
                            SLA Clock Paused
                        </span>
                        <span class="text-xs text-amber-100 font-medium">
                            Paused since {{ $ticket->sla_paused_at ? $ticket->sla_paused_at->format('d M Y, h:i A') : 'N/A' }}
                            ({{ $ticket->sla_paused_at ? $ticket->sla_paused_at->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : 'Just now' }} ago)
                        </span>
                    </div>
                    <h3 class="text-base font-extrabold mt-1">Waiting for Customer / Bank Authorization</h3>
                    <p class="text-xs text-amber-100 mt-0.5 leading-relaxed">
                        Authority: <strong>{{ $ticket->approval_source ?? 'Bank Authority' }}</strong> &bull; Reason: <em>"{{ $ticket->approval_request_reason }}"</em>
                    </p>
                    <div class="mt-2 text-[10px] text-amber-100/90 flex flex-wrap items-center gap-3">
                        <span><i class="fa-solid fa-user-pen mr-1"></i>Requested by: <strong>{{ $ticket->approvalRequestedBy?->name ?? 'Engineer' }}</strong></span>
                        @if($ticket->sla_deadline)
                            <span><i class="fa-regular fa-clock mr-1"></i>Current SLA Deadline: <strong>{{ $ticket->sla_deadline->format('d M Y, h:i A') }}</strong></span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start md:self-center shrink-0">
                @if(!auth()->user()->isEngineer())
                    <button type="button"
                            onclick="openGrantApprovalModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ $ticket->sla_paused_at ? $ticket->sla_paused_at->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : '' }}')"
                            class="px-4 py-2.5 rounded-xl bg-white text-emerald-800 hover:bg-emerald-50 font-black text-xs shadow-lg transition flex items-center space-x-2 cursor-pointer">
                        <i class="fa-solid fa-play text-emerald-600"></i>
                        <span>Approval Arrived (Resume Clock)</span>
                    </button>
                @else
                    <span class="px-3.5 py-2 rounded-xl bg-white/10 text-white font-bold text-xs border border-white/20 flex items-center space-x-1.5">
                        <i class="fa-solid fa-lock text-amber-200"></i>
                        <span>Awaiting Operations Sign-off</span>
                    </span>
                @endif
            </div>
        </div>
    </div>
    @elseif($ticket->approval_arrived_at && $ticket->approval_paused_seconds > 0)
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-xs">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-check-circle text-emerald-600 text-sm"></i>
            <span>
                <strong>Approval History:</strong> SLA clock was paused for {{ round($ticket->approval_paused_seconds / 3600, 1) }} hours. Approval confirmed on {{ $ticket->approval_arrived_at->format('d M Y, h:i A') }} by {{ $ticket->approvalArrivedBy?->name ?? 'Admin' }}.
                @if($ticket->approval_arrived_remarks)
                    Remarks: <em>"{{ $ticket->approval_arrived_remarks }}"</em>
                @endif
            </span>
        </div>
        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-200 text-emerald-800 shrink-0 self-start sm:self-center">
            TAT Extended by {{ round($ticket->approval_paused_seconds / 3600, 1) }}h
        </span>
    </div>
    @endif

    {{-- COMPLETE AUDIT TRAIL & ESCALATION / INTAKE LATENCY BANNER --}}
    @php
        $landingAudit = $ticket->getEmailLandingAudit();
        $auditTrailMetrics = $ticket->calculateAuditTrailMetrics();
    @endphp
    <div class="bg-white rounded-2xl shadow-sm border {{ $ticket->status === 'escalated' || $ticket->isSlaBreached() ? 'border-rose-300 ring-1 ring-rose-200' : 'border-slate-200' }} overflow-hidden">
        <div class="px-5 py-4 {{ $ticket->status === 'escalated' || $ticket->isSlaBreached() ? 'bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white' : 'bg-slate-900 text-white' }} flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <span class="w-10 h-10 rounded-xl bg-white/10 text-white flex items-center justify-center text-lg shadow-inner shrink-0">
                    <i class="fa-solid fa-timeline"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-rose-300">
                            Complete Lifecycle Audit Trail &amp; Intake Latency
                        </span>
                        @if($ticket->status === 'escalated')
                            <span class="bg-rose-500 text-white px-2 py-0.5 rounded text-[10px] font-black uppercase animate-pulse">
                                Active Superior Escalation
                            </span>
                        @endif
                    </div>
                    <div class="text-sm font-extrabold mt-0.5 text-white flex items-center gap-2 flex-wrap">
                        <span>Bank Email Landed: <strong>{{ $landingAudit['landed_at_formatted'] }}</strong></span>
                        <span class="text-xs px-2 py-0.5 rounded bg-rose-500/20 text-rose-200 border border-rose-400/30 font-mono">
                            {{ $landingAudit['elapsed_to_now'] }} elapsed to NOW ({{ $landingAudit['elapsed_hours_to_now'] }}h)
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs">
                @if($ticket->email_assignment_sent_at)
                    <div class="bg-white/10 px-3 py-1.5 rounded-xl border border-white/20 text-right">
                        <div class="text-[10px] text-slate-300">Reply Dispatched</div>
                        <div class="font-bold text-emerald-300">{{ $ticket->email_assignment_sent_at->format('d M H:i') }} (+{{ $ticket->created_at->diffInMinutes($ticket->email_assignment_sent_at) }}m TAT)</div>
                    </div>
                @else
                    <div class="bg-rose-500/20 px-3 py-1.5 rounded-xl border border-rose-400/30 text-right">
                        <div class="text-[10px] text-rose-200">Reply Status</div>
                        <div class="font-bold text-rose-300">Pending Email Dispatch</div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Audit Trail Milestones Grid -->
        <div class="p-4 sm:p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 bg-slate-50/70 text-xs border-b border-slate-100">
            <!-- 1. Email Intake -->
            <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 flex items-center justify-between">
                    <span>1. Email Intake</span>
                    <i class="fa-solid fa-inbox text-sky-600"></i>
                </span>
                <div class="font-bold text-slate-900">{{ $landingAudit['landed_at_formatted'] }}</div>
                <div class="text-[10px] text-slate-500 truncate" title="{{ $landingAudit['email_subject'] }}">&ldquo;{{ $landingAudit['email_subject'] }}&rdquo;</div>
                <div class="text-[10px] text-slate-400 mt-1">From: {{ $ticket->customer_email }}</div>
            </div>

            <!-- 2. SLA & Breach Countdown -->
            <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 flex items-center justify-between">
                    <span>2. SLA Clock &amp; Target</span>
                    <i class="fa-solid fa-clock-rotate-left text-amber-600"></i>
                </span>
                <div class="font-bold {{ $ticket->isSlaBreached() ? 'text-red-700' : 'text-slate-900' }}">
                    {{ $ticket->sla_deadline ? $ticket->sla_deadline->format('d M Y, h:i A') : 'Standard SLA' }}
                </div>
                <div class="text-[10px] {{ $ticket->isSlaBreached() ? 'text-red-600 font-bold' : 'text-slate-500' }}">
                    @if($ticket->isSlaBreached())
                        ⚠️ Breached {{ now()->diffForHumans($ticket->sla_deadline, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }} ago
                    @else
                        {{ $ticket->sla_deadline ? $ticket->sla_deadline->diffForHumans() : 'Active' }}
                    @endif
                </div>
                <div class="text-[10px] text-slate-400">Urgency: <strong class="uppercase text-slate-700">{{ $ticket->urgency }}</strong></div>
            </div>

            <!-- 3. Non-Working Pauses -->
            <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 flex items-center justify-between">
                    <span>3. Deductions &amp; Pauses</span>
                    <i class="fa-solid fa-pause text-purple-600"></i>
                </span>
                <div class="text-[11px] font-semibold text-slate-700">
                    Weekends: <strong class="font-mono text-amber-700">-{{ $auditTrailMetrics['weekend_formatted'] }}</strong>
                </div>
                <div class="text-[11px] font-semibold text-slate-700">
                    Approval Wait: <strong class="font-mono text-amber-700">-{{ $auditTrailMetrics['approval_formatted'] }}</strong>
                </div>
                <div class="text-[11px] font-semibold text-slate-700">
                    Transit Pauses: <strong class="font-mono text-purple-700">-{{ $auditTrailMetrics['transit_formatted'] }}</strong>
                </div>
            </div>

            <!-- 4. Turnaround Time to Now -->
            <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 flex items-center justify-between">
                    <span>4. Net Working Time</span>
                    <i class="fa-solid fa-gauge-high text-emerald-600"></i>
                </span>
                <div class="text-base font-black text-slate-900">{{ $auditTrailMetrics['net_formatted'] }}</div>
                <div class="text-[10px] text-slate-500">Gross: {{ $auditTrailMetrics['gross_formatted'] }}</div>
                <div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $auditTrailMetrics['is_in_tat'] ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-rose-100 text-rose-800 border-rose-300' }}">
                        {{ $auditTrailMetrics['tat_status_label'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- CENTRAL WORKSHOP LIFECYCLE & TRANSIT TRACKING BANNER --}}
    @if($ticket->isWorkshopFlow())
    <div class="bg-white rounded-2xl shadow-sm border border-purple-200 overflow-hidden">
        <!-- Banner Header -->
        <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 px-6 py-4 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-300 border border-purple-400/30 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-white">Central Workshop Lifecycle &amp; Smart Transit Tracking</h2>
                        @if(auth()->user()->isSuperior())
                        <a href="{{ route('workshop.index') }}" class="text-[10px] bg-purple-500/30 hover:bg-purple-500/50 text-purple-200 border border-purple-400/30 px-2 py-0.5 rounded-full font-semibold transition">
                            Open Workshop Hub &rarr;
                        </a>
                        @endif
                    </div>
                    <p class="text-[11px] text-purple-200 mt-0.5">
                        Central Facility: <strong>{{ $ticket->workshop_location ?? 'Central Workshop' }}</strong> &bull;
                        Current Holder: <strong class="text-white">{{ $ticket->engineer?->name ?? 'Unassigned' }}</strong>
                        @if($ticket->isInWorkshopTransit())
                            <span class="text-amber-300 font-semibold">(Field Engineer responsible during transit)</span>
                        @elseif($ticket->isInWorkshopRepair())
                            <span class="text-emerald-300 font-semibold">(Bench Handover Complete)</span>
                        @endif
                    </p>
                </div>
            </div>

            <!-- Smart Dual-Day SLA Badges -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <div class="bg-slate-800/80 border border-slate-700 px-3 py-1.5 rounded-xl text-center">
                    <div class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Overall Ticket Age</div>
                    <div class="font-mono font-black text-amber-400 text-sm">Day {{ $ticket->overall_ticket_day }}</div>
                </div>
                @if($ticket->workshop_received_at)
                    <div class="bg-purple-950/80 border border-purple-700 px-3 py-1.5 rounded-xl text-center">
                        <div class="text-[9px] uppercase tracking-wider text-purple-300 font-bold">Workshop Bench Age</div>
                        <div class="font-mono font-black text-purple-300 text-sm">Day {{ $ticket->workshop_engineer_day }}</div>
                    </div>
                @elseif($ticket->isInWorkshopTransit())
                    <div class="bg-amber-950/80 border border-amber-700 px-3 py-1.5 rounded-xl text-center">
                        <div class="text-[9px] uppercase tracking-wider text-amber-300 font-bold">Transit Elapsed</div>
                        <div class="font-mono font-black text-amber-300 text-sm">{{ $ticket->inbound_transit_duration_text }}</div>
                    </div>
                @endif
            </div>
        </div>

        <!-- 4-Stage Visual Stepper -->
        <div class="p-5 bg-slate-50/70 border-b border-slate-200">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <!-- Stage 1: Field Visit & Dispatch -->
                <div class="bg-white rounded-xl p-3.5 border {{ $ticket->workshop_dispatched_at ? 'border-purple-300 bg-purple-50/20' : 'border-slate-200' }} shadow-xs">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-slate-700 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-purple-600 text-white flex items-center justify-center text-[10px] font-bold">1</span>
                            <span>Field Dispatch</span>
                        </span>
                        <span class="text-[10px] font-bold text-purple-700">Done</span>
                    </div>
                    <div class="text-[11px] text-slate-600 space-y-0.5 mt-2">
                        <div>Field Tech: <strong>{{ $ticket->originalFieldEngineer?->name ?? $ticket->engineer?->name ?? 'Field Engineer' }}</strong></div>
                        <div>Courier: <strong class="text-slate-800">{{ $ticket->workshop_dispatch_courier ?? 'Courier' }}</strong></div>
                        <div class="font-mono text-[10px] text-purple-700 font-bold">{{ $ticket->workshop_dispatch_tracking ?? 'In Transit' }}</div>
                        <div class="text-[10px] text-slate-400">On-site: {{ $ticket->field_duration_text }}</div>
                    </div>
                </div>

                <!-- Stage 2: Physical Workshop Intake -->
                <div class="bg-white rounded-xl p-3.5 border {{ $ticket->workshop_received_at ? 'border-purple-300 bg-purple-50/20' : ($ticket->isInWorkshopTransit() ? 'border-amber-300 ring-2 ring-amber-200 bg-amber-50/30' : 'border-slate-200') }} shadow-xs">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-slate-700 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full {{ $ticket->workshop_received_at ? 'bg-purple-600 text-white' : ($ticket->isInWorkshopTransit() ? 'bg-amber-500 text-white animate-pulse' : 'bg-slate-300 text-slate-600') }} flex items-center justify-center text-[10px] font-bold">2</span>
                            <span>Workshop Intake</span>
                        </span>
                        @if($ticket->workshop_received_at)
                            <span class="text-[10px] font-bold text-purple-700">Received</span>
                        @elseif($ticket->isInWorkshopTransit())
                            <span class="text-[10px] font-bold text-amber-700 animate-pulse">In Transit</span>
                        @else
                            <span class="text-[10px] text-slate-400">Pending</span>
                        @endif
                    </div>
                    <div class="text-[11px] text-slate-600 space-y-0.5 mt-2">
                        @if($ticket->workshop_received_at)
                            <div>Bench Tech: <strong class="text-purple-800">{{ $ticket->workshopEngineer?->name ?? 'Workshop Engineer' }}</strong></div>
                            <div class="text-[10px] text-slate-400">Intake: {{ $ticket->workshop_received_at->format('d M, h:i A') }}</div>
                            <div class="text-[10px] text-slate-500 font-medium">Transit: {{ $ticket->inbound_transit_duration_text }}</div>
                        @else
                            <div class="text-amber-800 font-medium text-[11px]">Machine in transit</div>
                            <div class="text-[10px] text-slate-500">Ticket stays with field engineer until received.</div>
                        @endif
                    </div>
                </div>

                <!-- Stage 3: Bench Repair & QA -->
                <div class="bg-white rounded-xl p-3.5 border {{ $ticket->workshop_repaired_at ? 'border-blue-300 bg-blue-50/20' : ($ticket->isInWorkshopRepair() ? 'border-purple-300 ring-2 ring-purple-200 bg-purple-50/30' : 'border-slate-200') }} shadow-xs">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-slate-700 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full {{ $ticket->workshop_repaired_at ? 'bg-blue-600 text-white' : ($ticket->isInWorkshopRepair() ? 'bg-purple-600 text-white animate-pulse' : 'bg-slate-300 text-slate-600') }} flex items-center justify-center text-[10px] font-bold">3</span>
                            <span>Bench Repair QA</span>
                        </span>
                        @if($ticket->workshop_repaired_at)
                            <span class="text-[10px] font-bold text-blue-700">Tested OK</span>
                        @elseif($ticket->isInWorkshopRepair())
                            <span class="text-[10px] font-bold text-purple-700 animate-pulse">On Bench</span>
                        @else
                            <span class="text-[10px] text-slate-400">Pending</span>
                        @endif
                    </div>
                    <div class="text-[11px] text-slate-600 space-y-0.5 mt-2">
                        @if($ticket->workshop_repaired_at)
                            <div class="text-blue-800 font-bold">Tested &amp; Calibrated OK</div>
                            <div class="text-[10px] text-slate-400">Repair time: {{ $ticket->workshop_repair_duration_text }}</div>
                        @elseif($ticket->isInWorkshopRepair())
                            <div>Bench Day: <strong class="text-purple-800">Day {{ $ticket->workshop_engineer_day }}</strong></div>
                            <div class="text-[10px] text-slate-500">Active bench technician repair.</div>
                        @else
                            <div class="text-slate-400 text-[11px]">Awaiting bench queue</div>
                        @endif
                    </div>
                </div>

                <!-- Stage 4: Return Cargo & Bank Delivery -->
                <div class="bg-white rounded-xl p-3.5 border {{ $ticket->bank_received_at ? 'border-emerald-300 bg-emerald-50/20' : ($ticket->isReturnTransit() ? 'border-indigo-300 ring-2 ring-indigo-200 bg-indigo-50/30' : 'border-slate-200') }} shadow-xs">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-slate-700 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full {{ $ticket->bank_received_at ? 'bg-emerald-600 text-white' : ($ticket->isReturnTransit() ? 'bg-indigo-600 text-white animate-pulse' : 'bg-slate-300 text-slate-600') }} flex items-center justify-center text-[10px] font-bold">4</span>
                            <span>Bank Delivery</span>
                        </span>
                        @if($ticket->bank_received_at)
                            <span class="text-[10px] font-bold text-emerald-700">Delivered</span>
                        @elseif($ticket->isReturnTransit())
                            <span class="text-[10px] font-bold text-indigo-700 animate-pulse">Return Transit</span>
                        @else
                            <span class="text-[10px] text-slate-400">Pending</span>
                        @endif
                    </div>
                    <div class="text-[11px] text-slate-600 space-y-0.5 mt-2">
                        @if($ticket->bank_received_at)
                            <div class="text-emerald-800 font-bold">Delivered &amp; Verified</div>
                            <div class="text-[10px] text-slate-400">Delivered: {{ $ticket->bank_received_at->format('d M, h:i A') }}</div>
                            <div class="text-[10px] text-emerald-700 font-medium">Total SLA: {{ $ticket->total_resolution_duration_text }}</div>
                        @elseif($ticket->isReturnTransit())
                            <div>Carrier: <strong class="text-indigo-800">{{ $ticket->return_courier }}</strong></div>
                            <div class="font-mono text-[10px] text-indigo-700 font-bold">{{ $ticket->return_tracking_number }}</div>
                            <div class="text-[10px] text-slate-500">In return transit to bank.</div>
                        @else
                            <div class="text-slate-400 text-[11px]">Awaiting bench repair</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Lifecycle Controls Row -->
        <div class="p-4 bg-white flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="text-slate-600 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-purple-600"></i>
                <span>
                    @if($ticket->status === 'awaiting_workshop')
                        Machine in transit. Ticket remains with field engineer until received at workshop.
                    @elseif($ticket->status === 'in_workshop_repair')
                        Machine received at workshop. Transferred to bench engineer <strong>{{ $ticket->workshopEngineer?->name }}</strong>.
                    @elseif($ticket->status === 'workshop_repaired')
                        Bench testing complete. Ready for courier return dispatch to branch.
                    @elseif($ticket->status === 'return_transit')
                        Machine dispatched to bank via <strong>{{ $ticket->return_courier }}</strong> ({{ $ticket->return_tracking_number }}).
                    @elseif($ticket->status === 'closed')
                        Re-installed at branch &amp; closed. Full SLA turnaround: <strong>{{ $ticket->total_resolution_duration_text }}</strong>.
                    @endif
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($ticket->status === 'awaiting_workshop' && auth()->user()->isSuperior())
                    <button type="button"
                            onclick="openWorkshopReceiveModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ addslashes($ticket->workshop_location ?? '') }}')"
                            class="px-3.5 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg text-xs shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-box-open"></i>
                        <span>Receive at Workshop &amp; Assign Tech</span>
                    </button>
                @elseif($ticket->status === 'in_workshop_repair' && (auth()->user()->isSuperior() || auth()->id() === $ticket->assigned_engineer_id))
                    <button type="button"
                            onclick="openWorkshopResolveModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-check-double"></i>
                        <span>Mark Bench Done</span>
                    </button>
                @elseif($ticket->status === 'workshop_repaired' && auth()->user()->isSuperior())
                    <button type="button"
                            onclick="openWorkshopReturnModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-truck"></i>
                        <span>Dispatch Back to Bank</span>
                    </button>
                @elseif($ticket->status === 'return_transit' && auth()->user()->isSuperior())
                    <button type="button"
                            onclick="openWorkshopCloseModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Confirm Bank Receipt &amp; Close</span>
                    </button>
                @endif

                @if(auth()->user()->isSuperior())
                <a href="{{ route('workshop.index') }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition inline-flex items-center gap-1">
                    <i class="fa-solid fa-table-list"></i>
                    <span>Workshop Hub</span>
                </a>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- RESOLUTION & PROOF OF SERVICE BANNER (VISIBLE TO BOTH ADMIN AND ENGINEER) --}}
    @if(in_array($ticket->status, ['resolved', 'closed', 'workshop_repaired']) || $ticket->hasSupportingDocument() || !empty($ticket->resolution_summary))
    <div class="bg-white rounded-2xl shadow-sm border border-emerald-200 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-800 via-teal-800 to-slate-900 px-6 py-4 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-white">Service Completion &amp; Proof of Work</h2>
                        <span class="text-[10px] bg-emerald-500/30 text-emerald-200 border border-emerald-400/30 px-2 py-0.5 rounded-full font-semibold uppercase">
                            {{ str_replace('_', ' ', $ticket->status) }}
                        </span>
                    </div>
                    <p class="text-[11px] text-emerald-200 mt-0.5">
                        Assigned Engineer: <strong class="text-white">{{ $ticket->engineer?->name ?? 'Field Engineer' }}</strong>
                        @if($ticket->resolved_at)
                            &bull; Completed: <strong>{{ $ticket->resolved_at->format('d M Y, h:i A') }}</strong> ({{ $ticket->resolved_at->diffForHumans() }})
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($ticket->hasSupportingDocument())
                    <a href="{{ $ticket->supportingDocumentUrl() }}" target="_blank" class="px-3.5 py-1.5 bg-white text-emerald-800 hover:bg-emerald-50 font-bold rounded-lg text-xs shadow-sm transition inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-up-right-from-square text-emerald-600"></i>
                        <span>Open Proof Document</span>
                    </a>
                @endif
                @if(!auth()->user()->isEngineer() && in_array($ticket->status, ['resolved', 'closed']) && $ticket->canSendResolutionEmail())
                    <button type="button" 
                            onclick="openResolutionEmailModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ addslashes($ticket->customer_email ?? '') }}', '{{ addslashes($ticket->customer_cc ?? '') }}', '{{ $ticket->hasSupportingDocument() ? $ticket->supportingDocumentUrl() : '' }}', '{{ $ticket->resolution_document_name ?? ($ticket->supporting_document ? basename($ticket->supporting_document) : '') }}')"
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-xs shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Send Resolution Email</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="p-5 grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Work Carried Out -->
            <div class="lg:col-span-{{ $ticket->hasSupportingDocument() ? '7' : '12' }} space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                    <i class="fa-solid fa-screwdriver-wrench text-emerald-600"></i>
                    <span>Work Carried Out / Resolution Summary</span>
                </span>
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-800 leading-relaxed font-medium whitespace-pre-line">
                    {{ $ticket->resolution_summary ?: 'Completed on-site field maintenance, calibrated sensors, and machine tested successfully.' }}
                </div>
                @if($ticket->workshop_repair_summary && $ticket->workshop_repair_summary !== $ticket->resolution_summary)
                    <div class="mt-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-purple-700">Central Workshop Bench QA Report:</span>
                        <div class="p-3 bg-purple-50/60 rounded-xl border border-purple-200 text-xs text-purple-900 mt-1 whitespace-pre-line">
                            {{ $ticket->workshop_repair_summary }}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Supporting Document / Proof Preview -->
            @if($ticket->hasSupportingDocument())
            <div class="lg:col-span-5 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                        <i class="fa-solid fa-paperclip text-emerald-600"></i>
                        <span>Proof of Work / Signed Handover Slip</span>
                    </span>
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('tickets.document', $ticket) }}" target="_blank" class="text-[11px] text-sky-700 hover:underline font-bold">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-0.5"></i> View
                        </a>
                        <span class="text-slate-300">&bull;</span>
                        <a href="{{ route('tickets.document', $ticket) }}" download class="text-[11px] text-emerald-700 hover:underline font-bold">
                            <i class="fa-solid fa-download mr-0.5"></i> Download
                        </a>
                        @if(auth()->user()->isSuperior() || (auth()->user()->isEngineer() && $ticket->assigned_engineer_id === auth()->id()))
                        <span class="text-slate-300">&bull;</span>
                        <button type="button" onclick="document.getElementById('replaceDocModal').classList.remove('hidden')" class="text-[11px] text-amber-700 hover:underline font-bold cursor-pointer">
                            <i class="fa-solid fa-arrows-rotate mr-0.5"></i> Replace
                        </button>
                        @endif
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    @if($ticket->isSupportingDocumentImage())
                        <div class="relative group rounded-lg overflow-hidden border border-slate-200 bg-white">
                            <a href="{{ route('tickets.document', $ticket) }}" target="_blank" class="block">
                                <img src="{{ route('tickets.document', $ticket) }}" alt="Proof of Work" class="w-full max-h-56 object-contain rounded-lg hover:opacity-95 transition bg-slate-100">
                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold gap-1.5">
                                    <i class="fa-solid fa-magnifying-glass-plus text-base"></i>
                                    <span>Click to view full photo</span>
                                </div>
                            </a>
                        </div>
                    @else
                        <div class="flex items-center space-x-3 p-3 bg-white rounded-lg border border-slate-200">
                            <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xl shrink-0">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>
                            <div class="overflow-hidden flex-1">
                                <div class="font-bold text-xs text-slate-800 truncate">{{ $ticket->resolution_document_name ?? basename($ticket->supporting_document) }}</div>
                                <div class="text-[10px] text-slate-400">PDF Document Attachment</div>
                            </div>
                            <a href="{{ route('tickets.document', $ticket) }}" target="_blank" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition">
                                View
                            </a>
                        </div>
                    @endif
                    <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-200">
                        <span class="font-mono truncate max-w-[200px]">{{ $ticket->resolution_document_name ?? basename($ticket->supporting_document) }}</span>
                        <span class="text-emerald-700 font-bold flex items-center gap-1">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Verified
                        </span>
                    </div>
                </div>
            </div>

            @if(auth()->user()->isSuperior() || (auth()->user()->isEngineer() && $ticket->assigned_engineer_id === auth()->id()))
            <div id="replaceDocModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden">
                    <div class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between">
                        <div class="font-bold text-xs flex items-center space-x-2">
                            <i class="fa-solid fa-file-arrow-up text-sky-400"></i>
                            <span>Replace Supporting Document</span>
                        </div>
                        <button type="button" onclick="document.getElementById('replaceDocModal').classList.add('hidden')" class="text-slate-400 hover:text-white text-sm">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <form action="{{ route('tickets.update-document', $ticket) }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                        @csrf
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Select Replacement Document / Picture</label>
                            <input type="file" name="supporting_document" required accept=".jpeg,.png,.jpg,.gif,.webp,.pdf" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-sky-100 file:text-sky-800 hover:file:bg-sky-200 border border-slate-200 rounded-xl p-2 bg-slate-50">
                            <p class="text-[10px] text-slate-400 mt-1">Accepts images (PNG, JPG, WEBP) or PDF up to 10MB.</p>
                        </div>
                        <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                            <button type="button" onclick="document.getElementById('replaceDocModal').classList.add('hidden')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-bold shadow-sm">
                                <i class="fa-solid fa-upload mr-1"></i> Upload &amp; Replace
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
            @endif
        </div>
    </div>
    @endif

    <!-- DUAL-PANE VERIFICATION WORKBENCH: EMAIL ON LEFT, EDITABLE FIELDS ON RIGHT -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3.5 bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center space-x-2">
                <span class="p-1.5 bg-sky-500 text-white rounded text-xs"><i class="fa-solid fa-code-compare"></i></span>
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-100">
                        Verification Workbench: Original Bank Email (Left) &bull; Extracted Machine &amp; Branch (Right)
                    </h2>
                    <p class="text-[11px] text-slate-400">Read the original bank ticket email on the left and verify/edit the hardware specs, serial number, and branch address on the right.</p>
                </div>
            </div>
            @if(!auth()->user()->isEngineer())
            <div class="flex items-center space-x-2 text-xs">
                <a href="{{ route('tickets.edit', $ticket) }}" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[11px] font-medium transition">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Full Standalone Editor
                </a>
            </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-slate-200">
            
            <!-- LEFT PANE (5 COLS): COMPLETE ORIGINAL EMAIL TRANSCRIPT (ALWAYS OPEN & READABLE) -->
            <div class="lg:col-span-5 p-5 bg-slate-50/70 flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-center justify-between pb-2 mb-3 border-b border-slate-200">
                        <div class="flex items-center space-x-1.5">
                            <i class="fa-regular fa-envelope-open text-sky-600"></i>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Original Bank Email (Open)</h3>
                        </div>
                        <span class="text-[10px] bg-slate-200 text-slate-700 px-2 py-0.5 rounded font-mono font-bold">
                            {{ $ticket->customer_ref_no ? 'Ref: ' . $ticket->customer_ref_no : 'Ticket #' . $ticket->ticket_no }}
                        </span>
                    </div>

                    <!-- Email Metadata Header -->
                    <div class="bg-white border border-slate-200 rounded-lg p-3 text-xs space-y-1 mb-3 shadow-xs">
                        <div class="flex items-start justify-between">
                            <span class="text-slate-500 font-medium">From:</span>
                            <strong class="text-slate-800 font-mono text-[11px] select-all">{{ $ticket->customer_email ?? 'Bank Sender' }}</strong>
                        </div>
                        <div class="flex items-start justify-between">
                            <span class="text-slate-500 font-medium">Subject:</span>
                            <span class="text-slate-900 font-semibold text-right text-[11px] max-w-[280px] truncate" title="{{ $ticket->email_subject ?? $ticket->issue_summary }}">
                                {{ $ticket->email_subject ?? $ticket->issue_summary }}
                            </span>
                        </div>
                        <div class="flex items-start justify-between">
                            <span class="text-slate-500 font-medium">Date:</span>
                            <span class="text-slate-600 font-mono text-[11px]">{{ $ticket->created_at->format('D, d M Y - h:i A') }}</span>
                        </div>
                        @if($ticket->incoming_message_id)
                            <div class="flex items-start justify-between pt-1 border-t border-slate-100">
                                <span class="text-slate-400 text-[10px]">Message-ID:</span>
                                <code class="text-[9px] text-slate-500 font-mono truncate max-w-[240px]">{{ $ticket->incoming_message_id }}</code>
                            </div>
                        @endif
                    </div>

                    <!-- Complete Email Transcript Text with AI Highlights -->
                    <div x-data="{ viewMode: 'highlighted' }">
                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center justify-between">
                            <span class="flex items-center space-x-1.5">
                                <i class="fa-solid fa-envelope-open-text text-sky-600"></i>
                                <span>Email Body Content:</span>
                            </span>
                            <div class="inline-flex rounded-lg bg-slate-200 p-0.5 text-[10px] font-semibold">
                                <button type="button" @click="viewMode = 'highlighted'" :class="viewMode === 'highlighted' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="px-2 py-0.5 rounded-md transition flex items-center space-x-1">
                                    <i class="fa-solid fa-wand-magic-sparkles text-[9px]"></i>
                                    <span>AI Highlights</span>
                                </button>
                                <button type="button" @click="viewMode = 'raw'" :class="viewMode === 'raw' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'" class="px-2 py-0.5 rounded-md transition flex items-center space-x-1">
                                    <i class="fa-solid fa-align-left text-[9px]"></i>
                                    <span>Raw Text</span>
                                </button>
                            </div>
                        </div>

                        <!-- Highlights Legend Chips -->
                        <div x-show="viewMode === 'highlighted'" class="flex flex-wrap gap-1 mb-2 text-[10px]">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/40 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span>Address
                            </span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-300 border border-blue-500/40 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1"></span>Bank
                            </span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1"></span>Ticket Ref
                            </span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span>Machine/Serial
                            </span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mr-1"></span>City
                            </span>
                        </div>

                        <!-- Highlighted View -->
                        <div x-show="viewMode === 'highlighted'" class="bg-slate-900 text-slate-100 p-4 rounded-xl font-mono text-[11px] leading-relaxed whitespace-pre-wrap select-text max-h-[520px] overflow-y-auto border border-slate-800 shadow-inner">
{!! $ticket->getHighlightedBodyHtml() !!}
                        </div>

                        <!-- Raw Text View -->
                        <div x-show="viewMode === 'raw'" style="display: none;" class="bg-slate-900 text-slate-200 p-4 rounded-xl font-mono text-[11px] leading-relaxed whitespace-pre-wrap select-text max-h-[520px] overflow-y-auto border border-slate-800 shadow-inner">
{{ $ticket->issue_description ?? $ticket->issue_summary }}
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-sky-50 rounded-lg border border-sky-100 text-[11px] text-sky-900">
                    <i class="fa-solid fa-lightbulb text-sky-600 mr-1"></i>
                    @if(auth()->user()->isEngineer())
                        <strong>Engineer Directive:</strong> Review the original issue description in the bank message on the left to prepare needed testing tools and spare parts.
                    @else
                        <strong>Operator Tip:</strong> Check the serial number, model, BOM contact, and branch address in the email on the left, update any corrections in the form on the right, and click <strong>"Save &amp; Finalize"</strong>.
                    @endif
                </div>
            </div>

            <!-- RIGHT PANE (7 COLS): MACHINE HARDWARE & WARRANTY (EDITABLE) + BRANCH ADDRESS -->
            <div class="lg:col-span-7 p-5 bg-white space-y-4">
                <form action="{{ route('tickets.finalize', $ticket) }}" method="POST" class="space-y-4">
                    @csrf

                    <fieldset @disabled(auth()->user()->isEngineer()) class="space-y-4">
                    <!-- Card 1: Machine Hardware & Warranty (Editable) -->
                    <div class="bg-slate-50/60 p-4 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-solid fa-microchip text-sky-600"></i>
                                <span>Machine Hardware &amp; Warranty {{ auth()->user()->isEngineer() ? '(Verified)' : '(Editable)' }}</span>
                            </h3>
                            <span class="text-[10px] bg-sky-100 text-sky-800 px-2 py-0.5 rounded font-bold">Step 1: Verify Hardware</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Machine Type *</label>
                                <select name="machine_type" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-semibold focus:ring-1 focus:ring-sky-500">
                                    <option value="">-- Unspecified / Unknown --</option>
                                    <option value="Cash Sorting Machine" {{ $ticket->machine_type === 'Cash Sorting Machine' ? 'selected' : '' }}>Cash Sorting Machine</option>
                                    <option value="Counting Machine" {{ $ticket->machine_type === 'Counting Machine' ? 'selected' : '' }}>Counting Machine</option>
                                    <option value="Binding Machine" {{ $ticket->machine_type === 'Binding Machine' ? 'selected' : '' }}>Binding Machine</option>
                                    <option value="ATM" {{ $ticket->machine_type === 'ATM' ? 'selected' : '' }}>ATM (Cash Dispenser)</option>
                                    <option value="CDM" {{ $ticket->machine_type === 'CDM' ? 'selected' : '' }}>CDM (Cash Deposit)</option>
                                    <option value="POS" {{ $ticket->machine_type === 'POS' ? 'selected' : '' }}>POS Terminal</option>
                                    <option value="Kiosk" {{ $ticket->machine_type === 'Kiosk' ? 'selected' : '' }}>Cheque / Statement Kiosk</option>
                                    <option value="Other" {{ $ticket->machine_type === 'Other' ? 'selected' : '' }}>Other Equipment</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Machine Model</label>
                                <input type="text" name="machine_model" value="{{ old('machine_model', $ticket->machine_model) }}" placeholder="e.g. vc 870 or GAQFJ 3201" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-semibold focus:ring-1 focus:ring-sky-500">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">
                                    <i class="fa-solid fa-barcode text-slate-400 mr-1"></i>Serial Number / TID *
                                </label>
                                <input type="text" name="machine_serial_no" value="{{ old('machine_serial_no', $ticket->machine_serial_no) }}" placeholder="e.g. cms11205271" class="w-full bg-white border-2 border-indigo-200 rounded-lg p-2 text-xs font-mono font-bold text-indigo-950 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Warranty Status *</label>
                                <select name="warranty_status" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-semibold focus:ring-1 focus:ring-sky-500">
                                    <option value="in_warranty" {{ $ticket->warranty_status === 'in_warranty' ? 'selected' : '' }}>In Warranty / AMC SLA</option>
                                    <option value="out_of_warranty" {{ $ticket->warranty_status === 'out_of_warranty' ? 'selected' : '' }}>Out of Warranty (Billable)</option>
                                    <option value="unknown" {{ $ticket->warranty_status === 'unknown' ? 'selected' : '' }}>Unknown / To Verify</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Branch Address & Bank Contacts (Editable) -->
                    <div class="bg-slate-50/60 p-4 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-solid fa-location-dot text-rose-500"></i>
                                <span>Branch Physical Address &amp; Contacts (Editable)</span>
                            </h3>
                            <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold">Step 2: Verify Address</span>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block font-bold text-slate-700">
                                        <i class="fa-solid fa-map-pin text-rose-500 mr-1"></i>Branch Physical Street Address *
                                    </label>
                                    @if(!empty($ticket->branch_address))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                            <i class="fa-solid fa-wand-magic-sparkles text-rose-600 mr-1"></i>AI Extracted
                                        </span>
                                    @endif
                                </div>
                                <input type="text" name="branch_address" value="{{ old('branch_address', $ticket->branch_address) }}" placeholder="e.g. BA Building, Mansehra Road, Near SNGPL Office, Abbottabad, Pakistan" class="w-full bg-white border-2 border-rose-200 rounded-lg p-2 text-xs font-medium text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Branch Contact / BOM</label>
                                    <input type="text" name="customer_name" value="{{ old('customer_name', $ticket->customer_name) }}" placeholder="e.g. Shahzan Saza (BOM)" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-medium focus:ring-1 focus:ring-sky-500">
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Mobile / Phone</label>
                                    <input type="text" name="customer_mobile" value="{{ old('customer_mobile', $ticket->customer_mobile) }}" placeholder="0313-3008900" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-mono font-medium focus:ring-1 focus:ring-sky-500">
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Bank Sender Email (To)</label>
                                    <input type="email" name="customer_email" value="{{ old('customer_email', $ticket->customer_email) }}" placeholder="i.kumailnawaz@gmail.com" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-mono font-medium focus:ring-1 focus:ring-sky-500">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                                        <span><i class="fa-solid fa-copy text-slate-400 mr-1"></i>Bank CC Addresses (Stored from Email Thread)</span>
                                        <span class="text-[10px] text-slate-400 font-normal">Included when replying</span>
                                    </label>
                                    <input type="text" name="customer_cc" value="{{ old('customer_cc', $ticket->customer_cc) }}" placeholder="support@bank.com, bom@bank.com" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-mono font-medium focus:ring-1 focus:ring-sky-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Bank Name</label>
                                    <input type="text" name="bank_name" value="{{ old('bank_name', $ticket->bank_name) }}" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Branch Name</label>
                                    <input type="text" name="branch_name" value="{{ old('branch_name', $ticket->branch_name) }}" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">City / Region</label>
                                    <input type="text" name="branch_location" value="{{ old('branch_location', $ticket->branch_location) }}" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500">
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Issue Summary (One-liner)</label>
                                <input type="text" name="issue_summary" value="{{ old('issue_summary', $ticket->issue_summary) }}" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-semibold text-slate-900 focus:ring-1 focus:ring-sky-500">
                            </div>
                        </div>
                    </div>
                    </fieldset>

                    @if(!auth()->user()->isEngineer())
                    <!-- ONE-CLICK SAVE & FINALIZE ACTION BAR -->
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="text-xs text-slate-600">
                            <span class="font-bold text-slate-800"><i class="fa-solid fa-check-double text-emerald-600 mr-1"></i> Human Finalization:</span>
                            Clicking Save verifies all extracted details.
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl text-xs shadow hover:shadow-md transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-user-check text-sm"></i>
                            <span>Save &amp; Finalize Ticket Details</span>
                        </button>
                    </div>
                    @else
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex items-center space-x-2">
                        <i class="fa-solid fa-wrench text-amber-600"></i>
                        <span><strong>Field Engineer Mode:</strong> Hardware &amp; branch details are verified by Operations Admins. Use the resolution and workshop actions below.</span>
                    </div>
                    @endif
                </form>
            </div>

        </div>
    </div>

    @if(!auth()->user()->isEngineer())
    <!-- 3-STEP WORKFLOW PIPELINE: THREE CARDS SIDE BY SIDE -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- STEP 1: ENGINEER ALIGNMENT CARD -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4 flex flex-col justify-between">
            <div class="space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2">
                        <span class="w-6 h-6 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-bold">1</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Engineer Alignment</h2>
                    </div>
                    @if($ticket->engineer)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Assigned</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 animate-pulse">Unassigned</span>
                    @endif
                </div>

                @if($ticket->engineer)
                    <!-- Currently Assigned Profile -->
                    <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-200 text-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm text-slate-900">{{ $ticket->engineer->name }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $ticket->engineer->is_available ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                {{ $ticket->engineer->is_available ? 'Available' : 'On Leave' }}
                            </span>
                        </div>
                        <div class="text-slate-600">
                            Base: <strong class="text-slate-800">{{ $ticket->engineer->base_city }}</strong> &bull;
                            Skill: <strong class="text-slate-800">{{ $ticket->engineer->specialization }}</strong>
                        </div>
                        <div class="text-slate-600 flex items-center space-x-1">
                            <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                            <span>Phone: <strong class="text-emerald-700 font-mono">{{ $ticket->engineer->phone_whatsapp }}</strong></span>
                        </div>
                        <div class="text-[10px] text-slate-400 pt-1 border-t border-slate-200">
                            Assigned by: {{ $ticket->assignedBy?->name ?? 'Admin' }} on {{ $ticket->assigned_at?->format('d M, h:i A') }}
                        </div>
                    </div>
                @endif

                <!-- Alignment Form -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        {{ $ticket->engineer ? 'Re-Assign Different Engineer:' : 'Select Engineer from Database:' }}
                    </label>
                    <form action="{{ route('tickets.assign', $ticket) }}" method="POST" class="space-y-3">
                        @csrf
                        <select name="engineer_id" required class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                            <option value="">-- Choose Engineer in Database --</option>
                            @foreach($engineers as $eng)
                                <option value="{{ $eng->id }}" {{ $ticket->assigned_engineer_id == $eng->id ? 'selected' : '' }}>
                                    {{ $eng->name }} &bull; {{ $eng->base_city }} &bull; {{ $eng->specialization }} &bull; [{{ $eng->assigned_tickets_count }} active] &bull; (WA: {{ $eng->phone_whatsapp }})
                                </option>
                            @endforeach
                        </select>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Define TAT / SLA Target:</label>
                            <select name="sla_tat" class="w-full border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-sky-500 bg-white">
                                <option value="">Keep Existing SLA ({{ $ticket->sla_deadline ? $ticket->sla_deadline->diffForHumans() : 'Auto' }})</option>
                                <option value="1 day">1 Day SLA (Urgent)</option>
                                <option value="2 days">2 Days SLA (Standard)</option>
                                <option value="3 days">3 Days SLA</option>
                                <option value="4 days">4 Days SLA (Extended)</option>
                                <option value="4 hours">4 Hours TAT (Emergency)</option>
                                <option value="8 hours">8 Hours TAT (Critical)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Or Specific Custom SLA Date:</label>
                            <input type="datetime-local" name="custom_sla_deadline" class="w-full border border-slate-300 rounded-lg p-1.5 text-xs text-slate-700 bg-white">
                        </div>
                        <input type="text" name="notes" placeholder="Optional assignment note..." class="w-full border border-slate-300 rounded-lg p-2 text-xs">
                        <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2 rounded-lg text-xs shadow-sm transition">
                            <i class="fa-solid fa-user-check mr-1.5"></i> {{ $ticket->engineer ? 'Confirm Re-Alignment & SLA' : 'Confirm Assignment & SLA' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- STEP 2: NOTIFY ON WHATSAPP BUTTON -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">2</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">WhatsApp Group Dispatch</h2>
                    </div>
                    @if($ticket->whatsapp_notified)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            <i class="fa-solid fa-check"></i> Dispatched
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500">Pending</span>
                    @endif
                </div>

                <p class="text-xs text-slate-600">
                    One click → captures email screenshot → sends it + complaint message to
                    <strong>WhatsApp group "testing"</strong>, tagging
                    <strong>{{ $ticket->engineer?->name ?? 'N/A' }}</strong>
                    (<span class="font-mono text-emerald-700">{{ $ticket->engineer?->phone_whatsapp ?? 'No phone' }}</span>).
                </p>

                {{-- Message Preview --}}
                @if($ticket->assigned_engineer_id)
                    @php
                        $waService = app(\App\Services\WhatsAppService::class);
                        $waMsg     = $waService->getAssignmentMessageText($ticket, $ticket->engineer);
                    @endphp
                    <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-lg">
                        <div class="text-[10px] font-bold text-emerald-800 mb-1.5 flex items-center justify-between">
                            <span><i class="fa-brands fa-whatsapp mr-1"></i>Will send to group:</span>
                            <span class="bg-emerald-200 text-emerald-900 px-1.5 py-0.5 rounded text-[9px] font-bold">AUTO MODE 🤖</span>
                        </div>
                        <pre id="wa-msg-preview" class="text-[10px] font-mono text-emerald-950 whitespace-pre-wrap leading-relaxed max-h-28 overflow-y-auto">{{ $waMsg }}</pre>
                    </div>
                @endif
            </div>

            <div class="space-y-2.5">
                @if($ticket->assigned_engineer_id)

                    {{-- PRIMARY: COPY EMAIL AS IMAGE & OPEN WHATSAPP --}}
                    <button onclick="copyEmailAsImageAndOpenWhatsApp()" id="wa-copy-btn"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3 px-4 rounded-xl text-sm shadow-md hover:shadow-lg transition flex items-center justify-center space-x-2 active:scale-95 cursor-pointer">
                        <i class="fa-solid fa-copy text-base"></i>
                        <span id="wa-copy-label">
                            📋 {{ $ticket->whatsapp_notified ? 'Re-Copy Email as Image & Open WhatsApp' : 'Copy Email as Image & Open WhatsApp' }}
                        </span>
                    </button>

                    {{-- QUICK ACTIONS ROW --}}
                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="copyCleanEmailText()" id="wa-copy-email-text-btn"
                            class="flex-1 py-1.5 px-2 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold transition flex items-center justify-center space-x-1 cursor-pointer"
                            title="Copy clean, formatted email text">
                            <i class="fa-solid fa-envelope-open-text text-slate-500"></i>
                            <span id="wa-copy-email-text-label">Copy Email Text</span>
                        </button>
                        <button type="button" onclick="copyWhatsAppMessageText()" id="wa-copy-text-btn"
                            class="py-1.5 px-2.5 bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-300 rounded-lg text-xs font-semibold transition flex items-center justify-center space-x-1 cursor-pointer"
                            title="Copy short assignment text">
                            <i class="fa-solid fa-file-lines text-slate-400"></i>
                            <span id="wa-copy-text-label">Bot Text</span>
                        </button>
                        <a href="whatsapp://" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-xs font-bold transition flex items-center space-x-1" title="Open WhatsApp Desktop">
                            <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                            <span>App</span>
                        </a>
                        <a href="https://web.whatsapp.com" target="_blank" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold transition flex items-center space-x-1" title="Open WhatsApp Web">
                            <i class="fa-solid fa-arrow-up-right-from-square text-slate-400"></i>
                            <span>Web</span>
                        </a>
                    </div>

                    {{-- Status message area --}}
                    <div id="wa-status-box" class="hidden rounded-lg p-3 text-xs font-medium"></div>

                    {{-- Zero-Friction Dispatch Instruction Card --}}
                    <div class="bg-emerald-50/70 border border-emerald-200/90 rounded-lg p-2.5 text-[11px] text-emerald-950 space-y-1">
                        <div class="font-bold text-emerald-900 flex items-center space-x-1">
                            <i class="fa-solid fa-wand-magic-sparkles text-emerald-600"></i>
                            <span>Local WhatsApp Dispatch (No Bot Server Needed):</span>
                        </div>
                        <div><span class="font-bold text-emerald-700">①</span> Click <strong>"Copy Email as Image &amp; Open WhatsApp"</strong> to copy the clean original email card as an image.</div>
                        <div><span class="font-bold text-emerald-700">②</span> It automatically <strong>unlocks Step 3</strong> (Bank Assignment Email) and launches WhatsApp.</div>
                        <div><span class="font-bold text-emerald-700">③</span> In WhatsApp, just press <kbd class="px-1.5 py-0.5 bg-white border border-emerald-300 rounded font-mono text-[10px] font-bold">Ctrl + V</kbd> to paste the clean email image!</div>
                    </div>

                    {{-- Collapsible Alternative: Automated Bot Dispatch --}}
                    <details class="text-[11px] text-slate-500 pt-1">
                        <summary class="cursor-pointer hover:text-slate-800 font-semibold select-none flex items-center space-x-1">
                            <i class="fa-solid fa-robot text-slate-400"></i>
                            <span>Optional: Dispatch via Automated Bot (wwebjs-service)</span>
                        </summary>
                        <div class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
                            <p class="text-[10px] text-slate-500">Requires local Node.js bot running on port 3000 linked with QR code.</p>
                            <button onclick="sendToWhatsAppGroup()" id="wa-send-btn"
                                class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 rounded-lg text-xs transition flex items-center justify-center space-x-1.5 active:scale-95 cursor-pointer">
                                <i class="fa-brands fa-whatsapp"></i>
                                <span id="wa-send-label">🚀 Send via Automated Bot</span>
                            </button>
                        </div>
                    </details>

                    {{-- Actual Email Render Target (Rendered offscreen for html2canvas to capture authentic email screenshot) --}}
                    <div id="email-screenshot-target" style="position:fixed;left:-9999px;top:0;z-index:-999;opacity:1;pointer-events:none;">
                        <style>
                            #email-screenshot-inner table {
                                width: 100% !important;
                                max-width: 100% !important;
                                border-collapse: collapse;
                            }
                            #email-screenshot-inner td, #email-screenshot-inner th {
                                height: auto !important;
                            }
                            #email-screenshot-inner img {
                                max-width: 100% !important;
                                height: auto !important;
                            }
                        </style>
                        <div id="email-screenshot-inner" style="background:#ffffff;padding:24px 28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;width:720px;color:#0f172a;line-height:1.5;box-sizing:border-box;">
                            
                            <!-- Authentic Email Header -->
                            <div style="padding-bottom:14px;margin-bottom:18px;border-bottom:2px solid #e2e8f0;background:#ffffff;">
                                <div style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:8px;line-height:1.3;">
                                    {{ $ticket->incomingEmail?->subject ?: ($ticket->email_subject ?: ('Complaint #' . $ticket->ticket_no . ' - ' . $ticket->bank_name)) }}
                                </div>
                                <div style="font-size:12px;color:#475569;line-height:1.7;">
                                    <div><strong style="color:#1e293b;">From:</strong> {{ $ticket->incomingEmail?->from_name ? ($ticket->incomingEmail->from_name . ' <' . $ticket->incomingEmail->from_email . '>') : ($ticket->customer_name ? ($ticket->customer_name . ' <' . ($ticket->customer_email ?: '') . '>') : ($ticket->customer_email ?? ($ticket->bank_name . ' Operations'))) }}</div>
                                    @if(!empty($ticket->incomingEmail?->to_email))
                                        <div><strong style="color:#1e293b;">To:</strong> {{ $ticket->incomingEmail->to_email }}</div>
                                    @endif
                                    @if(!empty($ticket->customer_cc) || !empty($ticket->incomingEmail?->cc_emails))
                                        <div><strong style="color:#1e293b;">Cc:</strong> {{ $ticket->customer_cc ?: $ticket->incomingEmail?->cc_emails }}</div>
                                    @endif
                                    <div><strong style="color:#1e293b;">Date:</strong> {{ $ticket->incomingEmail?->email_date ? $ticket->incomingEmail->email_date->format('D, d M Y, h:i A') : $ticket->created_at->format('D, d M Y, h:i A') }}</div>
                                </div>
                            </div>

                            <!-- Actual Email Body (Original Bank Email) -->
                            <div class="actual-email-body" style="font-size:13px;color:#1e293b;line-height:1.5;background:#ffffff;">
                                @if($ticket->incomingEmail && !empty($ticket->incomingEmail->body_html))
                                    {!! $ticket->incomingEmail->body_html !!}
                                @elseif(!empty($ticket->issue_description))
                                    <div style="white-space:pre-wrap;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#1e293b;">{{ $ticket->issue_description }}</div>
                                @else
                                    <div style="white-space:pre-wrap;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#1e293b;">{{ $ticket->issue_summary }}</div>
                                @endif
                            </div>

                        </div>
                    </div>

                    {{-- Raw clean email text for instant text copying --}}
                    <textarea id="clean-email-raw-text" class="hidden">{{ $ticket->cleanEmailText() }}</textarea>

                    {{-- CSRF token for AJAX --}}
                    <meta name="csrf-token-wa" content="{{ csrf_token() }}">
                    {{-- Route URLs for JS --}}
                    <div id="wa-copy-route-url" data-url="{{ route('tickets.mark-whatsapp-copied', $ticket) }}" class="hidden"></div>
                    <div id="wa-route-url" data-url="{{ route('tickets.send-group-whatsapp', $ticket) }}" class="hidden"></div>
                    <div id="wa-has-email" data-value="1" class="hidden"></div>

                @else
                    <button disabled class="w-full bg-slate-200 text-slate-400 font-semibold py-2.5 rounded-lg text-xs cursor-not-allowed flex items-center justify-center space-x-1.5">
                        <i class="fa-solid fa-lock text-xs"></i>
                        <span>Assign Engineer First</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- STEP 3: SEND ASSIGNMENT EMAIL TO BANK (STRICTLY LOCKED UNTIL WHATSAPP SENT) -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2">
                        <span class="w-6 h-6 rounded-full bg-sky-600 text-white flex items-center justify-center text-xs font-bold">3</span>
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Send Bank Assignment Email</h2>
                    </div>
                    @if($ticket->email_assignment_sent)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800">
                            <i class="fa-solid fa-check"></i> Replied
                        </span>
                    @elseif($ticket->whatsapp_notified)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 animate-pulse">Unlocked</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-400">
                            <i class="fa-solid fa-lock"></i> Locked
                        </span>
                    @endif
                </div>

                <p class="text-xs text-slate-600">
                    Replies to the original email thread received from the bank with official confirmation: Ticket No, Engineer Name & Mobile, and Expected SLA turnaround.
                </p>

                <!-- Strict Rule Indicator -->
                @if(!$ticket->whatsapp_notified)
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-2.5 rounded-r text-[11px] text-amber-900 font-medium">
                        <i class="fa-solid fa-lock text-amber-600 mr-1"></i>
                        <strong>SLA Enforcement Rule:</strong> Unlocks <em>only after</em> notifying the engineer on WhatsApp (Step 2).
                    </div>
                @else
                    <div class="bg-sky-50 border border-sky-200 p-3 rounded-lg text-[11px] text-sky-950 font-mono space-y-1.5">
                        <div class="font-bold text-sky-800 flex items-center justify-between">
                            <div class="flex items-center space-x-1">
                                <i class="fa-solid fa-reply text-sky-600"></i>
                                <span>Exact Conversation Thread Reply:</span>
                            </div>
                            <span class="text-[9px] bg-sky-200 text-sky-900 px-1.5 py-0.5 rounded font-bold">RFC Threading</span>
                        </div>
                        <div>To: <strong>{{ $ticket->customer_email ?? 'Bank Sender' }}</strong></div>
                        @if(!empty($ticket->customer_cc))
                            <div class="text-[10px] text-slate-600 truncate">Cc: <strong class="font-mono text-slate-800">{{ $ticket->customer_cc }}</strong></div>
                        @endif
                        <div class="truncate">Subject: <strong>{{ $ticket->email_subject ? (preg_match('/^re:\s*/i', $ticket->email_subject) ? $ticket->email_subject : 'RE: ' . $ticket->email_subject) : ('RE: Ticket #' . $ticket->ticket_no . ' - ' . $ticket->issue_summary) }}</strong></div>
                        @if($ticket->incoming_message_id)
                            <div class="truncate text-[10px] text-slate-500">
                                In-Reply-To: <code class="bg-white px-1 py-0.5 rounded border border-sky-200 text-[9px]">{{ $ticket->incoming_message_id }}</code>
                            </div>
                        @endif
                        <div>Engineer: {{ $ticket->engineer?->name }} ({{ $ticket->engineer?->phone_whatsapp }})</div>
                        <div>Target SLA: <strong>{{ $ticket->sla_deadline ? $ticket->sla_deadline->format('d M, h:i A') : $ticket->expectedResponseHours() . ' hours' }}</strong></div>
                    </div>
                @endif
            </div>

            <div>
                @if($ticket->whatsapp_notified)
                    <form action="{{ route('tickets.send-assignment-email', $ticket) }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 flex items-center justify-between">
                                <span class="flex items-center space-x-1">
                                    <i class="fa-solid fa-copy text-sky-600"></i>
                                    <span>Bank CC Recipients (From Original Bank Thread):</span>
                                </span>
                                <span class="text-[10px] text-slate-400 font-normal">Included alongside To address</span>
                            </label>
                            <input type="text" name="customer_cc" value="{{ old('customer_cc', $ticket->customer_cc) }}" placeholder="e.g. branch.mgr@bank.com, area.mgr@bank.com" class="w-full border border-slate-300 rounded-lg p-2 text-xs font-mono text-slate-800 focus:ring-1 focus:ring-sky-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                Custom Instructions / Remarks for Bank (Optional):
                            </label>
                            <textarea name="custom_notes" rows="2" placeholder="e.g. Field engineer will arrive with required spare parts and tools..." class="w-full border border-slate-300 rounded-lg p-2 text-xs text-slate-800 focus:ring-1 focus:ring-sky-500"></textarea>
                        </div>

                        <!-- Live Email Reply Template Preview Accordion -->
                        <details class="border border-sky-200 rounded-lg overflow-hidden bg-white text-xs">
                            <summary class="p-2.5 bg-sky-50 text-[11px] font-bold text-sky-900 cursor-pointer flex items-center justify-between hover:bg-sky-100 transition">
                                <span class="flex items-center space-x-1.5">
                                    <i class="fa-solid fa-eye text-sky-600"></i>
                                    <span>Preview Outgoing Official Email Template</span>
                                </span>
                                <span class="text-[10px] text-sky-700 uppercase font-semibold">Click to preview</span>
                            </summary>
                            <div class="p-3 bg-slate-100 border-t border-sky-100 space-y-2 text-[11px] text-slate-700 max-h-72 overflow-y-auto">
                                <div class="bg-white border border-slate-300 rounded-lg overflow-hidden shadow-sm max-w-xl mx-auto">
                                    <!-- Header -->
                                    <div class="bg-[#17212b] p-4 text-white flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-base tracking-wide">CMS COMPANY</div>
                                            <div class="text-[10px] text-slate-400 uppercase tracking-wider">Help Desk &amp; Technical Support</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] text-slate-400 uppercase">Service Ticket</div>
                                            <div class="text-base font-bold text-white">#{{ $ticket->ticket_no }}</div>
                                        </div>
                                    </div>
                                    <!-- Status Bar -->
                                    <div class="px-4 py-2.5 bg-[#fafbfc] border-b border-slate-200 flex items-center justify-between">
                                        <div>
                                            <div class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Engineer Assignment Notification</div>
                                            <div class="text-xs font-bold text-slate-900">Field Support Task Assigned</div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-orange-100 text-orange-800 border border-orange-300">
                                            HIGH PRIORITY
                                        </span>
                                    </div>
                                    <!-- Body -->
                                    <div class="p-4 space-y-3 text-xs text-slate-800">
                                        <p>Dear <strong>{{ $ticket->bank_name }} Operations Team</strong>,</p>
                                        <p class="text-slate-600 text-[11.5px]">this is to inform you complain has been registered with cms company help desk and assigned to a certified field support engineer.</p>

                                        <!-- Assignment Details Box -->
                                        <div class="border border-slate-200 rounded overflow-hidden">
                                            <div class="bg-slate-50 px-3 py-1.5 font-bold text-[10px] text-slate-700 uppercase tracking-wider border-b border-slate-200">
                                                Assignment Details
                                            </div>
                                            <div class="divide-y divide-slate-100 text-[11px]">
                                                <div class="px-3 py-1.5 flex justify-between">
                                                    <span class="text-slate-500">Assigned Field Engineer:</span>
                                                    <span class="font-bold text-slate-900">{{ $ticket->engineer?->name ?? 'Field Engineer' }} — {{ $ticket->engineer?->base_city ?? 'Regional Office' }}</span>
                                                </div>
                                                <div class="px-3 py-1.5 flex justify-between">
                                                    <span class="text-slate-500">Task Status:</span>
                                                    <span class="font-bold text-emerald-700">ASSIGNED</span>
                                                </div>
                                                <div class="px-3 py-1.5 flex justify-between">
                                                    <span class="text-slate-500">Expected Resolution:</span>
                                                    <span class="font-bold text-slate-900">Within Defined TAT</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Complaint & Site Info -->
                                        <div class="border border-slate-200 rounded overflow-hidden">
                                            <div class="bg-slate-50 px-3 py-1.5 font-bold text-[10px] text-slate-700 uppercase tracking-wider border-b border-slate-200">
                                                Complaint &amp; Site Information
                                            </div>
                                            <div class="divide-y divide-slate-100 text-[11px]">
                                                <div class="px-3 py-1.5 flex justify-between">
                                                    <span class="text-slate-500">Complaint Reference:</span>
                                                    <span class="font-bold text-slate-900">#{{ $ticket->customer_ref_no ?: $ticket->ticket_no }}</span>
                                                </div>
                                                <div class="px-3 py-1.5 flex justify-between">
                                                    <span class="text-slate-500">Bank / Branch:</span>
                                                    <span class="font-bold text-slate-900 text-right">{{ $ticket->bank_name }}<br><span class="font-normal text-slate-600">{{ $ticket->branch_name }} ({{ $ticket->branch_location }})</span></span>
                                                </div>
                                                <div class="px-3 py-1.5 flex justify-between">
                                                    <span class="text-slate-500">Machine:</span>
                                                    <span class="font-bold text-slate-900">{{ $ticket->machine_type }} {{ $ticket->machine_model ? '- ' . $ticket->machine_model : '' }} (S/N: {{ $ticket->machine_serial_no ?? 'N/A' }})</span>
                                                </div>
                                                <div class="px-3 py-1.5 flex justify-between">
                                                    <span class="text-slate-500">Branch Contact:</span>
                                                    <span class="font-bold text-slate-900">{{ $ticket->customer_name }} | {{ $ticket->customer_mobile ?? 'N/A' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Required -->
                                        <div class="bg-slate-50 border-l-4 border-slate-700 p-2.5 rounded text-[11px] text-slate-700">
                                            <strong class="text-slate-900">Branch Coordination Required:</strong> Please facilitate branch security entry and access for the designated engineer ({{ $ticket->engineer?->name ?? 'Field Engineer' }}).
                                        </div>
                                    </div>
                                    <div class="bg-[#17212b] px-4 py-2.5 text-[10px] text-slate-400 flex items-center justify-between">
                                        <span>CMS Company Help Desk &bull; Automated Service Notification</span>
                                        <span>Ticket #{{ $ticket->ticket_no }}</span>
                                    </div>
                                </div>
                            </div>
                        </details>

                        <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-semibold py-2.5 rounded-lg text-xs shadow hover:shadow-md transition flex items-center justify-center space-x-1.5">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>{{ $ticket->email_assignment_sent ? 'Re-Send Bank Assignment Reply' : 'Send Assignment Email via GoDaddy SMTP' }}</span>
                        </button>
                    </form>
                @else
                    <button disabled class="w-full bg-slate-200 text-slate-400 font-semibold py-2.5 rounded-lg text-xs cursor-not-allowed flex items-center justify-center space-x-1.5" title="Notify engineer on WhatsApp first">
                        <i class="fa-solid fa-lock"></i>
                        <span>Send Email (Locked: WhatsApp Required First)</span>
                    </button>
                @endif
            </div>
        </div>

    </div>
    @else
    <!-- ENGINEER FIELD SERVICE ORDER BANNER -->
    @php
        $sla = $ticket->getSlaHealth();
        $slaPercent = $sla['percent'] ?? 100;
        $isBreached = $sla['breached'] ?? false;
        $isResolved = in_array($ticket->status, ['resolved', 'closed']);
        $tatHours = $ticket->expectedResponseHours();
    @endphp
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white rounded-2xl p-5 shadow-lg border border-slate-700 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-700/80 pb-3">
            <div class="flex items-center space-x-2.5">
                <span class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-base font-bold border border-emerald-500/30 shadow-inner">
                    <i class="fa-solid fa-wrench"></i>
                </span>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                        <span>Assigned Field Service Order</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-300 border border-emerald-700/50 font-mono">#{{ $ticket->ticket_no }}</span>
                    </h2>
                    <p class="text-[11px] text-slate-300">Assigned Engineer: <strong class="text-white">{{ auth()->user()->name }}</strong> &bull; Base: {{ auth()->user()->base_city ?? 'Field Office' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-orange-500/20 text-orange-400 border border-orange-500/30">
                    <i class="fa-solid fa-fire mr-1"></i> {{ strtoupper($ticket->urgency) }} PRIORITY
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-slate-700 text-slate-200 border border-slate-600">
                    <i class="fa-regular fa-clock mr-1 text-slate-400"></i> SLA Deadline: {{ $ticket->sla_deadline ? $ticket->sla_deadline->format('d M Y, h:i A') : 'Standard SLA' }}
                </span>
            </div>
        </div>

        <!-- SLA TURNAROUND PROGRESS BAR -->
        <div class="bg-slate-800/90 p-4 rounded-xl border border-slate-700/80 space-y-2.5 shadow-inner">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs">
                <div class="flex items-center space-x-2">
                    <span class="text-slate-300 font-bold uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                        <i class="fa-solid fa-gauge-high text-emerald-400"></i>
                        <span>SLA Turnaround Progress</span>
                    </span>
                    @if($isResolved)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                            <i class="fa-solid fa-circle-check mr-1"></i> 100% Completed
                        </span>
                    @elseif($isBreached)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> SLA Breached
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/20 text-sky-300 border border-sky-500/40">
                            {{ $slaPercent }}% Time Window Remaining
                        </span>
                    @endif
                </div>
                <div class="text-[11px] font-mono">
                    @if($isResolved)
                        <span class="text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i> Resolved {{ $ticket->resolved_at ? $ticket->resolved_at->diffForHumans() : 'Successfully' }}</span>
                    @elseif($isBreached)
                        <span class="text-rose-400 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Breached {{ $ticket->sla_deadline ? $ticket->sla_deadline->diffForHumans() : '' }}</span>
                    @else
                        <span class="text-amber-300"><i class="fa-regular fa-clock mr-1"></i> {{ $sla['label'] }} Remaining</span>
                    @endif
                </div>
            </div>

            <!-- Dynamic Animated Progress Bar -->
            <div class="w-full bg-slate-950/80 rounded-full h-3.5 p-0.5 border border-slate-700 overflow-hidden shadow-inner">
                <div class="h-full rounded-full transition-all duration-500 {{ $isResolved ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : ($isBreached ? 'bg-gradient-to-r from-rose-600 to-red-500 animate-pulse' : ($sla['color'] === 'amber' ? 'bg-gradient-to-r from-amber-500 to-orange-400' : ($sla['color'] === 'rose' ? 'bg-gradient-to-r from-rose-600 to-red-500' : 'bg-gradient-to-r from-emerald-500 to-teal-400'))) }}"
                     style="width: {{ $isResolved ? 100 : max(5, $slaPercent) }}%">
                </div>
            </div>

            <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                <span><i class="fa-solid fa-truck-fast mr-1 text-slate-400"></i>Dispatched: {{ $ticket->assigned_at ? $ticket->assigned_at->format('d M, h:i A') : 'Assigned' }}</span>
                <span class="hidden sm:inline text-slate-300">Target Window: <strong class="text-white">{{ $tatHours }} Hours TAT</strong></span>
                <span><i class="fa-regular fa-calendar-check mr-1 text-emerald-400"></i>Target Due: {{ $ticket->sla_deadline ? $ticket->sla_deadline->format('d M, h:i A') : 'Standard SLA' }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-300">
            <div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
                <span class="text-slate-400 block text-[10px] uppercase font-bold mb-1">Branch Destination</span>
                <div class="font-bold text-white text-sm">{{ $ticket->bank_name }}</div>
                <div class="text-xs text-slate-300">{{ $ticket->branch_name }} &bull; {{ $ticket->branch_location }}</div>
                <div class="text-[11px] text-slate-400 mt-1"><i class="fa-solid fa-location-dot text-rose-400 mr-1"></i>{{ $ticket->branch_address ?? 'Branch address on record' }}</div>
            </div>
            <div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
                <span class="text-slate-400 block text-[10px] uppercase font-bold mb-1">Target Equipment</span>
                <div class="font-bold text-white">{{ $ticket->machine_type ?? 'Cash Handling Machine' }}</div>
                <div class="text-xs text-slate-300">Model: {{ $ticket->machine_model ?? 'Standard' }}</div>
                <div class="text-xs font-mono text-emerald-400 mt-1">S/N: {{ $ticket->machine_serial_no ?? 'N/A' }}</div>
            </div>
            <div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700/60">
                <span class="text-slate-400 block text-[10px] uppercase font-bold mb-1">Branch Contact &amp; Status</span>
                <div class="font-bold text-white">{{ $ticket->customer_name ?? 'Branch Manager' }}</div>
                <div class="text-xs font-mono text-sky-400 mt-0.5"><i class="fa-solid fa-phone mr-1"></i>{{ $ticket->customer_mobile ?? 'N/A' }}</div>
                <div class="text-[11px] text-slate-400 mt-1">Status: <span class="capitalize font-semibold text-emerald-400">{{ str_replace('_', ' ', $ticket->status) }}</span></div>
            </div>
        </div>

        <!-- ENGINEER DIRECT ACTIONS ROW -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-slate-700/80">
            <div class="flex items-center space-x-2 text-xs text-slate-300">
                <i class="fa-solid fa-screwdriver-wrench text-emerald-400"></i>
                <span>Engineer Actions: Complete on-site service or send machine to central workshop.</span>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                @if(!$isResolved)
                    @if($ticket->status === 'awaiting_workshop')
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center space-x-1.5">
                                <i class="fa-solid fa-truck-moving text-amber-400"></i>
                                <span>In Transit to Central Workshop (Courier: {{ $ticket->workshop_dispatch_courier ?? 'Cargo' }})</span>
                            </span>
                            <span class="text-[11px] text-slate-400">
                                Assigned to you until physical workshop intake.
                            </span>
                        </div>
                    @elseif($ticket->status === 'in_workshop_repair')
                        @if(auth()->id() === $ticket->assigned_engineer_id)
                            <!-- Workshop Bench Technician Actions -->
                            <button type="button"
                                    onclick="openWorkshopResolveModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-900/40 transition flex items-center space-x-1.5 active:scale-95 cursor-pointer">
                                <i class="fa-solid fa-check-double text-sm"></i>
                                <span>Mark Bench Repair Complete</span>
                            </button>
                            <a href="{{ route('parts.requests.create', ['ticket_id' => $ticket->id]) }}" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white font-bold rounded-xl text-xs shadow-md shadow-sky-900/40 transition flex items-center space-x-1.5 active:scale-95 cursor-pointer">
                                <i class="fa-solid fa-gears text-sm"></i>
                                <span>Request Spare Parts</span>
                            </a>
                        @else
                            <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 flex items-center space-x-1.5">
                                <i class="fa-solid fa-screwdriver-wrench text-purple-400"></i>
                                <span>Bench Repair in Progress (Bench Tech: {{ $ticket->workshopEngineer?->name ?? 'Workshop Engineer' }})</span>
                            </span>
                        @endif
                    @elseif($ticket->status === 'workshop_repaired')
                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-500/20 text-blue-300 border border-blue-500/40 flex items-center space-x-1.5">
                            <i class="fa-solid fa-box-archive text-blue-400"></i>
                            <span>Bench Repaired &amp; Tested OK &bull; Awaiting Return Cargo to Bank</span>
                        </span>
                    @elseif($ticket->status === 'return_transit')
                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 flex items-center space-x-1.5">
                            <i class="fa-solid fa-truck text-indigo-400"></i>
                            <span>In Return Transit to Bank Branch (Tracking: {{ $ticket->return_tracking_number }})</span>
                        </span>
                    @else
                        <!-- Standard On-Site Actions -->
                        <!-- Option: Mark as Complete -->
                        <button type="button" onclick="openEngineerCompleteModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-900/40 transition flex items-center space-x-1.5 active:scale-95 cursor-pointer">
                            <i class="fa-solid fa-circle-check text-sm"></i>
                            <span>Mark as Complete</span>
                        </button>

                        <!-- Option: Send to Workshop (Modal Trigger) -->
                        <button type="button" onclick="openEngineerWorkshopModal()" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs shadow-md shadow-purple-900/40 transition flex items-center space-x-1.5 active:scale-95 cursor-pointer">
                            <i class="fa-solid fa-truck-ramp-box text-sm"></i>
                            <span>Send to Workshop</span>
                        </button>

                        <!-- Option: Request Spare Parts -->
                        <a href="{{ route('parts.requests.create', ['ticket_id' => $ticket->id]) }}" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white font-bold rounded-xl text-xs shadow-md shadow-sky-900/40 transition flex items-center space-x-1.5 active:scale-95 cursor-pointer">
                            <i class="fa-solid fa-gears text-sm"></i>
                            <span>Request Spare Parts</span>
                        </a>
                    @endif
                @else
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center space-x-1.5">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Service Completed &amp; Resolved</span>
                        </span>
                        @if($ticket->supporting_document)
                            <a href="{{ asset('storage/' . $ticket->supporting_document) }}" target="_blank" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-sky-500/20 text-sky-300 border border-sky-500/40 hover:bg-sky-500/30 flex items-center space-x-1.5 transition">
                                <i class="fa-solid fa-paperclip text-sky-400"></i>
                                <span>View Supporting Doc</span>
                            </a>
                        @endif
                        @if($ticket->hasClaimedExpenses())
                            <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 border border-slate-700 flex items-center space-x-1.5" title="Resolution cannot be undone because tour expenses have already been filed for this ticket">
                                <i class="fa-solid fa-lock text-amber-400"></i>
                                <span>Locked &bull; Expenses Claimed</span>
                            </span>
                        @else
                            <form action="{{ route('tickets.undo-resolve', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('Undo completion for Ticket #{{ $ticket->ticket_no }} and revert back to In Progress?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 hover:bg-amber-500/30 flex items-center space-x-1.5 transition cursor-pointer active:scale-95" title="Accidentally marked complete? Click to revert to In Progress">
                                    <i class="fa-solid fa-rotate-left text-amber-400"></i>
                                    <span>Undo Mark Done</span>
                                </button>
                            </form>
                        @endif
                        @if($ticket->canClaimExpense())
                            <button type="button" 
                                    onclick="openTicketClaimExpenseModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ addslashes($ticket->branch_location) }}', '{{ addslashes($ticket->engineer?->base_city ?? auth()->user()->base_city ?? 'Lahore') }}')"
                                    class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 active:scale-95 cursor-pointer">
                                <i class="fa-solid fa-receipt"></i>
                                <span>Claim Tour Expense</span>
                            </button>
                        @elseif($ticket->hasActiveExpenseClaim())
                            @php $claim = $ticket->activeExpenseClaim(); @endphp
                            <span class="px-3.5 py-2 bg-slate-800 text-amber-300 border border-slate-700 rounded-xl text-xs font-bold flex items-center space-x-1.5" title="Claim #EXP-{{ str_pad($claim->id ?? 0, 4, '0', STR_PAD_LEFT) }} is active (status: {{ $claim->status ?? 'submitted' }}). Cannot create another claim until rejected.">
                                <i class="fa-solid fa-clock text-amber-400"></i>
                                <span>Expense Claim Active ({{ ucfirst($claim->status ?? 'Submitted') }})</span>
                            </span>
                        @elseif($ticket->hasRejectedExpenseClaim())
                            <button type="button" 
                                    onclick="openTicketClaimExpenseModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ addslashes($ticket->branch_location) }}', '{{ addslashes($ticket->engineer?->base_city ?? auth()->user()->base_city ?? 'Lahore') }}')"
                                    class="px-4 py-2 bg-rose-500 hover:bg-rose-400 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center space-x-1.5 active:scale-95 cursor-pointer"
                                    title="Previous expense claim was rejected by Operations Manager. Click to submit a corrected claim.">
                                <i class="fa-solid fa-rotate-left"></i>
                                <span>Re-Claim Expense (Rejected)</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if(!auth()->user()->isEngineer())
    <!-- TICKET LIFECYCLE ACTIONS BAR (ADMIN & OPERATIONS MANAGER) -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div>
            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Ticket Lifecycle &amp; Work Completion Actions</h2>
            <p class="text-[11px] text-slate-500">Mark work completed, submit field expenses, or confirm official closure.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Workshop & Lifecycle Stage Actions -->
            @if($ticket->status === 'awaiting_workshop')
                <button type="button" 
                        onclick="openWorkshopReceiveModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ addslashes($ticket->workshop_location ?? '') }}')"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Receive at Workshop &amp; Assign Tech</span>
                </button>
            @elseif($ticket->status === 'in_workshop_repair')
                <button type="button" 
                        onclick="openWorkshopResolveModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-check-double"></i>
                    <span>Mark Bench Repair Done</span>
                </button>
            @elseif($ticket->status === 'workshop_repaired')
                <button type="button" 
                        onclick="openWorkshopReturnModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-truck"></i>
                    <span>Dispatch Repaired Machine Back to Bank</span>
                </button>
            @elseif($ticket->status === 'return_transit')
                <button type="button" 
                        onclick="openWorkshopCloseModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Confirm Bank Receipt &amp; Close Ticket</span>
                </button>
            @elseif($ticket->status === 'awaiting_approval')
                @if(!auth()->user()->isEngineer())
                    <button type="button" 
                            onclick="openGrantApprovalModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ $ticket->sla_paused_at ? $ticket->sla_paused_at->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : '' }}')"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-play"></i>
                        <span>Approval Arrived (Resume SLA Clock)</span>
                    </button>
                @else
                    <span class="px-4 py-2 bg-amber-100 text-amber-900 border border-amber-300 font-bold rounded-lg text-xs flex items-center space-x-1.5">
                        <i class="fa-solid fa-hourglass-half text-amber-600"></i>
                        <span>SLA Paused (Awaiting Authorization)</span>
                    </span>
                @endif
            @elseif(in_array($ticket->status, ['assigned', 'in_progress', 'escalated']))
                <button type="button" 
                        onclick="openCompleteModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}')"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Mark Resolved (Work Completed)</span>
                </button>

                <a href="{{ route('parts.requests.create', ['ticket_id' => $ticket->id]) }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-gears text-emerald-400"></i>
                    <span>Request Spare Parts</span>
                </a>

                <form action="{{ route('tickets.request-approval', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('Pause SLA timer and mark Ticket #{{ $ticket->ticket_no }} as Waiting for Approval?');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-pause"></i>
                        <span>Waiting for Approval (Pause SLA)</span>
                    </button>
                </form>
            @endif

            <!-- Send Resolution Email (Operations Manager) -->
            @if(in_array($ticket->status, ['resolved', 'closed']) && $ticket->canSendResolutionEmail())
                <button type="button" 
                        onclick="openResolutionEmailModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ addslashes($ticket->customer_email ?? '') }}', '{{ addslashes($ticket->customer_cc ?? '') }}', '{{ $ticket->hasSupportingDocument() ? $ticket->supportingDocumentUrl() : '' }}', '{{ $ticket->resolution_document_name ?? ($ticket->supporting_document ? basename($ticket->supporting_document) : '') }}')"
                        class="px-4 py-2 {{ $ticket->resolution_email_sent ? 'bg-emerald-50 text-emerald-800 border border-emerald-300' : 'bg-sky-600 text-white hover:bg-sky-700' }} font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid {{ $ticket->resolution_email_sent ? 'fa-envelope-circle-check text-emerald-600' : 'fa-paper-plane' }}"></i>
                    <span>{{ $ticket->resolution_email_sent ? 'Resolution Email Sent (Re-send)' : 'Send Resolution Email to Bank' }}</span>
                </button>
            @endif

            <!-- Claim Tour Expense (Only for Engineers) -->
            @if(auth()->user()->isEngineer())
                @if($ticket->canClaimExpense(auth()->id()))
                    <button type="button" 
                            onclick="openTicketClaimExpenseModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ addslashes($ticket->branch_location) }}', '{{ addslashes($ticket->engineer?->base_city ?? auth()->user()->base_city ?? 'Lahore') }}')"
                            class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold rounded-lg text-xs transition flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-receipt text-emerald-600"></i>
                        <span>Claim Tour Expense for this Ticket</span>
                    </button>
                @elseif($ticket->hasActiveExpenseClaim())
                    <span class="px-3.5 py-2 bg-slate-100 text-slate-600 border border-slate-200 rounded-lg text-xs font-bold flex items-center space-x-1.5" title="Active claim pending. Cannot create another claim until rejected.">
                        <i class="fa-solid fa-clock text-amber-500"></i>
                        <span>Claim Submitted (Pending Audit)</span>
                    </span>
                @endif
            @endif

            <!-- Close Ticket & Undo Resolution (Admin Confirmation) -->
            @if($ticket->status === 'resolved')
                @if($ticket->hasClaimedExpenses())
                    <span class="px-3.5 py-2 bg-slate-100 text-slate-500 border border-slate-300 rounded-lg text-xs font-bold flex items-center space-x-1.5" title="Resolution is permanently locked because tour expenses have already been claimed">
                        <i class="fa-solid fa-lock text-slate-400"></i>
                        <span>Resolution Locked (Expenses Claimed)</span>
                    </span>
                @else
                    <form action="{{ route('tickets.undo-resolve', $ticket) }}" method="POST" class="inline" onsubmit="return confirm('Revert Ticket #{{ $ticket->ticket_no }} back to In Progress?');">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                            <i class="fa-solid fa-rotate-left text-amber-600"></i>
                            <span>Undo Mark Done (Revert)</span>
                        </button>
                    </form>
                @endif
                <form action="{{ route('tickets.close', $ticket) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg text-xs shadow-sm transition flex items-center space-x-1.5">
                        <i class="fa-solid fa-box-archive"></i>
                        <span>Confirm &amp; Close Ticket Officially</span>
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endif
    @if(!auth()->user()->isEngineer())
    <!-- DAILY PROGRESS FEEDBACK & FIELD UPDATES (OPERATIONS MANAGEMENT & ADMIN) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center space-x-2">
                    <span class="p-1.5 bg-indigo-100 text-indigo-700 rounded-lg text-xs">
                        <i class="fa-solid fa-list-check"></i>
                    </span>
                    <span>Daily Progress Feedback &amp; SLA Milestones</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Field engineers and operations managers record daily work logs, requested spare parts, and ETA until final resolution.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                    <i class="fa-solid fa-clock-rotate-left mr-1 text-slate-500"></i>
                    {{ $ticket->feedbacks->count() }} Updates Recorded
                </span>
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                    Ticket Overall: Day {{ $ticket->overall_ticket_day }}
                </span>
                @if($ticket->workshop_received_at)
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-100 text-purple-900 border border-purple-200">
                        Workshop Bench: Day {{ $ticket->workshop_engineer_day }} ({{ $ticket->workshopEngineer?->name }})
                    </span>
                @elseif($ticket->isInWorkshopTransit())
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        Inbound Transit: {{ $ticket->inbound_transit_duration_text }} ({{ $ticket->originalFieldEngineer?->name ?? $ticket->engineer?->name }})
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Timeline of Recorded Feedbacks (7 cols) -->
            <div class="lg:col-span-7 space-y-3">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Activity &amp; Progress Timeline
                </h3>

                @php
                    $fbMatrix = $ticket->getDailyFeedbackMatrix();
                @endphp

                @if(empty($fbMatrix))
                <div class="bg-slate-50 border border-dashed border-slate-200 rounded-xl p-8 text-center text-xs text-slate-400">
                    <i class="fa-solid fa-clipboard-question text-3xl text-slate-300 mb-2"></i>
                    <p class="font-medium text-slate-600">No daily feedbacks logged yet.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Assigned engineer or operations manager can log today's on-site status.</p>
                </div>
                @else
                <div class="relative pl-6 space-y-4 before:content-[''] before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                    @foreach(array_reverse($fbMatrix) as $item)
                        @if($item['is_logged'])
                            @php $fb = $item['feedback']; @endphp
                            <div class="relative bg-slate-50 hover:bg-slate-100/70 border border-slate-200/80 rounded-xl p-4 transition shadow-sm text-xs">
                                <span class="absolute -left-[27px] top-4 w-3.5 h-3.5 rounded-full border-2 border-white bg-indigo-600 shadow"></span>
                                
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <div>
                                        <span class="bg-indigo-100 text-indigo-800 font-extrabold px-2 py-0.5 rounded text-[10px] uppercase">
                                            Day {{ $item['day_number'] }}
                                        </span>
                                        <span class="font-bold text-slate-800 ml-1.5">{{ $item['action_taken'] }}</span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        {{ $item['submitted_at'] ? $item['submitted_at']->format('d M Y, h:i A') : '' }}
                                    </span>
                                </div>

                                <p class="text-slate-700 leading-relaxed font-sans text-xs">
                                    {{ $item['feedback_text'] }}
                                </p>

                                @if($item['parts_required'])
                                <div class="mt-2 text-[11px] bg-amber-50 text-amber-900 border border-amber-200 px-2.5 py-1 rounded-lg flex items-center gap-1.5">
                                    <i class="fa-solid fa-screwdriver-wrench text-amber-600"></i>
                                    <span><strong>Parts Needed:</strong> {{ $item['parts_required'] }}</span>
                                </div>
                                @endif

                                @if($fb && $fb->eta_completion)
                                <div class="mt-1.5 text-[11px] text-slate-500">
                                    <i class="fa-regular fa-calendar-check mr-1 text-emerald-600"></i>
                                    <span>Target Completion ETA: <strong>{{ $fb->eta_completion->format('d M Y, h:i A') }}</strong></span>
                                </div>
                                @endif

                                @if($fb && $fb->photo_evidence)
                                <div class="mt-2">
                                    <a href="{{ asset('storage/' . $fb->photo_evidence) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-sky-600 hover:text-sky-800 font-semibold bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-sm">
                                        <i class="fa-solid fa-image text-sky-500"></i>
                                        <span>View Attached On-Site Photo</span>
                                    </a>
                                </div>
                                @endif

                                <div class="mt-2 pt-2 border-t border-slate-200/60 text-[10px] text-slate-400 flex items-center justify-between">
                                    <span>Logged by: <strong>{{ $item['engineer_name'] }}</strong></span>
                                    <span class="capitalize">Field Operational Log</span>
                                </div>
                            </div>
                        @else
                            {{-- MISSING FEEDBACK DAY FLAGGED AS N/A --}}
                            <div class="relative bg-rose-50/90 hover:bg-rose-100/90 border-2 border-rose-300 rounded-xl p-4 transition shadow-sm text-xs ring-1 ring-rose-200">
                                <span class="absolute -left-[27px] top-4 w-3.5 h-3.5 rounded-full border-2 border-white bg-rose-600 shadow animate-pulse"></span>
                                
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <div>
                                        <span class="bg-rose-600 text-white font-black px-2 py-0.5 rounded text-[10px] uppercase tracking-wider">
                                            Day {{ $item['day_number'] }} : N/A
                                        </span>
                                        <span class="font-extrabold text-rose-800 ml-1.5">Missing Daily Progress Feedback</span>
                                    </div>
                                    <span class="text-[10px] text-rose-600 font-bold uppercase">
                                        Expected {{ $item['date_estimated']->format('d M Y') }}
                                    </span>
                                </div>

                                <div class="text-rose-800 font-bold text-xs flex items-center gap-1.5 my-1">
                                    <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                                    <span>Day {{ $item['day_number'] }}: N/A (Status update not recorded)</span>
                                </div>

                                <p class="text-rose-700 leading-relaxed font-sans text-[11px]">
                                    No engineer progress update was submitted for this active operational day. In breach of 24-hour daily status reporting mandate.
                                </p>
                            </div>
                        @endif
                    @endforeach
                </div>
                @endif
            </div>

            <!-- Right: Submit Daily Feedback Form (5 cols) -->
            <div class="lg:col-span-5 bg-slate-50 border border-slate-200 rounded-xl p-5 space-y-4">
                @php
                    $canSubmitFeedback = (!auth()->user()->isEngineer()) || ($ticket->assigned_engineer_id === auth()->id());
                    $todayFeedback = $ticket->feedbacks->first(function($f) {
                        return ($f->submitted_at && $f->submitted_at->isToday()) || ($f->created_at && $f->created_at->isToday());
                    });
                    $calendarDay = max(1, (int) ($ticket->created_at ?? now())->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1);
                @endphp

                @if($canSubmitFeedback)
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid {{ $todayFeedback ? 'fa-pen-to-square text-emerald-600' : 'fa-pen-nib text-indigo-600' }}"></i>
                        <span>Record Today's Work Feedback</span>
                        <span class="text-[10px] font-semibold text-slate-500 lowercase">({{ $todayFeedback ? "edit day {$todayFeedback->day_number}" : "day {$calendarDay}" }})</span>
                    </h3>
                    <span class="text-[10px] {{ $todayFeedback ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800' }} font-bold px-2 py-0.5 rounded">
                        {{ $todayFeedback ? "Day {$todayFeedback->day_number} Logged" : (auth()->user()->isEngineer() ? 'Field Engineer' : 'Operations Manager') }}
                    </span>
                </div>

                <form action="{{ route('tickets.feedback', $ticket) }}" method="POST" enctype="multipart/form-data" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Action / Stage Taken Today *</label>
                        <select name="action_taken" required class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs">
                            <option value="Parts Arranged &amp; En Route" {{ ($todayFeedback?->action_taken == 'Parts Arranged & En Route') ? 'selected' : '' }}>Parts Arranged &amp; En Route</option>
                            <option value="On-Site Hardware Inspection" {{ ($todayFeedback?->action_taken == 'On-Site Hardware Inspection') ? 'selected' : '' }}>On-Site Hardware Inspection</option>
                            <option value="On-Site Diagnosis Performed" {{ ($todayFeedback?->action_taken == 'On-Site Diagnosis Performed') ? 'selected' : '' }}>On-Site Diagnosis Performed</option>
                            <option value="Defective Module Replaced" {{ ($todayFeedback?->action_taken == 'Defective Module Replaced') ? 'selected' : '' }}>Defective Module Replaced</option>
                            <option value="Machine Testing &amp; Diagnostic Runs" {{ ($todayFeedback?->action_taken == 'Machine Testing & Diagnostic Runs') ? 'selected' : '' }}>Machine Testing &amp; Diagnostic Runs</option>
                            <option value="Cleaning &amp; Sensor Servicing" {{ ($todayFeedback?->action_taken == 'Cleaning & Sensor Servicing') ? 'selected' : '' }}>Cleaning &amp; Sensor Servicing</option>
                            <option value="Branch Closed - Visit Rescheduled" {{ ($todayFeedback?->action_taken == 'Branch Closed - Visit Rescheduled') ? 'selected' : '' }}>Branch Closed - Visit Rescheduled</option>
                            <option value="Awaiting Spare Parts Approval" {{ ($todayFeedback?->action_taken == 'Awaiting Spare Parts Approval') ? 'selected' : '' }}>Awaiting Spare Parts Approval</option>
                            <option value="Awaiting Bank Approval / PO" {{ ($todayFeedback?->action_taken == 'Awaiting Bank Approval / PO') ? 'selected' : '' }}>Awaiting Bank Approval / PO</option>
                            <option value="Machine Hardware Repaired" {{ ($todayFeedback?->action_taken == 'Machine Hardware Repaired') ? 'selected' : '' }}>Machine Hardware Repaired</option>
                            <option value="Workshop Inbound" {{ ($todayFeedback?->action_taken == 'Workshop Inbound') ? 'selected' : '' }}>Workshop Inbound</option>
                            <option value="Workshop Outbound" {{ ($todayFeedback?->action_taken == 'Workshop Outbound') ? 'selected' : '' }}>Workshop Outbound</option>
                            <option value="Recommended for Central Workshop" {{ ($todayFeedback?->action_taken == 'Recommended for Central Workshop') ? 'selected' : '' }}>Recommended for Central Workshop</option>
                            <option value="Machine in Transit to Workshop" {{ ($todayFeedback?->action_taken == 'Machine in Transit to Workshop') ? 'selected' : '' }}>Machine in Transit to Central Workshop</option>
                            <option value="Workshop Bench Diagnostics" {{ ($todayFeedback?->action_taken == 'Workshop Bench Diagnostics') ? 'selected' : '' }}>Workshop Bench Diagnostics</option>
                            <option value="Workshop Bench Repair &amp; QA Testing" {{ ($todayFeedback?->action_taken == 'Workshop Bench Repair & QA Testing') ? 'selected' : '' }}>Workshop Bench Repair &amp; QA Testing</option>
                            <option value="Machine in Transit to Bank Branch" {{ ($todayFeedback?->action_taken == 'Machine in Transit to Bank Branch') ? 'selected' : '' }}>Machine in Transit to Bank Branch</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Work Progress Description *</label>
                        <textarea name="feedback_text" rows="3" required placeholder="Describe technical diagnostic results, actions performed, or current condition of ATM/POS..." class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500">{{ $todayFeedback?->feedback_text }}</textarea>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Spare Parts / Hardware Needed (Optional)</label>
                        <input type="text" name="parts_required" value="{{ $todayFeedback?->parts_required }}" placeholder="e.g. Dispenser belt, Card reader shutter..." class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">ETA Completion</label>
                            <input type="datetime-local" name="eta_completion" value="{{ $todayFeedback?->eta_completion ? $todayFeedback->eta_completion->format('Y-m-d\TH:i') : '' }}" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Photo Proof (Optional)</label>
                            <input type="file" name="photo_evidence" accept="image/*" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[11px] file:font-semibold file:bg-indigo-100 file:text-indigo-800">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-2.5 {{ $todayFeedback ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white font-bold rounded-lg shadow-sm transition flex items-center justify-center space-x-1.5 active:scale-95">
                            <i class="fa-solid {{ $todayFeedback ? 'fa-check' : 'fa-cloud-arrow-up' }}"></i>
                            <span>{{ $todayFeedback ? "Update Day {$todayFeedback->day_number} Daily Progress Log" : "Save & Submit Day {$calendarDay} Progress Log" }}</span>
                        </button>
                    </div>
                </form>
                @else
                <div class="text-center py-6 text-slate-500 space-y-2">
                    <div class="w-10 h-10 bg-slate-200 rounded-full flex items-center justify-center mx-auto text-slate-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </div>
                    <div class="font-bold text-slate-700">Engineer Feedback Restricted</div>
                    <p class="text-[11px] text-slate-500">
                        This complaint ticket is assigned to <strong>{{ $ticket->assignedEngineer?->name ?? 'another engineer' }}</strong>. Only the assigned engineer or operations management can log progress feedback.
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- SPARE PARTS REQUISITION & DISPATCH HISTORY -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-gears"></i>
                </span>
                <div>
                    <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Spare Parts Requisitions &amp; Dispatch History</h2>
                    <p class="text-[11px] text-slate-500">Component requests, stock availability checks, manager approvals, and dispatch tracking</p>
                </div>
            </div>
            <a href="{{ route('parts.requests.create', ['ticket_id' => $ticket->id]) }}" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>Request Parts</span>
            </a>
        </div>

        <div class="p-5">
            @if($ticket->partRequests->count() > 0)
                <div class="space-y-4">
                    @foreach($ticket->partRequests as $pr)
                        <div class="border rounded-xl border-slate-200 p-4 hover:border-sky-300 transition bg-slate-50/30 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                <div class="flex items-center space-x-2.5">
                                    <span class="font-mono font-bold text-sm text-sky-700">
                                        <a href="{{ route('parts.requests.show', $pr) }}" class="hover:underline">
                                            {{ $pr->request_number }}
                                        </a>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                        {{ $pr->status === 'dispatched' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : '' }}
                                        {{ $pr->status === 'approved' ? 'bg-green-100 text-green-800 border border-green-200' : '' }}
                                        {{ $pr->status === 'pending_approval' ? 'bg-purple-100 text-purple-800 border border-purple-200' : '' }}
                                        {{ $pr->status === 'stock_verified' ? 'bg-blue-100 text-blue-800 border border-blue-200' : '' }}
                                        {{ $pr->status === 'pending_stock_check' ? 'bg-amber-100 text-amber-800 border border-amber-200' : '' }}
                                        {{ $pr->status === 'rejected' ? 'bg-rose-100 text-rose-800 border border-rose-200' : '' }}">
                                        {{ $pr->status_label }}
                                    </span>
                                </div>
                                <div class="flex items-center space-x-3 text-xs text-slate-500">
                                    <span>Requested by <strong>{{ $pr->engineer->name ?? 'Engineer' }}</strong></span>
                                    <span>&bull;</span>
                                    <span>{{ $pr->created_at->format('d M Y, h:i A') }}</span>
                                    <a href="{{ route('parts.requests.show', $pr) }}" class="px-2.5 py-1 bg-white border border-slate-300 rounded text-slate-700 font-semibold hover:bg-slate-50 transition">
                                        View Request &rarr;
                                    </a>
                                </div>
                            </div>

                            <!-- Machine Details & Fault -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-white p-3 rounded-lg border border-slate-100">
                                <div>
                                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Machine Equipment</span>
                                    <span class="font-semibold text-slate-800">{{ $pr->machineModel->name ?? ($ticket->machine_model ?? 'Standard ATM') }}</span>
                                    <span class="font-mono text-slate-500 text-[11px]"> (S/N: {{ $pr->machine_serial_no ?? ($ticket->machine_serial_no ?? 'N/A') }})</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Diagnostic Fault</span>
                                    <span class="text-slate-700 italic">{{ Str::limit($pr->fault_description, 80) }}</span>
                                </div>
                            </div>

                            <!-- Parts Items Line Table -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-slate-100/70 text-slate-600 font-bold">
                                        <tr>
                                            <th class="py-1.5 px-3">Part Component</th>
                                            <th class="py-1.5 px-3 text-center text-amber-800 bg-amber-50">Advance Float</th>
                                            <th class="py-1.5 px-3 text-center">Warehouse Req.</th>
                                            <th class="py-1.5 px-3 text-center">Qty Approved</th>
                                            <th class="py-1.5 px-3 text-center">Qty Dispatched</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($pr->items as $item)
                                            <tr>
                                                <td class="py-2 px-3 font-medium text-slate-800">
                                                    {{ $item->part->name ?? '—' }} <span class="font-mono text-slate-400 text-[10px]">({{ $item->part->part_number ?? '—' }})</span>
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold text-amber-800">
                                                    @if($item->qty_from_envelope > 0)
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                            <i class="fa-solid fa-briefcase text-[9px]"></i> {{ $item->qty_from_envelope }}
                                                        </span>
                                                    @else
                                                        <span class="text-slate-300">—</span>
                                                    @endif
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold text-slate-700">{{ $item->qty_requested }}</td>
                                                <td class="py-2 px-3 text-center font-bold {{ $item->qty_approved !== null ? 'text-green-700' : 'text-slate-400' }}">
                                                    {{ $item->qty_approved !== null ? $item->qty_approved : 'Pending' }}
                                                </td>
                                                <td class="py-2 px-3 text-center font-bold {{ $item->qty_dispatched !== null ? 'text-emerald-700' : 'text-slate-400' }}">
                                                    {{ $item->qty_dispatched !== null ? $item->qty_dispatched : 'Pending' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Logistics Footer -->
                            @if($pr->status === 'dispatched')
                                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-2.5 text-xs text-emerald-950 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center space-x-2">
                                        <i class="fa-solid fa-truck-fast text-emerald-600"></i>
                                        <span>Dispatched via <strong>{{ $pr->dispatch_courier ?? 'Courier' }}</strong> &bull; Tracking: <strong class="font-mono">{{ $pr->dispatch_tracking_number ?? 'In Transit' }}</strong></span>
                                    </div>
                                    <div class="text-[11px] text-emerald-800">
                                        From: {{ $pr->dispatchLocation->name ?? 'Head Office' }} &bull; {{ $pr->dispatched_at ? $pr->dispatched_at->format('d M Y, h:i A') : '' }}
                                    </div>
                                </div>
                            @elseif($pr->status === 'approved')
                                <div class="bg-green-50 border border-green-200 rounded-lg p-2.5 text-xs text-green-900 flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <i class="fa-solid fa-circle-check text-green-600"></i>
                                        <span>Release approved by <strong>{{ $pr->approvedBy->name ?? 'Manager' }}</strong>. Ready for dispatch.</span>
                                    </div>
                                    @if(auth()->user()->isSuperior())
                                        <a href="{{ route('parts.requests.show', $pr) }}" class="px-2 py-1 bg-green-600 text-white rounded font-bold text-[11px] hover:bg-green-700">Dispatch Now</a>
                                    @endif
                                </div>
                            @elseif($pr->status === 'pending_stock_check' && auth()->user()->isSuperior())
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 text-xs text-amber-900 flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <i class="fa-solid fa-boxes-stacked text-amber-600"></i>
                                        <span>Awaiting Office Staff to verify inventory stock &amp; submit to manager.</span>
                                    </div>
                                    <a href="{{ route('parts.requests.show', $pr) }}" class="px-2 py-1 bg-amber-600 text-white rounded font-bold text-[11px] hover:bg-amber-700">Verify Stock</a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-6 text-slate-400">
                    <i class="fa-solid fa-gears text-3xl mb-2 text-slate-300"></i>
                    <p class="text-xs">No spare parts requisitions filed for this complaint yet.</p>
                    <a href="{{ route('parts.requests.create', ['ticket_id' => $ticket->id]) }}" class="inline-flex items-center space-x-1.5 mt-2 text-xs font-bold text-sky-600 hover:text-sky-700">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>Create Part Requisition</span>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- BOTTOM OF PAGE: WORKSHOP TRANSFER & COMPLETE AUDIT TRAIL -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 pt-2">

        <!-- WORKSHOP TRANSFER & RE-ALIGNMENT (6 cols for Admin / Ops) -->
        @if(!auth()->user()->isEngineer())
        <div class="lg:col-span-6 bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                    <i class="fa-solid fa-wrench text-purple-600"></i>
                    <span>Workshop Transfer &amp; Re-Alignment</span>
                </h2>
                @if($ticket->workshop_location)
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800">
                        In Workshop
                    </span>
                @endif
            </div>

            <p class="text-xs text-slate-600">
                If machine cannot be repaired on-site at branch, dispatch to a regional central workshop and re-assign ticket to a workshop engineer.
            </p>

            @if($ticket->isWorkshopFlow())
                <div class="bg-purple-50 p-4 rounded-xl border border-purple-200 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-purple-900 flex items-center gap-1.5">
                            <i class="fa-solid fa-warehouse text-purple-600"></i>
                            <span>{{ $ticket->workshop_location ?? 'Central Workshop' }}</span>
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-200 text-purple-900">
                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-slate-700 pt-2 border-t border-purple-100">
                        <div>Original Field Eng: <strong>{{ $ticket->originalFieldEngineer?->name ?? $ticket->engineer?->name }}</strong></div>
                        <div>Workshop Bench Tech: <strong>{{ $ticket->workshopEngineer?->name ?? 'Awaiting intake' }}</strong></div>
                        <div>Inbound Courier: <strong>{{ $ticket->workshop_dispatch_courier ?? 'Courier' }}</strong> ({{ $ticket->workshop_dispatch_tracking ?? 'In Transit' }})</div>
                        <div>Intake Recorded: <strong>{{ $ticket->workshop_received_at ? $ticket->workshop_received_at->format('d M, h:i A') : 'Pending' }}</strong></div>
                        @if($ticket->return_dispatched_at)
                            <div>Return Courier: <strong>{{ $ticket->return_courier }}</strong> ({{ $ticket->return_tracking_number }})</div>
                            <div>Bank Delivered: <strong>{{ $ticket->bank_received_at ? $ticket->bank_received_at->format('d M, h:i A') : 'In Return Transit' }}</strong></div>
                        @endif
                    </div>
                    @if(auth()->user()->isSuperior())
                    <div class="pt-2 flex items-center justify-end gap-2">
                        <a href="{{ route('workshop.index') }}" class="px-3 py-1.5 bg-purple-700 hover:bg-purple-800 text-white rounded-lg text-xs font-bold transition inline-flex items-center gap-1">
                            <i class="fa-solid fa-warehouse"></i>
                            <span>Open Workshop Hub</span>
                        </a>
                    </div>
                    @endif
                </div>
            @else
                <form action="{{ route('tickets.workshop', $ticket) }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Workshop Location *</label>
                        <select name="workshop_location" required class="w-full border border-slate-300 rounded-lg p-2 text-xs">
                            <option value="Lahore Central Workshop">Lahore Central Workshop</option>
                            <option value="Karachi Hub Workshop">Karachi Hub Workshop</option>
                            <option value="Islamabad Regional Workshop">Islamabad Regional Workshop</option>
                            <option value="Multan Service Center">Multan Service Center</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Dispatch Courier Service</label>
                            <input type="text" name="workshop_dispatch_courier" placeholder="e.g. TCS Cargo / In-Person" class="w-full border border-slate-300 rounded-lg p-2 text-xs bg-white">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Courier Tracking / Bilty #</label>
                            <input type="text" name="workshop_dispatch_tracking" placeholder="e.g. TCS-882910" class="w-full border border-slate-300 rounded-lg p-2 text-xs bg-white font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Dispatch Reason / Machine Fault Notes *</label>
                        <input type="text" name="notes" required placeholder="Tracking # or reason..." class="w-full border border-slate-300 rounded-lg p-2 text-xs">
                    </div>
                    <div class="text-[11px] text-amber-700 bg-amber-50 p-2 rounded-lg border border-amber-200">
                        <i class="fa-solid fa-shield-halved mr-1"></i>
                        <strong>Smart Transit Policy:</strong> Ticket will stay assigned to the field engineer until physically received at the workshop.
                    </div>
                    <div class="text-right">
                        <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg text-xs shadow-sm transition flex items-center justify-center space-x-1.5 ml-auto">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                            <span>Send to Workshop &amp; Start Transit</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
        @endif

        <!-- COMPLETE AUDIT TRAIL TIMELINE (6 cols for Admin, 12 cols for Engineer) -->
        <div class="{{ auth()->user()->isEngineer() ? 'lg:col-span-12' : 'lg:col-span-6' }} bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                    <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                        <i class="fa-solid fa-clock-rotate-left text-slate-500"></i>
                        <span>Complete Audit Trail</span>
                    </h2>
                    <span class="text-[10px] text-slate-400 font-mono">{{ $ticket->logs->count() }} Events</span>
                </div>
                <div class="space-y-2.5 text-xs max-h-80 overflow-y-auto pr-1">
                    @forelse($ticket->logs as $log)
                        <div class="flex items-start space-x-2 text-slate-600 border-b border-slate-50 pb-2">
                            <span class="text-[10px] text-slate-400 font-mono whitespace-nowrap mt-0.5">
                                {{ $log->created_at->format('d M, h:i A') }}
                            </span>
                            <span class="font-bold text-slate-700 uppercase text-[9px] bg-slate-100 px-1.5 py-0.5 rounded">
                                {{ $log->action }}
                            </span>
                            <span class="flex-1 text-[11px]">{{ $log->notes }}</span>
                        </div>
                    @empty
                        <div class="text-slate-400 text-xs py-4 text-center">No activity logs recorded.</div>
                    @endforelse
                </div>
            </div>
            <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-400 flex items-center justify-between">
                <span>Immutable audit log</span>
                <span class="font-mono text-[10px]">Ticket ID: #{{ $ticket->ticket_no }}</span>
            </div>
        </div>

    </div>

</div>

@include('tickets.partials.workshop-modal')
@include('tickets.partials.workshop-lifecycle-modals')
@include('tickets.partials.mark-complete-modal')
@include('tickets.partials.claim-expense-modal')
@include('tickets.partials.approval-modals')
@if(!auth()->user()->isEngineer())
    @include('tickets.partials.send-resolution-email-modal')
@endif
@endsection

@push('scripts')
{{-- html2canvas: renders email HTML to PNG in the browser --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
/**
 * ONE-CLICK LOCAL DISPATCH:
 * Renders complaint email card as an image with html2canvas,
 * copies PNG directly to clipboard via ClipboardItem API,
 * marks ticket as WhatsApp Dispatched in DB (unlocking Step 3),
 * and launches local WhatsApp desktop app / web.
 */
async function copyEmailAsImageAndOpenWhatsApp() {
    const btn        = document.getElementById('wa-copy-btn');
    const label      = document.getElementById('wa-copy-label');
    const statusBox  = document.getElementById('wa-status-box');
    const copyUrl    = document.getElementById('wa-copy-route-url')?.dataset?.url;
    const csrf       = document.querySelector('meta[name="csrf-token-wa"]')?.content
                    || document.querySelector('meta[name="csrf-token"]')?.content;

    if (!copyUrl) {
        alert('Copy dispatch route not found.');
        return;
    }

    btn.disabled = true;
    label.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Generating Image Card...';

    function showStatus(success, msg) {
        statusBox.classList.remove('hidden', 'bg-emerald-50', 'border-emerald-300', 'text-emerald-950',
                                               'bg-rose-50',    'border-rose-300',    'text-rose-950');
        if (success) {
            statusBox.classList.add('bg-emerald-50', 'border', 'border-emerald-300', 'text-emerald-950');
        } else {
            statusBox.classList.add('bg-rose-50', 'border', 'border-rose-300', 'text-rose-950');
        }
        statusBox.innerHTML = msg;
    }

    try {
        const inner = document.getElementById('email-screenshot-inner');
        if (!inner) {
            throw new Error('Complaint email card element not found.');
        }

        // 1. Capture card as crisp canvas
        const canvas = await html2canvas(inner, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            allowTaint: true,
            logging: false,
            imageTimeout: 3000,
        });

        // 2. Convert to PNG blob
        const blob = await new Promise((resolve, reject) => {
            canvas.toBlob(b => b ? resolve(b) : reject(new Error('Canvas conversion failed')), 'image/png');
        });

        // 3. Write directly to clipboard as PNG Image
        let imageCopied = false;
        if (navigator.clipboard && window.ClipboardItem) {
            try {
                const item = new ClipboardItem({ 'image/png': blob });
                await navigator.clipboard.write([item]);
                imageCopied = true;
            } catch (clipErr) {
                console.warn('ClipboardItem write error:', clipErr);
            }
        }

        // If ClipboardItem not supported, fallback to copying text
        if (!imageCopied && navigator.clipboard) {
            const previewEl = document.getElementById('wa-msg-preview');
            if (previewEl) {
                await navigator.clipboard.writeText(previewEl.innerText || previewEl.textContent);
            }
        }

        // 4. Mark ticket as WhatsApp Dispatched on backend to unlock Step 3
        label.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Unlocking Step 3...';
        const res = await fetch(copyUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({}),
        });

        const data = await res.json();

        if (res.ok && data.success) {
            btn.disabled = false;
            btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
            btn.classList.add('bg-emerald-800');
            label.innerHTML = '✅ Copied! Paste (Ctrl+V) in WhatsApp';

            showStatus(true,
                '<div class="space-y-1.5">' +
                    '<div class="font-bold flex items-center space-x-1.5 text-emerald-800">' +
                        '<i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>' +
                        '<span>Complaint Email Copied to Clipboard as Image!</span>' +
                    '</div>' +
                    '<div class="text-xs text-slate-700">' +
                        'Switch to WhatsApp and press <kbd class="px-1.5 py-0.5 bg-white border border-emerald-300 rounded font-mono font-bold text-[10px]">Ctrl + V</kbd> to paste the image card directly.' +
                    '</div>' +
                    '<div class="text-[11px] text-emerald-700 font-bold">' +
                        '🎉 Step 3 (Send Bank Assignment Email) is now UNLOCKED! Activating...' +
                    '</div>' +
                '</div>'
            );

            // 5. Open local WhatsApp application protocol
            try {
                window.location.href = "whatsapp://";
            } catch(e) {}

            // 6. Reload page in 1.2s to activate Step 3 form smoothly
            setTimeout(function() {
                location.reload();
            }, 1200);

        } else {
            throw new Error(data.error || 'Server could not record dispatch.');
        }

    } catch (err) {
        btn.disabled = false;
        label.innerHTML = '📋 Retry Copy Email as Image';
        showStatus(false, '<i class="fa-solid fa-triangle-exclamation mr-1"></i> <strong>Error:</strong> ' + err.message);
    }
}

function copyWhatsAppMessageText() {
    const textEl = document.getElementById('wa-msg-preview');
    const label  = document.getElementById('wa-copy-text-label');
    if (!textEl) return;
    
    const text = textEl.innerText || textEl.textContent;
    navigator.clipboard.writeText(text).then(function() {
        const oldText = label.innerText;
        label.innerText = 'Copied!';
        setTimeout(() => { label.innerText = oldText; }, 2000);
    }).catch(function() {
        alert('Could not copy text.');
    });
}

function copyCleanEmailText() {
    const textEl = document.getElementById('clean-email-raw-text');
    const label  = document.getElementById('wa-copy-email-text-label');
    if (!textEl) return;
    
    const text = textEl.value || textEl.innerText;
    navigator.clipboard.writeText(text).then(function() {
        const oldText = label.innerText;
        label.innerText = 'Copied Email!';
        setTimeout(() => { label.innerText = oldText; }, 2000);
    }).catch(function() {
        alert('Could not copy email text.');
    });
}

/**
 * ONE-CLICK: Captures email as screenshot, sends image + message to
 * WhatsApp group via AJAX → Laravel → wwebjs-service. Fully automated.
 */
function sendToWhatsAppGroup() {
    const btn        = document.getElementById('wa-send-btn');
    const label      = document.getElementById('wa-send-label');
    const statusBox  = document.getElementById('wa-status-box');
    const routeEl    = document.getElementById('wa-route-url');
    const hasEmailEl = document.getElementById('wa-has-email');
    const csrf       = document.querySelector('meta[name="csrf-token-wa"]')?.content
                    || document.querySelector('meta[name="csrf-token"]')?.content;

    const url      = routeEl?.dataset.url;
    const hasEmail = hasEmailEl?.dataset.value === '1';

    if (!url) { alert('Route not found.'); return; }

    // — UI: loading state —
    btn.disabled = true;
    label.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Sending...';

    function showStatus(success, msg) {
        statusBox.classList.remove('hidden', 'bg-emerald-50', 'border-emerald-300', 'text-emerald-800',
                                               'bg-rose-50',    'border-rose-300',    'text-rose-800');
        if (success) {
            statusBox.classList.add('bg-emerald-50', 'border', 'border-emerald-300', 'text-emerald-800');
        } else {
            statusBox.classList.add('bg-rose-50', 'border', 'border-rose-300', 'text-rose-800');
        }
        statusBox.innerHTML = msg;
    }

    function postToServer(imageBase64) {
        label.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Dispatching to group...';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                image_base64: imageBase64 || null,
            }),
        })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function(res) {
            btn.disabled = false;
            if (res.ok && res.data.success) {
                label.innerHTML = '✅ Sent! Reload to see status';
                btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                btn.classList.add('bg-emerald-800');
                showStatus(true,
                    '<i class="fa-solid fa-circle-check mr-1"></i>' + res.data.msg +
                    ' <span class="ml-2 underline cursor-pointer" onclick="location.reload()">Refresh page</span>');
            } else {
                label.innerHTML = '🚀 Retry Send to WhatsApp Group';
                showStatus(false,
                    '<i class="fa-solid fa-triangle-exclamation mr-1"></i>' +
                    '<strong>Error:</strong> ' + (res.data.error || 'Unknown error.') +
                    '<br><span class="text-xs opacity-75">Make sure wwebjs-service is running (node wwebjs-service/index.js) and linked via QR.</span>');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            label.innerHTML = '🚀 Retry Send to WhatsApp Group';
            showStatus(false, '<i class="fa-solid fa-triangle-exclamation mr-1"></i> Network error: ' + err.message);
        });
    }

    // — Capture screenshot if email exists, else send text only —
    if (hasEmail) {
        const inner = document.getElementById('email-screenshot-inner');
        if (!inner) { postToServer(null); return; }

        label.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Capturing email screenshot...';

        html2canvas(inner, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            allowTaint: true,
            logging: false,
            imageTimeout: 3000,
        }).then(function(canvas) {
            const dataUrl = canvas.toDataURL('image/png');
            postToServer(dataUrl);
        }).catch(function(err) {
            console.warn('html2canvas failed, sending text only:', err);
            postToServer(null);
        });
    } else {
        // No email linked — just send the text message
        postToServer(null);
    }
}

/**
 * Engineer Modal Controls
 */
function openEngineerWorkshopModal() {
    openWorkshopModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}', '{{ $ticket->workshop_location ?? '' }}');
}

function closeEngineerWorkshopModal() {
    closeWorkshopModal();
}

function openEngineerCompleteModal() {
    openCompleteModal({{ $ticket->id }}, '{{ $ticket->ticket_no }}', '{{ addslashes($ticket->bank_name) }}');
}

function closeEngineerCompleteModal() {
    closeCompleteModal();
}

// Close modals when clicking on background backdrop
window.addEventListener('click', function(e) {
    const wsModal = document.getElementById('engineerWorkshopModal');
    const compModal = document.getElementById('engineerCompleteModal');
    if (e.target === wsModal) closeEngineerWorkshopModal();
    if (e.target === compModal) closeEngineerCompleteModal();
});
</script>
@endpush

