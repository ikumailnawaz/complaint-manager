<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5DetailedReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $engineerAli;
    protected User $engineerUsman;
    protected MachineModel $model;
    protected Part $part;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Operations Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03001111111',
        ]);

        $this->engineerAli = User::create([
            'name' => 'Ali Khan',
            'email' => 'ali@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03002222222',
        ]);

        $this->engineerUsman = User::create([
            'name' => 'Usman Tariq',
            'email' => 'usman@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Karachi',
            'phone_whatsapp' => '03003333333',
        ]);

        $this->model = MachineModel::create([
            'name' => 'Hyosung 8600',
            'manufacturer' => 'Nautilus Hyosung',
            'machine_type' => 'crm',
        ]);

        $this->part = Part::create([
            'part_number' => 'PART-TEST-99',
            'name' => 'Dispenser Roller',
            'unit' => 'pcs',
            'unit_cost' => 3500.00,
        ]);
    }

    public function test_bank_tickets_audit_trail_tab_displays_timelines_and_logs(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-AUDIT-TEST-1',
            'customer_ref_no' => 'HBL-REF-01',
            'bank_name' => 'Habib Bank Limited (HBL)',
            'branch_name' => 'Mall Road',
            'branch_location' => 'Lahore',
            'machine_type' => 'Cash Recycler',
            'machine_serial_no' => 'HYO-9912',
            'urgency' => 'high',
            'status' => 'resolved',
            'assigned_engineer_id' => $this->engineerAli->id,
            'email_subject' => 'ATM Note Jam Error',
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subHours(5),
            'approval_requested_at' => Carbon::now()->subHours(4),
            'approval_arrived_at' => Carbon::now()->subHours(2),
            'approval_paused_seconds' => 7200, // 2 Hours
            'workshop_location' => 'Lahore Workshop',
            'workshop_dispatched_at' => Carbon::now()->subDays(2),
            'workshop_received_at' => Carbon::now()->subDays(2)->addHours(4),
            'workshop_repaired_at' => Carbon::now()->subDays(1),
            'return_dispatched_at' => Carbon::now()->subDays(1)->addHours(2),
            'bank_received_at' => Carbon::now()->subDays(1)->addHours(6),
            'resolved_at' => Carbon::now()->subHour(),
            'resolution_summary' => 'Roller replaced successfully',
            'resolution_email_sent' => true,
            'resolution_email_sent_at' => Carbon::now()->subHour(),
            'created_at' => Carbon::now()->subHours(6),
        ]);

        TicketFeedback::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'submitted_by_id' => $this->engineerAli->id,
            'day_number' => 1,
            'feedback_text' => 'Examined transport path, rollers worn out.',
            'action_taken' => 'Cleaned transport path.',
            'status' => 'parts_required',
            'submitted_at' => Carbon::now()->subHours(5),
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.index', ['tab' => 'bank_tickets', 'bank_filter' => 'Habib Bank Limited (HBL)']));
        $response->assertStatus(200);
        $response->assertSee('CMP-AUDIT-TEST-1');
        $response->assertSee('HBL-REF-01');
        $response->assertSee('ATM Note Jam Error');
        $response->assertSee('120 mins'); // 2 hours approval wait
        $response->assertSee('Examined transport path, rollers worn out.');
        $response->assertSee('Official Email Dispatched to Bank');
    }

    public function test_machine_faults_tab_renders_serial_parts_and_video(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-FAULT-TEST-2',
            'bank_name' => 'Meezan Bank Ltd',
            'branch_location' => 'Karachi',
            'machine_serial_no' => 'SN-CRITICAL-77',
            'urgency' => 'critical',
            'status' => 'in_progress',
            'created_at' => Carbon::now(),
        ]);

        $pr = PartRequest::create([
            'request_number' => 'PR-FAULT-99',
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'machine_model_id' => $this->model->id,
            'machine_serial_no' => 'SN-CRITICAL-77',
            'fault_description' => 'Motor burning smell and shuddering gear belt',
            'status' => 'approved',
            'fault_video_path' => 'part-requests/videos/sample_test.mp4',
            'created_at' => Carbon::now(),
        ]);

        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $this->part->id,
            'qty_requested' => 3,
            'qty_approved' => 3,
            'unit_cost' => 3500.00,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.index', ['tab' => 'machine_faults']));
        $response->assertStatus(200);
        $response->assertSee('PR-FAULT-99');
        $response->assertSee('SN-CRITICAL-77');
        $response->assertSee('Motor burning smell and shuddering gear belt');
        $response->assertSee('Dispenser Roller');
        $response->assertSee('Download Video');
    }

    public function test_engineer_parts_and_tat_performance_report(): void
    {
        // 1 In-TAT resolution for Ali
        $ticket1 = Ticket::create([
            'ticket_no' => 'CMP-IN-TAT-1',
            'bank_name' => 'MCB',
            'branch_location' => 'Lahore',
            'status' => 'resolved',
            'assigned_engineer_id' => $this->engineerAli->id,
            'sla_deadline' => Carbon::now()->subHours(2),
            'resolved_at' => Carbon::now()->subHours(3), // In-TAT!
            'created_at' => Carbon::now()->subHours(6),
        ]);

        // 1 Out-of-TAT resolution for Ali
        Ticket::create([
            'ticket_no' => 'CMP-OUT-TAT-2',
            'bank_name' => 'MCB',
            'branch_location' => 'Lahore',
            'status' => 'resolved',
            'assigned_engineer_id' => $this->engineerAli->id,
            'sla_deadline' => Carbon::now()->subHours(5),
            'resolved_at' => Carbon::now()->subHours(1), // Out-of-TAT!
            'created_at' => Carbon::now()->subHours(8),
        ]);

        // Part requested by Ali
        $pr = PartRequest::create([
            'request_number' => 'PR-ALI-01',
            'ticket_id' => $ticket1->id,
            'engineer_id' => $this->engineerAli->id,
            'machine_serial_no' => 'SN-ALI-MACHINE',
            'fault_description' => 'Gearbox failure',
            'status' => 'dispatched',
            'created_at' => Carbon::now(),
        ]);

        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $this->part->id,
            'qty_requested' => 2,
            'qty_approved' => 2,
            'unit_cost' => 3500.00,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.index', ['tab' => 'engineer_parts']));
        $response->assertStatus(200);
        $response->assertSee('Ali Khan');
        $response->assertSee('1 In-TAT');
        $response->assertSee('1 Overdue');
        $response->assertSee('50%'); // 1 in-tat out of 2 = 50%
        $response->assertSee('SN-ALI-MACHINE');
    }

    public function test_csv_exports_for_detailed_reports(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-CSV-1',
            'bank_name' => 'Faysal Bank',
            'branch_name' => 'Main',
            'branch_location' => 'Gujranwala',
            'machine_serial_no' => 'SN-CSV-99',
            'status' => 'resolved',
            'created_at' => Carbon::now(),
        ]);

        $pr = PartRequest::create([
            'request_number' => 'PR-CSV-1',
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'machine_serial_no' => 'SN-CSV-99',
            'fault_description' => 'Belt split',
            'status' => 'approved',
            'created_at' => Carbon::now(),
        ]);

        PartRequestItem::create([
            'part_request_id' => $pr->id,
            'part_id' => $this->part->id,
            'qty_requested' => 1,
            'qty_approved' => 1,
            'unit_cost' => 3500.00,
        ]);

        // Bank Tickets CSV
        $resBank = $this->actingAs($this->adminUser)->get(route('reports.export-csv', ['tab' => 'bank_tickets', 'type' => 'bank_tickets', 'preset' => 'all']));
        $resBank->assertStatus(200);
        $contentBank = $resBank->streamedContent();
        $this->assertStringContainsString('CMP-CSV-1', $contentBank);
        $this->assertStringContainsString('Faysal Bank', $contentBank);

        // Machine Faults CSV
        $resFault = $this->actingAs($this->adminUser)->get(route('reports.export-csv', ['tab' => 'machine_faults', 'type' => 'machine_faults', 'preset' => 'all']));
        $resFault->assertStatus(200);
        $contentFault = $resFault->streamedContent();
        $this->assertStringContainsString('PR-CSV-1', $contentFault);
        $this->assertStringContainsString('SN-CSV-99', $contentFault);
        $this->assertStringContainsString('Belt split', $contentFault);

        // Engineer Parts CSV
        $resEng = $this->actingAs($this->adminUser)->get(route('reports.export-csv', ['tab' => 'engineer_parts', 'type' => 'engineer_parts', 'preset' => 'all']));
        $resEng->assertStatus(200);
        $contentEng = $resEng->streamedContent();
        $this->assertStringContainsString('PR-CSV-1', $contentEng);
        $this->assertStringContainsString('Ali Khan', $contentEng);
    }

    public function test_bank_ticket_audit_metrics_excludes_weekends_transit_and_approval_with_daily_feedbacks_in_csv(): void
    {
        // Saturday 26 Sep 09:45
        $receivedAt = Carbon::parse('2026-09-26 09:45:00');
        // Sunday 27 Sep 10:00 to 14:30 (4.5h transit in)
        $workshopDispatched = Carbon::parse('2026-09-27 10:00:00');
        $workshopReceived = Carbon::parse('2026-09-27 14:30:00');
        // Monday 28 Sep 15:30 to Tuesday 29 Sep 11:00 (19.5h return transit)
        $returnDispatched = Carbon::parse('2026-09-28 15:30:00');
        $bankReceived = Carbon::parse('2026-09-29 11:00:00');
        // Tuesday 29 Sep 12:30 resolved
        $resolvedAt = Carbon::parse('2026-09-29 12:30:00');

        $ticket = Ticket::create([
            'ticket_no' => 'CMP-AUDIT-WEEKEND-01',
            'bank_name' => 'Bank Alfalah',
            'branch_name' => 'Liberty',
            'branch_location' => 'Lahore',
            'urgency' => 'medium',
            'status' => 'resolved',
            'created_at' => $receivedAt,
            'sla_deadline' => Carbon::parse('2026-09-27 18:00:00'),
            'workshop_dispatched_at' => $workshopDispatched,
            'workshop_received_at' => $workshopReceived,
            'return_dispatched_at' => $returnDispatched,
            'bank_received_at' => $bankReceived,
            'resolved_at' => $resolvedAt,
            'resolution_summary' => 'Bench overhaul complete, operational at teller.',
        ]);
        $ticket->created_at = $receivedAt;
        $ticket->save();

        TicketFeedback::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'submitted_by_id' => $this->engineerAli->id,
            'day_number' => 1,
            'feedback_text' => 'Day 1: Dispatched to workshop.',
            'action_taken' => 'Prepared safe transport case.',
            'submitted_at' => Carbon::parse('2026-09-27 10:15:00'),
        ]);

        TicketFeedback::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'submitted_by_id' => $this->engineerAli->id,
            'day_number' => 2,
            'feedback_text' => 'Day 2: Overhaul in progress.',
            'action_taken' => 'Tested bearings on bench.',
            'submitted_at' => Carbon::parse('2026-09-28 11:00:00'),
        ]);

        TicketFeedback::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'submitted_by_id' => $this->engineerAli->id,
            'day_number' => 3,
            'feedback_text' => 'Day 3: Re-installed and handed over.',
            'action_taken' => 'Teller signoff obtained.',
            'submitted_at' => Carbon::parse('2026-09-29 12:00:00'),
        ]);

        $metrics = $ticket->calculateAuditTrailMetrics();

        // Gross duration: 74.75 hours (from Sat 09:45 to Tue 12:30)
        $this->assertEquals(74.8, $metrics['gross_hours']);
        // Weekend hours: 38.2 hours (Sat 09:45 to Sun 23:59:59)
        $this->assertEquals(38.2, $metrics['weekend_hours']);
        // Transit hours total: 24.0h (4.5h in + 19.5h out)
        $this->assertEquals(24.0, $metrics['transit_hours']);
        // Net hours: 12.5 working hours (74.8 gross - 38.2 weekend - 24.0 transit)
        $this->assertEquals(12.5, $metrics['net_hours']);
        // In-TAT compliance based on net working hours vs SLA
        $this->assertTrue($metrics['is_in_tat']);

        // Verify web UI view displays the metrics
        $response = $this->actingAs($this->adminUser)->get(route('reports.index', ['tab' => 'bank_tickets']));
        $response->assertStatus(200);
        $response->assertSee('CMP-AUDIT-WEEKEND-01');
        $response->assertSee('Total Lifecycle Turnaround Time (TAT)');
        $response->assertSee('Net Business Time:');
        $response->assertSee($metrics['net_formatted']);
        $response->assertSee('In-TAT Compliant');

        // Verify CSV export includes daily feedbacks and net duration columns
        $resCsv = $this->actingAs($this->adminUser)->get(route('reports.export-csv', ['tab' => 'bank_tickets', 'type' => 'bank_tickets', 'preset' => 'all']));
        $resCsv->assertStatus(200);
        $content = $resCsv->streamedContent();

        $this->assertStringContainsString('CMP-AUDIT-WEEKEND-01', $content);
        $this->assertStringContainsString('Gross Elapsed Time (Hours)', $content);
        $this->assertStringContainsString('Weekend Non-Working Deducted (Hours)', $content);
        $this->assertStringContainsString('Net SLA Business Resolution Time (Hours)', $content);
        $this->assertStringContainsString('Daily Feedbacks Complete Log (Day-by-Day Until Resolved)', $content);
        $this->assertStringContainsString('Day 1: Dispatched to workshop.', $content);
        $this->assertStringContainsString('Day 2: Overhaul in progress.', $content);
        $this->assertStringContainsString('Day 3: Re-installed and handed over.', $content);
    }

    public function test_machine_faults_tab_filters_by_machine_model_selection(): void
    {
        $modelB = MachineModel::create([
            'name' => 'NCR SelfServ 6684',
            'manufacturer' => 'NCR',
            'machine_type' => 'atm',
        ]);

        $ticketA = Ticket::create([
            'ticket_no' => 'CMP-PR-A',
            'bank_name' => 'Bank A',
            'branch_name' => 'Branch A',
            'branch_location' => 'Lahore',
            'urgency' => 'medium',
            'status' => 'open',
        ]);

        $ticketB = Ticket::create([
            'ticket_no' => 'CMP-PR-B',
            'bank_name' => 'Bank B',
            'branch_name' => 'Branch B',
            'branch_location' => 'Karachi',
            'urgency' => 'high',
            'status' => 'open',
        ]);

        $prA = PartRequest::create([
            'request_number' => 'PR-FILTER-A',
            'ticket_id' => $ticketA->id,
            'engineer_id' => $this->engineerAli->id,
            'machine_model_id' => $this->model->id,
            'machine_serial_no' => 'HYO-SER-01',
            'fault_description' => 'Hyosung roller jam',
            'status' => 'pending_approval',
        ]);

        $prB = PartRequest::create([
            'request_number' => 'PR-FILTER-B',
            'ticket_id' => $ticketB->id,
            'engineer_id' => $this->engineerAli->id,
            'machine_model_id' => $modelB->id,
            'machine_serial_no' => 'NCR-SER-02',
            'fault_description' => 'NCR cash shutter broken',
            'status' => 'pending_approval',
        ]);

        // Unfiltered index shows both
        $responseAll = $this->actingAs($this->adminUser)->get(route('reports.index', ['tab' => 'machine_faults']));
        $responseAll->assertStatus(200);
        $responseAll->assertSee('HYO-SER-01');
        $responseAll->assertSee('NCR-SER-02');
        $responseAll->assertSee('Hyosung 8600');
        $responseAll->assertSee('NCR SelfServ 6684');

        // Filtered by modelB only
        $responseFilterB = $this->actingAs($this->adminUser)->get(route('reports.index', [
            'tab' => 'machine_faults',
            'machine_model_id' => $modelB->id,
        ]));
        $responseFilterB->assertStatus(200);
        $responseFilterB->assertSee('NCR-SER-02');
        $responseFilterB->assertDontSee('HYO-SER-01');

        // CSV export with machine_model_id filter
        $resCsv = $this->actingAs($this->adminUser)->get(route('reports.export-csv', [
            'type' => 'machine_faults',
            'machine_model_id' => $modelB->id,
        ]));
        $resCsv->assertStatus(200);
        $csvText = $resCsv->streamedContent();
        $this->assertStringContainsString('NCR-SER-02', $csvText);
        $this->assertStringNotContainsString('HYO-SER-01', $csvText);
    }

    public function test_escalated_tickets_view_displays_audit_trail_and_missing_day_feedback_as_na(): void
    {
        $landedAt = Carbon::now()->subDays(3)->setHour(9)->setMinute(0);
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-ESCALATE-TEST-01',
            'customer_ref_no' => 'NBP-ESC-01',
            'bank_name' => 'National Bank of Pakistan',
            'branch_name' => 'Main Mall Branch',
            'branch_location' => 'Lahore',
            'machine_type' => 'ATM',
            'machine_serial_no' => 'NCR-TEST-88',
            'urgency' => 'high',
            'status' => 'escalated',
            'assigned_engineer_id' => $this->engineerAli->id,
            'email_subject' => 'URGENT: ATM Dispenser Down at NBP Mall Branch',
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subDays(3)->setHour(9)->setMinute(30),
            'sla_deadline' => Carbon::now()->subDay(), // Breached
            'escalated_at' => Carbon::now()->subHours(12),
        ]);
        $ticket->created_at = $landedAt;
        $ticket->save();

        // Feedbacks: Only Day 1 is logged; Day 2 and Day 3 are missing!
        TicketFeedback::create([
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineerAli->id,
            'submitted_by_id' => $this->engineerAli->id,
            'day_number' => 1,
            'feedback_text' => 'Reached site, inspected dispenser motor.',
            'action_taken' => 'Tested diagnostics code 0x44.',
            'submitted_at' => Carbon::now()->subDays(3)->setHour(12)->setMinute(0),
        ]);

        // Model matrix methods verification
        $matrix = $ticket->getDailyFeedbackMatrix();
        $this->assertCount(4, $matrix); // Day 1, Day 2, Day 3, Day 4 elapsed
        $this->assertTrue($matrix[0]['is_logged']);
        $this->assertEquals('Reached site, inspected dispenser motor.', $matrix[0]['feedback_text']);

        // Day 2 must be missing with N/A
        $this->assertFalse($matrix[1]['is_logged']);
        $this->assertEquals('missing', $matrix[1]['status']);
        $this->assertEquals('N/A', $matrix[1]['feedback_text']);

        // Email landing audit
        $audit = $ticket->getEmailLandingAudit();
        $this->assertEquals($landedAt->format('d M Y, h:i A'), $audit['landed_at_formatted']);
        $this->assertStringContainsString('days', $audit['elapsed_to_now']);

        // Verify Escalation Command Center View (/tickets/escalations)
        $respEsc = $this->actingAs($this->adminUser)->get(route('tickets.escalations'));
        $respEsc->assertStatus(200);
        $respEsc->assertSee('CMP-ESCALATE-TEST-01');
        $respEsc->assertSee('Bank Email Landing Time:');
        $respEsc->assertSee('elapsed to NOW');
        $respEsc->assertSee('Day 2 : N/A');
        $respEsc->assertSee('MISSING FEEDBACK');

        // Verify Ticket Detail Page View (/tickets/{id})
        $respShow = $this->actingAs($this->adminUser)->get(route('tickets.show', $ticket));
        $respShow->assertStatus(200);
        $respShow->assertSee('CMP-ESCALATE-TEST-01');
        $respShow->assertSee('Complete Lifecycle Audit Trail &amp; Intake Latency', false);
        $respShow->assertSee('Day 2 : N/A');
        $respShow->assertSee('Missing Daily Progress Feedback');
    }

    public function test_pdf_and_csv_reports_export_cleanly_without_unwanted_company_headers(): void
    {
        // 1. Verify Bank Tickets PDF Export
        $respPdfBank = $this->actingAs($this->adminUser)->get(route('reports.export-pdf', ['type' => 'bank_tickets']));
        $respPdfBank->assertStatus(200);
        $this->assertEquals('application/pdf', $respPdfBank->headers->get('Content-Type'));
        $contentPdfBank = $respPdfBank->getContent();
        $this->assertStringNotContainsString('Suit 402, Commercial Plaza, Lahore, Pakistan', $contentPdfBank);
        $this->assertStringNotContainsString('Service Delivery, SLA Enforcement', $contentPdfBank);

        // 2. Verify Machine Faults PDF Export
        $respPdfFault = $this->actingAs($this->adminUser)->get(route('reports.export-pdf', ['type' => 'machine_faults']));
        $respPdfFault->assertStatus(200);
        $this->assertEquals('application/pdf', $respPdfFault->headers->get('Content-Type'));
        $contentPdfFault = $respPdfFault->getContent();
        $this->assertStringNotContainsString('Suit 402, Commercial Plaza, Lahore, Pakistan', $contentPdfFault);

        // 3. Verify Bank Tickets CSV Export starts directly with column headers
        $respCsvBank = $this->actingAs($this->adminUser)->get(route('reports.export-csv', ['tab' => 'bank_tickets', 'type' => 'bank_tickets']));
        $respCsvBank->assertStatus(200);
        $contentCsvBank = $respCsvBank->streamedContent();
        $this->assertStringNotContainsString('Suit 402, Commercial Plaza, Lahore, Pakistan', $contentCsvBank);
        $this->assertStringNotContainsString('Service Delivery, SLA Enforcement', $contentCsvBank);
        $this->assertStringContainsString('Ticket #', $contentCsvBank);

        // 4. Verify Machine Faults CSV Export
        $respCsvFault = $this->actingAs($this->adminUser)->get(route('reports.export-csv', ['tab' => 'machine_faults', 'type' => 'machine_faults']));
        $respCsvFault->assertStatus(200);
        $contentCsvFault = $respCsvFault->streamedContent();
        $this->assertStringNotContainsString('Suit 402, Commercial Plaza, Lahore, Pakistan', $contentCsvFault);
        $this->assertStringContainsString('Claim Requisition #', $contentCsvFault);

        // 5. Verify Live Search on Bank Tickets Report Tab
        $respBankReport = $this->actingAs($this->adminUser)->get(route('reports.index', ['tab' => 'bank_tickets']));
        $respBankReport->assertStatus(200);
        $respBankReport->assertSee('id="bankAuditLiveSearch"', false);

        // 6. Verify Escalations table has scroll and View Daily Feedback modal button
        $escTicket = Ticket::create([
            'ticket_no' => 'CMP-ESC-MODAL-01',
            'bank_name' => 'Bank Alfalah',
            'branch_location' => 'Islamabad',
            'status' => 'escalated',
            'is_escalated' => true,
            'sla_breached' => true,
            'created_at' => Carbon::now()->subDays(3),
        ]);
        TicketFeedback::create([
            'ticket_id' => $escTicket->id,
            'engineer_id' => $this->engineerAli->id,
            'submitted_by_id' => $this->engineerAli->id,
            'day_number' => 1,
            'feedback_text' => 'Inspected and cleaned optics.',
            'action_taken' => 'Cleaned sensor glasses.',
            'submitted_at' => Carbon::now()->subDays(2),
        ]);

        $respEsc = $this->actingAs($this->adminUser)->get(route('tickets.escalations'));
        $respEsc->assertStatus(200);
        $respEsc->assertSee('overflow-x-auto overflow-y-auto max-h-[720px]', false);
        $respEsc->assertSee('View Daily Feedback');
        $respEsc->assertSee('openFeedbackModal');
        $respEsc->assertSee('feedback-modal-' . $escTicket->id);
    }
}
