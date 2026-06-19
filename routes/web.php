<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GithubController;
use App\Http\Controllers\TicketCategoryController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TriageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth routes (no middleware)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware('auth.app')->group(function () {
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/tickets/{id}', fn($id) => Inertia::render('Tickets/Show', ['ticketId' => $id]))->name('tickets.show');

    // Ticket storage (MySQL)
    Route::get('/api/tickets', [TicketController::class, 'index']);
    Route::get('/api/tickets/{vendorId}', [TicketController::class, 'show'])->whereNumber('vendorId');
    Route::post('/api/tickets/sync', [TicketController::class, 'sync']);
    Route::get('/api/tickets/sync/status', [TicketController::class, 'syncStatus']);

    // Vendor API proxy (CORS prevents direct browser calls)
    Route::get('/api/vendor/tickets/detail', [TicketController::class, 'detail']);
    Route::get('/api/vendor/tickets/summary', [TicketController::class, 'summary']);

    // Triage (awaiting client)
    Route::get('/api/triage/awaiting-client', [TriageController::class, 'index']);
    Route::post('/api/triage/awaiting-client', [TriageController::class, 'store']);
    Route::delete('/api/triage/awaiting-client/{ticketId}', [TriageController::class, 'destroy']);

    // Ticket categories
    Route::get('/api/ticket-categories', [TicketCategoryController::class, 'index']);
    Route::post('/api/ticket-categories', [TicketCategoryController::class, 'store']);
    Route::put('/api/ticket-categories/{ticketCategory}', [TicketCategoryController::class, 'update']);
    Route::delete('/api/ticket-categories/{ticketCategory}', [TicketCategoryController::class, 'destroy']);
    Route::patch('/api/tickets/{vendorId}/category', [TicketCategoryController::class, 'assignToTicket'])->whereNumber('vendorId');

    // GitHub branches (stored on tickets, synced via queue)
    Route::get('/api/github/branch', [GithubController::class, 'branch']);
    Route::post('/api/github/branches/sync', [GithubController::class, 'sync']);
    Route::get('/api/github/branches/sync/status', [GithubController::class, 'syncStatus']);
});
