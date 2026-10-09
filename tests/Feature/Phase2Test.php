<?php

namespace Tests\Feature;

use App\Models\InboxEmail;
use App\Models\Ticket;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $engineerUser;
    protected string $webhookSecret = 'test_n8n_secret_token';

    protected function setUp(): void
    {
        parent::setUp();

        // Seed an operations administrator
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03001234567',
            'is_available' => true,
        ]);

        // Seed a field engineer
        $this->engineerUser = User::create([
            'name' => 'Field Engineer Ali',
            'email' => 'ali@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Lahore',
            'phone_whatsapp' => '03007654321',
            'is_available' => true,
        ]);
    }

    /**
     * Test 1: Ingest webhook security - rejects requests without valid secret
     */
    public function test_webhook_ingest_rejects_unauthorized_secret(): void
    {
        $response = $this->postJson(route('api.tickets.ingest'), [
            'subject' => 'ATM Out of order',
            'body' => 'ATM broken at branch',
        ], [
            'X-Webhook-Secret' => 'invalid_secret_token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized webhook secret',
            ]);
    }

    /**
     * Test 2: Ingest webhook validation - requires subject or body
     */
    public function test_webhook_ingest_fails_validation_when_empty(): void
    {
        $response = $this->postJson(route('api.tickets.ingest'), [], [
            'X-Webhook-Secret' => $this->webhookSecret,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Subject or email body required',
            ]);
    }

    /**
     * Test 3: Ingest webhook creates unassigned complaint with bank customer reference
     */
    public function test_webhook_ingest_creates_open_ticket_with_bank_reference(): void
    {
        $payload = [
            'subject' => 'Bank Alfalah Ticket No: 174793 ATM Out of Service',
            'content' => 'ATM at Gulberg Branch Lahore has a cash jam error. Please attend urgently.',
            'from' => 'manager.gulberg@bankalfalah.com',
            'extracted_data' => [
                'type' => 'complaint',
                'customer_ref_no' => '174793',
                'bank_name' => 'Bank Alfalah',
                'branch_name' => 'Gulberg Branch',
                'branch_location' => 'Lahore',
                'machine_type' => 'ATM',
                'machine_model' => 'NCR 6634',
                'machine_serial_no' => 'NCR-123456',
                'warranty_hint' => 'in_warranty',
                'urgency' => 'high',
                'sla_tat' => '1 day',
                'issue_summary' => 'ATM cash jam error at Gulberg Branch',
            ],
        ];

        $response = $this->postJson(route('api.tickets.ingest'), $payload, [
            'X-Webhook-Secret' => $this->webhookSecret,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'is_complaint' => true,
                'ticket_no' => 'BAF-174793',
                'ticket_no_source' => 'from_email',
                'bank_name' => 'Bank Alfalah',
                'branch_location' => 'Lahore',
                'status' => 'open',
                'assigned_engineer' => null,
            ]);

        $this->assertDatabaseHas('tickets', [
            'ticket_no' => 'BAF-174793',
            'ticket_no_source' => 'from_email',
            'bank_name' => 'Bank Alfalah',
            'branch_location' => 'Lahore',
            'status' => 'open',
            'assigned_engineer_id' => null,
            'ai_classified' => true,
            'warranty_status' => 'in_warranty',
            'urgency' => 'high',
        ]);

        // Verify initial audit log created
        $ticket = Ticket::where('ticket_no', 'BAF-174793')->first();
        $this->assertNotNull($ticket);
        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'created',
        ]);
    }

    /**
     * Test 4: Ingest webhook auto-generates CMP-YYYY-XXXXX format when no bank ref is present
     */
    public function test_webhook_ingest_auto_generates_ticket_number_when_no_bank_ref(): void
    {
        $payload = [
            'subject' => 'Cash Counting Machine Jammed',
            'content' => 'The counting machine in Faisalabad branch is not powering on.',
            'from' => 'operations@ubl.com.pk',
            'extracted_data' => [
                'type' => 'complaint',
                'customer_ref_no' => null,
                'bank_name' => 'United Bank Limited',
                'branch_location' => 'Faisalabad',
                'machine_type' => 'Counting Machine',
                'urgency' => 'medium',
                'issue_summary' => 'Machine not powering on',
            ],
        ];

        $response = $this->postJson(route('api.tickets.ingest'), $payload, [
            'X-Webhook-Secret' => $this->webhookSecret,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'ticket_no_source' => 'auto_generated',
                'status' => 'open',
            ]);

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);
        $this->assertMatchesRegularExpression('/^CMP-\d{4}-\d{5}$/', $ticket->ticket_no);
        $this->assertEquals('auto_generated', $ticket->ticket_no_source);
    }

    /**
     * Test 5: Ingest webhook rejects non-complaint emails without creating a ticket
     */
    public function test_webhook_ingest_rejects_non_complaints(): void
    {
        $payload = [
            'subject' => 'Special discount promotion on office equipment',
            'content' => 'Contact us for 20% off on all paper stationery this week only.',
            'from' => 'spammer@promo.com',
            'extracted_data' => [
                'type' => 'spam',
            ],
        ];

        $response = $this->postJson(route('api.tickets.ingest'), $payload, [
            'X-Webhook-Secret' => $this->webhookSecret,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_complaint' => false,
                'type' => 'spam',
            ]);

        $this->assertEquals(0, Ticket::count());
    }

    /**
     * Test 6: GeminiService rule-based fallback accurately parses Pakistani banks and complaint details
     */
    public function test_gemini_service_fallback_extraction(): void
    {
        $gemini = new GeminiService();

        $subject = 'Ticket No: 125453 - GOUVA300 MACHINE OUT OF ORDER 21-09-2026';
        $body = "Dear Team,\r\nTicket No: 125453\r\nPlease attend the Cash Sorting Machine at Bank Alfalah Gulberg Branch Lahore.\r\nMachine Serial: CMS-987123.\r\nRegards,\r\nMr. Shoaib (BOM)\r\nPhone: 0321-9988776";
        $fromEmail = 'shoaib.bom@bankalfalah.com';

        $extracted = $gemini->fallbackEmailExtraction($subject, $body, $fromEmail);

        $this->assertEquals('complaint', $extracted['type']);
        $this->assertTrue($extracted['is_complaint']);
        $this->assertEquals('125453', $extracted['customer_ref_no']);
        $this->assertEquals('Bank Alfalah', $extracted['bank_name']);
        $this->assertEquals('Lahore', $extracted['branch_location']);
        $this->assertEquals('Cash Sorting Machine', $extracted['machine_type']);
        $this->assertEquals('CMS-987123', $extracted['machine_serial_no']);
    }

    /**
     * Test 7: Convert an InboxEmail to complaint ticket and block duplicate conversions
     */
    public function test_convert_inbox_email_to_complaint_and_prevent_duplicate(): void
    {
        $email = InboxEmail::create([
            'message_id' => '<test-email-msg-001@bankalfalah.com>',
            'uid' => '1001',
            'from_email' => 'branch.ops@bankalfalah.com',
            'from_name' => 'Branch Operations',
            'to_email' => 'noreply@qmstraders.com',
            'subject' => 'Ticket No: 180022 - Cash Dispenser Error',
            'body_text' => "Ticket No: 180022\nBank: Bank Alfalah\nBranch: Mall Road Rawalpindi\nCash dispenser jammed at Mall Road Branch Rawalpindi. Please fix.",
            'email_date' => Carbon::now(),
            'is_read' => true,
            'is_sent' => false,
        ]);

        $this->actingAs($this->adminUser);

        // Convert email to complaint
        $response = $this->post(route('settings.email.convert', $email->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'ticket_no' => 'BAF-180022',
            'status' => 'open',
            'bank_name' => 'Bank Alfalah',
        ]);

        $createdTicket = Ticket::where('ticket_no', 'BAF-180022')->first();
        $this->assertNotNull($createdTicket);

        // Email record should now link to the ticket
        $email->refresh();
        $this->assertEquals($createdTicket->id, $email->ticket_id);

        // Attempting duplicate conversion must be blocked
        $duplicateAttempt = $this->post(route('settings.email.convert', $email->id));
        $duplicateAttempt->assertRedirect(route('tickets.show', $createdTicket->id));
        $duplicateAttempt->assertSessionHas('warning');

        // Verify no second ticket was created
        $this->assertEquals(1, Ticket::count());
    }

    /**
     * Test 8: Admin assigns an open ticket to an engineer, moving status to 'assigned'
     */
    public function test_admin_can_assign_engineer_to_open_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-00001',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Habib Bank Limited',
            'branch_location' => 'Lahore',
            'machine_type' => 'ATM',
            'urgency' => 'high',
            'status' => 'open',
            'issue_summary' => 'ATM Card Reader Failure',
            'issue_description' => 'Card reader is not accepting cards.',
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->from(route('tickets.show', $ticket->id))
            ->post(route('tickets.assign', $ticket->id), [
                'engineer_id' => $this->engineerUser->id,
                'notes' => 'Assigned to nearest engineer in Lahore.',
                'sla_tat' => '1 day',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket->id));
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('assigned', $ticket->status);
        $this->assertEquals($this->engineerUser->id, $ticket->assigned_engineer_id);
        $this->assertEquals($this->adminUser->id, $ticket->assigned_by_id);
        $this->assertNotNull($ticket->assigned_at);

        // Check audit log
        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'assigned',
            'user_id' => $this->adminUser->id,
        ]);
    }

    /**
     * Test 9: Engineer cannot assign tickets (Restricted to Operations Administrators)
     */
    public function test_engineer_cannot_assign_tickets(): void
    {
        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-00002',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'Meezan Bank',
            'branch_location' => 'Karachi',
            'status' => 'open',
        ]);

        $this->actingAs($this->engineerUser);

        $response = $this->post(route('tickets.assign', $ticket->id), [
            'engineer_id' => $this->engineerUser->id,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 10: Ticket workshop reassignment flow when on-site repair is not possible
     */
    public function test_ticket_workshop_reassignment_flow(): void
    {
        // Mock WhatsAppService so no external HTTP request is made
        $this->mock(WhatsAppService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendWorkshopAlert')->once()->andReturn(['success' => true]);
        });

        $ticket = Ticket::create([
            'ticket_no' => 'CMP-2026-00003',
            'ticket_no_source' => 'auto_generated',
            'bank_name' => 'MCB Bank',
            'branch_location' => 'Lahore',
            'status' => 'assigned',
            'assigned_engineer_id' => $this->engineerUser->id,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->from(route('tickets.show', $ticket->id))
            ->post(route('tickets.workshop', $ticket->id), [
                'workshop_location' => 'Lahore Central Workshop',
                'workshop_engineer_id' => $this->engineerUser->id,
                'notes' => 'Machine requires complete mother-board replacement in workshop.',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket->id));

        $ticket->refresh();
        $this->assertEquals('awaiting_workshop', $ticket->status);
        $this->assertEquals('Lahore Central Workshop', $ticket->workshop_location);

        $this->assertDatabaseHas('ticket_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'workshop_transfer',
        ]);
    }

    /**
     * Test 11: Batch Auto-Triage handles multiple emails with the same ticket reference without crashing
     */
    public function test_batch_auto_triage_handles_duplicate_bank_references_gracefully(): void
    {
        // Email 1: First complaint with bank ticket reference 174698
        $email1 = InboxEmail::create([
            'message_id' => '<msg-batch-001@bankalfalah.com>',
            'uid' => '2001',
            'from_email' => 'branch@bankalfalah.com',
            'subject' => 'Ticket No :174698 - Machine Issue',
            'body_text' => "Ticket No: 174698\nBank: Bank Alfalah\nLocation: Lahore\nSorting Machine out of order.",
            'is_read' => true,
            'is_sent' => false,
        ]);

        // Email 2: Follow-up email referencing the EXACT SAME ticket number 174698
        $email2 = InboxEmail::create([
            'message_id' => '<msg-batch-002@bankalfalah.com>',
            'uid' => '2002',
            'from_email' => 'branch@bankalfalah.com',
            'subject' => 'RE: Ticket No :174698 - Machine Issue Followup',
            'body_text' => "Ticket No: 174698\nBank: Bank Alfalah\nLocation: Lahore\nAny update on this?",
            'is_read' => true,
            'is_sent' => false,
        ]);

        // Email 3: Spam email that should be skipped
        $email3 = InboxEmail::create([
            'message_id' => '<msg-batch-003@promo.com>',
            'uid' => '2003',
            'from_email' => 'ads@promo.com',
            'subject' => 'Huge discounts available',
            'body_text' => "Check out our latest catalogue of office chairs.",
            'is_read' => true,
            'is_sent' => false,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->post(route('settings.email.auto-triage'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify only 1 ticket was created with ticket_no BAF-174698
        $this->assertEquals(1, Ticket::where('ticket_no', 'BAF-174698')->count());
        $createdTicket = Ticket::where('ticket_no', 'BAF-174698')->first();

        // Both emails must now be linked to that single ticket
        $email1->refresh();
        $email2->refresh();
        $this->assertEquals($createdTicket->id, $email1->ticket_id);
        $this->assertEquals($createdTicket->id, $email2->ticket_id);

        // Spam email must not be linked to any ticket
        $email3->refresh();
        $this->assertNull($email3->ticket_id);
    }
}
