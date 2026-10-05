<?php

namespace Database\Seeders;

use App\Models\ExpenseClaim;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with 10 comprehensive tickets and users.
     */
    public function run(): void
    {
        // 0. Ensure Storage Sample Files Exist
        $disk = Storage::disk('public');
        $disk->makeDirectory('resolutions');
        $disk->makeDirectory('vouchers');
        $disk->makeDirectory('reports');

        $dummyPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF\n";
        $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        if (!$disk->exists('resolutions/sample_proof_scb.jpg')) {
            $disk->put('resolutions/sample_proof_scb.jpg', $dummyPng);
        }
        if (!$disk->exists('resolutions/service_report_CMP-2026-00107.pdf')) {
            $disk->put('resolutions/service_report_CMP-2026-00107.pdf', $dummyPdf);
        }
        if (!$disk->exists('resolutions/askari_handover_slip.pdf')) {
            $disk->put('resolutions/askari_handover_slip.pdf', $dummyPdf);
        }
        if (!$disk->exists('resolutions/bop_card_reader_proof.jpg')) {
            $disk->put('resolutions/bop_card_reader_proof.jpg', $dummyPng);
        }
        if (!$disk->exists('vouchers/ali_sahiwal_toll_fuel.pdf')) {
            $disk->put('vouchers/ali_sahiwal_toll_fuel.pdf', $dummyPdf);
        }
        if (!$disk->exists('vouchers/bop_fuel_blurred.jpg')) {
            $disk->put('vouchers/bop_fuel_blurred.jpg', $dummyPng);
        }
        if (!$disk->exists('reports/service_report_CMP-2026-00106.pdf')) {
            $disk->put('reports/service_report_CMP-2026-00106.pdf', $dummyPdf);
        }

        // 1. Core Users
        $superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'phone_whatsapp' => '03000000001',
            'base_city' => 'Lahore',
            'current_city' => 'Lahore',
        ]);

        $admin = User::create([
            'name' => 'Operations Admin',
            'email' => 'ops@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone_whatsapp' => '03000000002',
            'base_city' => 'Lahore',
            'current_city' => 'Lahore',
        ]);

        $superior = User::create([
            'name' => 'Regional Superior (North & Central)',
            'email' => 'supervisor@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'superior',
            'phone_whatsapp' => '03000000003',
            'base_city' => 'Islamabad',
            'current_city' => 'Islamabad',
        ]);

        // 2. Field Engineers
        $ali = User::create([
            'name' => 'Ali Khan',
            'email' => 'ali@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'engineer',
            'phone_whatsapp' => '03001234567',
            'base_city' => 'Lahore',
            'current_city' => 'Lahore',
            'specialization' => 'ATM & CDM Specialist',
            'is_available' => true,
        ]);

        $usman = User::create([
            'name' => 'Usman Tariq',
            'email' => 'usman@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'engineer',
            'phone_whatsapp' => '03219876543',
            'base_city' => 'Karachi',
            'current_city' => 'Karachi',
            'specialization' => 'POS & Kiosk Specialist',
            'is_available' => true,
        ]);

        $hamza = User::create([
            'name' => 'Hamza Ahmed',
            'email' => 'hamza@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'engineer',
            'phone_whatsapp' => '03335551234',
            'base_city' => 'Islamabad',
            'current_city' => 'Islamabad',
            'specialization' => 'ATM Hardware & Dispenser Specialist',
            'is_available' => true,
        ]);

        $bilal = User::create([
            'name' => 'Bilal Raza',
            'email' => 'bilal@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'engineer',
            'phone_whatsapp' => '03454443210',
            'base_city' => 'Multan',
            'current_city' => 'Multan',
            'specialization' => 'Multi-vendor Hardware Specialist',
            'is_available' => true,
        ]);

        $fahad = User::create([
            'name' => 'Fahad Shah',
            'email' => 'fahad@banksupport.com',
            'password' => Hash::make('password'),
            'role' => 'engineer',
            'phone_whatsapp' => '03126789012',
            'base_city' => 'Faisalabad',
            'current_city' => 'Faisalabad',
            'specialization' => 'Network & Software Diagnostics',
            'is_available' => false,
            'is_on_leave' => true,
        ]);

        // 3. 10 Sample Tickets Illustrating Diverse Operational States

        // Ticket 1: Freshly ingested from Bank Email -> UNASSIGNED (Open)
        $t1 = Ticket::create([
            'ticket_no' => 'CMP-2026-00101',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'MCB Bank',
            'branch_name' => 'Gulberg III Main Branch',
            'branch_location' => 'Lahore',
            'branch_address' => 'Main Boulevard, Gulberg III, Lahore',
            'customer_name' => 'Muhammad Rizwan (Branch Manager)',
            'customer_mobile' => '03008455112',
            'customer_email' => 'mcb.gulberg@mcb.com.pk',
            'customer_cc' => 'atm.ops@mcb.com.pk, regional.support@mcb.com.pk',
            'machine_type' => 'ATM',
            'machine_model' => 'Nautilus Hyosung MX5600',
            'machine_serial_no' => 'NH-882910-LH',
            'warranty_status' => 'in_warranty',
            'warranty_expiry' => Carbon::now()->addMonths(8),
            'urgency' => 'high',
            'status' => 'open',
            'issue_summary' => 'Cash dispenser shutter jam; error code 3004. Machine halting customer cash withdrawals.',
            'issue_description' => "Dear Support,\n\nOur ATM machine NH-882910-LH has developed a severe cash shutter jam since 9:00 AM. Cash dispenser is not delivering notes to customers. Kindly assign an engineer immediately.\n\nRegards,\nRizwan",
            'sla_deadline' => Carbon::now()->addHours(4),
            'ai_classified' => true,
        ]);
        TicketLog::create([
            'ticket_id' => $t1->id,
            'user_id' => null,
            'action' => 'created',
            'notes' => 'Ticket auto-created from bank email received from mcb.gulberg@mcb.com.pk. Extracted by Gemini AI.',
        ]);

        // Ticket 2: In Progress -> Assigned to Ali Khan (Lahore) with Healthy SLA (75% Remaining)
        $t2 = Ticket::create([
            'ticket_no' => 'CMP-2026-00102',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'HBL (Habib Bank Limited)',
            'branch_name' => 'Mall Road Corporate Branch',
            'branch_location' => 'Lahore',
            'branch_address' => '87 Shahrah-e-Quaid-e-Azam, Mall Road, Lahore',
            'customer_name' => 'Tariq Mehmood (Operations Head)',
            'customer_mobile' => '03214455889',
            'customer_email' => 'hbl.mallroad@hbl.com',
            'customer_cc' => 'branch.ops@hbl.com',
            'machine_type' => 'CDM',
            'machine_model' => 'Diebold Nixdorf ProCash 280',
            'machine_serial_no' => 'DN-440192-LH',
            'warranty_status' => 'in_warranty',
            'urgency' => 'medium',
            'status' => 'in_progress',
            'issue_summary' => 'Cheque & cash deposit unit sensor error; refusing notes.',
            'issue_description' => "CDM sensor unit malfunctioning. Customers deposit notes and unit ejects without counting. Onsite inspection requested.",
            'assigned_engineer_id' => $ali->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subHours(2),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subHours(2),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subHours(2),
            'sla_deadline' => Carbon::now()->addHours(6), // 8h TAT total, 6h remaining (75% Healthy Green)
            'ai_classified' => true,
        ]);
        TicketLog::create([
            'ticket_id' => $t2->id,
            'user_id' => $admin->id,
            'action' => 'assigned',
            'notes' => 'Assigned to Ali Khan (Lahore) based on city proximity and CDM specialization.',
        ]);

        // Ticket 3: In Progress -> Assigned to Hamza Ahmed (Islamabad) with Warning SLA (37.5% Remaining)
        $t3 = Ticket::create([
            'ticket_no' => 'CMP-2026-00103',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'UBL (United Bank Limited)',
            'branch_name' => 'Blue Area Corporate Branch',
            'branch_location' => 'Islamabad',
            'branch_address' => 'Jinnah Avenue, Blue Area, Islamabad',
            'customer_name' => 'Naveed Akhtar',
            'customer_mobile' => '03335123987',
            'customer_email' => 'ubl.bluearea@ubl.com.pk',
            'customer_cc' => 'central.ops@ubl.com.pk',
            'machine_type' => 'ATM',
            'machine_model' => 'Wincor Nixdorf ProCash 2050XE',
            'machine_serial_no' => 'WN-339182-ISB',
            'warranty_status' => 'in_warranty',
            'urgency' => 'medium',
            'status' => 'in_progress',
            'issue_summary' => 'Mainboard communication timeout with encryption pinpad (EPP).',
            'issue_description' => "EPP secure module intermittently disconnecting from system board. Requires diagnostic testing and secure key reloading.",
            'assigned_engineer_id' => $hamza->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subHours(5),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subHours(5),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subHours(5),
            'sla_deadline' => Carbon::now()->addHours(3), // 8h TAT total, 3h remaining (37.5% Warning Amber)
            'ai_classified' => true,
        ]);

        // Ticket 4: In Progress -> Assigned to Usman Tariq (Karachi) with Critical SLA (12.5% Remaining)
        $t4 = Ticket::create([
            'ticket_no' => 'CMP-2026-00104',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Meezan Bank Limited',
            'branch_name' => 'Clifton Corporate Branch',
            'branch_location' => 'Karachi',
            'branch_address' => 'Block 4, Clifton, Karachi',
            'customer_name' => 'Asad Siddiqui',
            'customer_mobile' => '03218822334',
            'customer_email' => 'meezan.clifton@meezanbank.com',
            'customer_cc' => 'khi.support@meezanbank.com',
            'machine_type' => 'ATM',
            'machine_model' => 'NCR SelfServ 6634',
            'machine_serial_no' => 'NCR-771890-KHI',
            'warranty_status' => 'in_warranty',
            'urgency' => 'critical',
            'status' => 'in_progress',
            'issue_summary' => 'Cash vault lock error & dispenser belt slippage.',
            'issue_description' => "Critical failure during morning peak cash transactions. Motor belt slipping and safe sensor flashing red.",
            'assigned_engineer_id' => $usman->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subMinutes(105),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subMinutes(105),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subMinutes(105),
            'sla_deadline' => Carbon::now()->addMinutes(15), // 2h TAT total, 15m remaining (12.5% Critical Red)
            'ai_classified' => true,
        ]);

        // Ticket 5: SLA Breached -> ESCALATED to Regional Superior
        $t5 = Ticket::create([
            'ticket_no' => 'CMP-2026-00105',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Allied Bank Limited (ABL)',
            'branch_name' => 'Bosan Road Branch',
            'branch_location' => 'Multan',
            'branch_address' => 'Bosan Road, Near Gulgasht Colony, Multan',
            'customer_name' => 'Sheikh Waqas (Branch Manager)',
            'customer_mobile' => '03017788990',
            'customer_email' => 'abl.multan@abl.com',
            'customer_cc' => 'operations@abl.com',
            'machine_type' => 'ATM',
            'machine_model' => 'GRG Banking H22N',
            'machine_serial_no' => 'GRG-55192-MUL',
            'warranty_status' => 'out_of_warranty',
            'urgency' => 'high',
            'status' => 'escalated',
            'issue_summary' => 'Complete system lockup; OS corrupt boot failure. Offline for > 24 hours.',
            'issue_description' => "ATM completely dark. Power supply functioning but hard drive clicking and BIOS reports No Boot Device.",
            'assigned_engineer_id' => $bilal->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subHours(30),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subHours(29),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subHours(29),
            'sla_deadline' => Carbon::now()->subHours(6), // Breached by 6 hours (Pulsing Red)
            'escalated_at' => Carbon::now()->subHours(5),
            'escalated_to_id' => $superior->id,
        ]);
        TicketLog::create([
            'ticket_id' => $t5->id,
            'user_id' => null,
            'action' => 'escalated',
            'notes' => 'CRITICAL: SLA deadline (4 hours for High urgency) breached. Ticket automatically escalated to Superior (supervisor@banksupport.com).',
        ]);

        // Ticket 6: Resolved by Ali Khan -> NO Expense Claimed Yet! Ready to test Claim Expense & Send Resolution Email
        $t6 = Ticket::create([
            'ticket_no' => 'CMP-2026-00106',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Standard Chartered Bank',
            'branch_name' => 'Main Boulevard Gulberg Branch',
            'branch_location' => 'Lahore',
            'branch_address' => 'Main Boulevard, Gulberg, Lahore',
            'customer_name' => 'Zeeshan Malik',
            'customer_mobile' => '03004411223',
            'customer_email' => 'scb.gulberg@sc.com',
            'customer_cc' => 'atm.helpdesk@sc.com, terminal.management@sc.com',
            'machine_type' => 'ATM',
            'machine_model' => 'NCR SelfServ 34 Walk-Up',
            'machine_serial_no' => 'SCB-34901-LHR',
            'warranty_status' => 'in_warranty',
            'urgency' => 'medium',
            'status' => 'resolved',
            'issue_summary' => 'Replaced journal printer head, reset optical sensors, tested 20 consecutive cash withdrawals with branch manager.',
            'issue_description' => "Receipt printer failing. Replaced thermal roller and sensor array. Unit verified operational.",
            'assigned_engineer_id' => $ali->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subHours(4),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subHours(4),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subHours(4),
            'resolved_at' => Carbon::now()->subHours(1),
            'resolution_summary' => 'Completed on-site field maintenance, calibrated sensors, and machine tested successfully in presence of branch staff.',
            'supporting_document' => 'resolutions/sample_proof_scb.jpg',
            'resolution_document_name' => 'onsite_cash_test_proof.jpg',
            'resolution_email_sent' => false, // Ready for Operations Manager to dispatch resolution email!
        ]);
        TicketLog::create([
            'ticket_id' => $t6->id,
            'user_id' => $ali->id,
            'action' => 'resolved',
            'notes' => 'Ticket marked as resolved by Ali Khan with supporting picture attached.',
        ]);

        // Ticket 7: Resolved with Active Expense Claim -> Locked from Re-claim and Undo (Audit Ready)
        $t7 = Ticket::create([
            'ticket_no' => 'CMP-2026-00107',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Bank Alfalah',
            'branch_name' => 'Sahiwal High Street Branch',
            'branch_location' => 'Sahiwal',
            'branch_address' => 'High Street, Civil Lines, Sahiwal',
            'customer_name' => 'Khurram Shehzad',
            'customer_mobile' => '03006981234',
            'customer_email' => 'alfalah.sahiwal@bankalfalah.com',
            'customer_cc' => 'punjab.ops@bankalfalah.com',
            'machine_type' => 'ATM',
            'machine_model' => 'NCR SelfServ 26',
            'machine_serial_no' => 'NCR-26019-SWL',
            'warranty_status' => 'in_warranty',
            'urgency' => 'medium',
            'status' => 'resolved',
            'issue_summary' => 'Replaced faulty receipt printer head & thermal roller.',
            'issue_description' => "Printer jam error constantly displayed. Replaced thermal printhead and tested 50 customer transaction receipts.",
            'assigned_engineer_id' => $ali->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subDays(1),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subDays(1),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subDays(1),
            'resolved_at' => Carbon::now()->subHours(2),
            'resolution_summary' => 'Replaced printer head and verified paper cutter mechanism.',
            'supporting_document' => 'resolutions/service_report_CMP-2026-00107.pdf',
            'resolution_document_name' => 'service_report_CMP-2026-00107.pdf',
            'resolution_email_sent' => false,
        ]);
        ExpenseClaim::create([
            'ticket_id' => $t7->id,
            'engineer_id' => $ali->id,
            'from_city' => 'Lahore',
            'to_city' => 'Sahiwal',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'description' => 'Travel to Sahiwal by company patrol car for urgent printer replacement.',
            'ai_distance_km' => 360.00, // Lahore to Sahiwal round trip (~180km each way)
            'ai_estimated_hours' => 5.5,
            'claimed_amount' => 8500.00,
            'suggested_amount' => 7200.00, // 360 km * 20 PKR/km
            'status' => 'submitted',
            'voucher_file' => 'vouchers/ali_sahiwal_toll_fuel.pdf',
            'supporting_doc' => 'reports/service_report_CMP-2026-00106.pdf',
        ]);

        // Ticket 8: Workshop Re-Alignment Flow (Machine in Workshop)
        $t8 = Ticket::create([
            'ticket_no' => 'CMP-2026-00108',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Faysal Bank',
            'branch_name' => 'Gujranwala GT Road Branch',
            'branch_location' => 'Gujranwala',
            'branch_address' => 'Near General Bus Stand, GT Road, Gujranwala',
            'customer_name' => 'Adnan Butt',
            'customer_mobile' => '03338123456',
            'customer_email' => 'faysal.gujranwala@faysalbank.com',
            'customer_cc' => 'branchops@faysalbank.com',
            'machine_type' => 'CDM',
            'machine_model' => 'Diebold CS 7700',
            'machine_serial_no' => 'CS-77001-GRW',
            'warranty_status' => 'out_of_warranty',
            'urgency' => 'high',
            'status' => 'awaiting_workshop',
            'issue_summary' => 'Dispenser gear cluster broken; machine transferred to Lahore Central Workshop for mechanical overhaul.',
            'issue_description' => "Complete mechanical failure of the cash transport gears. Cannot be repaired on site. Machine crated and dispatched to Lahore central workshop.",
            'assigned_engineer_id' => $ali->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subDays(2),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subDays(2),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subDays(2),
            'workshop_location' => 'Lahore Central Workshop',
        ]);
        TicketFeedback::create([
            'ticket_id' => $t8->id,
            'engineer_id' => $ali->id,
            'day_number' => 1,
            'action_taken' => 'Machine Sent to Workshop',
            'feedback_text' => 'On-site repair not feasible due to broken gear housing. Dispatched machine via registered cargo to Lahore Central Workshop.',
            'status' => 'submitted',
            'submitted_at' => Carbon::now()->subDays(1),
        ]);

        // Ticket 9: Resolved with Resolution Email Already Sent -> Emerald Badge Verification
        $t9 = Ticket::create([
            'ticket_no' => 'CMP-2026-00109',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Askari Bank Limited',
            'branch_name' => 'Saddar Cantt Branch',
            'branch_location' => 'Rawalpindi',
            'branch_address' => 'The Mall, Saddar Cantt, Rawalpindi',
            'customer_name' => 'Major (R) Haroon',
            'customer_mobile' => '03349911882',
            'customer_email' => 'askari.cantt@askaribank.com.pk',
            'customer_cc' => 'central.ops@askaribank.com.pk, regional.mgr@askaribank.com.pk',
            'machine_type' => 'ATM',
            'machine_model' => 'Diebold Nixdorf CS 5500',
            'machine_serial_no' => 'DN-55102-RWP',
            'warranty_status' => 'in_warranty',
            'urgency' => 'medium',
            'status' => 'resolved',
            'issue_summary' => 'Power supply unit replaced and mainboard recalibrated.',
            'issue_description' => "System powering down intermittently. Replaced internal PSU and tested with 24-hour burn-in.",
            'assigned_engineer_id' => $hamza->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subHours(6),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subHours(6),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subHours(6),
            'resolved_at' => Carbon::now()->subHours(3),
            'resolution_summary' => 'Power supply unit swapped with new OEM module. Voltage rail tested stable at 12.04V.',
            'supporting_document' => 'resolutions/askari_handover_slip.pdf',
            'resolution_document_name' => 'askari_handover_slip.pdf',
            'resolution_email_sent' => true,
            'resolution_email_sent_at' => Carbon::now()->subHours(2),
            'resolution_email_sent_by_id' => $admin->id,
            'resolution_email_to' => 'askari.cantt@askaribank.com.pk',
        ]);
        TicketLog::create([
            'ticket_id' => $t9->id,
            'user_id' => $admin->id,
            'action' => 'resolution_email_sent',
            'notes' => 'Resolution email dispatched to askari.cantt@askaribank.com.pk with PDF handover slip attached.',
        ]);

        // Ticket 10: Resolved with REJECTED Expense Claim -> Engineer Re-submission Flow
        $t10 = Ticket::create([
            'ticket_no' => 'CMP-2026-00110',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Bank of Punjab (BOP)',
            'branch_name' => 'Civil Lines Branch',
            'branch_location' => 'Faisalabad',
            'branch_address' => 'Civil Lines, Near Commissioner House, Faisalabad',
            'customer_name' => 'Muhammad Bilal Cheema',
            'customer_mobile' => '03456781234',
            'customer_email' => 'bop.civillines@bop.com.pk',
            'customer_cc' => 'atmops@bop.com.pk',
            'machine_type' => 'ATM',
            'machine_model' => 'NCR SelfServ 84',
            'machine_serial_no' => 'NCR-84019-FSD',
            'warranty_status' => 'in_warranty',
            'urgency' => 'medium',
            'status' => 'resolved',
            'issue_summary' => 'Replaced motorized card reader shutter assembly and calibrated alignment.',
            'issue_description' => "Card capture bin sensor error and shutter obstruction.",
            'assigned_engineer_id' => $ali->id,
            'assigned_by_id' => $admin->id,
            'assigned_at' => Carbon::now()->subDays(1),
            'whatsapp_notified' => true,
            'whatsapp_notified_at' => Carbon::now()->subDays(1),
            'email_assignment_sent' => true,
            'email_assignment_sent_at' => Carbon::now()->subDays(1),
            'resolved_at' => Carbon::now()->subHours(5),
            'resolution_summary' => 'Card reader motor replaced and 30 test customer transactions completed successfully.',
            'supporting_document' => 'resolutions/bop_card_reader_proof.jpg',
            'resolution_document_name' => 'bop_card_reader_proof.jpg',
            'resolution_email_sent' => false,
        ]);
        ExpenseClaim::create([
            'ticket_id' => $t10->id,
            'engineer_id' => $ali->id,
            'from_city' => 'Lahore',
            'to_city' => 'Faisalabad',
            'trip_type' => 'round_trip',
            'category' => 'fuel',
            'description' => 'Motorway M-3 fuel and toll charges for emergency dispatch to Faisalabad.',
            'ai_distance_km' => 260.00,
            'ai_estimated_hours' => 3.5,
            'claimed_amount' => 6800.00,
            'suggested_amount' => 5200.00,
            'status' => 'rejected',
            'voucher_file' => 'vouchers/bop_fuel_blurred.jpg',
            'admin_notes' => 'Toll plaza receipt illegible/blurred. Please re-upload a clear image or PDF voucher for audit.',
            'resubmission_count' => 1,
        ]);

        // Phase 6: Parts Management System
        $this->call(PartsSystemSeeder::class);
    }
}
