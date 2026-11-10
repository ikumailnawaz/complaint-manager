<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AfterSalesAuditApiController extends Controller
{
    /**
     * Read-only API endpoint for Financial Audit & Inventory Suite (After Sales Monitoring Tab).
     * Supports filtering by delay threshold in days (e.g., min_days=5, min_days=7), bank, urgency, engineer, scope, and missing feedback.
     */
    public function index(Request $request)
    {
        $minDays = max(0, (int) $request->input('min_days', 5));
        $scope = $request->input('scope', 'delayed'); // delayed | escalated | workshop | all_open | include_resolved
        $bank = trim((string) $request->input('bank', ''));
        $urgency = trim((string) $request->input('urgency', ''));
        $engineerId = (int) $request->input('engineer_id', 0);
        $city = trim((string) $request->input('city', ''));
        $missingOnly = (bool) $request->input('missing_feedback', false);
        $search = trim((string) $request->input('search', ''));

        $query = Ticket::with([
            'assignedEngineer:id,name,phone_whatsapp,base_city',
            'feedbacks.engineer:id,name',
            'inboxEmails',
        ])->where(function ($q) {
            $q->where('status', 'escalated')
              ->orWhere(function ($sub) {
                  $sub->whereNotNull('sla_deadline')
                      ->where('sla_deadline', '<', Carbon::now())
                      ->whereNotIn('status', ['resolved', 'closed']);
              });
        })
          ->whereNotIn('status', ['resolved', 'closed'])
          ->whereNull('closed_at')
          ->whereNull('resolved_at');

        if ($scope === 'high_urgency') {
            $query->whereIn('urgency', ['high', 'critical']);
        } elseif ($scope === 'reopened') {
            $query->where('reopen_count', '>', 0);
        }

        if ($minDays > 0) {
            $cutoff = Carbon::now()->subDays($minDays);
            $query->where('created_at', '<=', $cutoff);
        }

        if ($bank !== '') {
            $query->where('bank_name', 'like', "%{$bank}%");
        }
        if ($urgency !== '') {
            $query->where('urgency', $urgency);
        }
        if ($engineerId > 0) {
            $query->where('assigned_engineer_id', $engineerId);
        }
        if ($city !== '') {
            $query->where('branch_location', 'like', "%{$city}%");
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                    ->orWhere('customer_ref_no', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('branch_name', 'like', "%{$search}%")
                    ->orWhere('branch_location', 'like', "%{$search}%")
                    ->orWhere('machine_model', 'like', "%{$search}%")
                    ->orWhere('machine_serial_no', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'asc')->limit(300)->get();

        $items = [];
        foreach ($tickets as $ticket) {
            $matrix = $ticket->getDailyFeedbackMatrix();
            $missingDaysCount = count($matrix['missing_days'] ?? []);
            if ($missingOnly && $missingDaysCount === 0) {
                continue;
            }
            $slaHealth = $ticket->getSlaHealth();
            $emailAudit = $ticket->getEmailLandingAudit();
            $elapsedDays = max(1, (int) Carbon::parse($ticket->created_at)->startOfDay()->diffInDays(
                ($ticket->resolved_at ? Carbon::parse($ticket->resolved_at) : now())->startOfDay()
            ) + 1);

            $items[] = [
                'id'                     => $ticket->id,
                'ticket_no'              => $ticket->ticket_no,
                'customer_ref_no'        => $ticket->customer_ref_no,
                'bank_name'              => $ticket->bank_name,
                'branch_name'            => $ticket->branch_name,
                'branch_location'        => $ticket->branch_location,
                'branch_address'         => $ticket->branch_address,
                'machine_type'           => $ticket->machine_type,
                'machine_model'          => $ticket->machine_model,
                'machine_serial_no'      => $ticket->machine_serial_no,
                'urgency'                => $ticket->urgency,
                'status'                 => $ticket->status,
                'issue_summary'          => $ticket->issue_summary,
                'created_at'             => $ticket->created_at?->format('Y-m-d H:i:s'),
                'sla_deadline'           => $ticket->sla_deadline?->format('Y-m-d H:i:s'),
                'escalated_at'           => $ticket->escalated_at?->format('Y-m-d H:i:s'),
                'resolved_at'            => $ticket->resolved_at?->format('Y-m-d H:i:s'),
                'elapsed_days'           => $elapsedDays,
                'engineer'               => $ticket->assignedEngineer ? [
                    'id'             => $ticket->assignedEngineer->id,
                    'name'           => $ticket->assignedEngineer->name,
                    'phone_whatsapp' => $ticket->assignedEngineer->phone_whatsapp,
                    'base_city'      => $ticket->assignedEngineer->base_city,
                ] : null,
                'sla_health'             => $slaHealth,
                'email_landing'          => $emailAudit,
                'daily_feedback_matrix'  => $matrix,
            ];
        }

        return response()->json([
            'success'  => true,
            'min_days' => $minDays,
            'count'    => count($items),
            'tickets'  => $items,
        ]);
    }

    /**
     * Read-only single ticket detail & complete daily feedback audit trail.
     */
    public function show($id)
    {
        $ticket = Ticket::with([
            'assignedEngineer:id,name,phone_whatsapp,base_city',
            'feedbacks.engineer:id,name',
            'logs.user:id,name',
            'inboxEmails',
            'partRequests',
            'expenseClaims',
        ])->findOrFail($id);

        return response()->json([
            'success'               => true,
            'ticket'                => $ticket,
            'sla_health'            => $ticket->getSlaHealth(),
            'email_landing'         => $ticket->getEmailLandingAudit(),
            'daily_feedback_matrix' => $ticket->getDailyFeedbackMatrix(),
        ]);
    }
}
