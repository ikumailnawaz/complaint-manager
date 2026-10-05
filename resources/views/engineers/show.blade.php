@extends('layouts.app')

@section('title', 'Engineer Report - ' . $engineer->name . ' - Bank Complaint Manager')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center space-x-3">
            <a href="{{ route('engineers.index') }}" class="p-2 text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition" title="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $engineer->name }}</h1>
                <div class="text-xs text-slate-500">
                    Field Technician Performance Dossier &bull; Base City: <strong>{{ $engineer->base_city }}</strong> &bull; Spec: <strong>{{ $engineer->specialization }}</strong>
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <form action="{{ route('engineers.toggle-availability', $engineer) }}" method="POST">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase transition {{ $engineer->is_available ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-rose-100 text-rose-800 hover:bg-rose-200' }}">
                    <i class="fa-solid fa-circle text-[8px] mr-1"></i> {{ $engineer->is_available ? 'Status: Available' : 'Status: On Leave' }}
                </button>
            </form>
        </div>
    </div>

    <!-- Performance KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-slate-500 uppercase font-semibold">SLA Compliance Rate</span>
            <div class="text-2xl font-extrabold {{ $metrics['sla_compliance'] >= 80 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">
                {{ $metrics['sla_compliance'] }}%
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Turnaround Time adherence</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-slate-500 uppercase font-semibold">Resolved Complaints</span>
            <div class="text-2xl font-bold text-slate-800 mt-1">
                {{ $metrics['resolved'] }} / {{ $metrics['total_assigned'] }}
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">{{ $metrics['active'] }} currently active</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-slate-500 uppercase font-semibold">Escalation Count</span>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ $metrics['escalated'] }}</div>
            <div class="text-[10px] text-rose-500 mt-0.5">SLA breaches reported</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-slate-500 uppercase font-semibold">Tour Expenses Reimbursed</span>
            <div class="text-2xl font-bold text-indigo-600 mt-1">
                PKR {{ number_format($metrics['total_paid'], 0) }}
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Claimed: PKR {{ number_format($metrics['total_claimed'], 0) }}</div>
        </div>
    </div>

    <!-- Assigned Tickets History -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="font-bold text-sm text-slate-800">
                <i class="fa-solid fa-ticket text-sky-600 mr-1.5"></i> Assigned Complaints History
            </div>
            <span class="text-xs text-slate-500">{{ $tickets->total() }} total tickets</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                        <th class="p-3">Ticket #</th>
                        <th class="p-3">Bank & Location</th>
                        <th class="p-3">Machine</th>
                        <th class="p-3">Urgency</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tickets as $t)
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 font-bold text-slate-900">
                                <a href="{{ route('tickets.show', $t) }}" class="text-sky-600 hover:underline">
                                    {{ $t->ticket_no }}
                                </a>
                            </td>
                            <td class="p-3">
                                <div class="font-bold text-slate-800">{{ $t->bank_name }}</div>
                                <div class="text-slate-500 text-[11px]">{{ $t->branch_location }}</div>
                            </td>
                            <td class="p-3 text-slate-700">
                                {{ $t->machine_type }} ({{ $t->machine_serial_no ?? 'N/A' }})
                            </td>
                            <td class="p-3 uppercase font-bold text-[10px]">
                                <span class="{{ $t->urgency === 'high' ? 'text-rose-600' : 'text-amber-600' }}">
                                    {{ $t->urgency }}
                                </span>
                            </td>
                            <td class="p-3 uppercase font-bold text-[10px]">
                                {{ str_replace('_', ' ', $t->status) }}
                            </td>
                            <td class="p-3 text-right">
                                <a href="{{ route('tickets.show', $t) }}" class="text-sky-600 font-semibold hover:underline">
                                    View &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400">No tickets found for this engineer.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())
            <div class="p-3 border-t border-slate-200 bg-slate-50">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
