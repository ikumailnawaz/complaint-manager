<?php

namespace Tests\Feature;

use App\Models\MachineModel;
use App\Models\PmMachine;
use App\Models\PmRecord;
use App\Models\PmSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreventiveMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $engineerAli;
    protected User $engineerUsman;
    protected MachineModel $model;
    protected PmMachine $machine;
    protected PmSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::create([
            'name' => 'Operations Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'base_city' => 'Lahore',
            'is_available' => true,
        ]);

        $this->engineerAli = User::create([
            'name' => 'Ali Khan',
            'email' => 'ali@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Lahore',
            'is_available' => true,
        ]);

        $this->engineerUsman = User::create([
            'name' => 'Usman Tariq',
            'email' => 'usman@test.com',
            'password' => bcrypt('password'),
            'role' => 'engineer',
            'base_city' => 'Karachi',
            'is_available' => true,
        ]);

        $this->model = MachineModel::create([
            'name' => 'NCR SelfServ 87',
            'manufacturer' => 'NCR',
            'machine_type' => 'atm',
        ]);

        $this->machine = PmMachine::create([
            'machine_model_id' => $this->model->id,
            'serial_number' => 'ATM-NCR-1001',
            'location' => 'HBL Gulberg Branch, Lahore',
            'bank_name' => 'Habib Bank Limited',
            'assigned_engineer_id' => $this->engineerAli->id,
        ]);

        $this->schedule = PmSchedule::create([
            'pm_machine_id' => $this->machine->id,
            'title' => 'Monthly Cleaning & Health Check',
            'frequency_days' => 30,
            'assigned_engineer_id' => null, // falls back to machine default (Ali)
            'next_due_date' => now()->toDateString(),
        ]);
    }

    public function test_engineer_views_my_pm_tasks_and_completes_with_document(): void
    {
        // 1. Ali visits /pm/tasks
        $res = $this->actingAs($this->engineerAli)->get(route('pm.tasks.index'));
        $res->assertOk();
        $res->assertSee('Monthly Cleaning & Health Check');
        $res->assertSee('ATM-NCR-1001');

        // 2. Ali completes task with document upload
        $doc = UploadedFile::fake()->create('service_report.pdf', 500, 'application/pdf');

        $completeRes = $this->actingAs($this->engineerAli)->post(route('pm.tasks.complete', $this->schedule), [
            'notes' => 'Cleaned card reader, tested thermal printer, unit OK.',
            'performed_at' => now()->format('Y-m-d H:i:s'),
            'document' => $doc,
        ]);

        $completeRes->assertRedirect(route('pm.tasks.index'));

        // Assert record exists
        $this->assertDatabaseHas('pm_records', [
            'pm_schedule_id' => $this->schedule->id,
            'pm_machine_id' => $this->machine->id,
            'performed_by_id' => $this->engineerAli->id,
            'status' => 'completed',
            'document_original_name' => 'service_report.pdf',
        ]);

        // Assert schedule advanced by 30 days
        $this->schedule->refresh();
        $this->assertEquals(now()->addDays(30)->toDateString(), $this->schedule->next_due_date->toDateString());
    }

    public function test_completed_pm_task_is_editable_and_document_can_be_replaced(): void
    {
        // Complete the task
        $doc = UploadedFile::fake()->create('old_report.pdf', 300, 'application/pdf');
        $this->actingAs($this->engineerAli)->post(route('pm.tasks.complete', $this->schedule), [
            'notes' => 'Original observations',
            'document' => $doc,
        ]);

        $record = PmRecord::where('pm_schedule_id', $this->schedule->id)->first();
        $this->assertNotNull($record);

        // Ali visits /pm/tasks — sees Edit Task / Doc button and document link
        $indexRes = $this->actingAs($this->engineerAli)->get(route('pm.tasks.index'));
        $indexRes->assertOk();
        $indexRes->assertSee('Edit Task / Doc');
        $indexRes->assertSee('old_report.pdf');

        // Ali visits edit page for this record
        $editRes = $this->actingAs($this->engineerAli)->get(route('pm.records.edit', $record));
        $editRes->assertOk();
        $editRes->assertSee('Original observations');
        $editRes->assertSee('old_report.pdf');

        // Ali updates record notes and replaces document
        $newDoc = UploadedFile::fake()->create('signed_checklist.png', 400, 'image/png');
        $updateRes = $this->actingAs($this->engineerAli)->put(route('pm.records.update', $record), [
            'notes' => 'Updated notes after customer sign-off',
            'document' => $newDoc,
        ]);

        $updateRes->assertRedirect(route('pm.tasks.index'));

        $record->refresh();
        $this->assertEquals('Updated notes after customer sign-off', $record->notes);
        $this->assertEquals('signed_checklist.png', $record->document_original_name);
    }

    public function test_admin_can_view_document_in_browser_and_download(): void
    {
        $doc = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');
        $this->actingAs($this->engineerAli)->post(route('pm.tasks.complete', $this->schedule), [
            'notes' => 'All done',
            'document' => $doc,
        ]);

        $record = PmRecord::where('pm_schedule_id', $this->schedule->id)->first();

        // Admin views document inline
        $viewRes = $this->actingAs($this->admin)->get(route('pm.records.view', $record));
        $viewRes->assertOk();

        // Admin downloads document
        $dlRes = $this->actingAs($this->admin)->get(route('pm.records.download', $record));
        $dlRes->assertOk();
    }

    public function test_admin_views_schedules_with_engineer_name_document_link_and_date_filter(): void
    {
        // Complete the task
        $doc = UploadedFile::fake()->create('maintenance.pdf', 200, 'application/pdf');
        $this->actingAs($this->engineerAli)->post(route('pm.tasks.complete', $this->schedule), [
            'notes' => 'Done',
            'document' => $doc,
        ]);

        // Admin visits /pm/schedules
        $res = $this->actingAs($this->admin)->get(route('pm.schedules.index'));
        $res->assertOk();
        $res->assertSee('Ali Khan'); // Responsible engineer clearly visible
        $res->assertSee('View Doc'); // Supporting doc link visible
        $res->assertSee('Monthly Docs'); // Monthly records & doc modal button visible

        // Filter by month
        $month = now()->format('Y-m');
        $filteredRes = $this->actingAs($this->admin)->get(route('pm.schedules.index', [
            'month' => $month,
            'date_type' => 'last_done',
        ]));
        $filteredRes->assertOk();
        $filteredRes->assertSee('Monthly Cleaning & Health Check');

        // Admin reassigns engineer
        $reassignRes = $this->actingAs($this->admin)->postJson(route('pm.schedules.reassign', $this->schedule), [
            'assigned_engineer_id' => $this->engineerUsman->id,
        ]);
        $reassignRes->assertOk();

        $this->schedule->refresh();
        $this->assertEquals($this->engineerUsman->id, $this->schedule->assigned_engineer_id);
    }

    public function test_operations_manager_does_not_see_my_pm_tasks_in_sidebar_and_is_redirected_to_schedules(): void
    {
        // 1. Operations Manager visiting /pm/tasks is redirected to /pm/schedules
        $res = $this->actingAs($this->admin)->get(route('pm.tasks.index'));
        $res->assertRedirect(route('pm.schedules.index'));

        // 2. Sidebar for Operations Manager has PM Schedules and Machine Registry, but NOT My PM Tasks
        $schedulePage = $this->actingAs($this->admin)->get(route('pm.schedules.index'));
        $schedulePage->assertOk();
        $schedulePage->assertSee('Machine Registry');
        $schedulePage->assertSee('PM Schedules');
        $schedulePage->assertDontSee('My PM Tasks');

        // 3. Sidebar for Engineer has My PM Tasks, but NOT Machine Registry or PM Schedules
        $engineerPage = $this->actingAs($this->engineerAli)->get(route('pm.tasks.index'));
        $engineerPage->assertOk();
        $engineerPage->assertSee('My PM Tasks');
        $engineerPage->assertDontSee('Machine Registry');
        $engineerPage->assertDontSee('PM Schedules');
    }

    public function test_re_submitting_on_task_show_updates_existing_record_and_replaces_document(): void
    {
        // First completion
        $doc1 = UploadedFile::fake()->create('initial_doc.pdf', 300, 'application/pdf');
        $this->actingAs($this->engineerAli)->post(route('pm.tasks.complete', $this->schedule), [
            'notes' => 'First pass notes',
            'document' => $doc1,
        ]);

        $record = PmRecord::where('pm_schedule_id', $this->schedule->id)->first();
        $this->assertEquals('initial_doc.pdf', $record->document_original_name);
        $this->assertEquals('First pass notes', $record->notes);

        // Re-edit / re-submit with new document and notes on the task page
        $doc2 = UploadedFile::fake()->create('updated_receipt.png', 500, 'image/png');
        $this->actingAs($this->engineerAli)->post(route('pm.tasks.complete', $this->schedule), [
            'notes' => 'Updated pass notes with signed proof',
            'document' => $doc2,
        ]);

        // It must NOT create a duplicate record; it must update the existing record
        $this->assertEquals(1, PmRecord::where('pm_schedule_id', $this->schedule->id)->count());
        $record->refresh();
        $this->assertEquals('updated_receipt.png', $record->document_original_name);
        $this->assertEquals('Updated pass notes with signed proof', $record->notes);
    }
}
