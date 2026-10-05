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

    // Tour Expenses
    Route::get('/engineer/expenses', [MobileApiController::class, 'getExpenses'])->name('api.engineer.expenses');
    Route::post('/engineer/expenses', [MobileApiController::class, 'submitExpense'])->name('api.engineer.expenses.store');

    // Spare Parts & Personal Advance Envelope
    Route::get('/engineer/envelope', [MobileApiController::class, 'getEnvelope'])->name('api.engineer.envelope');

    // Preventive Maintenance
    Route::get('/engineer/pm-tasks', [MobileApiController::class, 'getPmTasks'])->name('api.engineer.pm-tasks');
});

// n8n Webhook / Email Ingestion API
Route::post('/v1/tickets/ingest', [TicketIngestController::class, 'ingest'])->name('api.tickets.ingest');
