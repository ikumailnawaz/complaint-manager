<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketLog;
use App\Models\ExpenseClaim;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\EngineerInventory;
use App\Models\EngineerAdvanceTransaction;
use App\Models\StockMovement;
use App\Models\PmMachine;
use App\Models\PmSchedule;
use App\Models\PmRecord;
use App\Models\AppNotification;
use App\Models\InboxEmail;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserResetSeeder extends Seeder
{
    /**
     * Drop all existing users and operational data, and seed the requested users.
     */
    public function run(): void
    {
        // 1. Wipe all existing operational records & users cleanly
        DB::statement('PRAGMA foreign_keys = OFF;');
        
        DB::table('ticket_feedbacks')->delete();
        DB::table('ticket_logs')->delete();
        DB::table('expense_claims')->delete();
        DB::table('part_request_items')->delete();
        DB::table('part_requests')->delete();
        DB::table('engineer_inventories')->delete();
        DB::table('engineer_advance_transactions')->delete();
        DB::table('stock_movements')->delete();
        DB::table('pm_records')->delete();
        DB::table('pm_schedules')->delete();
        DB::table('pm_machines')->delete();
        DB::table('app_notifications')->delete();
        DB::table('inbox_emails')->delete();
        DB::table('tickets')->delete();
        DB::table('users')->delete();

        DB::statement('PRAGMA foreign_keys = ON;');

        // 2. Exact user list requested by user
        $usersList = [
            [
                'fullname' => 'farhankhalid',
                'role'     => 'engineer',
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'ikram',
                'role'     => 'engineer',
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'abdulbasit',
                'role'     => 'engineer',
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'sheryar',
                'role'     => 'engineer',
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'sadam',
                'role'     => 'engineer',
                'city'     => 'Sahiwal',
            ],
            [
                'fullname' => 'afzal',
                'role'     => 'engineer',
                'city'     => 'Faisalabad',
            ],
            [
                'fullname' => 'mdaud',
                'role'     => 'engineer',
                'city'     => 'Multan',
            ],
            [
                'fullname' => 'awais',
                'role'     => 'engineer',
                'city'     => 'Sukkur',
            ],
            [
                'fullname' => 'ahsan',
                'role'     => 'engineer',
                'city'     => 'N/A',
            ],
            [
                'fullname' => 'shariq',
                'role'     => 'engineer',
                'city'     => 'Karachi',
            ],
            [
                'fullname' => 'raghib',
                'role'     => 'engineer',
                'city'     => 'Karachi',
            ],
            [
                'fullname' => 'adnan',
                'role'     => 'office_staff',
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'zahid',
                'role'     => 'engineer',
                'city'     => 'Karachi',
            ],
            [
                'fullname' => 'rashid',
                'role'     => 'engineer',
                'city'     => 'Karachi',
            ],
            [
                'fullname' => 'zeeshan',
                'role'     => 'engineer',
                'city'     => 'Gujranwala',
            ],
            [
                'fullname' => 'waqas',
                'role'     => 'engineer',
                'city'     => 'N/A',
            ],
            [
                'fullname' => 'ronald',
                'role'     => 'admin', // Operation Manager
                'city'     => 'Karachi',
            ],
            [
                'fullname' => 'aliibrahim',
                'role'     => 'engineer',
                'city'     => 'Peshawar',
            ],
            [
                'fullname' => 'fayyaz',
                'role'     => 'engineer',
                'city'     => 'Karachi',
            ],
            [
                'fullname' => 'mukhtair',
                'role'     => 'engineer',
                'city'     => 'Hyderabad',
            ],
            [
                'fullname' => 'nazir',
                'role'     => 'admin', // Operation Manager
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'usman',
                'role'     => 'engineer',
                'city'     => 'Islamabad',
            ],
            [
                'fullname' => 'Murtiza',
                'role'     => 'engineer',
                'city'     => 'Quetta',
            ],
            [
                'fullname' => 'Shahbaz',
                'role'     => 'engineer',
                'city'     => 'Multan',
            ],
            [
                'fullname' => 'Denngans',
                'role'     => 'engineer',
                'city'     => 'N/A',
            ],
            [
                'fullname' => 'Zain',
                'role'     => 'engineer',
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'Shahid',
                'role'     => 'engineer',
                'city'     => 'Lahore',
            ],
            [
                'fullname' => 'Kumail',
                'role'     => 'super_admin',
                'city'     => 'Lahore',
            ],
        ];

        $phonePrefixes = ['0300', '0321', '0333', '0345', '0312'];
        $createdUsers = [];

        foreach ($usersList as $index => $u) {
            $rawName = $u['fullname'];
            $cleanLower = strtolower($rawName);
            
            // Password rule: kumail -> kumail123, others -> {cleanLower}123
            $plainPassword = $cleanLower . '123';
            
            $phone = $phonePrefixes[$index % count($phonePrefixes)] . sprintf('%07d', $index + 1000000);

            $city = ($u['city'] === 'N/A' || empty($u['city'])) ? null : $u['city'];

            $user = User::create([
                'name'           => $rawName,
                'email'          => $cleanLower . '@banksupport.com',
                'password'       => Hash::make($plainPassword),
                'role'           => $u['role'],
                'phone_whatsapp' => $phone,
                'base_city'      => $city,
                'current_city'   => $city,
                'specialization' => match($u['role']) {
                    'super_admin'  => 'Enterprise Super Admin & ERP Director',
                    'admin'        => 'Operations Manager',
                    'office_staff' => 'Office Staff & Inventory Hub',
                    default        => 'Field Hardware & Sorter Specialist',
                },
                'is_available'   => true,
                'is_on_leave'    => false,
            ]);

            $createdUsers[$cleanLower] = $user;
        }

        // 3. Seed fresh initial tickets assigned to the newly created engineers
        $farhan = $createdUsers['farhankhalid'] ?? null;
        $usman = $createdUsers['usman'] ?? null;
        $kumail = $createdUsers['kumail'] ?? null;
        $adnan = $createdUsers['adnan'] ?? null;

        if ($farhan && $kumail) {
            $t1 = Ticket::create([
                'ticket_no'             => 'CMP-2026-0001',
                'bank_name'             => 'MCB Bank Limited',
                'branch_name'           => 'Gulberg III Branch',
                'branch_location'       => 'Lahore',
                'branch_address'        => 'Main Boulevard, Gulberg III, Lahore',
                'customer_name'         => 'Asad Munir',
                'customer_mobile'       => '03214567890',
                'customer_email'        => 'mcb.gulberg@mcb.com.pk',
                'machine_type'          => 'Banknote Sorter',
                'machine_model'         => 'KIS-NT-7823',
                'machine_serial_no'     => 'GLY-7823-LHR',
                'urgency'               => 'medium',
                'status'                => 'assigned',
                'issue_summary'         => 'Banknote Sorter Jam & Optical Sensor Overheating',
                'issue_description'     => 'Teller unit rejects notes on pocket 1 with optical drift.',
                'assigned_engineer_id'  => $farhan->id,
                'assigned_by_id'        => $kumail->id,
                'assigned_at'           => Carbon::now()->subHours(5),
                'sla_deadline'          => Carbon::now()->addHours(19),
            ]);

            TicketLog::create([
                'ticket_id' => $t1->id,
                'user_id'   => $kumail->id,
                'action'    => 'Ticket Assigned',
                'notes'     => "Ticket dispatched and assigned to Engineer {$farhan->name} (Lahore).",
            ]);

            TicketFeedback::create([
                'ticket_id'      => $t1->id,
                'engineer_id'    => $farhan->id,
                'day_number'     => 1,
                'feedback_text'  => 'Reached branch, opened rear transport assembly. Cleaning optical sensors.',
                'parts_required' => 'Sensor cleaning kit applied.',
                'action_taken'   => 'Cleaned sensors, performing test counting batches.',
                'submitted_at'   => Carbon::now()->subHours(2),
                'submitted_by_id'=> $farhan->id,
            ]);

            AppNotification::create([
                'user_id'     => $farhan->id,
                'type'        => 'ticket_assigned',
                'title'       => 'New Ticket Assigned: CMP-2026-0001',
                'message'     => 'MCB Bank Limited (Gulberg III Branch, Lahore) has been assigned to you.',
                'link'        => route('tickets.show', $t1),
                'icon'        => 'fa-solid fa-ticket',
                'color'       => 'sky',
                'is_read'     => false,
            ]);
        }

        if ($usman && $kumail) {
            $t2 = Ticket::create([
                'ticket_no'             => 'CMP-2026-0002',
                'bank_name'             => 'Bank Alfalah Limited',
                'branch_name'           => 'Blue Area Branch',
                'branch_location'       => 'Islamabad',
                'branch_address'        => 'Jinnah Avenue, Blue Area, Islamabad',
                'customer_name'         => 'Tariq Mehmood',
                'customer_mobile'       => '03335551234',
                'customer_email'        => 'alfalah.bluearea@bankalfalah.com',
                'machine_type'          => 'Cash Recycler',
                'machine_model'         => 'Glory USF-51',
                'machine_serial_no'     => 'GLY-BA-3312',
                'urgency'               => 'high',
                'status'                => 'in_progress',
                'issue_summary'         => 'Cash Recycler belt slipping during end-of-day teller cash balancing',
                'issue_description'     => 'Belts slipping, need roller gear replacement.',
                'assigned_engineer_id'  => $usman->id,
                'assigned_by_id'        => $kumail->id,
                'assigned_at'           => Carbon::now()->subHours(8),
                'sla_deadline'          => Carbon::now()->addHours(16),
            ]);

            TicketLog::create([
                'ticket_id' => $t2->id,
                'user_id'   => $kumail->id,
                'action'    => 'Ticket Assigned',
                'notes'     => "Ticket assigned to Engineer {$usman->name} (Islamabad).",
            ]);

            AppNotification::create([
                'user_id'     => $usman->id,
                'type'        => 'ticket_assigned',
                'title'       => 'New Ticket Assigned: CMP-2026-0002',
                'message'     => 'Bank Alfalah Limited (Blue Area Branch, Islamabad) assigned to you.',
                'link'        => route('tickets.show', $t2),
                'icon'        => 'fa-solid fa-ticket',
                'color'       => 'amber',
                'is_read'     => false,
            ]);
        }
    }
}
