<?php

namespace App\Http\Controllers;

use App\Models\ExpenseClaim;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;

class EngineerController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (auth()->check() && auth()->user()->isOfficeStaff()) {
                abort(403, 'Office staff does not have access to engineer directory management.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = User::where('role', 'engineer')
            ->withCount([
                'assignedTickets as total_assigned_count',
                'assignedTickets as active_count' => function ($q) {
                    $q->whereIn('status', ['assigned', 'in_progress', 'awaiting_workshop']);
                },
                'assignedTickets as resolved_count' => function ($q) {
                    $q->whereIn('status', ['resolved', 'closed']);
                },
                'assignedTickets as escalated_count' => function ($q) {
                    $q->where('status', 'escalated');
                },
            ]);

        if ($request->filled('city')) {
            $query->where('base_city', 'like', "%{$request->city}%");
        }

        if ($request->filled('specialization')) {
            $query->where('specialization', 'like', "%{$request->specialization}%");
        }

        if ($request->filled('status')) {
            if ($request->status === 'available') {
                $query->where('is_available', true);
            } elseif ($request->status === 'on_leave') {
                $query->where('is_available', false);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('base_city', 'like', "%{$search}%")
                  ->orWhere('phone_whatsapp', 'like', "%{$search}%")
                  ->orWhere('specialization', 'like', "%{$search}%");
            });
        }

        $rawPerPage = $request->input('per_page', '25');
        if ($rawPerPage === 'all') {
            $perPage = 1000;
        } else {
            $perPage = in_array((int)$rawPerPage, [10, 25, 50, 100]) ? (int)$rawPerPage : 25;
        }
        $engineers = $query->paginate($perPage)->withQueryString();

        return view('engineers.index', compact('engineers'));
    }

    public function show(User $engineer)
    {
        abort_unless($engineer->isEngineer(), 404);

        $tickets = Ticket::forEngineer($engineer->id)->latest()->paginate(10);

        $expenses = ExpenseClaim::where('engineer_id', $engineer->id)->latest()->take(10)->get();

        $metrics = [
            'total_assigned' => Ticket::forEngineer($engineer->id)->count(),
            'resolved' => Ticket::forEngineer($engineer->id)->whereIn('status', ['resolved', 'closed'])->count(),
            'active' => Ticket::forEngineer($engineer->id)->whereIn('status', ['assigned', 'in_progress', 'awaiting_workshop'])->count(),
            'escalated' => Ticket::forEngineer($engineer->id)->where('status', 'escalated')->count(),
            'total_claimed' => ExpenseClaim::where('engineer_id', $engineer->id)->sum('claimed_amount'),
            'total_paid' => ExpenseClaim::where('engineer_id', $engineer->id)->where('status', 'paid')->sum('claimed_amount'),
            'sla_compliance' => $this->calculateSlaCompliance($engineer->id),
        ];

        return view('engineers.show', compact('engineer', 'tickets', 'expenses', 'metrics'));
    }

    public function toggleAvailability(Request $request, User $engineer)
    {
        $engineer->update([
            'is_available' => !$engineer->is_available,
        ]);

        $statusText = $engineer->is_available ? 'Available' : 'Unavailable (On Leave / In Transit)';
        return back()->with('success', "Engineer {$engineer->name} status changed to: {$statusText}");
    }

    private function calculateSlaCompliance(int $engineerId): int
    {
        $total = Ticket::forEngineer($engineerId)->count();
        if ($total === 0) return 100;

        $escalated = Ticket::forEngineer($engineerId)->where('status', 'escalated')->count();
        $compliance = round((($total - $escalated) / $total) * 100);

        return max(0, min(100, (int)$compliance));
    }
}
