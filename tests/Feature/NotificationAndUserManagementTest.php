<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Ticket;
use App\Models\PartRequest;
use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $officeStaff;
    protected User $engineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@bankerp.com',
            'role' => 'super_admin',
            'is_available' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Operations Admin',
            'email' => 'admin@bankerp.com',
            'role' => 'admin',
            'is_available' => true,
        ]);

        $this->officeStaff = User::factory()->create([
            'name' => 'Warehouse Staff',
            'email' => 'officestaff@bankerp.com',
            'role' => 'office_staff',
            'is_available' => true,
        ]);

        $this->engineer = User::factory()->create([
            'name' => 'Field Engineer',
            'email' => 'engineer@bankerp.com',
            'role' => 'engineer',
            'is_available' => true,
        ]);
    }

    public function test_admin_and_super_admin_can_access_user_management(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));
        $response->assertStatus(200);
        $response->assertSee('User Management');

        $responseSuper = $this->actingAs($this->superAdmin)->get(route('users.index'));
        $responseSuper->assertStatus(200);
    }

    public function test_office_staff_and_engineers_are_forbidden_from_user_management(): void
    {
        $responseStaff = $this->actingAs($this->officeStaff)->get(route('users.index'));
        $responseStaff->assertStatus(403);

        $responseEng = $this->actingAs($this->engineer)->get(route('users.index'));
        $responseEng->assertStatus(403);
    }

    public function test_admin_can_create_new_user(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'New Field Tech',
            'email' => 'tech@bankerp.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'engineer',
            'base_city' => 'Multan',
            'phone_whatsapp' => '03001234567',
            'is_available' => 1,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'tech@bankerp.com',
            'role' => 'engineer',
            'base_city' => 'Multan',
            'is_available' => 1,
        ]);
    }

    public function test_operations_admin_cannot_elevate_a_user_to_super_admin(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Rogue Super Admin',
            'email' => 'rogue@bankerp.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'super_admin',
            'is_available' => 1,
        ]);

        // Expect validation failure since super_admin is not in availableRoles for admin
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'rogue@bankerp.com']);
    }

    public function test_super_admin_can_assign_super_admin_role(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Second Super Admin',
            'email' => 'secondsuper@bankerp.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'super_admin',
            'is_available' => 1,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'secondsuper@bankerp.com',
            'role' => 'super_admin',
        ]);
    }

    public function test_admin_can_toggle_user_active_status(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'Target Engineer',
            'email' => 'target@bankerp.com',
            'role' => 'engineer',
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('users.toggleStatus', $targetUser));
        $response->assertRedirect();

        $this->assertFalse($targetUser->fresh()->is_available);
    }

    public function test_notification_service_dispatches_ticket_assigned(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-NOTIF-01',
            'bank_name' => 'HBL',
            'branch_name' => 'Gulberg Lahore',
            'branch_location' => 'Lahore',
            'status' => 'assigned',
            'urgency' => 'high',
            'issue_summary' => 'Sorter jam test',
        ]);

        NotificationService::notifyTicketAssigned($ticket, $this->engineer);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->engineer->id,
            'type' => 'ticket_assigned',
        ]);
    }

    public function test_notification_service_dispatches_part_request_lifecycle(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-NOTIF-02',
            'bank_name' => 'MCB',
            'branch_name' => 'Mall Road Lahore',
            'branch_location' => 'Lahore',
            'status' => 'open',
            'urgency' => 'medium',
            'issue_summary' => 'Roller belt defect',
        ]);

        $partRequest = PartRequest::create([
            'request_number' => 'PR-2026-TEST-99',
            'ticket_id' => $ticket->id,
            'engineer_id' => $this->engineer->id,
            'status' => 'pending_stock_check',
            'fault_description' => 'Test sensor fail',
        ]);

        // Stage 1 creation notifies warehouse / office_staff
        NotificationService::notifyPartRequestCreated($partRequest);
        $this->assertDatabaseHas('app_notifications', [
            'role_target' => 'office_staff',
            'type' => 'part_request_created',
        ]);

        // Stage 1 verified notifies super_admin for Stage 2 approval
        NotificationService::notifyPartRequestVerified($partRequest);
        $this->assertDatabaseHas('app_notifications', [
            'role_target' => 'super_admin',
            'type' => 'part_request_verified',
        ]);

        // Stage 2 approved notifies engineer
        NotificationService::notifyPartRequestApproved($partRequest);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->engineer->id,
            'type' => 'part_request_approved',
        ]);
    }

    public function test_unread_notifications_api_and_mark_as_read(): void
    {
        // Create 2 notifications for engineer
        $notif1 = AppNotification::create([
            'user_id' => $this->engineer->id,
            'type' => 'ticket_assigned',
            'title' => 'New Ticket Assigned',
            'message' => 'Ticket #101 has been assigned to you.',
            'is_read' => false,
        ]);

        $notif2 = AppNotification::create([
            'user_id' => $this->engineer->id,
            'type' => 'part_request_approved',
            'title' => 'Part Request Approved',
            'message' => 'Roller belt request was approved.',
            'is_read' => false,
        ]);

        // Query unread API endpoint
        $response = $this->actingAs($this->engineer)->getJson(route('notifications.unread'));
        $response->assertStatus(200);
        $response->assertJson([
            'count' => 2,
        ]);
        $this->assertCount(2, $response->json('notifications'));

        // Mark single notification read
        $readResponse = $this->actingAs($this->engineer)->postJson(route('notifications.mark-read', $notif1));
        $readResponse->assertStatus(200);
        $this->assertTrue($notif1->fresh()->is_read);

        // Mark all as read
        $markAllResponse = $this->actingAs($this->engineer)->postJson(route('notifications.mark-all-read'));
        $markAllResponse->assertStatus(200);
        $this->assertTrue($notif2->fresh()->is_read);

        // Subsequent check returns count 0
        $finalCheck = $this->actingAs($this->engineer)->getJson(route('notifications.unread'));
        $finalCheck->assertJson(['count' => 0]);
    }

    public function test_notifications_audit_center_page_renders_correctly(): void
    {
        AppNotification::create([
            'user_id' => $this->admin->id,
            'type' => 'escalation',
            'title' => 'Critical SLA Escalation',
            'message' => 'Ticket #99 has breached 4-hour SLA.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('notifications.index'));
        $response->assertStatus(200);
        $response->assertSee('Notifications Center');
        $response->assertSee('Critical SLA Escalation');
    }
}
