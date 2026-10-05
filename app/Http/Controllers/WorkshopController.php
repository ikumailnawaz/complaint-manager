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
        $workshopLocations = [
            'Lahore Central Workshop',
            'Karachi Hub Workshop',
            'Islamabad Regional Workshop',
            'Multan Service Center',
        ];

        return view('workshop.index', compact('tickets', 'tab', 'counts', 'engineers', 'workshopLocations'));
    }

    /**
     * Mark Machine Received at Workshop Facility & Handover to Workshop Engineer
     */
    public function receive(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        abort_unless(Auth::user()->isSuperior(), 403, 'Only management can process physical workshop intake.');

        $request->validate([
            'workshop_engineer_id'   => 'required|exists:users,id',
            'workshop_intake_remarks' => 'required|string|max:500',
        ], [
            'workshop_engineer_id.required'   => 'Please assign the Workshop Engineer who is physically taking charge of this machine.',
            'workshop_intake_remarks.required' => 'Physical condition & intake inspection notes are required.',
        ]);

        $workshopEngineer = User::findOrFail($request->workshop_engineer_id);

        // Calculate inbound transit duration
        $dispatchedAt = $ticket->workshop_dispatched_at ?? now();
        $receivedAt = now();
        $transitDuration = $ticket->inbound_transit_duration_text;

        $ticket->update([
            'status'                   => 'in_workshop_repair',
            'workshop_received_at'     => $receivedAt,
            'workshop_received_by_id'  => Auth::id(),
            'workshop_engineer_id'     => $workshopEngineer->id,
            // Formal Handover: Ticket now assigned to the Workshop Engineer!
            'assigned_engineer_id'     => $workshopEngineer->id,
            'workshop_intake_remarks'  => $request->workshop_intake_remarks,
        ]);

        // WhatsApp notification to the newly assigned workshop engineer
        $whatsapp->sendWorkshopAlert($ticket, $ticket->workshop_location ?? 'Central Workshop', $workshopEngineer);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'action'    => 'workshop_received',
            'notes'     => "Machine physically received at {$ticket->workshop_location} by " . Auth::user()->name . ". Handover complete: Assigned to Workshop Engineer {$workshopEngineer->name}. (Inbound Cargo Transit Time: {$transitDuration}). Inspection Remarks: {$request->workshop_intake_remarks}",
        ]);

        return back()->with('success', "Machine received at workshop! Ticket transferred to Workshop Engineer {$workshopEngineer->name}.");
    }

    /**
     * Workshop Engineer Marks Bench Repair Complete
     */
    public function resolve(Request $request, Ticket $ticket, WhatsAppService $whatsapp)
    {
        $user = Auth::user();

        // Allowed for assigned workshop engineer OR superior
        if ($user->isEngineer() && $ticket->assigned_engineer_id !== $user->id) {
            abort(403, 'Only the assigned workshop engineer can mark bench repair complete.');
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
            'notes'     => "Bench repair completed by Workshop Engineer {$user->name} (Bench Repair Duration: {$benchDuration}). Machine QA tested OK. Summary: {$request->workshop_repair_summary}",
        ]);

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

        return back()->with('success', "Bank receipt confirmed! Ticket #{$ticket->ticket_no} successfully closed with full turnaround metrics logged.");
    }
}
