<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicTrackController extends Controller
{
    /**
     * Clean ticket reference input by removing surrounding brackets, quotes, whitespace.
     */
    public static function cleanReference(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }
        $val = trim($raw);
        // Strip square brackets [ ], curly braces { }, parens ( ), angle brackets < >, quotes, etc.
        $val = trim($val, "[](){}<>'\" \t\n\r\0\x0B");
        return $val;
    }

    /**
     * Find ticket by public reference: supports [CMP-2026-00086], CMP202600086, UBL-127997, 127997, etc.
     */
    public function resolveTicket(?string $rawInput): ?Ticket
    {
        $cleaned = self::cleanReference($rawInput);
        if (empty($cleaned)) {
            return null;
        }

        // 1. Direct exact or case-insensitive match on ticket_no or customer_ref_no
        $ticket = Ticket::with(['engineer', 'activeTicketEngineers.engineer', 'feedbacks.engineer', 'logs', 'partRequests'])
            ->where(function ($q) use ($cleaned) {
                $q->whereRaw('LOWER(ticket_no) = ?', [strtolower($cleaned)])
                  ->orWhereRaw('LOWER(customer_ref_no) = ?', [strtolower($cleaned)]);
            })
            ->first();

        if ($ticket) {
            return $ticket;
        }

        // 2. Alphanumeric normalized match (e.g., 'CMP202600086' matching 'CMP-2026-00086' or 'UBL127997' matching 'UBL-127997')
        $alnumInput = preg_replace('/[^A-Za-z0-9]/', '', $cleaned);
        if (!empty($alnumInput)) {
            $candidates = Ticket::select('id', 'ticket_no', 'customer_ref_no')
                ->latest('id')
                ->limit(2000)
                ->get();

            foreach ($candidates as $cand) {
                $candTicketAlnum = preg_replace('/[^A-Za-z0-9]/', '', $cand->ticket_no);
                $candRefAlnum = preg_replace('/[^A-Za-z0-9]/', '', (string) $cand->customer_ref_no);

                if (strcasecmp($candTicketAlnum, $alnumInput) === 0 || (!empty($candRefAlnum) && strcasecmp($candRefAlnum, $alnumInput) === 0)) {
                    return Ticket::with(['engineer', 'activeTicketEngineers.engineer', 'feedbacks.engineer', 'logs', 'partRequests'])
                        ->find($cand->id);
                }
            }
        }

        // 3. Substring / partial numeric match (e.g., searching '00086' or '127997')
        if (strlen($cleaned) >= 4) {
            $ticket = Ticket::with(['engineer', 'activeTicketEngineers.engineer', 'feedbacks.engineer', 'logs', 'partRequests'])
                ->where(function ($q) use ($cleaned) {
                    $q->where('ticket_no', 'like', "%{$cleaned}%")
                      ->orWhere('customer_ref_no', 'like', "%{$cleaned}%");
                })
                ->first();

            if ($ticket) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * Display public tracking search page or tracking details if ?ticket query is present.
     */
    public function index(Request $request)
    {
        $rawQuery = $request->input('ticket') ?? $request->input('q') ?? $request->input('tracking_no');

        if (!empty($rawQuery)) {
            $ticket = $this->resolveTicket($rawQuery);
            if ($ticket) {
                return $this->renderTrackingView($ticket, $rawQuery);
            }

            return view('track.index', [
                'ticket'        => null,
                'searchedQuery' => $rawQuery,
                'error'         => "No complaint record found matching reference '{$rawQuery}'. Please verify your ticket number.",
            ]);
        }

        return view('track.index', [
            'ticket'        => null,
            'searchedQuery' => null,
            'error'         => null,
        ]);
    }

    /**
     * Direct URL lookup: /track/{ticket_no}
     */
    public function show(string $ticket_no)
    {
        $ticket = $this->resolveTicket($ticket_no);
        if ($ticket) {
            return $this->renderTrackingView($ticket, $ticket_no);
        }

        return redirect()->route('track.index', ['ticket' => $ticket_no])
            ->with('error', "No complaint record found matching reference '{$ticket_no}'. Please verify your ticket number.");
    }

    /**
     * POST search handler (redirects cleanly to /track?ticket=...)
     */
    public function search(Request $request)
    {
        $query = trim($request->input('ticket_no') ?? $request->input('ticket') ?? '');
        if (empty($query)) {
            return redirect()->route('track.index');
        }

        return redirect()->route('track.index', ['ticket' => $query]);
    }

    /**
     * Render the comprehensive courier-style tracking timeline.
     */
    protected function renderTrackingView(Ticket $ticket, string $searchedQuery)
    {
        $events = $this->buildTrackingEvents($ticket);
        $milestones = $this->buildMilestones($ticket);

        return view('track.index', compact('ticket', 'searchedQuery', 'events', 'milestones'));
    }

    /**
     * Build courier-style milestone stages (1 through 5).
     */
    protected function buildMilestones(Ticket $ticket): array
    {
        $isClosed = $ticket->status === 'closed';
        $isResolved = in_array($ticket->status, ['resolved', 'closed'], true);
        $isInProgress = in_array($ticket->status, ['in_progress', 'awaiting_workshop', 'awaiting_approval', 'resolved', 'closed'], true) || $ticket->feedbacks->isNotEmpty();
        $isAssigned = !empty($ticket->assigned_engineer_id) || $isInProgress;

        return [
            [
                'step'        => 1,
                'label'       => 'Registered',
                'sublabel'    => $ticket->created_at ? $ticket->created_at->format('d M, h:i A') : 'Logged',
                'completed'   => true,
                'current'     => !$isAssigned && !$isInProgress,
                'icon'        => 'fa-solid fa-file-lines',
            ],
            [
                'step'        => 2,
                'label'       => 'Assigned',
                'sublabel'    => $ticket->engineer ? $ticket->engineer->name : 'Field Engineer',
                'completed'   => $isAssigned,
                'current'     => $isAssigned && !$isInProgress,
                'icon'        => 'fa-solid fa-user-check',
            ],
            [
                'step'        => 3,
                'label'       => 'Field Work / On-Site',
                'sublabel'    => match($ticket->status) {
                    'awaiting_workshop' => 'Workshop Repair',
                    'awaiting_approval' => 'Awaiting Approval',
                    default             => 'Diagnostic & Work',
                },
                'completed'   => $isInProgress,
                'current'     => $isInProgress && !$isResolved,
                'icon'        => 'fa-solid fa-wrench',
            ],
            [
                'step'        => 4,
                'label'       => 'Work Restored',
                'sublabel'    => $ticket->resolved_at ? $ticket->resolved_at->format('d M, h:i A') : 'Service Slip Signed',
                'completed'   => $isResolved,
                'current'     => $isResolved && !$isClosed,
                'icon'        => 'fa-solid fa-circle-check',
            ],
            [
                'step'        => 5,
                'label'       => 'Closed & Verified',
                'sublabel'    => $ticket->closed_at ? $ticket->closed_at->format('d M, h:i A') : 'Final Sign-off',
                'completed'   => $isClosed,
                'current'     => $isClosed,
                'icon'        => 'fa-solid fa-square-check',
            ],
        ];
    }

    /**
     * Build unified chronological tracking event stream.
     */
    protected function buildTrackingEvents(Ticket $ticket): Collection
    {
        $events = collect();

        // 1. Complaint Registered
        $events->push([
            'timestamp'   => $ticket->created_at ?? now(),
            'title'       => 'Complaint Registered with Help Desk',
            'type'        => 'registered',
            'badge'       => 'Ticket Logged',
            'badge_color' => 'sky',
            'icon'        => 'fa-solid fa-file-circle-check',
            'description' => "Complaint successfully registered into system for {$ticket->bank_name} - {$ticket->branch_name} ({$ticket->branch_location}).",
            'details'     => array_filter([
                'Ticket #'       => $ticket->ticket_no,
                'Bank Reference' => $ticket->customer_ref_no ? "#{$ticket->customer_ref_no}" : null,
                'Target Machine' => "{$ticket->machine_type} " . ($ticket->machine_model ? "- {$ticket->machine_model}" : '') . ($ticket->machine_serial_no ? " (S/N: {$ticket->machine_serial_no})" : ''),
                'Initial Fault'  => $ticket->issue_summary,
                'SLA Priority'   => $ticket->urgency ? strtoupper($ticket->urgency) . ' PRIORITY' : null,
            ]),
        ]);

        // 2. Engineer Assigned
        if ($ticket->assigned_engineer_id && $ticket->engineer) {
            $events->push([
                'timestamp'   => $ticket->assigned_at ?? ($ticket->created_at ? $ticket->created_at->copy()->addMinutes(15) : now()),
                'title'       => 'Field Engineer Assigned & Dispatched',
                'type'        => 'assignment',
                'badge'       => 'Engineer Dispatched',
                'badge_color' => 'indigo',
                'icon'        => 'fa-solid fa-user-gear',
                'description' => "Assigned to Certified Field Support Engineer {$ticket->engineer->name} ({$ticket->engineer->base_city}) for on-site diagnostic and repair.",
                'details'     => array_filter([
                    'Lead Engineer'  => "{$ticket->engineer->name} ({$ticket->engineer->base_city})",
                    'Specialization' => $ticket->engineer->specialization ?? 'Field Hardware & Sorter Specialist',
                ]),
            ]);
        }

        // 3. Daily SLA Feedbacks
        foreach ($ticket->feedbacks as $fb) {
            $fbTime = $fb->submitted_at ?? $fb->created_at;
            $events->push([
                'timestamp'   => $fbTime,
                'title'       => "Daily SLA Field Progress (Day {$fb->day_number})",
                'type'        => 'feedback',
                'badge'       => "Day {$fb->day_number} SLA Update",
                'badge_color' => 'teal',
                'icon'        => 'fa-solid fa-clipboard-check',
                'description' => $fb->feedback_text,
                'details'     => array_filter([
                    'Action Taken'   => $fb->action_taken,
                    'Parts Required' => $fb->parts_required,
                    'Expected ETA'   => $fb->eta_completion,
                    'Field Engineer' => $fb->engineer?->name,
                ]),
            ]);
        }

        // 4. Approval Paused / Resumed
        if ($ticket->approval_requested_at) {
            $events->push([
                'timestamp'   => $ticket->approval_requested_at,
                'title'       => 'Awaiting Bank Approval (SLA Clock Paused)',
                'type'        => 'approval_paused',
                'badge'       => 'SLA Paused',
                'badge_color' => 'amber',
                'icon'        => 'fa-solid fa-hourglass-half',
                'description' => 'Work temporarily paused awaiting formal authorization / quotation approval from bank management.',
                'details'     => array_filter([
                    'Approval Reason' => $ticket->approval_request_reason,
                    'Requested At'    => $ticket->approval_requested_at->format('d M Y, h:i A'),
                ]),
            ]);
        }

        if ($ticket->approval_arrived_at) {
            $events->push([
                'timestamp'   => $ticket->approval_arrived_at,
                'title'       => 'Bank Approval Granted (SLA Clock Resumed)',
                'type'        => 'approval_resumed',
                'badge'       => 'SLA Resumed',
                'badge_color' => 'emerald',
                'icon'        => 'fa-solid fa-circle-check',
                'description' => 'Formal authorization received. Field operations and SLA turnaround clock actively resumed.',
                'details'     => array_filter([
                    'Remarks'     => $ticket->approval_arrived_remarks,
                    'Approved At' => $ticket->approval_arrived_at->format('d M Y, h:i A'),
                ]),
            ]);
        }

        // 5. Central Workshop Lifecycle
        if ($ticket->workshop_dispatched_at) {
            $events->push([
                'timestamp'   => $ticket->workshop_dispatched_at,
                'title'       => 'Unit Dispatched to Central Repair Workshop',
                'type'        => 'workshop_dispatched',
                'badge'       => 'In Transit to Lab',
                'badge_color' => 'purple',
                'icon'        => 'fa-solid fa-truck-fast',
                'description' => 'Component / unit sent to Central Technical Workshop for bench testing and board-level calibration.',
                'details'     => array_filter([
                    'Courier Service' => $ticket->workshop_dispatch_courier,
                    'Tracking / Slip' => $ticket->workshop_dispatch_tracking,
                    'Dispatch Notes'  => $ticket->workshop_dispatch_notes,
                ]),
            ]);
        }

        if ($ticket->workshop_received_at) {
            $events->push([
                'timestamp'   => $ticket->workshop_received_at,
                'title'       => 'Intake Verified at Central Workshop Lab',
                'type'        => 'workshop_intake',
                'badge'       => 'Workshop Intake',
                'badge_color' => 'purple',
                'icon'        => 'fa-solid fa-boxes-packing',
                'description' => 'Unit received and checked into workshop intake. Physical component inspection underway.',
                'details'     => array_filter([
                    'Intake Remarks' => $ticket->workshop_intake_remarks,
                ]),
            ]);
        }

        if ($ticket->workshop_repaired_at) {
            $events->push([
                'timestamp'   => $ticket->workshop_repaired_at,
                'title'       => 'Workshop Diagnostics & Repair Complete',
                'type'        => 'workshop_repaired',
                'badge'       => 'Repaired in Lab',
                'badge_color' => 'emerald',
                'icon'        => 'fa-solid fa-microchip',
                'description' => $ticket->workshop_repair_summary ?: 'Workshop component repair completed successfully. Calibrated and quality checked.',
                'details'     => array_filter([
                    'Completed On' => $ticket->workshop_repaired_at->format('d M Y, h:i A'),
                ]),
            ]);
        }

        // 6. Spare Part Requests
        foreach ($ticket->partRequests as $pr) {
            $events->push([
                'timestamp'   => $pr->created_at,
                'title'       => "Spare Parts Requisition #{$pr->request_number}",
                'type'        => 'part_request',
                'badge'       => 'Parts ' . ucfirst($pr->status),
                'badge_color' => match($pr->status) {
                    'dispatched', 'installed' => 'emerald',
                    'rejected'                => 'rose',
                    default                   => 'blue',
                },
                'icon'        => 'fa-solid fa-gears',
                'description' => "Spare parts requisition logged for {$ticket->machine_type}. Status: " . ucfirst($pr->status) . '.',
                'details'     => array_filter([
                    'Requisition #' => $pr->request_number,
                    'Status'        => ucfirst($pr->status),
                ]),
            ]);
        }

        // 7. Notable Lifecycle Logs (Reopen, Escalations)
        foreach ($ticket->logs as $log) {
            if (in_array($log->action, ['reopened', 'escalated'])) {
                $events->push([
                    'timestamp'   => $log->created_at,
                    'title'       => match($log->action) {
                        'reopened'  => 'Ticket Reopened for Additional Service Tour',
                        'escalated' => 'Escalated to Regional Technical Superior',
                        default     => ucfirst(str_replace('_', ' ', $log->action)),
                    },
                    'type'        => 'lifecycle_log',
                    'badge'       => match($log->action) {
                        'reopened'  => 'Reopened',
                        'escalated' => 'Escalated',
                        default     => 'Update',
                    },
                    'badge_color' => match($log->action) {
                        'reopened'  => 'amber',
                        'escalated' => 'rose',
                        default     => 'slate',
                    },
                    'icon'        => match($log->action) {
                        'reopened'  => 'fa-solid fa-rotate-right',
                        'escalated' => 'fa-solid fa-triangle-exclamation',
                        default     => 'fa-solid fa-pen-to-square',
                    },
                    'description' => $log->notes,
                    'details'     => [],
                ]);
            }
        }

        // 8. Service Resolved
        if ($ticket->resolved_at || in_array($ticket->status, ['resolved', 'closed'], true)) {
            $events->push([
                'timestamp'   => $ticket->resolved_at ?? $ticket->updated_at,
                'title'       => 'Service Completed & Restored to Operation',
                'type'        => 'resolved',
                'badge'       => 'Work Restored',
                'badge_color' => 'emerald',
                'icon'        => 'fa-solid fa-circle-check',
                'description' => 'Machine verified in normal operational status on-site. Service completion acknowledged with branch personnel.',
                'details'     => array_filter([
                    'Resolved At' => ($ticket->resolved_at ?? $ticket->updated_at)->format('d M Y, h:i A'),
                ]),
            ]);
        }

        // 9. Ticket Closed
        if ($ticket->closed_at || $ticket->status === 'closed') {
            $events->push([
                'timestamp'   => $ticket->closed_at ?? $ticket->updated_at,
                'title'       => 'Complaint Officially Closed & Confirmed',
                'type'        => 'closed',
                'badge'       => 'Closed & Confirmed',
                'badge_color' => 'slate',
                'icon'        => 'fa-solid fa-square-check',
                'description' => 'Complaint marked closed and archived in system following satisfactory service confirmation.',
                'details'     => array_filter([
                    'Closed At' => ($ticket->closed_at ?? $ticket->updated_at)->format('d M Y, h:i A'),
                ]),
            ]);
        }

        // Sort latest first (courier tracking convention)
        return $events->sortByDesc('timestamp')->values();
    }
}
