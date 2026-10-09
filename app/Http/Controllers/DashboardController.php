<?php

namespace App\Http\Controllers;

use App\Models\ExpenseClaim;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isOfficeStaff()) {
            return redirect()->route('tickets.open');
        }

        if ($user->isEngineer()) {
            return $this->engineerDashboard($user);
        }

        // Admin & Superior Dashboard
        $stats = [
            'total_tickets' => Ticket::count(),
            'open_unassigned' => Ticket::where('status', 'open')->whereNull('assigned_engineer_id')->count(),
            'assigned' => Ticket::where('status', 'assigned')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'awaiting_approval' => Ticket::where('status', 'awaiting_approval')->count(),
            'escalated' => Ticket::where('status', 'escalated')->count(),
            'awaiting_workshop' => Ticket::where('status', 'awaiting_workshop')->count(),
            'resolved' => Ticket::whereIn('status', ['resolved', 'closed'])->count(),
            'pending_expenses' => ExpenseClaim::where('status', 'submitted')->count(),
            'active_engineers' => User::where('role', 'engineer')->where('is_available', true)->count(),
        ];

        // Action needed: Unassigned tickets
        $unassignedTickets = Ticket::where('status', 'open')
            ->whereNull('assigned_engineer_id')
            ->latest()
            ->take(5)
            ->get();

        // Action needed: Assigned but WhatsApp not notified
        $needWhatsAppTickets = Ticket::whereNotNull('assigned_engineer_id')
            ->where('whatsapp_notified', false)
            ->with(['engineer'])
            ->latest('assigned_at')
            ->take(5)
            ->get();

        // Action needed: WhatsApp sent but bank email not yet sent
        $needEmailTickets = Ticket::where('whatsapp_notified', true)
            ->where('email_assignment_sent', false)
            ->with(['engineer'])
            ->latest('whatsapp_notified_at')
            ->take(5)
            ->get();

        // Escalated Tickets
        $escalatedTickets = Ticket::where('status', 'escalated')
            ->with(['engineer', 'escalatedTo'])
            ->latest('escalated_at')
            ->take(5)
            ->get();

        // Recent Feedbacks
        $recentFeedbacks = TicketFeedback::with(['ticket', 'engineer'])
            ->latest()
            ->take(6)
            ->get();

        return view('dashboard.index', compact(
            'stats',
            'unassignedTickets',
            'needWhatsAppTickets',
            'needEmailTickets',
            'escalatedTickets',
            'recentFeedbacks'
        ));
    }

    private function engineerDashboard(User $engineer)
    {
        $myTickets = Ticket::where(function ($q) use ($engineer) {
                $q->forEngineer($engineer->id)
                  ->orWhere('original_field_engineer_id', $engineer->id);
            })
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest()
            ->get();

        $stats = [
            'active_tickets' => $myTickets->whereIn('status', ['assigned', 'in_progress', 'awaiting_approval', 'awaiting_workshop', 'in_workshop_repair', 'workshop_repaired', 'return_transit', 'escalated'])->count(),
            'resolved_tickets' => Ticket::where(function ($q) use ($engineer) {
                $q->forEngineer($engineer->id)
                  ->orWhere('original_field_engineer_id', $engineer->id);
            })->whereIn('status', ['resolved', 'closed'])->count(),
            'pending_expenses' => ExpenseClaim::where('engineer_id', $engineer->id)->where('status', 'submitted')->count(),
            'approved_expenses' => ExpenseClaim::where('engineer_id', $engineer->id)->whereIn('status', ['approved', 'paid'])->count(),
        ];

        $myExpenses = ExpenseClaim::where('engineer_id', $engineer->id)
            ->with('ticket')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.engineer', compact('myTickets', 'stats', 'myExpenses'));
    }
}
