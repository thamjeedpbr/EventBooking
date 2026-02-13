<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\PreventDoubleBooking;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public event routes (anyone can view)
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Event routes (organizer only)
    Route::middleware(CheckRole::class . ':organizer,admin')->group(function () {
        Route::post('/events', [EventController::class, 'store']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);

        // Ticket routes (organizer only)
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::put('/tickets/{id}', [TicketController::class, 'update']);
        Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
    });

    // Booking routes (customer only)
    Route::middleware(CheckRole::class . ':customer')->group(function () {
        Route::post('/bookings', [BookingController::class, 'store'])
            ->middleware(PreventDoubleBooking::class);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

        // Payment routes (customer only)
        Route::post('/payments/process', [PaymentController::class, 'processPayment']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
    });
});
