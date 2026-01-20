<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return ResponseFormatter::error(null, 'Unauthorized', 403);
        }

        $today = Carbon::now()->toDateString();

        // 1. Statistics
        $stats = [
            'today_bookings_count' => Booking::whereDate('booking_date', $today)->count(),
            'pending_bookings_count' => Booking::whereIn('status', ['pending', 'menunggu', 'konfirmasi'])->count(),
            'total_patients' => User::where('role', 'pasien')->count(),
            'total_therapists' => User::where('role', 'terapis')->count(),
        ];

        // 2. Today's Schedule (Active bookings only)
        // Exclude cancelled? Maybe admin wants to see everything, but mainly active appointments.
        $todaySchedule = Booking::with(['patient', 'therapist', 'service'])
            ->whereDate('booking_date', $today)
            ->whereNotIn('status', ['cancelled', 'canceled', 'batal'])
            ->orderBy('booking_time', 'asc')
            ->limit(10)
            ->get();

        return ResponseFormatter::success([
            'stats' => $stats,
            'today_schedule' => $todaySchedule
        ], 'Dashboard data retrieved successfully');
    }
}
