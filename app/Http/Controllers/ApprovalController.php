<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    /**
     * Display the Pending Approvals Command Hub for Operations Managers.
     */
    public function index(Request $request)
    {
        if (Auth::user()->isEngineer()) {
            abort(403, 'The Pending Approvals Hub is reserved for Operations Administrators.');
        }

        $query = Ticket::where('status', 'awaiting_approval')
            ->with(['engineer', 'approvalRequestedBy', 'feedbacks.engineer'])
            ->latest('approval_requested_at');

        if ($request->filled('bank')) {
            $query->where('bank_name', 'like', "%{$request->bank}%");
        }

        if ($request->filled('location')) {
            $query->where('branch_location', 'like', "%{$request->location}%");
        }

        if ($request->filled('engineer_id')) {
            $query->where('assigned_engineer_id', $request->engineer_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('branch_name', 'like', "%{$search}%")
                  ->orWhere('machine_serial_no', 'like', "%{$search}%")
                  ->orWhere('approval_source', 'like', "%{$search}%")
                  ->orWhere('approval_request_reason', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(20)->withQueryString();

        $stats = [
            'total_pending'     => Ticket::where('status', 'awaiting_approval')->count(),
            'banks_count'       => Ticket::where('status', 'awaiting_approval')->distinct('bank_name')->count('bank_name'),
            'paused_over_24h'   => Ticket::where('status', 'awaiting_approval')
                ->where('sla_paused_at', '<=', Carbon::now()->subDay())
                ->count(),
        ];

        return view('tickets.approvals', compact('tickets', 'stats'));
    }

    /**
     * Engineer or Admin pauses ticket SLA clock awaiting bank/customer approval.
     */
    public function requestApproval(Request $request, Ticket $ticket)
    {
        $user = Auth::user();

        // Check permission: assigned engineer or manager
        if ($user->isEngineer() && !$ticket->hasEngineer($user)) {
            abort(403, 'You are not assigned to this ticket.');
        }

        if ($ticket->status === 'awaiting_approval') {
            return back()->with('info', "Ticket #{$ticket->ticket_no} is already awaiting approval.");
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return back()->with('error', "Cannot pause a resolved or closed ticket.");
        }

        $request->validate([
            'approval_source' => 'nullable|string|max:150',
            'reason'          => 'nullable|string|max:1000',
        ]);

        $now = Carbon::now();
        $source = $request->input('approval_source') ?: 'Customer / Bank Sign-off';
        $reason = $request->input('reason') ?: 'Work put on hold awaiting customer / bank authorization.';

        $ticket->update([
            'status'                   => 'awaiting_approval',
            'approval_requested_at'    => $now,
            'approval_requested_by_id' => $user->id,
            'approval_source'          => $source,
            'approval_request_reason'  => $reason,
            'sla_paused_at'            => $now,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'approval_requested',
            'notes'     => "SLA Clock Paused by {$user->name}. Ticket marked as 'Waiting for Approval'. Reason: {$reason}",
        ]);

        return back()->with('success', "Ticket #{$ticket->ticket_no} is now marked as Waiting for Approval. SLA timer has been paused!");
    }

    /**
     * Admin records that approval has arrived, shifts SLA deadline forward, and resumes engineer TAT.
     */
    public function grantApproval(Request $request, Ticket $ticket)
    {
        $user = Auth::user();

        if ($user->isEngineer()) {
            abort(403, 'Only Operations Administrators can mark approval arrived.');
        }

        if ($ticket->status !== 'awaiting_approval') {
            return back()->with('error', "Ticket #{$ticket->ticket_no} is not currently awaiting approval.");
        }

        $request->validate([
            'remarks' => 'required|string|min:3|max:1000',
        ]);

        $now = Carbon::now();
        $pausedAt = $ticket->sla_paused_at ?? $ticket->approval_requested_at ?? $now;
        $pausedSeconds = max(0, $pausedAt->diffInSeconds($now));

        $pausedHumans = $pausedAt->diffForHumans($now, [
            'parts' => 2,
            'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
        ]);

        // Shift SLA deadline forward by exactly the duration ticket was paused
        $oldDeadline = $ticket->sla_deadline ? $ticket->sla_deadline->copy() : null;
        $newDeadline = $ticket->sla_deadline ? $ticket->sla_deadline->copy()->addSeconds($pausedSeconds) : null;

        $ticket->update([
            'status'                   => 'in_progress',
            'sla_paused_at'            => null,
            'sla_deadline'             => $newDeadline,
            'approval_paused_seconds'  => ($ticket->approval_paused_seconds ?? 0) + $pausedSeconds,
            'approval_arrived_at'      => $now,
            'approval_arrived_by_id'   => $user->id,
            'approval_arrived_remarks' => $request->remarks,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'approval_granted',
            'notes'     => "Approval recorded by {$user->name}. SLA clock resumed after {$pausedHumans} pause. " . ($newDeadline ? "SLA deadline shifted to {$newDeadline->format('d M Y, h:i A')}." : "") . " Remarks: {$request->remarks}",
        ]);

        return back()->with('success', "Approval confirmed for Ticket #{$ticket->ticket_no}! SLA clock resumed. Engineer's TAT adjusted by {$pausedHumans}.");
    }
}
