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
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        
        DB::table('ticket_feedbacks')->truncate();
        DB::table('ticket_logs')->truncate();
        DB::table('expense_claims')->truncate();
        DB::table('part_request_items')->truncate();
        DB::table('part_requests')->truncate();
        DB::table('engineer_inventories')->truncate();
        DB::table('engineer_advance_transactions')->truncate();
        DB::table('stock_movements')->truncate();
        DB::table('pm_records')->truncate();
        DB::table('pm_schedules')->truncate();
        DB::table('pm_machines')->truncate();
        DB::table('app_notifications')->truncate();
        DB::table('inbox_emails')->truncate();
        DB::table('tickets')->truncate();
        DB::table('users')->truncate();

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

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
    }
}
