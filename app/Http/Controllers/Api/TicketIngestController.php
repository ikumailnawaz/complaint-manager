<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketLog;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketIngestController extends Controller
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Ingest email payload from n8n workflow or direct API.
     * Endpoint: POST /api/v1/tickets/ingest
     */
    public function ingest(Request $request)
    {
        // Optional webhook secret verification
        $configuredSecret = env('N8N_WEBHOOK_SECRET', '');
        if (!empty($configuredSecret)) {
            $headerSecret = $request->header('X-Webhook-Secret') ?? $request->input('secret');
            if ($headerSecret !== $configuredSecret) {
                return response()->json(['error' => 'Unauthorized webhook secret'], 401);
            }
        }

        $subject = $request->input('subject', '');
        $body = $request->input('body', $request->input('content', ''));
        $fromEmail = $request->input('from_email', $request->input('from', ''));

        if (empty($subject) && empty($body)) {
            return response()->json(['error' => 'Subject or email body required'], 422);
        }

        // If n8n already extracted json, use it; otherwise invoke Gemini AI
        $aiData = $request->input('extracted_data');
        if (!is_array($aiData) || (!isset($aiData['type']) && empty($aiData['bank_name']))) {
            $aiData = $this->gemini->classifyAndExtractEmail($subject, $body, $fromEmail);
        }

        // Check if classified as complaint
        if (
            (isset($aiData['is_complaint']) && !$aiData['is_complaint']) ||
            (isset($aiData['type']) && in_array(strtolower($aiData['type']), ['spam', 'inquiry', 'conversation', 'other']))
        ) {
            $type = $aiData['type'] ?? 'other';
            return response()->json([
                'success' => true,
                'is_complaint' => false,
                'type' => $type,
                'message' => 'Email classified as ' . $type . ' - ticket not created.',
            ]);
        }

        // Determine Ticket Number (Bank's custom ref vs Auto-Generated)
        $refNo = $aiData['customer_ref_no'] ?? null;
        $bankName = $aiData['bank_name'] ?? 'Bank Branch';
        if (!empty($refNo)) {
            $rawTicketNo = strtoupper(trim($refNo));
            $existing = Ticket::ticketExistsForBank($bankName, $rawTicketNo);
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'is_complaint' => true,
                    'is_duplicate' => true,
                    'ticket_id' => $existing->id,
                    'ticket_no' => $existing->ticket_no,
                    'ticket_no_source' => $existing->ticket_no_source,
                    'bank_name' => $existing->bank_name,
                    'branch_location' => $existing->branch_location,
                    'machine_type' => $existing->machine_type,
                    'machine_serial_no' => $existing->machine_serial_no,
                    'urgency' => $existing->urgency,
                    'status' => $existing->status,
                    'assigned_engineer' => $existing->engineer?->name,
                    'view_url' => url("/tickets/{$existing->id}"),
                    'message' => "Ticket #{$existing->ticket_no} already exists for {$bankName}. Duplicate creation prevented.",
                ], 200);
            }
            $ticketNo = Ticket::formatTicketNo($bankName, $rawTicketNo);
            $ticketSource = 'from_email';
        } else {
            $year = date('Y');
            $nextId = (Ticket::max('id') ?? 0) + 1;
            do {
                $ticketNo = 'CMP-' . $year . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
                $nextId++;
            } while (Ticket::where('ticket_no', $ticketNo)->exists());
            $ticketSource = 'auto_generated';
        }

        // Urgency & SLA Deadline (Supports 1 Day, 2 Days, 3 Days, 4 Days, or Hours)
        $urgency = strtolower($aiData['urgency'] ?? 'medium');
        if (!in_array($urgency, ['high', 'medium', 'low'])) {
            $urgency = 'medium';
        }

        $slaTat = $aiData['sla_tat'] ?? null;
        if ($slaTat && preg_match('/(\d+)\s*day/i', $slaTat, $m)) {
            $slaDeadline = Carbon::now()->addDays((int)$m[1]);
        } elseif ($slaTat && preg_match('/(\d+)\s*hour/i', $slaTat, $m)) {
            $slaDeadline = Carbon::now()->addHours((int)$m[1]);
        } else {
            $slaHours = match ($urgency) {
                'high' => 4,
                'medium' => 8,
                'low' => 24,
                default => 8,
            };
            $slaDeadline = Carbon::now()->addHours($slaHours);
        }

        // CREATE TICKET (strictly UNASSIGNED matching user requirement!)
        $ticket = Ticket::create([
            'ticket_no' => $ticketNo,
            'ticket_no_source' => $ticketSource,
            'customer_ref_no' => $refNo,
            'bank_name' => $aiData['bank_name'] ?? 'Bank Branch',
            'branch_name' => $aiData['branch_name'] ?? null,
            'branch_location' => $aiData['branch_location'] ?? 'Lahore',
            'branch_address' => $aiData['branch_address'] ?? null,
            'customer_name' => $aiData['customer_name'] ?? null,
            'customer_mobile' => $aiData['customer_mobile'] ?? null,
            'customer_email' => $fromEmail ?: ($aiData['customer_email'] ?? null),
            'machine_type' => $aiData['machine_type'] ?? null,
            'machine_model' => $aiData['machine_model'] ?? null,
            'machine_serial_no' => $aiData['machine_serial_no'] ?? null,
            'warranty_status' => in_array($aiData['warranty_hint'] ?? '', ['in_warranty', 'out_of_warranty']) ? $aiData['warranty_hint'] : 'unknown',
            'urgency' => $urgency,
            'status' => 'open', // OPEN = Unassigned
            'issue_summary' => $aiData['issue_summary'] ?? substr($subject, 0, 150),
            'issue_description' => $body,
            'assigned_engineer_id' => null, // Admin will pick manually
            'sla_deadline' => $slaDeadline,
            'ai_classified' => true,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'action' => 'created',
            'notes' => "Ticket automatically ingested via n8n & Gemini AI. Extracted bank: {$ticket->bank_name}, machine: {$ticket->machine_type}, location: {$ticket->branch_location}. Awaiting manual engineer assignment.",
        ]);

        return response()->json([
            'success' => true,
            'is_complaint' => true,
            'ticket_id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'ticket_no_source' => $ticket->ticket_no_source,
            'bank_name' => $ticket->bank_name,
            'branch_location' => $ticket->branch_location,
            'machine_type' => $ticket->machine_type,
            'machine_serial_no' => $ticket->machine_serial_no,
            'urgency' => $ticket->urgency,
            'status' => $ticket->status,
            'assigned_engineer' => null,
            'view_url' => url("/tickets/{$ticket->id}"),
            'message' => 'Complaint successfully created. Admin notified to assign engineer.',
        ], 201);
    }
}
