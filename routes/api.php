<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\InstructorController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminDashboardController;

/*
|--------------------------------------------------------------------------
| Born Angel API Routes - RBAC
|--------------------------------------------------------------------------
|
| Role Hierarchies (Strict):
| 1. Super Admin: God Mode (Manage Admins, Veto power). Master Account (ID 1) immutable.
| 2. Admin: Operational Mode (Manage Services, Schedules, Users). Cannot touch Super Admins.
| 3. Instructor: Restricted Mode (View Own Schedule/Reviews only).
| 4. User: Customer Mode (Book, Review).
|
*/

// =================================================================================
// 1. PUBLIC ROUTES (No Authentication Required)
// =================================================================================

// Authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public Read-Only Data
// Note: /schedules and /reviews are context-aware (show different data based on auth status)
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{id}', [ServiceController::class, 'show']);

Route::get('/instructors', [InstructorController::class, 'index']);
Route::get('/instructors/{id}', [InstructorController::class, 'show']);

Route::get('/schedules', [ScheduleController::class, 'index']); // Context-aware: Public sees upcoming only
Route::get('/schedules/{id}', [ScheduleController::class, 'show']);

Route::get('/testimonials', [ReviewController::class, 'testimonials']); // Public: all reviews for homepage

// Payments Webhook (Midtrans)
Route::post('/payments/callback', [PaymentController::class, 'callback']);


// =================================================================================
// 2. AUTHENTICATED ROUTES (Sanctum Token Required)
// =================================================================================
Route::middleware('auth:sanctum')->group(function () {

    // -----------------------------------------------------------------------------
    // Auth & Profile (All Authenticated Roles)
    // -----------------------------------------------------------------------------
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

    // Debug endpoint (TEMPORARY - remove in production)
    Route::get('/test-auth', function (Request $request) {
        return response()->json([
            'authenticated' => true,
            'user' => $request->user(),
            'role' => $request->user()->role,
            'guard_check' => auth()->guard('sanctum')->check(),
        ]);
    });

    // -----------------------------------------------------------------------------
    // USER/CUSTOMER Features (role: user)
    // -----------------------------------------------------------------------------
    // Note: Bookings index is accessible to all authenticated users
    // Controller logic handles filtering (User sees own, Admin sees all)
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']); // User only
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']); // Owner or Admin

    // Payments
    Route::get('/payments/snap-token/{booking_id}', [PaymentController::class, 'getSnapToken']);

    // Reviews (GET is here so $request->user() is available for context-aware filtering)
    Route::get('/reviews', [ReviewController::class, 'index']); // User sees own, Instructor sees own classes, Admin sees all
    Route::post('/reviews', [ReviewController::class, 'store']); // User only (must own booking)
    Route::put('/reviews/{id}', [ReviewController::class, 'update']); // Owner only (checked in controller)
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']); // Owner or Admin (checked in controller)

    // -----------------------------------------------------------------------------
    // INSTRUCTOR Features (role: instructor)
    // -----------------------------------------------------------------------------
    // Note: Instructors use the same public endpoints but get filtered data:
    // - GET /schedules -> Returns only their own schedules (past & future)
    // - GET /reviews -> Returns only reviews for their classes
    // No special routes needed - context-aware controllers handle this

    // -----------------------------------------------------------------------------
    // ADMIN & SUPER ADMIN Features (role: admin, super_admin)
    // -----------------------------------------------------------------------------
    Route::middleware('role:admin,super_admin')->group(function () {
        // Dashboard Stats
        Route::get('/admin/dashboard/stats', [AdminDashboardController::class, 'index']);

        // User Management
        Route::get('/users', [UserController::class, 'index']); // Supports ?role=instructor filter
        Route::post('/users', [UserController::class, 'store']); // Create admin/instructor accounts
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']); // Hierarchy enforced in controller
        Route::delete('/users/{id}', [UserController::class, 'destroy']); // Hierarchy enforced in controller

        // Service Management
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);
        Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

        // Instructor Profile Management
        Route::post('/instructors', [InstructorController::class, 'store']);
        Route::put('/instructors/{id}', [InstructorController::class, 'update']);
        Route::delete('/instructors/{id}', [InstructorController::class, 'destroy']);

        // Schedule Management
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::put('/schedules/{id}', [ScheduleController::class, 'update']);
        Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);

        // Reports & Analytics (BI) - Super Admin & Admin
        Route::get('/reports/revenue', [App\Http\Controllers\Api\ReportController::class, 'revenue']);
        Route::get('/reports/services-performance', [App\Http\Controllers\Api\ReportController::class, 'servicePerformance']);
        Route::get('/reports/operational-stats', [App\Http\Controllers\Api\ReportController::class, 'operationalStats']);
        Route::get('/reports/instructor-performance', [App\Http\Controllers\Api\ReportController::class, 'instructorPerformance']);
        Route::get('/reports/peak-hours', [App\Http\Controllers\Api\ReportController::class, 'peakHours']);
    });

});
