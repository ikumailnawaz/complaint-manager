<?php

namespace Tests\Feature;

use App\Models\MachineModel;
use App\Models\Part;
use App\Models\PartRequest;
use App\Models\PartRequestItem;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketProgressAndAdditionalMachineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $engineer;
    protected Ticket $ticket;
    protected MachineModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Adnan Admin',
        ]);

        $this->engineer = User::factory()->create([
            'role' => 'engineer',
            'name' => 'Zain Engineer',
            'base_city' => 'Lahore',
        ]);

        $this->model = MachineModel::create([
            'name' => 'Glory GFS-120',
            'machine_type' => 'Cash Sorting Machine',
        ]);

        $this->ticket = Ticket::create([
            'ticket_no' => 'TKT-2026-TEST-01',
            'bank_name' => 'Bank Alfalah',
            'branch_name' => 'Mall Road',
            'branch_location' => 'Lahore',
            'machine_model' => 'Glory GFS-120',
            'machine_serial_no' => 'SN-GLORY-001',
            'urgency' => 'high',
            'status' => 'assigned',
            'ticket_type' => 'complaint',
            'assigned_engineer_id' => $this->engineer->id,
            'assigned_at' => now()->subHours(5),
            'sla_paused_at' => null,
            'created_at' => now()->subHours(6),
        ]);
    }

    public function test_approvals_hub_calculates_paused_over_48h_and_renders_badge(): void
    {
        // Ticket waiting for 50 hours (>48h)
        $pausedTicket = Ticket::create([
            'ticket_no' => 'TKT-PAUSED-99',
            'bank_name' => 'Meezan Bank',
            'branch_name' => 'Gulberg',
            'branch_location' => 'Lahore',
            'urgency' => 'medium',
            'status' => 'awaiting_approval',
            'sla_paused_at' => now()->subHours(50),
            'ticket_type' => 'complaint',
        ]);

        $res = $this->actingAs($this->admin)->get(route('approvals.index'));
        $res->assertStatus(200);
        $res->assertSee('Attention Required');
        $res->assertSee('TKT-PAUSED-99');
        $res->assertSee('Contact Bank');
    }

    public function test_ticket_creation_supports_installation_type_and_machine_types(): void
    {
        // Register a Shrink Wrapping Machine model
        MachineModel::create([
            'name' => 'ShrinkMaster 3000',
            'machine_type' => 'Shrink Wrapping Machine',
        ]);

        // View ticket create page
        $res = $this->actingAs($this->admin)->get(route('tickets.create'));
        $res->assertStatus(200);
        $res->assertSee('Shrink Wrapping Machine');
        $res->assertSee('New Machine Installation');

        // Store installation ticket
        $postRes = $this->actingAs($this->admin)->post(route('tickets.store'), [
            'ticket_no_type' => 'auto',
            'ticket_type' => 'installation',
            'bank_name' => 'HBL',
            'branch_name' => 'F-6 Supermarket',
            'branch_location' => 'Islamabad',
            'machine_type' => 'Shrink Wrapping Machine',
            'machine_model' => 'ShrinkMaster 3000',
            'machine_serial_no' => 'SN-SHRINK-99',
            'warranty_status' => 'in_warranty',
            'urgency' => 'medium',
            'sla_tat' => '1d',
            'issue_summary' => 'New machine installation and commissioning at client branch.',
        ]);

        $postRes->assertRedirect();
        $created = Ticket::where('machine_serial_no', 'SN-SHRINK-99')->first();
        $this->assertNotNull($created);
        $this->assertEquals('installation', $created->ticket_type);
        $this->assertTrue($created->isInstallation());
        $this->assertFalse($created->isComplaint());
    }

    public function test_part_request_creation_and_update_with_additional_machine(): void
    {
        Storage::fake('public');

        $part = Part::create([
            'name' => 'Transport Feed Belt',
            'part_number' => 'TEST-BELT-01',
            'description' => 'Transport Feed Belt',
            'machine_model_id' => $this->model->id,
            'unit' => 'pcs',
        ]);

        $additionalModel = MachineModel::create([
            'name' => 'CountMaster C-50',
            'machine_type' => 'Cash Counting Machine',
        ]);

        $video = UploadedFile::fake()->create('fault_demo.mp4', 500, 'video/mp4');

        $res = $this->actingAs($this->engineer)->post(route('parts.requests.store'), [
            'ticket_id' => $this->ticket->id,
            'machine_model_id' => $this->model->id,
            'machine_serial_no' => 'SN-PRIMARY-111',
            'fault_description' => 'Primary feed belt damaged',
            'items' => [
                [
                    'part_id' => $part->id,
                    'qty_requested' => 1,
                    'notes' => 'Urgent replacement',
                ],
            ],
            'has_additional_machine' => '1',
            'additional_machine_model_id' => $additionalModel->id,
            'additional_machine_serial_no' => 'SN-SECONDARY-222',
            'additional_machine_type' => 'Cash Counting Machine',
            'additional_fault_description' => 'Sensor jamming intermittently',
            'additional_fault_video' => $video,
        ]);

        $res->assertRedirect();

        $pr = PartRequest::where('ticket_id', $this->ticket->id)->first();
        $this->assertNotNull($pr);
        $this->assertTrue($pr->hasAdditionalMachine());
        $this->assertEquals('SN-SECONDARY-222', $pr->additional_machine_serial_no);
        $this->assertEquals('CountMaster C-50', $pr->additional_machine_model);
        $this->assertNotNull($pr->additional_fault_video_path);

        // Verify show page displays additional machine details
        $showRes = $this->actingAs($this->admin)->get(route('parts.requests.show', $pr));
        $showRes->assertStatus(200);
        $showRes->assertSee('Additional Broken Machine at Site');
        $showRes->assertSee('SN-SECONDARY-222');
        $showRes->assertSee('CountMaster C-50');
    }

    public function test_ticket_progress_modal_endpoint_renders_lifecycle_and_hours(): void
    {
        // Add a daily feedback log to ticket
        TicketFeedback::create([
            'ticket_id' => $this->ticket->id,
            'engineer_id' => $this->engineer->id,
            'feedback_text' => 'Replaced belts, waiting for bank confirmation.',
            'status' => 'in_progress',
        ]);

        $res = $this->actingAs($this->admin)->get(route('tickets.progress-modal', $this->ticket));
        $res->assertStatus(200);
        $res->assertSee('Complete Lifecycle Timeline');
        $res->assertSee('1. Alignment');
        $res->assertSee('2. Approval Freeze');
        $res->assertSee('Net Work Time');
        $res->assertSee('Day-Wise SLA Field Feedback');
        $res->assertSee('Replaced belts, waiting for bank confirmation.');
    }
}
