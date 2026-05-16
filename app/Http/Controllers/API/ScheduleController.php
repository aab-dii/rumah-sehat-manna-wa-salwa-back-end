<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
                'start_time' => $request->start_time,
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
    // Emergency Close for a specific date
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($therapistId, $date, $reason) {
            // 1. Mark Schedule as inactive/emergency (Per-therapist closure)
            Schedule::updateOrCreate(
                ['therapist_id' => $therapistId, 'specific_date' => $date],
                [
                    'is_active' => false, 
                    'type' => 'emergency', 
                    'note' => $reason, 
                    'start_time' => '00:00', 
                    'end_time' => '00:00',
                    'location_type' => 'clinic'
                ]
            );

            // 2. Find and cancel bookings for this therapist
            $bookings = \App\Models\Booking::where('booking_date', $date)
                ->where('therapist_id', $therapistId)
                ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi', 'terjadwal'])
                ->with(['patient', 'service'])
                ->get();

            $count = 0;
            foreach ($bookings as $booking) {
                $booking->update([
                    'status' => 'canceled',
                    'cancellation_reason' => "Tutup Darurat: $reason"
                ]);

                // 3. Send Notification
                if ($booking->patient && $booking->patient->fcm_token) {
                    \App\Services\FcmService::send(
                        to: $booking->patient->fcm_token,
                        title: "Pembatalan Darurat Terapis \u{26A0}\u{FE0F}",
                        body: "Mohon maaf, janji temu Anda dengan terapis kami pada " . Carbon::parse($date)->translatedFormat('d F Y') . " dibatalkan karena keadaan darurat: $reason",
                        data: [
                            'type' => 'emergency_cancellation',
                            'booking_id' => (string) $booking->id
                        ],
                        type: 'emergency_cancellation',
                        userId: $booking->patient_id,
                        role: 'pasien'
                    );
                }
                $count++;
            }

            return ResponseFormatter::success(
                ['cancelled_count' => $count],
                "Berhasil menutup jadwal. $count booking telah dibatalkan dan pasien telah dinotifikasi."
            );
        });
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($therapistId, $startDate, $endDate, $reason) {
            // 1. Create Holiday Record
            Schedule::create([
                'therapist_id' => $therapistId,
                'specific_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'is_active' => false,
                'type' => 'holiday',
                'note' => $reason,
                'start_time' => '00:00',
                'end_time' => '00:00',
                'location_type' => 'clinic',
            ]);

            // 2. Find and cancel bookings
            $bookings = \App\Models\Booking::where('therapist_id', $therapistId)
                ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi', 'terjadwal'])
                ->with(['patient', 'service'])
                ->get();

            $count = 0;
            foreach ($bookings as $booking) {
                $booking->update([
                    'status' => 'canceled',
                    'cancellation_reason' => "Jadwal Libur Terapis: $reason"
                ]);

                // 3. Send Notification
                if ($booking->patient && $booking->patient->fcm_token) {
                    \App\Services\FcmService::send(
                        to: $booking->patient->fcm_token,
                        title: "Jadwal Terapis Libur \u{1F3D6}\u{FE0F}",
                        body: "Janji temu Anda pada " . Carbon::parse($booking->booking_date)->translatedFormat('d F Y') . " dibatalkan karena terapis sedang libur: $reason",
                        data: [
                            'type' => 'holiday_cancellation',
                            'booking_id' => (string) $booking->id
                        ],
                        type: 'holiday_cancellation',
                        userId: $booking->patient_id,
                        role: 'pasien'
                    );
                }
                $count++;
            }

            return ResponseFormatter::success(
                ['cancelled_bookings' => $count],
                "Jadwal libur berhasil ditambahkan. $count booking telah dibatalkan dan pasien telah dinotifikasi."
            );
        });
    }
}
