<?php

namespace App\Http\Controllers;

use App\Models\ExpenseClaim;
use App\Models\MachineModel;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Display the Executive Reports & Analytics Dashboard with multiple dedicated modules.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->isEngineer()) {
            return redirect()->route('dashboard')->with('warning', 'Reports and executive KPI analytics are restricted to operations management.');
        }

        if ($user->isOfficeStaff()) {
            if ($request->input('tab') !== 'machine_faults') {
                return redirect()->route('reports.index', ['tab' => 'machine_faults']);
            }
            $activeTab = 'machine_faults';
        } else {
            $activeTab = $request->input('tab', 'overview'); // 'overview', 'bank_tickets', 'machine_faults', 'engineer_parts'
        }
        $dateFilter = $this->resolveDateFilter($request);
        $fromDate = $dateFilter['from_date'];
        $toDate = $dateFilter['to_date'];
        $preset = $dateFilter['preset'];

        // Base Ticket Query for timeframe
        $baseTicketQuery = Ticket::query();
        if ($fromDate) {
            $baseTicketQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $baseTicketQuery->whereDate('created_at', '<=', $toDate);
        }

        // ==========================================
        // 1. EXECUTIVE OVERVIEW METRICS
        // ==========================================
        $allTickets = (clone $baseTicketQuery)->with(['assignedEngineer'])->get();

        $totalTickets = $allTickets->count();
        $resolvedTickets = $allTickets->filter(fn($t) => in_array($t->status, ['resolved', 'closed']));
        $openTickets = $allTickets->filter(fn($t) => in_array($t->status, ['open', 'in_progress', 'awaiting_workshop', 'in_workshop_repair', 'workshop_repaired', 'return_transit', 'awaiting_approval']));
        $escalatedTickets = $allTickets->filter(fn($t) => $t->status === 'escalated' || ($t->sla_deadline && $t->sla_deadline < now() && !in_array($t->status, ['resolved', 'closed'])));

        // SLA Compliance Calculation
        $slaCompliantCount = 0;
        $slaBreachedCount = 0;
        $totalHoursToResolve = 0;
        $resolvedCountWithTime = 0;

        foreach ($allTickets as $ticket) {
            $isResolved = in_array($ticket->status, ['resolved', 'closed']);
            
            if ($isResolved) {
                $resolvedAt = $ticket->resolved_at ?? $ticket->updated_at ?? $ticket->created_at;
                $createdAt = $ticket->created_at;
                $durationHours = max(0.1, $createdAt->diffInMinutes($resolvedAt) / 60);
                $totalHoursToResolve += $durationHours;
                $resolvedCountWithTime++;

                if (!$ticket->sla_deadline || $resolvedAt <= $ticket->sla_deadline) {
                    $slaCompliantCount++;
                } else {
                    $slaBreachedCount++;
                }
            } else {
                if ($ticket->sla_deadline && $ticket->sla_deadline < now()) {
                    $slaBreachedCount++;
                } else {
                    $slaCompliantCount++;
                }
            }
        }

        $slaEvaluated = $slaCompliantCount + $slaBreachedCount;
        $slaComplianceRate = $slaEvaluated > 0 ? round(($slaCompliantCount / $slaEvaluated) * 100, 1) : 100.0;
        $mttrHours = $resolvedCountWithTime > 0 ? round($totalHoursToResolve / $resolvedCountWithTime, 1) : 0.0;
        $resolutionRate = $totalTickets > 0 ? round(($resolvedTickets->count() / $totalTickets) * 100, 1) : 0.0;

        // Expenses
        $expenseQuery = ExpenseClaim::query();
        if ($fromDate) {
            $expenseQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $expenseQuery->whereDate('created_at', '<=', $toDate);
        }
        $expenses = $expenseQuery->get();

        $kpis = [
            'total_tickets' => $totalTickets,
            'resolved_tickets' => $resolvedTickets->count(),
            'open_tickets' => $openTickets->count(),
            'escalated_tickets' => $escalatedTickets->count(),
            'resolution_rate' => $resolutionRate,
            'sla_compliance_rate' => $slaComplianceRate,
            'sla_compliant_count' => $slaCompliantCount,
            'sla_breached_count' => $slaBreachedCount,
            'mttr_hours' => $mttrHours,
            'total_claimed' => $expenses->sum('claimed_amount'),
            'total_paid' => $expenses->where('status', 'paid')->sum('claimed_amount'),
            'total_distance_km' => $expenses->sum('ai_distance_km'),
            'total_benchmark' => $expenses->sum('suggested_amount'),
        ];

        // Bank Breakdown for Overview
        $bankMetrics = $allTickets->groupBy('bank_name')->map(function ($tickets, $bankName) {
            $total = $tickets->count();
            $resolved = $tickets->filter(fn($t) => in_array($t->status, ['resolved', 'closed']))->count();
            $breached = $tickets->filter(function ($t) {
                $isResolved = in_array($t->status, ['resolved', 'closed']);
                if ($isResolved) {
                    $resolvedAt = $t->resolved_at ?? $t->updated_at;
                    return $t->sla_deadline && $resolvedAt > $t->sla_deadline;
                }
                return $t->sla_deadline && $t->sla_deadline < now();
            })->count();

            $complianceRate = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100.0;

            $resolvedWithTime = $tickets->filter(fn($t) => in_array($t->status, ['resolved', 'closed']));
            $totalMins = 0;
            $resCount = 0;
            foreach ($resolvedWithTime as $rt) {
                $resAt = $rt->resolved_at ?? $rt->updated_at ?? $rt->created_at;
                $totalMins += $rt->created_at->diffInMinutes($resAt);
                $resCount++;
            }
            $avgHours = $resCount > 0 ? round(($totalMins / $resCount) / 60, 1) : 0.0;

            return [
                'bank_name' => $bankName ?: 'Unspecified Bank',
                'total' => $total,
                'resolved' => $resolved,
                'open' => $total - $resolved,
                'breached' => $breached,
                'compliance_rate' => $complianceRate,
                'avg_resolution_hours' => $avgHours,
            ];
        })->sortByDesc('total')->values();

        // Chronic Machines
        $chronicThreshold = (int)$request->input('chronic_threshold', 2);
        $chronicMachines = Ticket::select(
                'machine_serial_no',
                'machine_type',
                'bank_name',
                'branch_location',
                DB::raw('COUNT(*) as complaint_count'),
                DB::raw('MAX(created_at) as last_complaint_at')
            )
            ->whereNotNull('machine_serial_no')
            ->where('machine_serial_no', '!=', '')
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy('machine_serial_no', 'machine_type', 'bank_name', 'branch_location')
            ->having('complaint_count', '>=', $chronicThreshold)
            ->orderByDesc('complaint_count')
            ->get()
            ->map(function ($cm) {
                return [
                    'serial_no' => $cm->machine_serial_no,
                    'machine_type' => $cm->machine_type ?? 'ATM / Machine',
                    'bank_name' => $cm->bank_name,
                    'location' => $cm->branch_location ?? 'Head Office',
                    'complaint_count' => $cm->complaint_count,
                    'last_complaint_at' => Carbon::parse($cm->last_complaint_at)->format('d M Y H:i'),
                    'severity' => $cm->complaint_count >= 3 ? 'critical' : 'warning',
                    'action_needed' => $cm->complaint_count >= 3 ? 'Workshop Overhaul / Module Replace' : 'Close Technical Inspection',
                ];
            });


        // ==============================================================
        // 2. TAB: BANK-WISE TICKET DETAIL & COMPLETE AUDIT TRAIL
        // ==============================================================
        $allBankNames = Ticket::whereNotNull('bank_name')->where('bank_name', '!=', '')->distinct()->orderBy('bank_name')->pluck('bank_name');
        $selectedBank = $request->input('bank_filter');

        $bankAuditTickets = collect();
        if ($activeTab === 'bank_tickets' || $selectedBank) {
            $bankAuditQuery = Ticket::with([
                'assignedEngineer',
                'feedbacks.submittedBy',
                'logs.user',
                'partRequests.items.part',
                'workshopReceivedBy',
                'returnDispatchedBy',
                'bankReceivedConfirmedBy',
                'approvalRequestedBy',
                'approvalArrivedBy',
                'emailSentBy',
                'resolutionEmailSentBy',
            ]);

            if ($selectedBank) {
                $bankAuditQuery->where('bank_name', $selectedBank);
            }
            if ($fromDate) {
                $bankAuditQuery->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $bankAuditQuery->whereDate('created_at', '<=', $toDate);
            }
            if ($request->filled('bank_search')) {
                $bs = '%' . trim($request->bank_search) . '%';
                $bankAuditQuery->where(function ($q) use ($bs) {
                    $q->where('ticket_no', 'like', $bs)
                      ->orWhere('customer_ref_no', 'like', $bs)
                      ->orWhere('bank_name', 'like', $bs)
                      ->orWhere('branch_name', 'like', $bs)
                      ->orWhere('branch_location', 'like', $bs)
                      ->orWhere('machine_serial_no', 'like', $bs)
                      ->orWhere('machine_model', 'like', $bs)
                      ->orWhere('issue_summary', 'like', $bs)
                      ->orWhereHas('assignedEngineer', fn($eq) => $eq->where('name', 'like', $bs));
                });
            }

            // Compute summary metrics for Bank Audit Trail
            $allBankItems = (clone $bankAuditQuery)->get();
            $totalAudit = $allBankItems->count();
            $sumGross = 0;
            $sumDeducted = 0;
            $sumNet = 0;
            $inTat = 0;

            foreach ($allBankItems as $item) {
                $m = $item->calculateAuditTrailMetrics();
                $sumGross += $m['gross_hours'];
                $sumDeducted += $m['total_deducted_hours'];
                $sumNet += $m['net_hours'];
                if ($m['is_in_tat']) {
                    $inTat++;
                }
            }

            $bankAuditSummary = [
                'total' => $totalAudit,
                'avg_gross_hours' => $totalAudit > 0 ? round($sumGross / $totalAudit, 1) : 0.0,
                'avg_deducted_hours' => $totalAudit > 0 ? round($sumDeducted / $totalAudit, 1) : 0.0,
                'avg_net_hours' => $totalAudit > 0 ? round($sumNet / $totalAudit, 1) : 0.0,
                'in_tat_count' => $inTat,
                'out_tat_count' => $totalAudit - $inTat,
                'compliance_rate' => $totalAudit > 0 ? round(($inTat / $totalAudit) * 100, 1) : 100.0,
            ];

            $bankAuditTickets = $bankAuditQuery->latest('created_at')->paginate(15)->withQueryString();
        } else {
            $bankAuditSummary = [
                'total' => 0,
                'avg_gross_hours' => 0.0,
                'avg_deducted_hours' => 0.0,
                'avg_net_hours' => 0.0,
                'in_tat_count' => 0,
                'out_tat_count' => 0,
                'compliance_rate' => 100.0,
            ];
        }


        // ==============================================================
        // 3. TAB: MACHINE FAULTS & PARTS REPORT WITH DOWNLOADABLE VIDEO
        // ==============================================================
        $machineModels = MachineModel::orderBy('name')->get();
        $selectedModelId = $request->input('machine_model_id');
        $machineFaultRequests = collect();

        if ($activeTab === 'machine_faults') {
            $mfQuery = PartRequest::with([
                'ticket.assignedEngineer',
                'engineer',
                'machineModel',
                'items.part',
                'approvedBy',
                'dispatchedBy',
            ]);

            if ($selectedModelId) {
                $mfQuery->where('machine_model_id', $selectedModelId);
            }
            if ($fromDate) {
                $mfQuery->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $mfQuery->whereDate('created_at', '<=', $toDate);
            }
            if ($request->filled('machine_search')) {
                $ms = '%' . trim($request->machine_search) . '%';
                $mfQuery->where(function ($q) use ($ms) {
                    $q->where('machine_serial_no', 'like', $ms)
                      ->orWhere('fault_description', 'like', $ms)
                      ->orWhereHas('ticket', fn($tq) => $tq->where('bank_name', 'like', $ms)->orWhere('ticket_no', 'like', $ms));
                });
            }

            $machineFaultRequests = $mfQuery->latest('created_at')->paginate(15)->withQueryString();
        }


        // ==============================================================
        // 4. TAB: ENGINEER PARTS & IN-TAT / OUT-OF-TAT RESOLUTIONS REPORT
        // ==============================================================
        $engineers = User::where('role', 'engineer')->orderBy('name')->get();
        $selectedEngineerId = $request->input('engineer_filter');

        $engineerTatMetrics = collect();
        $engineerPartsRequests = collect();

        if ($activeTab === 'engineer_parts' || $activeTab === 'overview') {
            $engineerTatMetrics = $engineers->map(function ($eng) use ($allTickets, $expenses) {
                $engTickets = $allTickets->filter(fn($t) => (int)$t->assigned_engineer_id === $eng->id || (int)$t->original_field_engineer_id === $eng->id);
                $totalAssigned = $engTickets->count();

                $inTatResolved = 0;
                $outTatResolved = 0;
                $totalMins = 0;
                $resolvedCount = 0;

                foreach ($engTickets as $t) {
                    $isResolved = in_array($t->status, ['resolved', 'closed']);
                    if ($isResolved) {
                        $resolvedAt = $t->resolved_at ?? $t->updated_at ?? $t->created_at;
                        $durationMins = $t->created_at->diffInMinutes($resolvedAt);
                        $totalMins += $durationMins;
                        $resolvedCount++;

                        if (!$t->sla_deadline || $resolvedAt <= $t->sla_deadline) {
                            $inTatResolved++;
                        } else {
                            $outTatResolved++;
                        }
                    }
                }

                $activeBreached = $engTickets->filter(fn($t) => !in_array($t->status, ['resolved', 'closed']) && $t->sla_deadline && $t->sla_deadline < now())->count();
                $totalResolved = $inTatResolved + $outTatResolved;
                $tatComplianceRate = $totalResolved > 0 ? round(($inTatResolved / $totalResolved) * 100, 1) : 100.0;
                $avgTatHours = $resolvedCount > 0 ? round(($totalMins / $resolvedCount) / 60, 1) : 0.0;

                // Parts count requested by this engineer
                $partsCount = PartRequestItem::whereHas('partRequest', fn($pr) => $pr->where('engineer_id', $eng->id))->sum('qty_requested');
                $partsApprovedCount = PartRequestItem::whereHas('partRequest', fn($pr) => $pr->where('engineer_id', $eng->id))->sum('qty_approved');

                $engExpenses = $expenses->where('engineer_id', $eng->id);

                return [
                    'id' => $eng->id,
                    'name' => $eng->name,
                    'base_city' => $eng->base_city ?? 'Lahore',
                    'phone' => $eng->phone_whatsapp ?? 'N/A',
                    'total_assigned' => $totalAssigned,
                    'resolved' => $totalResolved,
                    'in_tat_resolved' => $inTatResolved,
                    'out_tat_resolved' => $outTatResolved,
                    'active_breached' => $activeBreached,
                    'tat_compliance_rate' => $tatComplianceRate,
                    'avg_tat_hours' => $avgTatHours,
                    'parts_requested_count' => (int)$partsCount,
                    'parts_approved_count' => (int)$partsApprovedCount,
                    'claimed_amount' => $engExpenses->sum('claimed_amount'),
                    'paid_amount' => $engExpenses->where('status', 'paid')->sum('claimed_amount'),
                    'distance_km' => $engExpenses->sum('ai_distance_km'),
                ];
            })->values();

            // Parts requested by engineer itemized list
            $epQuery = PartRequest::with([
                'ticket',
                'engineer',
                'machineModel',
                'items.part',
            ]);
            if ($selectedEngineerId) {
                $epQuery->where('engineer_id', $selectedEngineerId);
            }
            if ($fromDate) {
                $epQuery->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $epQuery->whereDate('created_at', '<=', $toDate);
            }

            $engineerPartsRequests = $epQuery->latest('created_at')->paginate(15)->withQueryString();
        }

        return view('reports.index', compact(
            'activeTab',
            'kpis',
            'bankMetrics',
            'chronicMachines',
            'preset',
            'fromDate',
            'toDate',
            'chronicThreshold',
            'allBankNames',
            'selectedBank',
            'bankAuditTickets',
            'bankAuditSummary',
            'machineModels',
            'selectedModelId',
            'machineFaultRequests',
            'engineers',
            'selectedEngineerId',
            'engineerTatMetrics',
            'engineerPartsRequests'
        ));
    }

    /**
     * Export Reports data to CSV format based on the active tab/type.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        if ($user->isEngineer()) {
            abort(403, 'Unauthorized export request.');
        }

        $type = $request->input('type', $request->input('tab', 'all'));
        if ($user->isOfficeStaff() && $type !== 'machine_faults') {
            abort(403, 'Office staff access is restricted to Machine Faults & Parts Reports.');
        }
        $dateFilter = $this->resolveDateFilter($request);
        $fromDate = $dateFilter['from_date'] ? Carbon::parse($dateFilter['from_date']) : null;
        $toDate = $dateFilter['to_date'] ? Carbon::parse($dateFilter['to_date']) : null;

        $filename = 'Report_' . ucfirst($type) . '_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($type, $fromDate, $toDate, $request) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            if ($type === 'bank_tickets') {
                // Bank Tickets Detailed Audit Trail
                fputcsv($output, [
                    'Ticket #',
                    'Bank Name',
                    'Branch Name',
                    'Location',
                    'Machine Model',
                    'Machine Serial No',
                    'Urgency',
                    'Status',
                    'Email Received (Logged At)',
                    'Reply / Assignment Sent At',
                    'Reply TAT (Mins)',
                    'Resolved At',
                    'Gross Elapsed Time (Hours)',
                    'Gross Elapsed Time (Formatted)',
                    'Weekend Non-Working Deducted (Hours)',
                    'Approval Wait Paused (Hours)',
                    'Workshop Transit In (Hours)',
                    'Workshop Return Transit (Hours)',
                    'Total Workshop Transit (Hours)',
                    'Total Deductions Excluded (Hours)',
                    'Net SLA Business Resolution Time (Hours)',
                    'Net SLA Business Resolution Time (Formatted)',
                    'Target SLA (Hours)',
                    'Net SLA Compliance',
                    'Workshop Intake At',
                    'Workshop Repaired At',
                    'Bank Delivery Received At',
                    'Resolution Summary',
                    'Resolution Email Sent At',
                    'Daily Feedbacks Count',
                    'Daily Feedbacks Complete Log (Day-by-Day Until Resolved)',
                    'Feedback Day 1',
                    'Feedback Day 2',
                    'Feedback Day 3',
                    'Feedback Day 4',
                    'Feedback Day 5',
                ]);

                $bankQuery = Ticket::with(['feedbacks.submittedBy']);
                if ($request->filled('bank_filter')) {
                    $bankQuery->where('bank_name', $request->bank_filter);
                }
                if ($fromDate) $bankQuery->whereDate('created_at', '>=', $fromDate);
                if ($toDate) $bankQuery->whereDate('created_at', '<=', $toDate);

                foreach ($bankQuery->latest()->get() as $t) {
                    $metrics = $t->calculateAuditTrailMetrics();

                    $replyTat = ($t->email_assignment_sent_at && $t->created_at)
                        ? $t->created_at->diffInMinutes($t->email_assignment_sent_at)
                        : 'N/A';

                    // Collect and format all daily progress updates until resolved
                    $feedbackLogs = [];
                    $feedbackByDay = [];
                    foreach ($t->feedbacks->sortBy('day_number') as $fb) {
                        $dateStr = $fb->submitted_at ? $fb->submitted_at->format('Y-m-d H:i') : $fb->created_at->format('Y-m-d H:i');
                        $partsStr = ($fb->parts_required && $fb->parts_required !== 'None') ? " [Parts: {$fb->parts_required}]" : '';
                        $actionStr = $fb->action_taken ? " [Action: {$fb->action_taken}]" : '';
                        $entry = "Day {$fb->day_number} ({$dateStr}): {$fb->feedback_text}{$actionStr}{$partsStr}";
                        $feedbackLogs[] = $entry;
                        $feedbackByDay[$fb->day_number] = $entry;
                    }
                    $completeLogStr = !empty($feedbackLogs) ? implode(" | ", $feedbackLogs) : 'No daily feedbacks logged';

                    fputcsv($output, [
                        $t->ticket_no,
                        $t->bank_name,
                        $t->branch_name ?? 'Branch',
                        $t->branch_location,
                        $t->machine_model ?? 'N/A',
                        $t->machine_serial_no ?? 'N/A',
                        strtoupper($t->urgency ?? 'NORMAL'),
                        strtoupper(str_replace('_', ' ', $t->status)),
                        $t->created_at->format('Y-m-d H:i'),
                        $t->email_assignment_sent_at ? $t->email_assignment_sent_at->format('Y-m-d H:i') : 'N/A',
                        $replyTat,
                        $t->resolved_at ? Carbon::parse($t->resolved_at)->format('Y-m-d H:i') : 'N/A',
                        $metrics['gross_hours'],
                        $metrics['gross_formatted'],
                        $metrics['weekend_hours'],
                        $metrics['approval_hours'],
                        $metrics['transit_in_hours'],
                        $metrics['transit_out_hours'],
                        $metrics['transit_hours'],
                        $metrics['total_deducted_hours'],
                        $metrics['net_hours'],
                        $metrics['net_formatted'],
                        $metrics['target_sla_hours'],
                        $metrics['tat_status_label'],
                        $t->workshop_received_at ? $t->workshop_received_at->format('Y-m-d H:i') : 'N/A',
                        $t->workshop_repaired_at ? $t->workshop_repaired_at->format('Y-m-d H:i') : 'N/A',
                        $t->bank_received_at ? $t->bank_received_at->format('Y-m-d H:i') : 'N/A',
                        $t->resolution_summary ?? 'N/A',
                        $t->resolution_email_sent_at ? $t->resolution_email_sent_at->format('Y-m-d H:i') : 'N/A',
                        $t->feedbacks->count(),
                        $completeLogStr,
                        $feedbackByDay[1] ?? 'N/A',
                        $feedbackByDay[2] ?? 'N/A',
                        $feedbackByDay[3] ?? 'N/A',
                        $feedbackByDay[4] ?? 'N/A',
                        $feedbackByDay[5] ?? 'N/A',
                    ]);
                }
            } elseif ($type === 'machine_faults') {
                // Machine Faults & Part Requests
                fputcsv($output, [
                    'Claim Requisition #',
                    'Machine Serial No',
                    'Machine Model',
                    'Bank Name',
                    'Branch Location',
                    'Associated Ticket #',
                    'Warranty Eligibility',
                    'Defective Part Required / Name',
                    'Part OEM Number',
                    'Qty Claimed (Free Replacement)',
                    'Qty Approved',
                    'Unit Cost (PKR)',
                    'Total Claim Value (PKR)',
                    'Claim Status',
                    'Field Engineer Diagnostic Findings',
                    'Certified Requesting Engineer',
                    'Video Proof Attached',
                    'Diagnostic Video Evidence URL',
                    'Requisition Date',
                ]);

                $mfQuery = PartRequest::with(['ticket', 'machineModel', 'items.part', 'engineer']);
                if ($request->filled('machine_model_id')) $mfQuery->where('machine_model_id', $request->machine_model_id);
                if ($fromDate) $mfQuery->whereDate('created_at', '>=', $fromDate);
                if ($toDate) $mfQuery->whereDate('created_at', '<=', $toDate);

                foreach ($mfQuery->latest()->get() as $pr) {
                    $hasVideo = !empty($pr->fault_video_path) ? 'YES' : 'NO';
                    $videoUrl = !empty($pr->fault_video_path) ? url('storage/' . $pr->fault_video_path) : 'N/A';
                    $modelName = $pr->machineModel?->name ?? $pr->machineModel?->model_name ?? 'N/A';
                    $engineerName = $pr->engineer?->name ?? 'Ali Khan';

                    if ($pr->items->isEmpty()) {
                        fputcsv($output, [
                            $pr->request_number,
                            $pr->machine_serial_no,
                            $modelName,
                            $pr->ticket?->bank_name ?? 'N/A',
                            $pr->ticket?->branch_location ?? 'N/A',
                            $pr->ticket?->ticket_no ?? 'N/A',
                            'Under Warranty - Free Replacement Entitled',
                            'Component specification pending itemization',
                            'N/A',
                            1,
                            0,
                            0.00,
                            0.00,
                            strtoupper($pr->status),
                            $pr->fault_description,
                            $engineerName,
                            $hasVideo,
                            $videoUrl,
                            $pr->created_at->format('Y-m-d H:i'),
                        ]);
                    } else {
                        foreach ($pr->items as $item) {
                            $total = $item->qty_requested * ($item->unit_cost ?? 0);
                            fputcsv($output, [
                                $pr->request_number,
                                $pr->machine_serial_no,
                                $modelName,
                                $pr->ticket?->bank_name ?? 'N/A',
                                $pr->ticket?->branch_location ?? 'N/A',
                                $pr->ticket?->ticket_no ?? 'N/A',
                                'Under Warranty - Free Replacement Entitled',
                                $item->part?->name ?? 'Part #' . $item->part_id,
                                $item->part?->part_number ?? 'OEM-PART-' . $item->part_id,
                                $item->qty_requested,
                                $item->qty_approved,
                                number_format($item->unit_cost ?? 0, 2, '.', ''),
                                number_format($total, 2, '.', ''),
                                strtoupper($pr->status),
                                $pr->fault_description,
                                $engineerName,
                                $hasVideo,
                                $videoUrl,
                                $pr->created_at->format('Y-m-d H:i'),
                            ]);
                        }
                    }
                }
            } elseif ($type === 'engineer_parts') {
                // Engineer Parts Consumption & TAT Resolution
                fputcsv($output, [
                    'Request #',
                    'Engineer Name',
                    'Engineer Base City',
                    'Machine Serial No',
                    'Ticket #',
                    'Bank Name',
                    'Part Name',
                    'Part Number',
                    'Qty Requested',
                    'Qty Approved',
                    'Unit Cost (PKR)',
                    'Request Status',
                    'Request Date',
                ]);

                $epQuery = PartRequest::with(['engineer', 'ticket', 'items.part']);
                if ($fromDate) $epQuery->whereDate('created_at', '>=', $fromDate);
                if ($toDate) $epQuery->whereDate('created_at', '<=', $toDate);

                foreach ($epQuery->latest()->get() as $pr) {
                    foreach ($pr->items as $item) {
                        fputcsv($output, [
                            $pr->request_number,
                            $pr->engineer?->name ?? 'N/A',
                            $pr->engineer?->base_city ?? 'Lahore',
                            $pr->machine_serial_no,
                            $pr->ticket?->ticket_no ?? 'N/A',
                            $pr->ticket?->bank_name ?? 'N/A',
                            $item->part?->name ?? 'Part #' . $item->part_id,
                            $item->part?->part_number ?? 'N/A',
                            $item->qty_requested,
                            $item->qty_approved,
                            $item->unit_cost ?? 0,
                            strtoupper($pr->status),
                            $pr->created_at->format('Y-m-d H:i'),
                        ]);
                    }
                }
            } else {
                // Default Master Overview Report
                fputcsv($output, [
                    'Ticket #',
                    'Bank Name',
                    'Branch Name',
                    'City / Location',
                    'Machine Type',
                    'Serial No',
                    'Urgency',
                    'Status',
                    'Assigned Engineer',
                    'Logged At',
                    'SLA Deadline',
                    'Resolved At',
                    'SLA Compliant',
                ]);

                $tickets = Ticket::with(['assignedEngineer']);
                if ($fromDate) $tickets->whereDate('created_at', '>=', $fromDate);
                if ($toDate) $tickets->whereDate('created_at', '<=', $toDate);

                foreach ($tickets->latest()->get() as $t) {
                    $isResolved = in_array($t->status, ['resolved', 'closed']);
                    $resolvedAt = $t->resolved_at ?? ($isResolved ? $t->updated_at : null);
                    
                    $compliant = 'N/A';
                    if ($isResolved) {
                        $compliant = (!$t->sla_deadline || $resolvedAt <= $t->sla_deadline) ? 'YES' : 'NO (BREACHED)';
                    } else {
                        $compliant = ($t->sla_deadline && $t->sla_deadline < now()) ? 'BREACHED (ACTIVE)' : 'IN PROGRESS';
                    }

                    fputcsv($output, [
                        $t->ticket_no,
                        $t->bank_name,
                        $t->branch_name ?? 'Branch',
                        $t->branch_location,
                        $t->machine_type ?? 'N/A',
                        $t->machine_serial_no ?? 'N/A',
                        strtoupper($t->urgency ?? 'NORMAL'),
                        strtoupper(str_replace('_', ' ', $t->status)),
                        $t->assignedEngineer?->name ?? 'Unassigned',
                        $t->created_at->format('Y-m-d H:i'),
                        $t->sla_deadline ? $t->sla_deadline->format('Y-m-d H:i') : 'N/A',
                        $resolvedAt ? Carbon::parse($resolvedAt)->format('Y-m-d H:i') : 'N/A',
                        $compliant,
                    ]);
                }
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Executive Reports as Official CMS Company PDF Documents.
     */
    public function exportPdf(Request $request)
    {
        $user = Auth::user();
        if ($user->isEngineer()) {
            abort(403, 'Unauthorized PDF export request.');
        }

        $type = $request->input('type', $request->input('tab', 'bank_tickets'));
        if ($user->isOfficeStaff() && $type !== 'machine_faults') {
            abort(403, 'Office staff access is restricted to Machine Faults & Parts Reports.');
        }
        $dateFilter = $this->resolveDateFilter($request);
        $fromDate = $dateFilter['from_date'] ? Carbon::parse($dateFilter['from_date']) : null;
        $toDate = $dateFilter['to_date'] ? Carbon::parse($dateFilter['to_date']) : null;

        if ($type === 'bank_tickets') {
            $bankQuery = Ticket::with(['feedbacks.submittedBy', 'assignedEngineer']);
            if ($request->filled('bank_filter')) {
                $bankQuery->where('bank_name', $request->bank_filter);
            }
            if ($fromDate) $bankQuery->whereDate('created_at', '>=', $fromDate);
            if ($toDate) $bankQuery->whereDate('created_at', '<=', $toDate);

            $tickets = $bankQuery->latest()->get();
            $auditedCount = $tickets->count();
            $compliantCount = 0;
            $totalGrossMinutes = 0;
            $totalNetMinutes = 0;
            $totalDeductedMinutes = 0;

            $items = [];
            foreach ($tickets as $t) {
                $m = $t->calculateAuditTrailMetrics();
                if ($m['is_in_tat']) $compliantCount++;
                $totalGrossMinutes += $m['gross_minutes'];
                $totalNetMinutes += $m['net_minutes'];
                $totalDeductedMinutes += $m['total_deducted_minutes'];
                $items[] = [
                    'ticket' => $t,
                    'metrics' => $m,
                    'daily_feedbacks' => $t->feedbacks->sortBy('day_number'),
                ];
            }

            $adherenceRate = $auditedCount > 0 ? round(($compliantCount / $auditedCount) * 100, 1) : 100;
            $avgGrossHours = $auditedCount > 0 ? round(($totalGrossMinutes / $auditedCount) / 60, 1) : 0;
            $avgNetHours = $auditedCount > 0 ? round(($totalNetMinutes / $auditedCount) / 60, 1) : 0;
            $avgDeductionsHours = $auditedCount > 0 ? round(($totalDeductedMinutes / $auditedCount) / 60, 1) : 0;

            $data = [
                'company_name' => 'CMS Company',
                'report_title' => 'Official Bank Service Level Agreement (SLA) & Audit Trail Report',
                'report_ref' => 'CMS-SLA-AUDIT-' . now()->format('Ymd-His'),
                'generated_at' => now()->format('d M Y, h:i A'),
                'date_range_label' => ($fromDate ? $fromDate->format('d M Y') : 'Inception') . ' to ' . ($toDate ? $toDate->format('d M Y') : 'Present'),
                'bank_scope' => $request->input('bank_filter', 'All Commercial Banks'),
                'audited_count' => $auditedCount,
                'compliant_count' => $compliantCount,
                'adherence_rate' => $adherenceRate,
                'avg_gross_hours' => $avgGrossHours,
                'avg_net_hours' => $avgNetHours,
                'avg_deductions_hours' => $avgDeductionsHours,
                'items' => $items,
            ];

            $pdf = Pdf::loadView('reports.pdf.bank_tickets', $data)
                ->setPaper('a4', 'landscape');

            return $pdf->download('CMS_Company_Bank_SLA_Audit_Report_' . now()->format('Ymd_His') . '.pdf');
        } elseif ($type === 'machine_faults') {
            $mfQuery = PartRequest::with(['ticket', 'machineModel', 'items.part', 'engineer']);
            if ($request->filled('machine_model_id')) {
                $mfQuery->where('machine_model_id', $request->machine_model_id);
            }
            if ($fromDate) $mfQuery->whereDate('created_at', '>=', $fromDate);
            if ($toDate) $mfQuery->whereDate('created_at', '<=', $toDate);

            $requests = $mfQuery->latest()->get();
            $selectedModel = $request->filled('machine_model_id') ? MachineModel::find($request->machine_model_id) : null;

            $totalPartsCount = 0;
            $totalClaimValue = 0;
            $videoCount = 0;
            foreach ($requests as $pr) {
                if ($pr->fault_video_path) $videoCount++;
                foreach ($pr->items as $it) {
                    $totalPartsCount += $it->qty_requested;
                    $totalClaimValue += ($it->qty_requested * ($it->unit_cost ?? 0));
                }
            }

            $data = [
                'company_name' => 'CMS Company',
                'report_title' => 'Supplier Warranty Claim & Defective Hardware Diagnostic Report',
                'report_ref' => 'CMS-RMA-CLAIM-' . now()->format('Ymd-His'),
                'generated_at' => now()->format('d M Y, h:i A'),
                'date_range_label' => ($fromDate ? $fromDate->format('d M Y') : 'Inception') . ' to ' . ($toDate ? $toDate->format('d M Y') : 'Present'),
                'model_filter' => $selectedModel ? ($selectedModel->name ?? $selectedModel->model_name) : 'All Machine Models',
                'total_claims' => $requests->count(),
                'total_parts_count' => $totalPartsCount,
                'total_claim_value' => $totalClaimValue,
                'video_count' => $videoCount,
                'requests' => $requests,
            ];

            $pdf = Pdf::loadView('reports.pdf.machine_faults', $data)
                ->setPaper('a4', 'landscape');

            return $pdf->download('CMS_Company_Supplier_Warranty_Claim_Report_' . now()->format('Ymd_His') . '.pdf');
        }

        abort(400, 'Invalid PDF export type requested.');
    }

    /**
     * Resolve date ranges from request presets or custom inputs.
     */
    protected function resolveDateFilter(Request $request): array
    {
        $preset = $request->input('preset', '30_days');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        if (!$fromDate && !$toDate && $preset) {
            switch ($preset) {
                case '7_days':
                    $fromDate = Carbon::now()->subDays(7)->toDateString();
                    $toDate = Carbon::now()->toDateString();
                    break;
                case '30_days':
                    $fromDate = Carbon::now()->subDays(30)->toDateString();
                    $toDate = Carbon::now()->toDateString();
                    break;
                case '90_days':
                    $fromDate = Carbon::now()->subDays(90)->toDateString();
                    $toDate = Carbon::now()->toDateString();
                    break;
                case 'this_month':
                    $fromDate = Carbon::now()->startOfMonth()->toDateString();
                    $toDate = Carbon::now()->toDateString();
                    break;
                case 'all':
                    $fromDate = null;
                    $toDate = null;
                    break;
                default:
                    $fromDate = Carbon::now()->subDays(30)->toDateString();
                    $toDate = Carbon::now()->toDateString();
                    break;
            }
        }

        return [
            'preset' => $preset,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }
}
