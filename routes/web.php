<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailIntegrationController;
use App\Http\Controllers\EngineerController;
use App\Http\Controllers\EngineerInventoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GrnController;
use App\Http\Controllers\PartRequestController;
use App\Http\Controllers\PartsController;
use App\Http\Controllers\PartStockController;
use App\Http\Controllers\PartTransferController;
use App\Http\Controllers\PmMachineController;
use App\Http\Controllers\PmScheduleController;
use App\Http\Controllers\PmTaskController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::redirect('/escalations', '/tickets/escalations');

    // Tickets
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/open', [TicketController::class, 'openTickets'])->name('open');
        Route::get('/escalations', [TicketController::class, 'escalations'])->name('escalations');
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::get('/create', [TicketController::class, 'create'])->name('create');
        Route::post('/', [TicketController::class, 'store'])->name('store');
        Route::get('/simulate-ingest', [TicketController::class, 'showSimulateIngest'])->name('simulate-ingest');
        Route::post('/simulate-ingest', [TicketController::class, 'processSimulateIngest'])->name('process-simulate-ingest');
        Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
        Route::get('/{ticket}/edit', [TicketController::class, 'edit'])->name('edit');
        Route::put('/{ticket}', [TicketController::class, 'update'])->name('update');
        Route::post('/{ticket}/finalize', [TicketController::class, 'finalizeDetails'])->name('finalize');
        
        // Manual Alignment & WhatsApp / Email flow
        Route::post('/{ticket}/assign', [TicketController::class, 'assignEngineer'])->name('assign');
        Route::post('/{ticket}/notify-whatsapp', [TicketController::class, 'notifyWhatsApp'])->name('notify-whatsapp');
        Route::post('/{ticket}/send-group-whatsapp', [TicketController::class, 'sendGroupWhatsApp'])->name('send-group-whatsapp');
        Route::post('/{ticket}/mark-whatsapp-copied', [TicketController::class, 'markWhatsAppCopied'])->name('mark-whatsapp-copied');
        Route::post('/{ticket}/send-assignment-email', [TicketController::class, 'sendAssignmentEmail'])->name('send-assignment-email');
        Route::post('/{ticket}/send-resolution-email', [TicketController::class, 'sendResolutionEmail'])->name('send-resolution-email');
        
        // Daily feedback & Lifecycle
        Route::post('/{ticket}/feedback', [TicketController::class, 'addFeedback'])->name('feedback');
        Route::post('/{ticket}/instruct', [TicketController::class, 'addInstruction'])->name('instruct');
        Route::post('/{ticket}/workshop', [TicketController::class, 'reassignWorkshop'])->name('workshop');
        Route::post('/{ticket}/workshop-receive', [\App\Http\Controllers\WorkshopController::class, 'receive'])->name('workshop-receive');
        Route::post('/{ticket}/workshop-resolve', [\App\Http\Controllers\WorkshopController::class, 'resolve'])->name('workshop-resolve');
        Route::post('/{ticket}/workshop-return-dispatch', [\App\Http\Controllers\WorkshopController::class, 'returnDispatch'])->name('workshop-return-dispatch');
        Route::post('/{ticket}/workshop-bank-received', [\App\Http\Controllers\WorkshopController::class, 'confirmBankDelivery'])->name('workshop-bank-received');
        Route::post('/{ticket}/escalate', [TicketController::class, 'escalate'])->name('escalate');
        Route::post('/{ticket}/resolve', [TicketController::class, 'markResolved'])->name('resolve');
        Route::post('/{ticket}/undo-resolve', [TicketController::class, 'undoResolve'])->name('undo-resolve');
        Route::post('/{ticket}/upload-document', [TicketController::class, 'uploadResolutionDocument'])->name('upload-document');
        Route::get('/{ticket}/document', [TicketController::class, 'viewSupportingDocument'])->name('document');
        Route::post('/{ticket}/update-document', [TicketController::class, 'updateSupportingDocument'])->name('update-document');
        Route::post('/{ticket}/close', [TicketController::class, 'closeTicket'])->name('close');
        Route::post('/{ticket}/request-approval', [ApprovalController::class, 'requestApproval'])->name('request-approval');
        Route::post('/{ticket}/grant-approval', [ApprovalController::class, 'grantApproval'])->name('grant-approval');
        Route::delete('/{ticket}', [TicketController::class, 'destroy'])->name('destroy');
    });

    // Pending Approvals Hub
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');

    // Central Workshop Hub
    Route::get('/workshop', [\App\Http\Controllers\WorkshopController::class, 'index'])->name('workshop.index');

    // Expenses
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::post('/upload-voucher', [ExpenseController::class, 'uploadVoucher'])->name('upload-voucher');
        Route::get('/export/csv', [ExpenseController::class, 'exportCsv'])->name('export-csv');
        Route::get('/{claim}', [ExpenseController::class, 'show'])->name('show');
        Route::get('/{claim}/voucher', [ExpenseController::class, 'viewVoucher'])->name('voucher');
        Route::post('/{claim}/update-voucher', [ExpenseController::class, 'updateVoucher'])->name('update-voucher');
        Route::post('/{claim}/approve', [ExpenseController::class, 'approve'])->name('approve');
        Route::post('/{claim}/reject', [ExpenseController::class, 'reject'])->name('reject');
        Route::post('/{claim}/resubmit', [ExpenseController::class, 'resubmit'])->name('resubmit');
        Route::post('/{claim}/pay', [ExpenseController::class, 'pay'])->name('pay');
        Route::post('/bulk-pay', [ExpenseController::class, 'bulkPay'])->name('bulk-pay');
    });

    // Reports & Executive SLA Analytics (Phase 5)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/csv', [ReportController::class, 'exportCsv'])->name('export-csv');
        Route::get('/export/pdf', [ReportController::class, 'exportPdf'])->name('export-pdf');
    });

    // Engineers (Admin & Superior directory)
    Route::prefix('engineers')->name('engineers.')->group(function () {
        Route::get('/', [EngineerController::class, 'index'])->name('index');
        Route::get('/{engineer}', [EngineerController::class, 'show'])->name('show');
        Route::post('/{engineer}/toggle-availability', [EngineerController::class, 'toggleAvailability'])->name('toggle-availability');
    });

    // GoDaddy Email Integration Settings, Webmail & Complaint Triage
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/email', [EmailIntegrationController::class, 'index'])->name('email');
        Route::post('/email/save', [EmailIntegrationController::class, 'saveSettings'])->name('email.save');
        Route::post('/email/sync', [EmailIntegrationController::class, 'syncNow'])->name('email.sync');
        Route::post('/email/empty', [EmailIntegrationController::class, 'emptyMailbox'])->name('email.empty');
        Route::post('/email/auto-triage', [EmailIntegrationController::class, 'autoTriageAll'])->name('email.auto-triage');
        Route::post('/email/compose', [EmailIntegrationController::class, 'composeEmail'])->name('email.compose');
        Route::post('/email/{email}/reply', [EmailIntegrationController::class, 'replyEmail'])->name('email.reply');
        Route::post('/email/{email}/convert', [EmailIntegrationController::class, 'convertToComplaint'])->name('email.convert');
    });

    // ─── PHASE 6: Parts Management System ─────────────────────────────────────
    Route::prefix('parts')->name('parts.')->group(function () {

        // Part Requests (Engineer submit + Staff/Manager workflow)
        Route::prefix('requests')->name('requests.')->group(function () {
            Route::get('/', [PartRequestController::class, 'index'])->name('index');
            Route::get('/create', [PartRequestController::class, 'create'])->name('create');
            Route::post('/', [PartRequestController::class, 'store'])->name('store');
            Route::get('/{partRequest}', [PartRequestController::class, 'show'])->name('show');
            Route::get('/{partRequest}/edit', [PartRequestController::class, 'edit'])->name('edit');
            Route::put('/{partRequest}', [PartRequestController::class, 'update'])->name('update');
            Route::post('/{partRequest}/verify-stock', [PartRequestController::class, 'verifyStock'])->name('verify-stock');
            Route::post('/{partRequest}/approve', [PartRequestController::class, 'approve'])->name('approve');
            Route::post('/{partRequest}/reject', [PartRequestController::class, 'reject'])->name('reject');
            Route::post('/{partRequest}/dispatch', [PartRequestController::class, 'dispatch'])->name('dispatch');
            Route::get('/{partRequest}/video', [PartRequestController::class, 'streamVideo'])->name('video');
            Route::get('/{partRequest}/video/download', [PartRequestController::class, 'downloadVideo'])->name('video.download');
            Route::post('/{partRequest}/update-video', [PartRequestController::class, 'updateVideo'])->name('video.update');
            Route::post('/{partRequest}/verify-faulty-return', [PartRequestController::class, 'verifyFaultyReturn'])->name('verify-faulty-return');
            Route::get('/{partRequest}/gate-pass', [PartRequestController::class, 'gatePass'])->name('gate-pass');
        });

        // Engineer Advance Inventory / Envelope Float
        Route::prefix('envelopes')->name('envelopes.')->group(function () {
            Route::get('/', [EngineerInventoryController::class, 'index'])->name('index');
            Route::post('/lend', [EngineerInventoryController::class, 'lend'])->name('lend');
            Route::post('/return', [EngineerInventoryController::class, 'returnToStore'])->name('return');
        });

        // GRN - Goods Received Notes
        Route::prefix('grn')->name('grn.')->group(function () {
            Route::get('/', [GrnController::class, 'index'])->name('index');
            Route::get('/create', [GrnController::class, 'create'])->name('create');
            Route::post('/', [GrnController::class, 'store'])->name('store');
            Route::get('/{grn}', [GrnController::class, 'show'])->name('show');
            Route::post('/{grn}/confirm', [GrnController::class, 'confirm'])->name('confirm');
            Route::delete('/{grn}', [GrnController::class, 'destroy'])->name('destroy');
        });

        // Stock Ledger & Movements
        Route::prefix('stock')->name('stock.')->group(function () {
            Route::get('/', [PartStockController::class, 'index'])->name('index');
            Route::get('/movements', [PartStockController::class, 'movements'])->name('movements');
            Route::post('/adjustment', [PartStockController::class, 'adjustment'])->name('adjustment');
        });

        // Inter-Location Transfers
        Route::prefix('transfers')->name('transfers.')->group(function () {
            Route::get('/', [PartTransferController::class, 'index'])->name('index');
            Route::get('/create', [PartTransferController::class, 'create'])->name('create');
            Route::post('/', [PartTransferController::class, 'store'])->name('store');
            Route::get('/{transfer}', [PartTransferController::class, 'show'])->name('show');
            Route::post('/{transfer}/dispatch', [PartTransferController::class, 'dispatch'])->name('dispatch');
            Route::post('/{transfer}/receive', [PartTransferController::class, 'receive'])->name('receive');
            Route::post('/{transfer}/cancel', [PartTransferController::class, 'cancel'])->name('cancel');
        });

        // Master Data: Parts Catalog
        Route::prefix('master')->name('master.')->group(function () {
            // Parts
            Route::get('/parts', [PartsController::class, 'partsIndex'])->name('parts');
            Route::get('/parts/create', [PartsController::class, 'partsCreate'])->name('parts.create');
            Route::post('/parts', [PartsController::class, 'partsStore'])->name('parts.store');
            Route::get('/parts/{part}/edit', [PartsController::class, 'partsEdit'])->name('parts.edit');
            Route::put('/parts/{part}', [PartsController::class, 'partsUpdate'])->name('parts.update');
            Route::delete('/parts/{part}', [PartsController::class, 'partsDestroy'])->name('parts.destroy');
            Route::post('/parts/{part}/toggle', [PartsController::class, 'partsToggleActive'])->name('parts.toggle');

            // Machine Models
            Route::get('/models', [PartsController::class, 'modelsIndex'])->name('models');
            Route::post('/models', [PartsController::class, 'modelsStore'])->name('models.store');
            Route::put('/models/{model}', [PartsController::class, 'modelsUpdate'])->name('models.update');
            Route::delete('/models/{model}', [PartsController::class, 'modelsDestroy'])->name('models.destroy');
            Route::post('/models/{model}/attach-part', [PartsController::class, 'modelsAttachPart'])->name('models.attach-part');
            Route::delete('/models/{model}/detach-part/{part}', [PartsController::class, 'modelsDetachPart'])->name('models.detach-part');

            // Locations
            Route::get('/locations', [PartsController::class, 'locationsIndex'])->name('locations');
            Route::post('/locations', [PartsController::class, 'locationsStore'])->name('locations.store');
            Route::put('/locations/{location}', [PartsController::class, 'locationsUpdate'])->name('locations.update');
        });

        // AJAX helpers
        Route::get('/api/search-parts', [PartsController::class, 'searchParts'])->name('api.search-parts');
        Route::get('/api/search-models', [PartsController::class, 'searchModels'])->name('api.search-models');
        Route::get('/api/model-parts/{model}', [PartsController::class, 'modelParts'])->name('api.model-parts');
    });

    // ─── PREVENTIVE MAINTENANCE MODULE ────────────────────────────────────────

    // PM Tasks (Engineers + Admins)
    Route::prefix('pm/tasks')->name('pm.tasks.')->group(function () {
        Route::get('/', [PmTaskController::class, 'index'])->name('index');
        Route::get('/{schedule}', [PmTaskController::class, 'show'])->name('show');
        Route::post('/{schedule}/complete', [PmTaskController::class, 'complete'])->name('complete');
    });

    // PM Document view & download (engineers & admins)
    Route::get('/pm/records/{record}/view', [PmTaskController::class, 'viewDocument'])->name('pm.records.view');
    Route::get('/pm/records/{record}/download', [PmTaskController::class, 'downloadDocument'])->name('pm.records.download');
    Route::get('/pm/records/{record}/edit', [PmTaskController::class, 'editRecord'])->name('pm.records.edit');
    Route::put('/pm/records/{record}', [PmTaskController::class, 'updateRecord'])->name('pm.records.update');

    // PM Machine Registry (Admin only)
    Route::prefix('pm/machines')->name('pm.machines.')->group(function () {
        Route::get('/', [PmMachineController::class, 'index'])->name('index');
        Route::get('/create', [PmMachineController::class, 'create'])->name('create');
        Route::post('/', [PmMachineController::class, 'store'])->name('store');
        Route::get('/{machine}', [PmMachineController::class, 'show'])->name('show');
        Route::get('/{machine}/edit', [PmMachineController::class, 'edit'])->name('edit');
        Route::put('/{machine}', [PmMachineController::class, 'update'])->name('update');
    });

    // PM Schedules (Admin only)
    Route::prefix('pm/schedules')->name('pm.schedules.')->group(function () {
        Route::get('/', [PmScheduleController::class, 'index'])->name('index');
        Route::get('/create', [PmScheduleController::class, 'create'])->name('create');
        Route::post('/', [PmScheduleController::class, 'store'])->name('store');
        Route::get('/{schedule}/edit', [PmScheduleController::class, 'edit'])->name('edit');
        Route::put('/{schedule}', [PmScheduleController::class, 'update'])->name('update');
        Route::post('/{schedule}/reassign', [PmScheduleController::class, 'reassign'])->name('reassign');
    });

    // ─── NOTIFICATION SYSTEM ──────────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('mark-read');
        Route::post('/{notification}/read/alias', [NotificationController::class, 'markRead'])->name('markRead');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('/mark-all-read/alias', [NotificationController::class, 'markAllRead'])->name('markAllRead');
    });

    // ─── USER MANAGEMENT & ROLE ASSIGNMENT (Admin Only) ───────────────────────
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::match(['post', 'patch'], '/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
        Route::match(['post', 'patch'], '/{user}/toggle-status/alias', [UserController::class, 'toggleStatus'])->name('toggleStatus');
    });
});

// Direct storage file server to guarantee all uploaded assets are accessible even without symlinks
Route::get('/storage/{path}', function (string $path) {
    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        return \Illuminate\Support\Facades\Storage::disk('public')->response($path);
    }
    abort(404, 'File not found in storage.');
})->where('path', '.*')->name('storage.file');

