<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\MedicalRecordController;
use App\Http\Controllers\API\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::get('user/firebase/{uid}', [AuthController::class, 'getUserByFirebaseUid']);

Route::get('services', [ServiceController::class, 'all']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'fetch']);
    Route::post('user/update', [AuthController::class, 'updateProfile']);
    Route::get('users', [AuthController::class, 'all']);
    Route::post('users/create', [AuthController::class, 'createUserByAdmin']); // Protected Admin Creation
    Route::post('users/{id}/update', [AuthController::class, 'updateUserByAdmin']); // Admin Update User
    Route::delete('users/{id}', [AuthController::class, 'destroy']); // Soft Delete
    Route::post('users/{id}/restore', [AuthController::class, 'restore']); // Restore Active
    Route::post('logout', [AuthController::class, 'logout']);

    // Services CRUD (Admin)
    Route::post('services', [ServiceController::class, 'store']);
    Route::post('services/{id}', [ServiceController::class, 'update']);
    Route::get('services/{id}', [ServiceController::class, 'show']);
    Route::delete('services/{id}', [ServiceController::class, 'destroy']);

    // Schedule Management
    Route::get('schedules/{therapistId}', [\App\Http\Controllers\API\ScheduleController::class, 'getSchedules']);
    Route::post('schedules/update', [\App\Http\Controllers\API\ScheduleController::class, 'updateSchedule']);
    Route::post('schedules/close-now', [\App\Http\Controllers\API\ScheduleController::class, 'emergencyClose']);
    Route::post('schedules/add-holiday', [\App\Http\Controllers\API\ScheduleController::class, 'addHoliday']);

    Route::get('bookings', [BookingController::class, 'all']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::post('bookings/{id}', [BookingController::class, 'update']);
    Route::get('bookings/{id}', [BookingController::class, 'show']);
    Route::delete('bookings/{id}', [BookingController::class, 'destroy']);
    Route::put('bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::get('available-slots', [BookingController::class, 'availableSlots']); // New Endpoint
    Route::post('checkout', [BookingController::class, 'checkout']);
    Route::post('bookings/{id}/reupload-proof', [BookingController::class, 'reuploadProof']);
    Route::post('bookings/{id}/reject-payment', [BookingController::class, 'rejectPayment']);

    Route::get('dashboard', [\App\Http\Controllers\API\DashboardController::class, 'index']);
    Route::get('medical-records', [MedicalRecordController::class, 'all']);
});
