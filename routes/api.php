<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BookingController;
// MedicalRecordController removed
use App\Http\Controllers\API\ScheduleController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Events\MyEvent;
use App\Services\FcmService;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // Maks 5 percobaan per menit per IP
Route::post('user/sync-firebase', [AuthController::class, 'syncFirebase'])->middleware('throttle:10,1'); // Maks 10 request per menit

Route::get('services', [ServiceController::class, 'all']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'fetch']);
    Route::post('user/update', [AuthController::class, 'updateProfile']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('update-fcm-token', [AuthController::class, 'updateFcmToken']);

    // User Management (Admin)
    Route::get('users', [UserController::class, 'index']);
    Route::post('users/create', [UserController::class, 'store']);
    Route::get('users/{id}', [UserController::class, 'show']); // Admin User Detail
    Route::post('users/{id}/update', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);
    Route::post('users/{id}/restore', [UserController::class, 'restore']);

    // Services CRUD (Admin)
    Route::post('services', [ServiceController::class, 'store']);
    Route::post('services/{id}', [ServiceController::class, 'update']);
    Route::get('services/{id}', [ServiceController::class, 'show']);
    Route::delete('services/{id}', [ServiceController::class, 'destroy']);

    // Schedule Management
    Route::get('schedules/{therapistId}', [ScheduleController::class, 'getSchedules']);
    Route::post('schedules/update', [ScheduleController::class, 'updateSchedule']);
    Route::post('schedules/close-now', [ScheduleController::class, 'emergencyClose']);
    Route::post('schedules/add-holiday', [ScheduleController::class, 'addHoliday']);

    Route::get('bookings', [BookingController::class, 'all']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::post('bookings/{id}', [BookingController::class, 'update']);
    Route::get('bookings/{id}', [BookingController::class, 'show']);
    Route::delete('bookings/{id}', [BookingController::class, 'destroy']);
    Route::put('bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::post('bookings/{id}/reupload-proof', [BookingController::class, 'reuploadProof']);
    Route::post('bookings/{id}/reject-payment', [BookingController::class, 'rejectPayment']);
    Route::post('bookings/{id}/accept-payment', [BookingController::class, 'acceptPayment']);
    Route::get('available-slots', [BookingController::class, 'availableSlots']);
    Route::get('check-availability', [BookingController::class, 'checkAvailability']);
    Route::post('checkout', [BookingController::class, 'checkout']);
    

    // Role-aware Dashboard (admin / terapis / pasien)
    Route::get('dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);

    // Admin Dashboard (legacy route – kept for backward compat)
    Route::middleware('isAdmin')->prefix('admin')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\API\DashboardController::class, 'index']);
    });
    Route::get('therapy-records', [\App\Http\Controllers\API\TherapyRecordController::class, 'all']);
    Route::post('therapy-records', [\App\Http\Controllers\API\TherapyRecordController::class, 'store']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
});


