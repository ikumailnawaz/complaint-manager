<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkshopController extends Controller
{
    /**
     * Central Workshop Hub
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->isSuperior(), 403, 'Central Workshop Hub is restricted to Operations Management.');

        $tab = $request->query('tab', 'inbound'); // inbound, active, repaired, return_transit, completed
        $search = $request->query('search');
        $locationFilter = $request->query('location');

        $query = Ticket::with(['engineer', 'originalFieldEngineer', 'workshopEngineer', 'workshopReceivedBy'])
            ->where(function ($q) {
                $q->whereNotNull('workshop_dispatched_at')
                  ->orWhereNotNull('workshop_location')
                  ->orWhereIn('status', ['awaiting_workshop', 'in_workshop_repair', 'workshop_repaired', 'return_transit']);
            });

        if ($search) {
            $s = "%{$search}%";
            $query->where(function ($q) use ($s) {
                $q->where('ticket_no', 'like', $s)
                  ->orWhere('bank_name', 'like', $s)
                  ->orWhere('branch_name', 'like', $s)
                  ->orWhere('machine_model', 'like', $s)
                  ->orWhere('machine_serial_no', 'like', $s)
                  ->orWhere('workshop_dispatch_tracking', 'like', $s)
                  ->orWhere('return_tracking_number', 'like', $s);
            });
        }

        if ($locationFilter) {
            $query->where('workshop_location', $locationFilter);
        }

        // Tab Filtering
        $counts = [
            'inbound'        => (clone $query)->where('status', 'awaiting_workshop')->count(),
            'active'         => (clone $query)->where('status', 'in_workshop_repair')->count(),
            'repaired'       => (clone $query)->where(function($q) {
                                    $q->where('status', 'workshop_repaired')
                                      ->orWhere(function($sq) {
                                          $sq->where('status', 'resolved')
                                             ->whereNotNull('workshop_received_at')
                                             ->whereNull('bank_received_at');
                                      });
                                })->count(),
            'return_transit' => (clone $query)->where('status', 'return_transit')->count(),
            'completed'      => (clone $query)->where('status', 'closed')->whereNotNull('workshop_dispatched_at')->count(),
        ];

        switch ($tab) {
            case 'active':
                $query->where('status', 'in_workshop_repair');
                break;
            case 'repaired':
                $query->where(function($q) {
                    $q->where('status', 'workshop_repaired')
                      ->orWhere(function($sq) {
                          $sq->where('status', 'resolved')
                             ->whereNotNull('workshop_received_at')
                             ->whereNull('bank_received_at');
                      });
                });
                break;
            case 'return_transit':
                $query->where('status', 'return_transit');
                break;
            case 'completed':
                $query->where('status', 'closed')->whereNotNull('workshop_dispatched_at');
                break;
            case 'inbound':
            default:
                $query->where('status', 'awaiting_workshop');
                $tab = 'inbound';
                break;
        }

        $tickets = $query->orderByDesc('updated_at')->paginate(15)->withQueryString();

        $engineers = User::where('role', 'engineer')->orderBy('name')->get();
        $incharges = User::whereIn('role', ['workshop_incharge', 'office_staff', 'admin'])
            ->orderByRaw("CASE WHEN role = 'workshop_incharge' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $workshopLocations = [
            'Lahore Central Workshop',
            'Karachi Hub Workshop',
            'Islamabad Regional Workshop',
            'Multan Service Center',
        ];

        return view('workshop.index', compact('tickets', 'tab', 'counts', 'engineers', 'incharges', 'workshopLocations'));
    }

    /**
     * Mark Machine Received at Workshop Facility & Handover to Workshop Incharge (Ikram)
     */
    public function receive(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        abort_unless(Auth::user()->isSuperior(), 403, 'Only management can process physical workshop intake.');

        $request->validate([
            'workshop_incharge_id'    => 'nullable|exists:users,id',
            'workshop_engineer_id'    => 'nullable|exists:users,id',
            'workshop_location'       => 'nullable|string|max:150',
            'workshop_intake_remarks' => 'required|string|max:500',
        ], [
            'workshop_intake_remarks.required' => 'Physical condition & intake inspection notes are required.',
        ]);

        // Find selected incharge, or default to user with role workshop_incharge (e.g. Ikram), or fallback
        $incharge = null;
        if ($request->filled('workshop_incharge_id')) {
            $incharge = User::find($request->workshop_incharge_id);
        }
        if (!$incharge) {
            $incharge = User::where('role', 'workshop_incharge')->first();
        }
        if (!$incharge && $request->filled('workshop_engineer_id')) {
            $incharge = User::find($request->workshop_engineer_id);
        }
        if (!$incharge) {
            $incharge = Auth::user();
        }

        $location = $request->workshop_location ?: ($ticket->workshop_location ?: 'Lahore Central Workshop');
        $dispatchedAt = $ticket->workshop_dispatched_at ?? now();
        $receivedAt = now();
        $transitDuration = $ticket->inbound_transit_duration_text;

        $ticket->update([
            'status'                  => 'in_workshop_repair',
            'workshop_location'       => $location,
            'workshop_received_at'    => $receivedAt,
            'workshop_received_by_id' => Auth::id(),
            'workshop_incharge_id'    => $incharge->id,
            // Ticket temporarily held by Workshop Incharge until realigned to bench engineer
            'assigned_engineer_id'    => $incharge->id,
            'workshop_intake_remarks' => $request->workshop_intake_remarks,
        ]);

        // Notification & WhatsApp alert to Workshop Incharge (Ikram)
        \App\Services\NotificationService::notifyWorkshopReceived($ticket, $incharge);
        try {
            $whatsapp->sendWorkshopAlert($ticket, $location, $incharge);
        } catch (\Throwable $e) {}

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'workshop_received',
            'notes'     => "Machine physically received at {$location} by " . Auth::user()->name . ". Handover complete: Assigned to Workshop Incharge {$incharge->name}. (Inbound Cargo Transit Time: {$transitDuration}). Inspection Remarks: {$request->workshop_intake_remarks}",
        ]);

        return back()->with('success', "Machine received at workshop! Ticket handed over to Workshop Incharge {$incharge->name}.");
    }

    /**
     * Workshop Incharge (Ikram) Re-aligns Machine to Bench Engineer with TAT
     */
    public function realign(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        abort_unless(Auth::user()->isSuperior() || Auth::user()->isWorkshopIncharge() || Auth::id() === $ticket->workshop_incharge_id, 403, 'Unauthorized to realign workshop machines.');

        $request->validate([
            'workshop_engineer_id'  => 'required|exists:users,id',
            'workshop_tat_hours'    => 'required|integer|min:1|max:720',
            'workshop_instructions' => 'nullable|string|max:1000',
        ], [
            'workshop_engineer_id.required' => 'Please select the Bench Engineer.',
            'workshop_tat_hours.required'   => 'Please specify Target Turnaround Time (TAT) in hours.',
        ]);

        $engineer = User::findOrFail($request->workshop_engineer_id);
        $tatHours = (int) $request->workshop_tat_hours;
        $tatDeadline = now()->addHours($tatHours);

        $ticket->update([
            'workshop_engineer_id'   => $engineer->id,
            'assigned_engineer_id'   => $engineer->id,
            'workshop_tat_hours'     => $tatHours,
            'workshop_tat_deadline'  => $tatDeadline,
            'workshop_instructions'  => $request->workshop_instructions,
            'workshop_realigned_at'  => now(),
        ]);

        // Align engineer to active engineers so it shows in their mobile app
        if (!$ticket->activeTicketEngineers()->where('engineer_id', $engineer->id)->exists()) {
            $ticket->ticketEngineers()->create([
                'engineer_id' => $engineer->id,
                'role'        => 'support',
                'assigned_at' => now(),
            ]);
        }

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'workshop_realigned',
            'notes'     => "Realigned to Bench Engineer {$engineer->name} with TAT of {$tatHours} Hours (Deadline: {$tatDeadline->format('d M Y, h:i A')}). Instructions: {$request->workshop_instructions}",
        ]);

        // Push notification & WhatsApp to Bench Engineer
        \App\Services\NotificationService::notifyWorkshopRealigned($ticket, $engineer, $tatHours, $request->workshop_instructions);
        try {
            $whatsapp->sendWorkshopAlert($ticket, $ticket->workshop_location ?? 'Central Workshop', $engineer);
        } catch (\Throwable $e) {}

        return back()->with('success', "Ticket assigned to Bench Engineer {$engineer->name} with TAT of {$tatHours} hours!");
    }

    /**
     * Workshop Engineer Marks Bench Repair Complete
     */
    public function resolve(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        $user = Auth::user();

        // Allowed for assigned workshop engineer, workshop incharge, OR superior
        if ($user->isEngineer() && !$ticket->hasEngineer($user) && $ticket->workshop_incharge_id !== $user->id) {
            abort(403, 'Only the assigned workshop technician or incharge can mark bench repair complete.');
        }

        $serial = trim((string)($ticket->machine_serial_no ?? ''));
        if ($serial === '' || strtoupper($serial) === 'N/A' || $serial === '—' || $serial === '-') {
            return back()->with('error', 'Machine Serial Number / TID is mandatory before marking bench repair complete. Please update machine specifications first.');
        }

        $request->validate([
            'workshop_repair_summary' => 'required|string|max:1000',
        ], [
            'workshop_repair_summary.required' => 'Detailed bench repair summary and test verification notes are required.',
        ]);

        $repairedAt = now();
        $benchDuration = $ticket->workshop_repair_duration_text;

        $ticket->update([
            'status'                  => 'workshop_repaired',
            'workshop_repaired_at'    => $repairedAt,
            'workshop_repair_summary' => $request->workshop_repair_summary,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'action'    => 'workshop_repaired',
            'notes'     => "Bench repair completed by Workshop Technician {$user->name} (Bench Repair Duration: {$benchDuration}). Machine QA tested OK. Summary: {$request->workshop_repair_summary}",
        ]);

        \App\Services\NotificationService::notifyWorkshopRepaired($ticket);

        return back()->with('success', "Bench repair marked complete! Machine is ready for return delivery to the bank.");
    }

    /**
     * Admin Dispatches Repaired Machine Back to Bank Branch
     */
    public function returnDispatch(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        abort_unless(Auth::user()->isSuperior(), 403, 'Only management can dispatch return cargo.');

        $request->validate([
            'return_courier'         => 'required|string|max:100',
            'return_tracking_number' => 'required|string|max:100',
            'return_dispatch_notes'  => 'nullable|string|max:500',
        ], [
            'return_courier.required'         => 'Return courier / cargo carrier service is required.',
            'return_tracking_number.required' => 'Courier tracking number is required for bank return.',
        ]);

        $ticket->update([
            'status'                  => 'return_transit',
            'return_courier'          => $request->return_courier,
            'return_tracking_number'  => $request->return_tracking_number,
            'return_dispatched_at'    => now(),
            'return_dispatched_by_id' => Auth::id(),
            'return_dispatch_notes'   => $request->return_dispatch_notes,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'workshop_return_dispatched',
            'notes'     => "Repaired machine dispatched back to {$ticket->bank_name} ({$ticket->branch_name}) via {$request->return_courier} (Tracking: {$request->return_tracking_number}). " . ($request->return_dispatch_notes ? "Notes: {$request->return_dispatch_notes}" : ''),
        ]);

        \App\Services\NotificationService::notifyWorkshopReturnDispatched($ticket);

        return back()->with('success', "Repaired machine dispatched back to bank branch via {$request->return_courier} (Tracking #{$request->return_tracking_number}).");
    }

    /**
     * Bank Branch Confirms Receipt & Admin Closes Ticket
     */
    public function confirmBankDelivery(Request $request, Ticket $ticket)
    {
        abort_unless(Auth::user()->isSuperior(), 403, 'Only management can close tickets.');

        $now = now();
        $ticket->update([
            'status'                        => 'closed',
            'bank_received_at'              => $now,
            'bank_received_confirmed_by_id' => Auth::id(),
            'resolved_at'                   => $ticket->resolved_at ?? $ticket->workshop_repaired_at ?? $now,
            'closed_at'                     => $now,
        ]);

        $fieldDuration = $ticket->field_duration_text;
        $inboundTransit = $ticket->inbound_transit_duration_text;
        $workshopDuration = $ticket->workshop_repair_duration_text;
        $returnTransit = $ticket->return_transit_duration_text;
        $totalResolution = $ticket->total_resolution_duration_text;

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'workshop_completed_closed',
            'notes'     => "Bank verified delivery. Machine operational. Ticket closed. Turnaround Breakdown -> On-Site Field: {$fieldDuration} | Inbound Cargo: {$inboundTransit} | Workshop Bench: {$workshopDuration} | Return Cargo: {$returnTransit} | Total SLA Resolution: {$totalResolution}.",
        ]);

        \App\Services\NotificationService::notifyTicketClosed($ticket);

        return back()->with('success', "Bank receipt confirmed! Ticket #{$ticket->ticket_no} successfully closed with full turnaround metrics logged.");
    }
}
