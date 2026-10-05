<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReportsDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->orWhere('role', 'super_admin')->first();
        $ali = User::where('email', 'ali@banksupport.com')->first();
        $usman = User::where('email', 'usman@banksupport.com')->first();
        $supervisor = User::where('email', 'supervisor@banksupport.com')->first();
        $model = MachineModel::first();
        $model2 = MachineModel::skip(1)->first() ?? $model;
        $model3 = MachineModel::skip(2)->first() ?? $model;
        $location = Location::first();
        $parts = Part::take(4)->get();

        if (!$ali || !$usman) {
            return;
        }

        // 1. TICKET 1: In-TAT Resolution with Multi-Day Daily Feedbacks & Dispatched Part
        $t1 = Ticket::updateOrCreate(
            ['ticket_no' => 'CMP-2026-AUDIT-01'],
            [
                'customer_ref_no' => 'HBL-LOG-9921',
                'bank_name' => 'Habib Bank Limited (HBL)',
                'branch_name' => 'Mall Road Branch',
                'branch_location' => 'Lahore',
                'branch_address' => '45 The Mall Road, Commercial Zone, Lahore',
                'customer_name' => 'Tariq Mehmood (Branch Ops Manager)',
                'customer_mobile' => '0301-8844221',
                'customer_email' => 'ops.mallroad@hbl.com',
                'machine_type' => 'Cash Recycler (CRM)',
                'machine_model' => $model?->model_name ?? 'Hyosung Monimax 8600',
                'machine_serial_no' => 'HYO-MM-4412',
                'urgency' => 'high',
                'status' => 'resolved',
                'issue_summary' => 'Note Jamming in Currency Presenter Slot & Roller Degradation',
                'issue_description' => 'Dear Support Desk,\n\nOur cash deposit machine CRM-01 at Mall Road branch is intermittently rejecting fresh 5000 and 1000 notes with sensor error code E-409.\nPlease dispatch field engineer urgently.\n\nRegards,\nHBL Operations',
                'assigned_engineer_id' => $ali->id,
                'assigned_by_id' => $admin?->id,
                'assigned_at' => Carbon::now()->subDays(2)->setHour(9)->setMinute(15),
                'email_subject' => 'URGENT: ATM Dispenser Note Jam at Mall Road Branch',
                'email_assignment_sent' => true,
                'email_assignment_sent_at' => Carbon::now()->subDays(2)->setHour(9)->setMinute(22),
                'email_assignment_sent_by_id' => $admin?->id,
                'whatsapp_notified' => true,
                'whatsapp_notified_at' => Carbon::now()->subDays(2)->setHour(9)->setMinute(18),
                'whatsapp_notified_by_id' => $admin?->id,
                'resolution_email_sent' => true,
                'resolution_email_sent_at' => Carbon::now()->subDays(1)->setHour(11)->setMinute(35),
                'resolution_email_sent_by_id' => $admin?->id,
                'resolution_email_to' => 'ops.mallroad@hbl.com',
                'sla_deadline' => Carbon::now()->subDays(1)->setHour(14)->setMinute(0),
                'resolved_at' => Carbon::now()->subDays(1)->setHour(11)->setMinute(30), // In-TAT!
                'resolution_summary' => 'Replaced pick-up roller assembly and transport belt. Conducted 50-note dispense test successfully.',
                'created_at' => Carbon::now()->subDays(2)->setHour(9)->setMinute(0),
                'updated_at' => Carbon::now()->subDays(1)->setHour(11)->setMinute(35),
            ]
        );

        // Daily Feedbacks for Ticket 1
        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t1->id, 'day_number' => 1],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'Reached branch at 10:15 AM. Found presenter belt split and feeding rollers worn out.',
                'action_taken' => 'Cleaned transport path, created emergency part requisition for roller assembly.',
                'parts_required' => 'Pick-up Roller Unit & Transport Belt',
                'eta_completion' => Carbon::now()->subDays(1)->setHour(12)->setMinute(0),
                'status' => 'parts_required',
                'submitted_at' => Carbon::now()->subDays(2)->setHour(11)->setMinute(30),
            ]
        );

        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t1->id, 'day_number' => 2],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'Installed new roller unit and tested 50 notes deposit and dispensing. Functioning smoothly.',
                'action_taken' => 'Replaced module, calibrated optical sensors, signed JVC service handover voucher.',
                'parts_required' => 'None',
                'status' => 'completed',
                'submitted_at' => Carbon::now()->subDays(1)->setHour(11)->setMinute(15),
            ]
        );

        // Ticket 1 Part Request with real video
        $pr1 = PartRequest::updateOrCreate(
            ['request_number' => 'PR-2026-AUDIT-01'],
            [
                'ticket_id' => $t1->id,
                'engineer_id' => $ali->id,
                'machine_model_id' => $model?->id ?? 1,
                'machine_serial_no' => 'HYO-MM-4412',
                'fault_description' => 'Heavy roller jitter causing frequent bank note jam in presenter gate.',
                'status' => 'dispatched',
                'fault_video_path' => 'part-requests/videos/0jrsvjDnEZ4O2V1JJisFND1Woam7YcXYfwd880VK.mp4',
                'stock_verified_by_id' => $admin?->id,
                'stock_verified_at' => Carbon::now()->subDays(2)->setHour(12)->setMinute(0),
                'approved_by_id' => $admin?->id,
                'approved_at' => Carbon::now()->subDays(2)->setHour(12)->setMinute(30),
                'dispatched_by_id' => $admin?->id,
                'dispatched_at' => Carbon::now()->subDays(2)->setHour(14)->setMinute(0),
                'dispatch_location_id' => $location?->id ?? 1,
                'dispatch_courier' => 'TCS Express',
                'dispatch_tracking_number' => 'TCS-99214488',
                'created_at' => Carbon::now()->subDays(2)->setHour(11)->setMinute(35),
            ]
        );

        if ($parts->count() > 0) {
            PartRequestItem::updateOrCreate(
                ['part_request_id' => $pr1->id, 'part_id' => $parts[0]->id],
                [
                    'qty_requested' => 1,
                    'qty_approved' => 1,
                    'qty_dispatched' => 1,
                    'unit_cost' => 4500.00,
                    'note' => 'Main Roller Assembly',
                ]
            );
        }

        // Ticket 1 Logs
        TicketLog::create([
            'ticket_id' => $t1->id,
            'user_id' => $admin?->id,
            'action' => 'ingest_received',
            'notes' => 'Complaint received via GoDaddy Webmail from ops.mallroad@hbl.com',
            'created_at' => Carbon::now()->subDays(2)->setHour(9)->setMinute(0),
        ]);
        TicketLog::create([
            'ticket_id' => $t1->id,
            'user_id' => $admin?->id,
            'action' => 'engineer_assigned',
            'notes' => 'Assigned to Field Engineer Ali Khan (Lahore). Acknowledgment email sent to bank.',
            'created_at' => Carbon::now()->subDays(2)->setHour(9)->setMinute(22),
        ]);


        // 2. TICKET 2: Out-of-TAT with Approval Pause & Urgent Solenoid Part
        $t2 = Ticket::updateOrCreate(
            ['ticket_no' => 'CMP-2026-AUDIT-02'],
            [
                'customer_ref_no' => 'MEEZAN-CR-771',
                'bank_name' => 'Meezan Bank Ltd',
                'branch_name' => 'Clifton Branch',
                'branch_location' => 'Karachi',
                'branch_address' => 'Plot 12, Block 4, Clifton, Karachi',
                'customer_name' => 'Saeed Anwar (Cash In-Charge)',
                'customer_mobile' => '0321-9955331',
                'customer_email' => 'clifton.ops@meezanbank.com',
                'machine_type' => 'Cash Sorter',
                'machine_model' => $model?->model_name ?? 'Glory USF-52',
                'machine_serial_no' => 'GLY-USF-9921',
                'urgency' => 'critical',
                'status' => 'resolved',
                'issue_summary' => 'Shutter Solenoid Failure & Main Power Board Short Circuit',
                'issue_description' => 'Shutter mechanism jammed shut with customer cash inside. Board tripped.',
                'assigned_engineer_id' => $usman->id,
                'assigned_by_id' => $admin?->id,
                'assigned_at' => Carbon::now()->subDays(3)->setHour(8)->setMinute(30),
                'email_subject' => 'CRITICAL: Sorter Lockup at Clifton Branch',
                'email_assignment_sent' => true,
                'email_assignment_sent_at' => Carbon::now()->subDays(3)->setHour(8)->setMinute(35),
                'sla_deadline' => Carbon::now()->subDays(3)->setHour(10)->setMinute(30), // 2h SLA
                'approval_requested_at' => Carbon::now()->subDays(3)->setHour(9)->setMinute(15),
                'approval_requested_by_id' => $usman->id,
                'approval_source' => 'Regional Superior',
                'approval_request_reason' => 'Off-contract emergency solenoid replacement required special budget sanction.',
                'sla_paused_at' => Carbon::now()->subDays(3)->setHour(9)->setMinute(15),
                'approval_paused_seconds' => 7200, // 2 Hours paused
                'approval_arrived_at' => Carbon::now()->subDays(3)->setHour(11)->setMinute(15),
                'approval_arrived_by_id' => $supervisor?->id,
                'approval_arrived_remarks' => 'Approved under emergency critical SLA provision.',
                'resolved_at' => Carbon::now()->subDays(3)->setHour(14)->setMinute(0), // Out-of-TAT!
                'resolution_summary' => 'Recovered trapped cash with branch manager present. Solenoid replaced and machine tested.',
                'resolution_email_sent' => true,
                'resolution_email_sent_at' => Carbon::now()->subDays(3)->setHour(14)->setMinute(10),
                'resolution_email_to' => 'clifton.ops@meezanbank.com',
                'created_at' => Carbon::now()->subDays(3)->setHour(8)->setMinute(0),
                'updated_at' => Carbon::now()->subDays(3)->setHour(14)->setMinute(10),
            ]
        );

        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t2->id, 'day_number' => 1],
            [
                'engineer_id' => $usman->id,
                'submitted_by_id' => $usman->id,
                'feedback_text' => 'Inspected machine. Solenoid locked in closed state. Paused work pending superior approval.',
                'action_taken' => 'Safe cash recovery completed with branch manager.',
                'parts_required' => 'Shutter Solenoid & Sensor Cable',
                'status' => 'in_progress',
                'submitted_at' => Carbon::now()->subDays(3)->setHour(9)->setMinute(20),
            ]
        );

        $pr2 = PartRequest::updateOrCreate(
            ['request_number' => 'PR-2026-AUDIT-02'],
            [
                'ticket_id' => $t2->id,
                'engineer_id' => $usman->id,
                'machine_model_id' => $model?->id ?? 1,
                'machine_serial_no' => 'GLY-USF-9921',
                'fault_description' => 'Solenoid coil burned out; video shows shutter seizing upon power cycle.',
                'status' => 'approved',
                'fault_video_path' => 'part-requests/videos/1NqncM2Q2Xv9gyVXMP93sP4c9wmACw2zkdpSPebs.mp4',
                'stock_verified_by_id' => $admin?->id,
                'stock_verified_at' => Carbon::now()->subDays(3)->setHour(10)->setMinute(0),
                'approved_by_id' => $supervisor?->id,
                'approved_at' => Carbon::now()->subDays(3)->setHour(11)->setMinute(15),
                'created_at' => Carbon::now()->subDays(3)->setHour(9)->setMinute(25),
            ]
        );

        if ($parts->count() > 1) {
            PartRequestItem::updateOrCreate(
                ['part_request_id' => $pr2->id, 'part_id' => $parts[1]->id],
                [
                    'qty_requested' => 2,
                    'qty_approved' => 2,
                    'qty_dispatched' => 0,
                    'unit_cost' => 2800.00,
                    'note' => 'Optical Position Sensor',
                ]
            );
        }


        // 3. TICKET 3: Full Central Workshop Round-Trip Lifecycle
        $t3 = Ticket::updateOrCreate(
            ['ticket_no' => 'CMP-2026-AUDIT-03'],
            [
                'customer_ref_no' => 'MCB-WS-1102',
                'bank_name' => 'MCB Bank Limited',
                'branch_name' => 'Gulberg III Branch',
                'branch_location' => 'Lahore',
                'branch_address' => 'Main Boulevard Gulberg, Lahore',
                'customer_name' => 'Shahid Nadeem',
                'customer_mobile' => '0300-5544112',
                'customer_email' => 'gulberg.mcb@mcb.com.pk',
                'machine_type' => 'Banknote Sorter',
                'machine_model' => $model?->model_name ?? 'Kisan Newton',
                'machine_serial_no' => 'KIS-NT-7823',
                'urgency' => 'medium',
                'status' => 'resolved',
                'issue_summary' => 'Severe Stacker Jamming & Internal Motor Overheating',
                'issue_description' => 'Unit making loud grinding noise from stacker gear box. Requires workshop overhaul.',
                'assigned_engineer_id' => $ali->id,
                'original_field_engineer_id' => $ali->id,
                'assigned_by_id' => $admin?->id,
                'assigned_at' => Carbon::now()->subDays(5)->setHour(10)->setMinute(0),
                'email_subject' => 'COMPLAINT: Banknote Sorter Overheating at Gulberg MCB',
                'email_assignment_sent' => true,
                'email_assignment_sent_at' => Carbon::now()->subDays(5)->setHour(10)->setMinute(15),
                'sla_deadline' => Carbon::now()->subDays(4)->setHour(18)->setMinute(0),
                // Workshop Lifecycle Fields:
                'workshop_location' => 'Lahore Central Workshop Hub',
                'workshop_dispatch_courier' => 'TCS Logistics Cargo',
                'workshop_dispatch_tracking' => 'TCS-CARGO-9918231',
                'workshop_dispatched_at' => Carbon::now()->subDays(4)->setHour(10)->setMinute(0),
                'workshop_dispatch_notes' => 'Dispatched via TCS heavy cargo with foam padding and security seal #8812.',
                'workshop_received_at' => Carbon::now()->subDays(4)->setHour(14)->setMinute(30), // Transit in: 4.5 hours
                'workshop_received_by_id' => $admin?->id,
                'workshop_intake_remarks' => 'Intake inspection complete. Seal intact. Broken gearbox bearing confirmed.',
                'workshop_repaired_at' => Carbon::now()->subDays(3)->setHour(12)->setMinute(0),
                'workshop_repair_summary' => 'Bench technician dismantled stacker assembly, replaced 4 bearings, lubricated gears, ran 2000-note stress test.',
                'return_courier' => 'TCS Logistics Cargo',
                'return_tracking_number' => 'TCS-RET-9918999',
                'return_dispatched_at' => Carbon::now()->subDays(3)->setHour(15)->setMinute(30),
                'return_dispatched_by_id' => $admin?->id,
                'return_dispatch_notes' => 'Return dispatched to branch. Engineer Ali instructed to supervise delivery.',
                'bank_received_at' => Carbon::now()->subDays(2)->setHour(11)->setMinute(0), // Return transit: 19.5 hours
                'bank_received_confirmed_by_id' => $admin?->id,
                'resolved_at' => Carbon::now()->subDays(2)->setHour(12)->setMinute(30),
                'resolution_summary' => 'Unit received back at branch, bench tested and re-installed into live teller station.',
                'resolution_email_sent' => true,
                'resolution_email_sent_at' => Carbon::now()->subDays(2)->setHour(12)->setMinute(45),
                'resolution_email_to' => 'gulberg.mcb@mcb.com.pk',
                'created_at' => Carbon::now()->subDays(5)->setHour(9)->setMinute(45),
                'updated_at' => Carbon::now()->subDays(2)->setHour(12)->setMinute(45),
            ]
        );

        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t3->id, 'day_number' => 1],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'Gearbox seizing up. Field repair not viable; dispatched unit to Central Workshop.',
                'action_taken' => 'Packaged unit in safe transport case, generated workshop handover slip.',
                'parts_required' => 'Complete Gearbox Overhaul',
                'status' => 'in_progress',
                'submitted_at' => Carbon::now()->subDays(4)->setHour(10)->setMinute(15),
            ]
        );

        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t3->id, 'day_number' => 2],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'Unit under repair at Central Workshop. Bench test ongoing.',
                'action_taken' => 'Coordinated with workshop supervisor.',
                'status' => 'in_progress',
                'submitted_at' => Carbon::now()->subDays(3)->setHour(11)->setMinute(0),
            ]
        );

        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t3->id, 'day_number' => 3],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'Machine delivered back to branch. Assisted branch staff with morning batch run. All clear.',
                'action_taken' => 'Final installation and handover signed.',
                'status' => 'completed',
                'submitted_at' => Carbon::now()->subDays(2)->setHour(12)->setMinute(0),
            ]
        );

        $pr3 = PartRequest::updateOrCreate(
            ['request_number' => 'PR-2026-AUDIT-03'],
            [
                'ticket_id' => $t3->id,
                'engineer_id' => $ali->id,
                'machine_model_id' => $model?->id ?? 1,
                'machine_serial_no' => 'KIS-NT-7823',
                'fault_description' => 'Loud gearbox grinding noise. High friction in main shaft bearings.',
                'status' => 'dispatched',
                'fault_video_path' => 'part-requests/videos/5p93SYFdcKsbFSYqe0SuJ6dFkBX500UyDPAMdQDv.mp4',
                'stock_verified_by_id' => $admin?->id,
                'stock_verified_at' => Carbon::now()->subDays(4)->setHour(16)->setMinute(0),
                'approved_by_id' => $admin?->id,
                'approved_at' => Carbon::now()->subDays(4)->setHour(16)->setMinute(30),
                'dispatched_by_id' => $admin?->id,
                'dispatched_at' => Carbon::now()->subDays(3)->setHour(9)->setMinute(0),
                'dispatch_location_id' => $location?->id ?? 1,
                'dispatch_courier' => 'TCS Express',
                'dispatch_tracking_number' => 'TCS-99182399',
                'created_at' => Carbon::now()->subDays(4)->setHour(15)->setMinute(0),
            ]
        );

        if ($parts->count() > 2) {
            PartRequestItem::updateOrCreate(
                ['part_request_id' => $pr3->id, 'part_id' => $parts[2]->id],
                [
                    'qty_requested' => 4,
                    'qty_approved' => 4,
                    'qty_dispatched' => 4,
                    'unit_cost' => 1250.00,
                    'note' => 'Main Drive Bearing',
                ]
            );
        }


        // 4. TICKET 4: Active Ticket (Repeat Serial HYO-MM-4412 for Chronic Machine tracking)
        $t4 = Ticket::updateOrCreate(
            ['ticket_no' => 'CMP-2026-AUDIT-04'],
            [
                'customer_ref_no' => 'UBL-ATM-331',
                'bank_name' => 'United Bank Limited (UBL)',
                'branch_name' => 'Blue Area Branch',
                'branch_location' => 'Islamabad',
                'branch_address' => 'Plot 82, Blue Area, Islamabad',
                'customer_name' => 'Adnan Farooq',
                'customer_mobile' => '0333-5129988',
                'customer_email' => 'bluearea.ops@ubl.com.pk',
                'machine_type' => 'Cash Recycler (CRM)',
                'machine_model' => $model?->model_name ?? 'Hyosung Monimax 8600',
                'machine_serial_no' => 'HYO-MM-4412', // Chronic Repeat!
                'urgency' => 'high',
                'status' => 'in_progress',
                'issue_summary' => 'Second Breakdown within 30 days: Reject Vault Full Sensor Failure',
                'issue_description' => 'ATM rejecting all customer deposits. Vault sensor shows permanent high voltage trigger.',
                'assigned_engineer_id' => $ali->id,
                'assigned_by_id' => $admin?->id,
                'assigned_at' => Carbon::now()->subHours(8),
                'email_subject' => 'URGENT: Reject Vault Error at UBL Blue Area',
                'email_assignment_sent' => true,
                'email_assignment_sent_at' => Carbon::now()->subHours(7)->setMinute(45),
                'sla_deadline' => Carbon::now()->addHours(2),
                'created_at' => Carbon::now()->subHours(8),
            ]
        );

        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t4->id, 'day_number' => 1],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'Vault sensor wire harness pinched behind lower safe door hinge.',
                'action_taken' => 'Inspected harness, ordered replacement wire assembly.',
                'parts_required' => 'Vault Sensor Wire Harness',
                'status' => 'in_progress',
                'submitted_at' => Carbon::now()->subHours(4),
            ]
        );

        $pr4 = PartRequest::updateOrCreate(
            ['request_number' => 'PR-2026-AUDIT-04'],
            [
                'ticket_id' => $t4->id,
                'engineer_id' => $ali->id,
                'machine_model_id' => $model?->id ?? 1,
                'machine_serial_no' => 'HYO-MM-4412',
                'fault_description' => 'Vault sensor wire pinched; shorts out when safe door swings closed.',
                'status' => 'pending_approval',
                'fault_video_path' => 'part-requests/videos/8JHTSl9wZRglRiWSdTu5dNTBVE8NIPWdxPAEPTcv.mp4',
                'stock_verified_by_id' => $admin?->id,
                'stock_verified_at' => Carbon::now()->subHours(3),
                'created_at' => Carbon::now()->subHours(4),
            ]
        );

        if ($parts->count() > 0) {
            PartRequestItem::updateOrCreate(
                ['part_request_id' => $pr4->id, 'part_id' => $parts[0]->id],
                [
                    'qty_requested' => 1,
                    'qty_approved' => 0,
                    'qty_dispatched' => 0,
                    'unit_cost' => 3200.00,
                    'note' => 'Sensor Harness Kit',
                ]
            );
        }

        // 5. TICKET 5: Escalated Ticket with Missing Day 2 & Day 4 Feedbacks (Audit Trail Verification)
        $t5CreatedAt = Carbon::now()->subDays(4)->setHour(9)->setMinute(15);
        $t5 = Ticket::updateOrCreate(
            ['ticket_no' => 'CMP-2026-ESCALATE-01'],
            [
                'customer_ref_no' => 'NBP-ESC-9011',
                'bank_name' => 'National Bank of Pakistan (NBP)',
                'branch_name' => 'Civil Lines Branch',
                'branch_location' => 'Lahore',
                'branch_address' => 'The Mall Road, Civil Lines, Lahore',
                'customer_name' => 'Muhammad Usman (Chief Manager)',
                'customer_mobile' => '0300-1122334',
                'customer_email' => 'nbp.civillines@nbp.com.pk',
                'customer_cc' => 'ops.punjab@nbp.com.pk',
                'machine_type' => 'Automated Teller Machine (ATM)',
                'machine_model' => $model2?->model_name ?? 'NCR SelfServ 6684',
                'machine_serial_no' => 'NCR-SS-9941',
                'urgency' => 'high',
                'status' => 'escalated',
                'issue_summary' => 'Cash dispenser jammed; error code 4402 - Cash Out sensor failure',
                'issue_description' => "Emergency complaint received from NBP Civil Lines.\nCash out sensor is completely unresponsive, teller transactions halted. Awaiting superior intervention.",
                'assigned_engineer_id' => $ali->id,
                'assigned_by_id' => $admin?->id,
                'assigned_at' => Carbon::now()->subDays(4)->setHour(10)->setMinute(0),
                'email_subject' => 'URGENT: ATM Dispenser Failure at NBP Civil Lines',
                'email_assignment_sent' => true,
                'email_assignment_sent_at' => Carbon::now()->subDays(4)->setHour(10)->setMinute(25),
                'email_assignment_sent_by_id' => $admin?->id,
                'sla_deadline' => Carbon::now()->subDays(2)->setHour(18)->setMinute(0), // Breached 2 days ago!
                'escalated_at' => Carbon::now()->subDays(1)->setHour(14)->setMinute(0),
                'escalated_to_id' => $supervisor?->id ?? $admin?->id,
                'created_at' => $t5CreatedAt,
            ]
        );
        \Illuminate\Support\Facades\DB::table('tickets')->where('id', $t5->id)->update(['created_at' => $t5CreatedAt]);

        // Ticket 5 Logs
        TicketLog::create([
            'ticket_id' => $t5->id,
            'user_id' => $admin?->id,
            'action' => 'ingest_received',
            'notes' => 'Complaint email received from nbp.civillines@nbp.com.pk regarding cash out sensor failure.',
            'created_at' => $t5CreatedAt,
        ]);
        TicketLog::create([
            'ticket_id' => $t5->id,
            'user_id' => $admin?->id,
            'action' => 'engineer_assigned',
            'notes' => 'Assigned to Field Engineer Ali Khan (Lahore). Acknowledgment email sent to bank.',
            'created_at' => Carbon::now()->subDays(4)->setHour(10)->setMinute(25),
        ]);
        TicketLog::create([
            'ticket_id' => $t5->id,
            'user_id' => $supervisor?->id ?? $admin?->id,
            'action' => 'escalated',
            'notes' => 'Escalated due to missing engineer daily updates and critical cash out sensor outage.',
            'created_at' => Carbon::now()->subDays(1)->setHour(14)->setMinute(0),
        ]);

        // Feedbacks for Ticket 5:
        // Day 1: Logged
        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t5->id, 'day_number' => 1],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'ATM reached at 11:30 AM. Dispenser sensor board throwing hardware code 0x44. Attempted cleaning and reseating connector pins.',
                'action_taken' => 'Diagnostic run performed, requested replacement sensor PCB.',
                'parts_required' => 'Dispenser Sensor PCB Module',
                'status' => 'parts_required',
                'submitted_at' => Carbon::now()->subDays(4)->setHour(13)->setMinute(45),
            ]
        );
        // Day 2: NOT LOGGED! -> Will render as "Day 2 : N/A (Missing Daily Feedback)"

        // Day 3: Logged
        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t5->id, 'day_number' => 3],
            [
                'engineer_id' => $ali->id,
                'submitted_by_id' => $ali->id,
                'feedback_text' => 'Revisited site with generic sensor board. Pin layout incompatible with NCR 6684 revision. Machine remains out of service.',
                'action_taken' => 'Escalated to superior for OEM part requisition or central workshop haul.',
                'parts_required' => 'OEM Optical Sensor Assembly (NCR Rev B)',
                'status' => 'escalated',
                'submitted_at' => Carbon::now()->subDays(2)->setHour(16)->setMinute(10),
            ]
        );
        // Day 4: NOT LOGGED! -> Will render as "Day 4 : N/A (Missing Daily Feedback)"

        // Ticket 5 Part Request with video
        $pr5 = PartRequest::updateOrCreate(
            ['request_number' => 'PR-2026-ESCALATE-01'],
            [
                'ticket_id' => $t5->id,
                'engineer_id' => $ali->id,
                'machine_model_id' => $model2?->id ?? $model?->id,
                'machine_serial_no' => 'NCR-SS-9941',
                'fault_description' => 'Optical feed sensor burnt out; presenter gear train slips on cycle.',
                'status' => 'pending_approval',
                'fault_video_path' => 'part-requests/videos/0jrsvjDnEZ4O2V1JJisFND1Woam7YcXYfwd880VK.mp4',
                'stock_verified_by_id' => $admin?->id,
                'stock_verified_at' => Carbon::now()->subDays(3)->setHour(14)->setMinute(0),
                'created_at' => Carbon::now()->subDays(4)->setHour(14)->setMinute(0),
            ]
        );

        if ($parts->count() > 1) {
            PartRequestItem::updateOrCreate(
                ['part_request_id' => $pr5->id, 'part_id' => $parts[1]->id],
                [
                    'qty_requested' => 1,
                    'qty_approved' => 0,
                    'qty_dispatched' => 0,
                    'unit_cost' => 6800.00,
                    'note' => 'Optical Sensor Module Rev B',
                ]
            );
        }

        // 6. TICKET 6: Escalated Ticket with Missing Day 2 & Day 3 Feedbacks
        $t6CreatedAt = Carbon::now()->subDays(3)->setHour(10)->setMinute(0);
        $t6 = Ticket::updateOrCreate(
            ['ticket_no' => 'CMP-2026-ESCALATE-02'],
            [
                'customer_ref_no' => 'ALF-ESC-4409',
                'bank_name' => 'Bank Alfalah',
                'branch_name' => 'Blue Area Branch',
                'branch_location' => 'Islamabad',
                'branch_address' => 'Jinnah Avenue, Blue Area, Islamabad',
                'customer_name' => 'Tariq Mehmood (Branch Ops)',
                'customer_mobile' => '0321-9988776',
                'customer_email' => 'ops.bluearea@bankalfalah.com',
                'machine_type' => 'Banknote Sorter',
                'machine_model' => $model3?->model_name ?? 'Glory USF-51',
                'machine_serial_no' => 'GLY-BA-3312',
                'urgency' => 'high',
                'status' => 'escalated',
                'issue_summary' => 'Banknote Sorter belt snapping & optical drift; multi-day feedback stalled',
                'issue_description' => "Glory USF-51 banknote sorter stopped mid-audit. Feed mechanism unresponsive. Engineer attended Day 1 but subsequent feedback missing.",
                'assigned_engineer_id' => $usman->id,
                'assigned_by_id' => $admin?->id,
                'assigned_at' => Carbon::now()->subDays(3)->setHour(10)->setMinute(15),
                'email_subject' => 'URGENT: Banknote Sorter Offline at Bank Alfalah Blue Area',
                'email_assignment_sent' => true,
                'email_assignment_sent_at' => Carbon::now()->subDays(3)->setHour(10)->setMinute(35),
                'sla_deadline' => Carbon::now()->subDays(1)->setHour(12)->setMinute(0), // Breached 1 day ago!
                'escalated_at' => Carbon::now()->subHours(18),
                'escalated_to_id' => $supervisor?->id ?? $admin?->id,
                'created_at' => $t6CreatedAt,
            ]
        );
        \Illuminate\Support\Facades\DB::table('tickets')->where('id', $t6->id)->update(['created_at' => $t6CreatedAt]);

        // Ticket 6 Logs
        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $admin?->id,
            'action' => 'ingest_received',
            'notes' => 'Complaint received via email from ops.bluearea@bankalfalah.com.',
            'created_at' => $t6CreatedAt,
        ]);
        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $admin?->id,
            'action' => 'engineer_assigned',
            'notes' => 'Assigned to Field Engineer Usman Tariq (Islamabad/North).',
            'created_at' => Carbon::now()->subDays(3)->setHour(10)->setMinute(35),
        ]);
        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $supervisor?->id ?? $admin?->id,
            'action' => 'escalated',
            'notes' => 'SLA deadline breached. Day 2 and Day 3 daily feedback missing from field engineer.',
            'created_at' => Carbon::now()->subHours(18),
        ]);

        // Feedbacks for Ticket 6:
        // Day 1: Logged
        TicketFeedback::updateOrCreate(
            ['ticket_id' => $t6->id, 'day_number' => 1],
            [
                'engineer_id' => $usman->id,
                'submitted_by_id' => $usman->id,
                'feedback_text' => 'Inspected sorter. Found feed belt torn and upper CIS sensor clouded with currency lint.',
                'action_taken' => 'Cleaned sensor glasses, requested replacement drive belt.',
                'parts_required' => 'Sorter Drive Belt (Glory USF)',
                'status' => 'parts_required',
                'submitted_at' => Carbon::now()->subDays(3)->setHour(12)->setMinute(30),
            ]
        );
        // Day 2: NOT LOGGED! -> Will render as "Day 2 : N/A (Missing Daily Feedback)"
        // Day 3: NOT LOGGED! -> Will render as "Day 3 : N/A (Missing Daily Feedback)"

        // Ticket 6 Part Request with video
        $pr6 = PartRequest::updateOrCreate(
            ['request_number' => 'PR-2026-ESCALATE-02'],
            [
                'ticket_id' => $t6->id,
                'engineer_id' => $usman->id,
                'machine_model_id' => $model3?->id ?? $model?->id,
                'machine_serial_no' => 'GLY-BA-3312',
                'fault_description' => 'Drive belt split and sensor calibration corrupted.',
                'status' => 'dispatched',
                'fault_video_path' => 'part-requests/videos/8JHTSl9wZRglRiWSdTu5dNTBVE8NIPWdxPAEPTcv.mp4',
                'stock_verified_by_id' => $admin?->id,
                'stock_verified_at' => Carbon::now()->subDays(2)->setHour(11)->setMinute(0),
                'approved_by_id' => $admin?->id,
                'approved_at' => Carbon::now()->subDays(2)->setHour(12)->setMinute(0),
                'dispatched_by_id' => $admin?->id,
                'dispatched_at' => Carbon::now()->subDays(2)->setHour(15)->setMinute(0),
                'dispatch_courier' => 'Leopards Courier',
                'dispatch_tracking_number' => 'LEO-7721890',
                'created_at' => Carbon::now()->subDays(3)->setHour(12)->setMinute(45),
            ]
        );

        if ($parts->count() > 2) {
            PartRequestItem::updateOrCreate(
                ['part_request_id' => $pr6->id, 'part_id' => $parts[2]->id],
                [
                    'qty_requested' => 1,
                    'qty_approved' => 1,
                    'qty_dispatched' => 1,
                    'unit_cost' => 1850.00,
                    'note' => 'Replacement Drive Belt',
                ]
            );
        }

        echo "ReportsDummyDataSeeder completed successfully: 6 audit tickets, multi-day feedbacks, escalated tickets with missing day feedbacks (N/A), workshop lifecycle, approval wait, part requests and video links created.\n";
    }
}
