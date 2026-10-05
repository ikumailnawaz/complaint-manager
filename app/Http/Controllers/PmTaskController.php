<?php

namespace App\Http\Controllers;

use App\Models\PmRecord;
use App\Models\PmSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PmTaskController extends Controller
{
    /**
     * Engineer: My PM tasks.
     * Shows overdue + due this month tasks assigned to the logged-in engineer.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isSuperior()) {
            return redirect()->route('pm.schedules.index');
        }

        $baseQuery = PmSchedule::with([
            'machine.machineModel',
            'machine.assignedEngineer',
            'assignedEngineer',
            'records' => fn($q) => $q->with('performedBy')->latest('performed_at'),
        ])
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                // Either the schedule has this engineer, or the machine's default engineer is this user
                $q->where('assigned_engineer_id', $user->id)
                  ->orWhere(function ($q2) use ($user) {
                      $q2->whereNull('assigned_engineer_id')
                         ->whereHas('machine', fn($m) => $m->where('assigned_engineer_id', $user->id));
                  });
            });

        $tab = $request->get('tab', 'due');

        if ($tab === 'completed') {
            $schedules = (clone $baseQuery)
                ->whereHas('records', fn($r) =>
                    $r->where('status', 'completed')
                      ->whereMonth('performed_at', now()->month)
                      ->whereYear('performed_at', now()->year)
                )->orderBy('next_due_date')->paginate(20)->withQueryString();
        } else {
            // Default: due this month, overdue, or completed this month
            $schedules = (clone $baseQuery)
                ->where(function ($q) {
                    $q->where('next_due_date', '<=', now()->endOfMonth()->toDateString())
                      ->orWhereHas('records', fn($r) =>
                          $r->where('status', 'completed')
                            ->whereMonth('performed_at', now()->month)
                            ->whereYear('performed_at', now()->year)
                      );
                })
                ->orderBy('next_due_date')
                ->paginate(20)->withQueryString();
        }

        // Counts for tabs
        $overdueCount = (clone $baseQuery)
            ->where('next_due_date', '<', now()->toDateString())->count();
        $dueSoonCount = (clone $baseQuery)
            ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])->count();

        return view('pm.tasks.index', compact('schedules', 'tab', 'overdueCount', 'dueSoonCount'));
    }

    /**
     * Engineer: Task detail + completion form.
     */
    public function show(PmSchedule $schedule)
    {
        $this->authorizeSchedule($schedule);

        $schedule->load([
            'machine.machineModel',
            'machine.assignedEngineer',
            'records' => fn($q) => $q->with('performedBy')->latest('performed_at')->limit(10)
        ]);

        return view('pm.tasks.show', compact('schedule'));
    }

    /**
     * Engineer: Mark task as completed + upload supporting document.
     */
    public function complete(Request $request, PmSchedule $schedule)
    {
        $this->authorizeSchedule($schedule);

        $data = $request->validate([
            'notes'        => 'nullable|string',
            'performed_at' => 'nullable|date',
            'document'     => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx|max:15360',
        ]);

        $performedAt = !empty($data['performed_at']) ? \Carbon\Carbon::parse($data['performed_at']) : now();
        $today       = $performedAt->toDateString();
        $isOverdue   = $schedule->next_due_date && $schedule->next_due_date->lt(now()->startOfDay());

        // Check if a record already exists for this schedule (completed today or for this month)
        $existingRecord = PmRecord::where('pm_schedule_id', $schedule->id)
            ->where('status', 'completed')
            ->where(function ($q) use ($today) {
                $q->whereDate('performed_at', $today)
                  ->orWhereDate('created_at', now()->toDateString())
                  ->orWhere(function ($q2) {
                      $q2->whereMonth('performed_at', now()->month)
                         ->whereYear('performed_at', now()->year);
                  });
            })
            ->latest('performed_at')
            ->first();

        if ($existingRecord) {
            // Engineer is re-editing or uploading document for the existing completion
            if ($request->hasFile('document')) {
                if ($existingRecord->document_path && Storage::disk('public')->exists($existingRecord->document_path)) {
                    Storage::disk('public')->delete($existingRecord->document_path);
                }
                $file = $request->file('document');
                $existingRecord->document_original_name = $file->getClientOriginalName();
                $existingRecord->document_path = $file->store('pm-documents', 'public');
            }

            if ($request->has('notes')) {
                $existingRecord->notes = $request->input('notes');
            }

            $existingRecord->performed_by_id = auth()->id();
            $existingRecord->performed_at = $performedAt;
            $existingRecord->save();

            $schedule->update(['last_performed_at' => $today]);

            return redirect()->route('pm.tasks.index')
                ->with('success', "✅ Maintenance record for {$schedule->machine->serial_number} updated successfully.");
        }

        $docPath = null;
        $docName = null;
        if ($request->hasFile('document')) {
            $file    = $request->file('document');
            $docName = $file->getClientOriginalName();
            $docPath = $file->store("pm-documents", 'public');
        }

        // Create new service record
        $record = PmRecord::create([
            'pm_schedule_id'        => $schedule->id,
            'pm_machine_id'         => $schedule->pm_machine_id,
            'performed_by_id'       => auth()->id(),
            'performed_at'          => $performedAt,
            'due_date'              => $schedule->next_due_date?->toDateString() ?? $today,
            'status'                => 'completed',
            'notes'                 => $data['notes'] ?? null,
            'document_path'         => $docPath,
            'document_original_name'=> $docName,
            'is_overdue'            => $isOverdue,
        ]);

        // Advance schedule
        $schedule->update([
            'last_performed_at' => $today,
            'next_due_date'     => now()->addDays($schedule->frequency_days)->toDateString(),
        ]);

        return redirect()->route('pm.tasks.index')
            ->with('success', "✅ Maintenance for {$schedule->machine->serial_number} marked complete. Next due: " . now()->addDays($schedule->frequency_days)->format('d M Y'));
    }

    /**
     * View supporting document inline in browser.
     */
    public function viewDocument(PmRecord $record)
    {
        $user = auth()->user();
        abort_unless(
            $user->isSuperior() || 
            $record->performed_by_id === $user->id || 
            $record->schedule?->assigned_engineer_id === $user->id || 
            $record->machine?->assigned_engineer_id === $user->id,
            403,
            'You do not have permission to view this document.'
        );
        abort_unless($record->document_path && Storage::disk('public')->exists($record->document_path), 404, 'File not found on server.');

        $path = Storage::disk('public')->path($record->document_path);
        $mime = Storage::disk('public')->mimeType($record->document_path) ?? 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . ($record->document_original_name ?? basename($path)) . '"',
        ]);
    }

    /**
     * Serve a document download for a pm_record.
     */
    public function downloadDocument(PmRecord $record)
    {
        $user = auth()->user();
        abort_unless(
            $user->isSuperior() || 
            $record->performed_by_id === $user->id || 
            $record->schedule?->assigned_engineer_id === $user->id || 
            $record->machine?->assigned_engineer_id === $user->id,
            403,
            'You do not have permission to download this document.'
        );
        abort_unless($record->document_path && Storage::disk('public')->exists($record->document_path), 404, 'File not found on server.');

        return Storage::disk('public')->download($record->document_path, $record->document_original_name ?? 'document');
    }

    /**
     * Edit a completed service record (notes, replace document).
     */
    public function editRecord(PmRecord $record)
    {
        $user = auth()->user();
        abort_unless(
            $user->isSuperior() || 
            $record->performed_by_id === $user->id || 
            $record->schedule?->assigned_engineer_id === $user->id || 
            $record->machine?->assigned_engineer_id === $user->id,
            403,
            'You are not authorized to edit this record.'
        );

        $record->load(['schedule.machine.machineModel', 'performedBy', 'machine']);

        return view('pm.records.edit', compact('record'));
    }

    /**
     * Update a completed service record.
     */
    public function updateRecord(Request $request, PmRecord $record)
    {
        $user = auth()->user();
        abort_unless(
            $user->isSuperior() || 
            $record->performed_by_id === $user->id || 
            $record->schedule?->assigned_engineer_id === $user->id || 
            $record->machine?->assigned_engineer_id === $user->id,
            403,
            'You are not authorized to edit this record.'
        );

        $data = $request->validate([
            'notes'        => 'nullable|string',
            'performed_at' => 'nullable|date',
            'document'     => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx|max:15360',
        ]);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            if ($file->isValid()) {
                // Delete old file if exists
                if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                    Storage::disk('public')->delete($record->document_path);
                }
                $record->document_original_name = $file->getClientOriginalName();
                $record->document_path = $file->store('pm-documents', 'public');
            }
        }

        if ($request->has('notes')) {
            $record->notes = $request->input('notes');
        }

        if (!empty($data['performed_at'])) {
            $record->performed_at = \Carbon\Carbon::parse($data['performed_at']);
        }

        if ($user->isEngineer()) {
            $record->performed_by_id = $user->id;
        }

        $record->save();

        if ($record->schedule) {
            $record->schedule->update([
                'last_performed_at' => $record->performed_at?->toDateString() ?? now()->toDateString(),
            ]);
        }

        if ($user->isEngineer()) {
            return redirect()->route('pm.tasks.index')
                ->with('success', "✅ PM task record for {$record->machine->serial_number} updated successfully.");
        }

        return redirect()->route('pm.machines.show', $record->pm_machine_id)
            ->with('success', "✅ Service record updated successfully.");
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function authorizeSchedule(PmSchedule $schedule): void
    {
        $user = auth()->user();
        if ($user->isSuperior()) return; // admins can always view/act

        $responsibleId = $schedule->assigned_engineer_id
            ?? $schedule->machine?->assigned_engineer_id;

        abort_unless($responsibleId === $user->id, 403, 'This task is not assigned to you.');
    }
}
