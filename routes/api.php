<?php

use App\Http\Controllers\Api\MobileApiController;
use App\Http\Controllers\Api\TicketIngestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes are prefixed with /api
*/

// Public Authentication Endpoint
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [MobileApiController::class, 'login'])->name('api.auth.login');
});

// Protected Mobile App Endpoints (Field Engineers & Mobile Users)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::post('/auth/logout', [MobileApiController::class, 'logout'])->name('api.auth.logout');
    Route::get('/engineer/profile', [MobileApiController::class, 'profile'])->name('api.engineer.profile');

    // Complaint Tickets
    Route::get('/engineer/tickets', [MobileApiController::class, 'getTickets'])->name('api.engineer.tickets');
    Route::get('/engineer/tickets/{id}', [MobileApiController::class, 'getTicketDetails'])->name('api.engineer.tickets.show');
    Route::post('/engineer/tickets/{id}/feedback', [MobileApiController::class, 'submitFeedback'])->name('api.engineer.tickets.feedback');
    Route::post('/engineer/tickets/{id}/resolve', [MobileApiController::class, 'resolveTicket'])->name('api.engineer.tickets.resolve');
    Route::post('/engineer/tickets/{id}/mark-undone', [MobileApiController::class, 'markUndone'])->name('api.engineer.tickets.mark-undone');
    Route::post('/engineer/tickets/{id}/request-approval', [MobileApiController::class, 'requestApproval'])->name('api.engineer.tickets.request-approval');
    Route::post('/engineer/tickets/{id}/workshop', [MobileApiController::class, 'sendToWorkshop'])->name('api.engineer.tickets.workshop');

    // Tour Expenses
    Route::get('/engineer/expenses', [MobileApiController::class, 'getExpenses'])->name('api.engineer.expenses');
    Route::post('/engineer/expenses', [MobileApiController::class, 'submitExpense'])->name('api.engineer.expenses.store');

    // Spare Parts & Personal Advance Envelope
    Route::get('/engineer/envelope', [MobileApiController::class, 'getEnvelope'])->name('api.engineer.envelope');

    // Preventive Maintenance
    Route::get('/engineer/pm-tasks', [MobileApiController::class, 'getPmTasks'])->name('api.engineer.pm-tasks');
    Route::post('/engineer/pm-tasks/{id}/complete', [MobileApiController::class, 'completePmTask'])->name('api.engineer.pm-tasks.complete');

    // Spare Part Requests
    Route::get('/engineer/part-requests', [MobileApiController::class, 'getPartRequests'])->name('api.engineer.part-requests');
    Route::post('/engineer/part-requests', [MobileApiController::class, 'submitPartRequest'])->name('api.engineer.part-requests.store');
    Route::get('/engineer/parts-catalog', [MobileApiController::class, 'getPartsCatalog'])->name('api.engineer.parts-catalog');
    Route::get('/engineer/machine-models', [MobileApiController::class, 'getMachineModels'])->name('api.engineer.machine-models');
    Route::get('/engineer/machine-models/{id}/parts', [MobileApiController::class, 'getModelParts'])->name('api.engineer.machine-models.parts');

    // Notifications
    Route::get('/engineer/notifications', [MobileApiController::class, 'getNotifications'])->name('api.engineer.notifications');
    Route::post('/engineer/notifications/{id}/read', [MobileApiController::class, 'markNotificationRead'])->name('api.engineer.notifications.read');
});


// n8n Webhook / Email Ingestion API
Route::post('/v1/tickets/ingest', [TicketIngestController::class, 'ingest'])->name('api.tickets.ingest');
