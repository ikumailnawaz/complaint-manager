<?php

namespace Tests\Feature;

use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\Ticket;
use App\Models\Tracking\AttendanceDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartRequestReuploadAndAttendanceFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $officeStaff;
    protected User $engineer;
    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name'     => 'Super Admin User',
            'email'    => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role'     => 'super_admin',
        ]);

        $this->officeStaff = User::create([
            'name'     => 'Office Staff Member',
            'email'    => 'office@test.com',
            'password' => bcrypt('password'),
            'role'     => 'office_staff',
        ]);

        $this->engineer = User::create([
            'name'     => 'Engineer User',
            'email'    => 'engineer@test.com',
            'password' => bcrypt('password'),
            'role'     => 'engineer',
        ]);

        $this->ticket = Ticket::create([
            'ticket_no'          => 'T-1001',
            'client_name'        => 'Bank Alfalah',
            'bank_name'          => 'Bank Alfalah',
            'branch_name'        => 'Main Branch',
            'branch_location'    => 'Lahore',
            'assigned_engineer_id' => $this->engineer->id,
            'status'             => 'assigned',
            'priority'           => 'medium',
            'service_type'       => 'corrective',
        ]);
    }

    public function test_office_staff_can_update_video_until_super_admin_approves()
    {
        Storage::fake('public');

        $partRequest = PartRequest::create([
            'request_number'    => 'PR-2026-0001',
            'ticket_id'         => $this->ticket->id,
            'engineer_id'       => $this->engineer->id,
            'status'            => 'pending_approval',
            'fault_description' => 'Damaged feed gear',
            'fault_video_path'  => 'part-requests/videos/initial.mp4',
        ]);

        $newVideo = UploadedFile::fake()->create('corrected_video.mp4', 500, 'video/mp4');

        $response = $this->actingAs($this->officeStaff)
            ->post(route('parts.requests.video.update', $partRequest), [
                'fault_video' => $newVideo,
            ]);

        $response->assertSessionHas('success');
        $partRequest->refresh();
        $this->assertNotNull($partRequest->fault_video_path);
        $this->assertNotEquals('part-requests/videos/initial.mp4', $partRequest->fault_video_path);

        // Once approved, video update should be rejected
        $partRequest->update(['status' => 'approved']);
        $anotherVideo = UploadedFile::fake()->create('another.mp4', 500, 'video/mp4');

        $responseApproved = $this->actingAs($this->officeStaff)
            ->post(route('parts.requests.video.update', $partRequest), [
                'fault_video' => $anotherVideo,
            ]);

        $responseApproved->assertSessionHas('error');
    }

    public function test_office_staff_can_reupload_and_resubmit_rejected_part_request()
    {
        Storage::fake('public');

        $partRequest = PartRequest::create([
            'request_number'    => 'PR-2026-0002',
            'ticket_id'         => $this->ticket->id,
            'engineer_id'       => $this->engineer->id,
            'status'            => 'rejected',
            'rejected_by_id'    => $this->superAdmin->id,
            'rejected_at'       => now(),
            'rejection_reason'  => 'Video was blurry and wrong model shown',
            'fault_description' => 'Roller assembly worn',
            'fault_video_path'  => 'part-requests/videos/blurry.mp4',
        ]);

        $newVideo = UploadedFile::fake()->create('clear_evidence.mp4', 1000, 'video/mp4');

        $response = $this->actingAs($this->officeStaff)
            ->post(route('parts.requests.reupload', $partRequest), [
                'fault_video'   => $newVideo,
                'resubmit_note' => 'Uploaded clear 1080p clip showing exact serial and worn roller assembly.',
            ]);

        $response->assertRedirect(route('parts.requests.show', $partRequest));
        $response->assertSessionHas('success');

        $partRequest->refresh();
        $this->assertEquals('pending_approval', $partRequest->status);
        $this->assertNull($partRequest->rejection_reason);
        $this->assertNull($partRequest->rejected_by_id);
        $this->assertEquals($this->officeStaff->id, $partRequest->stock_verified_by_id);
        $this->assertStringContainsString('Uploaded clear 1080p clip', $partRequest->stock_remarks);
        $this->assertStringContainsString('Prior rejection reason', $partRequest->stock_remarks);
    }

    public function test_attendance_filters_by_from_and_to_dates()
    {
        AttendanceDay::create([
            'user_id' => $this->engineer->id,
            'date'    => '2026-03-01',
            'status'  => 'present',
        ]);
        AttendanceDay::create([
            'user_id' => $this->engineer->id,
            'date'    => '2026-03-15',
            'status'  => 'late',
        ]);
        AttendanceDay::create([
            'user_id' => $this->engineer->id,
            'date'    => '2026-04-05',
            'status'  => 'present',
        ]);

        // Filter March 10 to March 20
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['tracking_module_unlocked' => true])
            ->get(route('tracking.attendance', [
                'from_date' => '2026-03-10',
                'to_date'   => '2026-03-20',
            ]));

        $response->assertStatus(200);
        $response->assertViewHas('attendanceDays', function ($days) {
            return $days->count() === 1 && $days->first()->date === '2026-03-15';
        });

        // Filter entire March
        $responseAllMarch = $this->actingAs($this->superAdmin)
            ->withSession(['tracking_module_unlocked' => true])
            ->get(route('tracking.attendance', [
                'from_date' => '2026-03-01',
                'to_date'   => '2026-03-31',
            ]));

        $responseAllMarch->assertStatus(200);
        $responseAllMarch->assertViewHas('attendanceDays', function ($days) {
            return $days->count() === 2;
        });
    }

    public function test_tickets_filter_by_from_and_to_dates()
    {
        Ticket::create([
            'ticket_no'          => 'T-2001',
            'client_name'        => 'MCB',
            'bank_name'          => 'MCB',
            'branch_name'        => 'Gulberg',
            'branch_location'    => 'Lahore',
            'status'             => 'open',
            'priority'           => 'medium',
            'service_type'       => 'corrective',
            'created_at'         => '2026-02-10 10:00:00',
        ]);

        Ticket::create([
            'ticket_no'          => 'T-2002',
            'client_name'        => 'UBL',
            'bank_name'          => 'UBL',
            'branch_name'        => 'Mall Road',
            'branch_location'    => 'Lahore',
            'status'             => 'open',
            'priority'           => 'medium',
            'service_type'       => 'corrective',
            'created_at'         => '2026-02-20 10:00:00',
        ]);

        Ticket::create([
            'ticket_no'          => 'T-2003',
            'client_name'        => 'HBL',
            'bank_name'          => 'HBL',
            'branch_name'        => 'Cantt',
            'branch_location'    => 'Lahore',
            'status'             => 'open',
            'priority'           => 'medium',
            'service_type'       => 'corrective',
            'created_at'         => '2026-03-05 10:00:00',
        ]);

        // Filter Feb 15 to Feb 25 -> Should only return T-2002
        $response = $this->actingAs($this->superAdmin)
            ->get(route('tickets.index', [
                'from_date' => '2026-02-15',
                'to_date'   => '2026-02-25',
            ]));

        $response->assertStatus(200);
        $response->assertViewHas('tickets', function ($tickets) {
            return $tickets->total() === 1 && $tickets->first()->ticket_no === 'T-2002';
        });

        // Filter Feb 01 to Feb 28 -> Should return T-2001 and T-2002
        $responseFeb = $this->actingAs($this->superAdmin)
            ->get(route('tickets.index', [
                'from_date' => '2026-02-01',
                'to_date'   => '2026-02-28',
            ]));

        $responseFeb->assertStatus(200);
        $responseFeb->assertViewHas('tickets', function ($tickets) {
            return $tickets->total() === 2;
        });
    }
}
