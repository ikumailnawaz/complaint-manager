<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EngineerTicketTableAndCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $engineer;
    protected User $otherEngineer;
    protected Ticket $assignedTicket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engineer = User::factory()->create([
            'name' => 'Ali Khan',
            'email' => 'ali@banksupport.com',
            'role' => 'engineer',
            'base_city' => 'Lahore',
            'is_available' => true,
        ]);

        $this->otherEngineer = User::factory()->create([
            'name' => 'Usman Tariq',
            'email' => 'usman@banksupport.com',
            'role' => 'engineer',
            'base_city' => 'Karachi',
            'is_available' => true,
        ]);

        $this->assignedTicket = Ticket::create([
            'ticket_no' => 'CMP-2026-TEST1',
            'bank_name' => 'MCB Bank',
            'branch_name' => 'Gulberg Branch',
            'branch_location' => 'Lahore',
            'status' => 'assigned',
            'urgency' => 'high',
            'assigned_engineer_id' => $this->engineer->id,
            'sla_deadline' => now()->addHours(4),
            'issue_summary' => 'Card dispenser jammed',
        ]);
    }

    public function test_engineer_dashboard_table_displays_sla_progress_bar_and_mark_as_complete_button(): void
    {
        $response = $this->actingAs($this->engineer)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('SLA Progress:');
        $response->assertSee('Remaining');
        $response->assertSee('Mark as Complete');
        $response->assertSee('engineerCompleteModal');
    }

    public function test_tickets_index_table_displays_sla_progress_column_and_mark_as_complete_option(): void
    {
        $response = $this->actingAs($this->engineer)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertSee('SLA Progress');
        $response->assertSee('Complete');
        $response->assertSee('engineerCompleteModal');
    }

    public function test_tickets_index_table_displays_workshop_button_and_modal(): void
    {
        $response = $this->actingAs($this->engineer)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertSee('Workshop');
        $response->assertSee('workshopTransferModal');
        $response->assertSee('openWorkshopModal');
    }

    public function test_ticket_can_be_sent_to_workshop_with_location_and_notes(): void
    {
        $response = $this->actingAs($this->engineer)
            ->post(route('tickets.workshop', $this->assignedTicket), [
                'workshop_location' => 'Lahore Central Workshop',
                'workshop_engineer_id' => $this->otherEngineer->id,
                'notes' => 'Damaged card reader motor needs bench replacement.',
            ]);

        $response->assertRedirect();

        $this->assignedTicket->refresh();
        $this->assertEquals('awaiting_workshop', $this->assignedTicket->status);
        $this->assertEquals('Lahore Central Workshop', $this->assignedTicket->workshop_location);
        // Smart Transit Policy: Ticket remains assigned to field engineer until physical workshop intake
        $this->assertEquals($this->engineer->id, $this->assignedTicket->assigned_engineer_id);
        $this->assertEquals($this->otherEngineer->id, $this->assignedTicket->workshop_engineer_id);
    }

    public function test_engineer_can_upload_supporting_document_with_success_message(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('repaired_machine.jpg', 600, 600);

        $response = $this->actingAs($this->engineer)
            ->postJson(route('tickets.upload-document', $this->assignedTicket), [
                'document' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'is_image' => true,
                'filename' => 'repaired_machine.jpg',
            ]);

        $path = $response->json('path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_engineer_cannot_upload_document_for_ticket_assigned_to_another_engineer(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('service_sheet.jpg');

        $response = $this->actingAs($this->otherEngineer)
            ->postJson(route('tickets.upload-document', $this->assignedTicket), [
                'document' => $file,
            ]);

        $response->assertStatus(403);
    }

    public function test_engineer_can_mark_ticket_as_complete_with_supporting_document(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('completion_proof.jpg');

        $response = $this->actingAs($this->engineer)
            ->post(route('tickets.resolve', $this->assignedTicket), [
                'resolution_summary' => 'Replaced dispenser belt and tested card transactions successfully.',
                'supporting_document' => $file,
            ]);

        $response->assertRedirect();
        
        $this->assignedTicket->refresh();
        $this->assertEquals('resolved', $this->assignedTicket->status);
        $this->assertNotNull($this->assignedTicket->resolved_at);
        $this->assertEquals('Replaced dispenser belt and tested card transactions successfully.', $this->assignedTicket->resolution_summary);
        $this->assertNotNull($this->assignedTicket->supporting_document);
        $this->assertEquals('completion_proof.jpg', $this->assignedTicket->resolution_document_name);
        Storage::disk('public')->assertExists($this->assignedTicket->supporting_document);
    }

    public function test_engineer_can_mark_complete_with_pre_uploaded_document_path(): void
    {
        Storage::fake('public');

        // First upload via AJAX endpoint
        $file = UploadedFile::fake()->image('onsite_signoff.png');
        $uploadResponse = $this->actingAs($this->engineer)
            ->postJson(route('tickets.upload-document', $this->assignedTicket), [
                'document' => $file,
            ]);
        $path = $uploadResponse->json('path');

        // Then resolve with uploaded path
        $response = $this->actingAs($this->engineer)
            ->postJson(route('tickets.resolve', $this->assignedTicket), [
                'resolution_summary' => 'Service performed and customer signoff received.',
                'uploaded_document_path' => $path,
                'uploaded_document_name' => 'onsite_signoff.png',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assignedTicket->refresh();
        $this->assertEquals('resolved', $this->assignedTicket->status);
        $this->assertEquals($path, $this->assignedTicket->supporting_document);
        $this->assertEquals('onsite_signoff.png', $this->assignedTicket->resolution_document_name);
    }

    public function test_unassigned_engineer_cannot_mark_ticket_as_complete(): void
    {
        $response = $this->actingAs($this->otherEngineer)
            ->post(route('tickets.resolve', $this->assignedTicket), [
                'resolution_summary' => 'Trying to resolve someone elses ticket',
            ]);

        $response->assertStatus(403);
    }

    public function test_engineer_can_undo_resolved_ticket(): void
    {
        // First mark resolved
        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => 'Testing resolution undo',
        ]);

        $response = $this->actingAs($this->engineer)
            ->post(route('tickets.undo-resolve', $this->assignedTicket));

        $response->assertRedirect();
        $this->assignedTicket->refresh();
        $this->assertEquals('in_progress', $this->assignedTicket->status);
        $this->assertNull($this->assignedTicket->resolved_at);

        // Check ticket log has resolution_undone action
        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $this->assignedTicket->id,
            'action' => 'resolution_undone',
        ]);
    }

    public function test_undo_button_is_visible_on_tables_for_resolved_ticket(): void
    {
        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        // Tickets index has Undo Done for resolved ticket
        $indexResponse = $this->actingAs($this->engineer)->get(route('tickets.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Undo Done');
        $indexResponse->assertSee(route('tickets.undo-resolve', $this->assignedTicket));
    }

    public function test_cannot_undo_closed_ticket(): void
    {
        $this->assignedTicket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->engineer)
            ->post(route('tickets.undo-resolve', $this->assignedTicket));

        $response->assertRedirect();
        $this->assignedTicket->refresh();
        $this->assertEquals('closed', $this->assignedTicket->status);
    }

    public function test_unassigned_engineer_cannot_undo_ticket(): void
    {
        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($this->otherEngineer)
            ->post(route('tickets.undo-resolve', $this->assignedTicket));

        $response->assertStatus(403);
    }

    public function test_ticket_with_claimed_expenses_cannot_be_undone(): void
    {
        // Mark resolved
        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        // Submit expense claim
        \App\Models\ExpenseClaim::create([
            'ticket_id' => $this->assignedTicket->id,
            'engineer_id' => $this->engineer->id,
            'from_city' => 'Lahore',
            'to_city' => 'Lahore',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 1500,
            'status' => 'submitted',
        ]);

        $this->assertTrue($this->assignedTicket->hasClaimedExpenses());
        $this->assertTrue($this->assignedTicket->isResolutionLocked());

        // Attempting to undo resolution must be blocked
        $response = $this->actingAs($this->engineer)
            ->post(route('tickets.undo-resolve', $this->assignedTicket));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assignedTicket->refresh();
        $this->assertEquals('resolved', $this->assignedTicket->status);

        // Undo button should NOT be visible on index; Locked indicator should be visible
        $indexResponse = $this->actingAs($this->engineer)->get(route('tickets.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('Undo Done');
        $indexResponse->assertSee('Locked');
    }

    public function test_medium_urgency_ticket_has_eight_hour_tat_and_proper_sla(): void
    {
        $mediumTicket = Ticket::create([
            'ticket_no' => 'CMP-2026-MED1',
            'bank_name' => 'Habib Bank Limited',
            'branch_name' => 'Mall Road',
            'branch_location' => 'Lahore',
            'status' => 'assigned',
            'urgency' => 'medium',
            'assigned_engineer_id' => $this->engineer->id,
            'issue_summary' => 'Receipt printer out of paper',
        ]);

        // Urgency TAT expectation is 8 hours
        $this->assertEquals(8, $mediumTicket->expectedResponseHours());

        // SLA deadline calculated should be 8 hours, not 2 days / 48 hours
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->post(route('tickets.store'), [
            'ticket_no_type' => 'auto',
            'bank_name' => 'UBL',
            'branch_location' => 'Lahore',
            'urgency' => 'medium',
            'warranty_status' => 'in_warranty',
            'issue_summary' => 'Test issue',
        ]);

        $response->assertSessionHasNoErrors();
        $createdTicket = Ticket::where('bank_name', 'UBL')->latest('id')->first();
        $this->assertNotNull($createdTicket);
        $this->assertEquals(8, $createdTicket->expectedResponseHours());
        $hoursDiff = round(now()->diffInHours($createdTicket->sla_deadline, false));
        $this->assertGreaterThanOrEqual(7, $hoursDiff);
        $this->assertLessThanOrEqual(8, $hoursDiff);

        // Check SLA health label does NOT have awkward 'from now'
        $sla = $createdTicket->getSlaHealth();
        $this->assertStringNotContainsString('from now', $sla['label']);
        $this->assertStringContainsString('hour', $sla['label']);
    }

    public function test_mark_resolved_redirects_to_tickets_index_without_claim_ticket_id(): void
    {
        $response = $this->actingAs($this->engineer)
            ->post(route('tickets.resolve', $this->assignedTicket), [
                'resolution_summary' => 'Field maintenance completed.',
            ]);

        $response->assertRedirect(route('tickets.index'));

        // Visiting tickets index shows Claim Expense button in Actions without forcing modal open
        $ticketsResponse = $this->actingAs($this->engineer)->get(route('tickets.index'));

        $ticketsResponse->assertStatus(200);
        $ticketsResponse->assertSee($this->assignedTicket->ticket_no);
        $ticketsResponse->assertSee('Claim Expense');
        $ticketsResponse->assertSee('ticketClaimExpenseModal');
    }

    public function test_engineer_tickets_table_does_not_show_whatsapp_and_bank_email(): void
    {
        $response = $this->actingAs($this->engineer)->get(route('tickets.index'));
        $response->assertStatus(200);

        // Field engineer must NOT see WhatsApp and Bank Email table columns
        $content = $response->getContent();
        $this->assertStringNotContainsString('<th class="py-3 px-3 text-center whitespace-nowrap">WhatsApp</th>', $content);
        $this->assertStringNotContainsString('<th class="py-3 px-3 text-center whitespace-nowrap">Bank Email</th>', $content);
    }

    public function test_operations_manager_table_shows_proper_columns_and_resolution_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@banksupport.com',
        ]);

        $response = $this->actingAs($admin)->get(route('tickets.index'));
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('Resolution Email', $content);
        $this->assertStringContainsString('Assigned Engineer', $content);
        $this->assertStringContainsString('WhatsApp', $content);
        $this->assertStringContainsString('Bank Email', $content);
    }

    public function test_expenses_index_removes_generic_submit_claim_button(): void
    {
        $response = $this->actingAs($this->engineer)->get(route('expenses.index'));
        $response->assertStatus(200);

        // Generic "Submit Tour Expense Claim" button is removed from /expenses
        $response->assertDontSee('Submit Tour Expense Claim');
    }

    public function test_single_active_expense_claim_policy(): void
    {
        // 1. Mark ticket as resolved
        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => 'Fixed sensor unit.',
        ]);

        $this->assertTrue($this->assignedTicket->canClaimExpense());

        // 2. Submit initial expense claim
        $claimResponse = $this->actingAs($this->engineer)->post(route('expenses.store'), [
            'ticket_id' => $this->assignedTicket->id,
            'from_city' => 'Lahore',
            'to_city' => 'Multan',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 4500,
            'redirect_to' => 'tickets',
        ]);

        $claimResponse->assertRedirect(route('tickets.index'));
        $this->assertDatabaseHas('expense_claims', [
            'ticket_id' => $this->assignedTicket->id,
            'status' => 'submitted',
        ]);

        $this->assignedTicket->refresh();
        $this->assertTrue($this->assignedTicket->hasActiveExpenseClaim());
        $this->assertFalse($this->assignedTicket->canClaimExpense());

        // 3. Attempt to submit second claim while first is active -> BLOCKED!
        $secondResponse = $this->actingAs($this->engineer)->post(route('expenses.store'), [
            'ticket_id' => $this->assignedTicket->id,
            'from_city' => 'Lahore',
            'to_city' => 'Multan',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 3000,
            'redirect_to' => 'tickets',
        ]);

        $secondResponse->assertSessionHas('error');

        // 4. Operations Manager rejects the claim
        $activeClaim = $this->assignedTicket->activeExpenseClaim();
        $this->assertNotNull($activeClaim);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('expenses.reject', $activeClaim), [
            'rejection_reason' => 'Receipt voucher missing, please re-upload.',
        ]);

        $this->assignedTicket->refresh();
        $this->assertFalse($this->assignedTicket->hasActiveExpenseClaim());
        $this->assertTrue($this->assignedTicket->hasRejectedExpenseClaim());
        $this->assertTrue($this->assignedTicket->canClaimExpense());

        // 5. Now engineer can re-submit claim!
        $resubmitResponse = $this->actingAs($this->engineer)->post(route('expenses.store'), [
            'ticket_id' => $this->assignedTicket->id,
            'from_city' => 'Lahore',
            'to_city' => 'Multan',
            'trip_type' => 'round_trip',
            'category' => 'travel',
            'claimed_amount' => 4500,
            'redirect_to' => 'tickets',
        ]);

        $resubmitResponse->assertRedirect(route('tickets.index'));
        $this->assertEquals(2, $this->assignedTicket->expenseClaims()->count());
    }

    public function test_operations_manager_can_dispatch_resolution_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@banksupport.com',
        ]);

        Storage::fake('public');
        $fakeDoc = UploadedFile::fake()->create('resolution_proof.jpg', 500, 'image/jpeg');
        $docPath = $fakeDoc->store('resolutions', 'public');

        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => 'Replaced feed belt and tested live cash dispenses.',
            'supporting_document' => $docPath,
            'resolution_document_name' => 'resolution_proof.jpg',
            'customer_email' => 'operations@hbl.com',
            'customer_cc' => 'branch.mgr@hbl.com',
        ]);

        $this->assertTrue($this->assignedTicket->canSendResolutionEmail());

        $response = $this->actingAs($admin)
            ->post(route('tickets.send-resolution-email', $this->assignedTicket), [
                'custom_notes' => 'Machine tested and handed over to Cash Incharge Mr. Kamran.',
            ]);

        $response->assertSessionHas('success');

        $this->assignedTicket->refresh();
        $this->assertTrue($this->assignedTicket->resolution_email_sent);
        $this->assertNotNull($this->assignedTicket->resolution_email_sent_at);
        $this->assertEquals($admin->id, $this->assignedTicket->resolution_email_sent_by_id);
        $this->assertEquals('operations@hbl.com', $this->assignedTicket->resolution_email_to);
    }

    public function test_engineer_can_upload_voucher_via_ajax_endpoint(): void
    {
        Storage::fake('public');

        $fakeVoucher = UploadedFile::fake()->image('toll_receipt.jpg', 600, 400);

        $response = $this->actingAs($this->engineer)
            ->postJson(route('expenses.upload-voucher'), [
                'voucher' => $fakeVoucher,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'filename' => 'toll_receipt.jpg',
                'is_image' => true,
            ]);

        $path = $response->json('path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_engineer_can_submit_expense_claim_with_pre_uploaded_voucher(): void
    {
        Storage::fake('public');

        // Pre-upload voucher
        $fakeVoucher = UploadedFile::fake()->create('hotel_bill.pdf', 300, 'application/pdf');
        $uploadResponse = $this->actingAs($this->engineer)
            ->postJson(route('expenses.upload-voucher'), [
                'voucher' => $fakeVoucher,
            ]);
        $uploadedPath = $uploadResponse->json('path');

        // Resolve ticket first so it can be claimed
        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($this->engineer)
            ->post(route('expenses.store'), [
                'ticket_id' => $this->assignedTicket->id,
                'from_city' => 'Lahore',
                'to_city' => 'Multan',
                'trip_type' => 'one_way',
                'category' => 'travel',
                'claimed_amount' => 5000,
                'uploaded_voucher_path' => $uploadedPath,
                'uploaded_voucher_name' => 'hotel_bill.pdf',
                'redirect_to' => 'tickets',
            ]);

        $response->assertRedirect(route('tickets.index'));

        $this->assertDatabaseHas('expense_claims', [
            'ticket_id' => $this->assignedTicket->id,
            'engineer_id' => $this->engineer->id,
            'voucher_file' => $uploadedPath,
            'claimed_amount' => 5000,
        ]);
    }

    public function test_operations_manager_can_mark_whatsapp_copied_to_unlock_step_3(): void
    {
        $admin = User::factory()->create([
            'name' => 'Operations Admin',
            'email' => 'admin@banksupport.com',
            'role' => 'admin',
        ]);

        $this->assertFalse((bool)$this->assignedTicket->whatsapp_notified);

        $response = $this->actingAs($admin)
            ->postJson(route('tickets.mark-whatsapp-copied', $this->assignedTicket));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assignedTicket->refresh();
        $this->assertTrue((bool)$this->assignedTicket->whatsapp_notified);
        $this->assertNotNull($this->assignedTicket->whatsapp_notified_at);
        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $this->assignedTicket->id,
            'action' => 'whatsapp_copied_dispatch',
        ]);
    }

    public function test_engineer_cannot_mark_whatsapp_copied(): void
    {
        $response = $this->actingAs($this->engineer)
            ->postJson(route('tickets.mark-whatsapp-copied', $this->assignedTicket));

        $response->assertStatus(403);
    }

    public function test_supporting_document_and_proof_visible_in_admin_panel_and_ticket_details(): void
    {
        $admin = User::factory()->create([
            'email' => 'operations_head@banksupport.com',
            'role' => 'admin',
        ]);

        $this->assignedTicket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => 'Tested sensor optics and calibrated bill dispenser.',
            'supporting_document' => 'resolutions/test_handover.png',
            'resolution_document_name' => 'signed_handover.png',
        ]);

        // 1. Admin visits complaints registry (/tickets)
        $resIndex = $this->actingAs($admin)->get(route('tickets.index'));
        $resIndex->assertOk();
        $resIndex->assertSee('Proof Attached');
        $resIndex->assertSee('View Completion Proof');

        // 2. Admin visits ticket details (/tickets/{id})
        $resShow = $this->actingAs($admin)->get(route('tickets.show', $this->assignedTicket));
        $resShow->assertOk();
        $resShow->assertSee('Service Completion &amp; Proof of Work', false);
        $resShow->assertSee('Work Carried Out / Resolution Summary');
        $resShow->assertSee('Proof of Work / Signed Handover Slip');
        $resShow->assertSee('signed_handover.png');
        $resShow->assertSee('Open Proof Document');
    }
}

