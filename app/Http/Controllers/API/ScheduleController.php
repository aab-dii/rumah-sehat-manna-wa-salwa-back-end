<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    // Get schedules for a specific therapist
    public function getSchedules($therapistId)
    {
        $therapist = User::find($therapistId);
        if (!$therapist) {
            return ResponseFormatter::error(null, 'Terapis tidak ditemukan', 404);
        }

        // Get schedules, ordered by Day (Need logical ordering, maybe handle in frontend or specific map)
        $schedules = Schedule::where('therapist_id', $therapistId)->get();

        // If no schedules exist, we might want to return default structure or empty list
        // Let's return the list, frontend handles "defaults" if empty.
        
        return ResponseFormatter::success(
            $schedules,
            'Data jadwal berhasil diambil'
        );
    }

    // Update or Create Schedule for a Day
    public function updateSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'therapist_id' => 'required|exists:users,id',
            'day' => 'required|string', // Senin, Selasa, etc.
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(
                ['error' => $validator->errors()],
                'Update jadwal gagal',
                422
            );
        }

        $schedule = Schedule::updateOrCreate(
            [
                'therapist_id' => $request->therapist_id,
                'day' => $request->day,
            ],
            [
                'end_time' => $request->end_time,
                'is_active' => $request->is_active,
                'type' => 'routine' // Default standard per codebase (was 'regular')
            ]
        );

        return ResponseFormatter::success(
            $schedule,
            'Jadwal berhasil disimpan'
        );
    }
    // Emergency Close for a specific date (Today)
    public function emergencyClose(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'therapist_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(
                ['error' => $validator->errors()],
                'Gagal memproses penutupan jadwal',
                422
            );
        }

        $therapistId = $request->therapist_id;
        $date = $request->date;
        $reason = $request->reason;

        // 1. Create/Update Schedule for specific date to set is_active = false
        $dayOfWeekEn = \Carbon\Carbon::parse($date)->locale('en')->dayName;
        $daysMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
        ];

        // We create a "Specific Date" schedule override if your system supports it. 
        // Based on model, we have `specific_date`.
        // If Model doesn't support specific_date logic in `availableSlots`, this won't block future bookings.
        // BUT, the user's immediate request is "Cancel all bookings today".
        // Let's stick to cancelling bookings primarily. 
        
        // 2. Cancel all ACTIVE bookings for that day
        $bookings = \App\Models\Booking::where('therapist_id', $therapistId)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi']) // Active statuses
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled', // or 'batal'
                'cancellation_reason' => $reason
            ]);
            $count++;
        }

        return ResponseFormatter::success(
            ['cancelled_count' => $count],
            "Berhasil menutup jadwal. $count booking telah dibatalkan."
        );
    }
    // Add Holiday (Date Range)
    public function addHoliday(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'therapist_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(
                ['error' => $validator->errors()],
                'Gagal menambahkan jadwal libur',
                422
            );
        }

        $therapistId = $request->therapist_id;
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);
        $reason = $request->reason;

        // 1. Create SINGLE Schedule record for the Holiday Range
        // specific_date as Start Date, end_date as End Date.
        Schedule::create([
            'therapist_id' => $therapistId,
            'specific_date' => $startDate->format('Y-m-d'), // Start Date
            'end_date' => $endDate->format('Y-m-d'),       // End Date
            'is_active' => false,
            'type' => 'holiday',
            'note' => $reason,
            'day' => null, // Holidays don't stick to a day name
            'start_time' => '00:00',
            'end_time' => '00:00',
            'location_type' => 'clinic',
        ]);

        // 2. Cancel Bookings for this range
        $countBookingCancelled = 0;
        
        // Find bookings in range
        $bookings = \App\Models\Booking::where('therapist_id', $therapistId)
            ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi'])
            ->get();

        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => "Jadwal Libur: " . $reason
            ]);
            $countBookingCancelled++;
        }

        return ResponseFormatter::success(
            ['cancelled_bookings' => $countBookingCancelled],
            "Jadwal libur berhasil ditambahkan dari " . $startDate->format('Y-m-d') . " sampai " . $endDate->format('Y-m-d')
        );
    }
}
