<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{


    public function show($id) {
        $booking = Booking::with(['patient', 'therapist', 'service'])->find($id);

        if ($booking) {
            return ResponseFormatter::success($booking, 'Detail Booking berhasil diambil');
        } else {
            return ResponseFormatter::error(null, 'Booking tidak ditemukan', 404);
        }
    }

    public function all(Request $request)
    {
        $id = $request->input('id');
        $limit = $request->input('limit', 10);
        $status = $request->input('status');

        if ($id) {
            $booking = Booking::with(['service', 'therapist'])->find($id);

            if ($booking) {
                return ResponseFormatter::success(
                    $booking,
                    'Data booking berhasil diambil'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data booking tidak ada',
                    404
                );
            }
        }

        $booking = Booking::with(['service', 'therapist', 'patient']);

        // Jika bukan admin, hanya ambil booking milik sendiri
        if (Auth::user()->role !== 'admin') {
            $booking->where('patient_id', Auth::user()->id);
        }

        if ($status) {
            // Support comma separated status: "pending,confirmed"
            $statuses = explode(',', $status);
            $booking->whereIn('status', $statuses);
        }

        $date = $request->input('date');
        if ($date) {
            $booking->where('booking_date', $date);
        }

        $search = $request->input('search');
        if ($search) {
             $booking->where(function($q) use ($search) {
                 $q->whereHas('patient', function($sub) use ($search) {
                     $sub->where('name', 'like', "%{$search}%");
                 })->orWhereHas('therapist', function($sub) use ($search) {
                     $sub->where('name', 'like', "%{$search}%");
                 });
             });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at'); // Default created_at
        $sortOrder = $request->input('sort_order', 'desc'); // Default desc
        
        // Allowed sort columns
        if (in_array($sortBy, ['created_at', 'booking_date', 'booking_time'])) {
            $booking->orderBy($sortBy, $sortOrder);
             // Secondary sort for booking_date to ensure consistent time ordering
            if ($sortBy === 'booking_date') {
                 $booking->orderBy('booking_time', $sortOrder);
            }
        } else {
             $booking->orderBy('created_at', 'desc');
        }

        return ResponseFormatter::success(
            $booking->paginate($limit),
            'Data list booking berhasil diambil'
        );
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'therapist_id' => 'required|exists:users,id',
            'booking_date' => 'required|date',
            'booking_time' => 'required',
            'location_type' => 'required|in:clinic,home',
            'address' => 'required|string',
            'total_price' => 'required|integer',
        ]);

        $booking = Booking::create([
            'patient_id' => Auth::user()->id,
            'service_id' => $request->service_id,
            'therapist_id' => $request->therapist_id,
            'booking_date' => $request->booking_date,
            'booking_time' => $request->booking_time,
            'location_type' => $request->location_type,
            'status' => 'pending',
            'address' => $request->address,
            'total_price' => $request->total_price,
        ]);

        return ResponseFormatter::success($booking, 'Booking berhasil dibuat');
    }

    public function store(\App\Http\Requests\StoreBookingRequest $request)
    {
        // 1. Data Retrieval (Service)
        $service = \App\Models\Service::findOrFail($request->service_id);
        $duration = $service->duration_minutes; // minutes
        $price = $service->price;

        // 2. Time Calculation
        $startTime = \Carbon\Carbon::createFromFormat('H:i', $request->start_time);
        $endTime = (clone $startTime)->addMinutes($duration);
        
        $bookingDate = $request->booking_date;
        $therapistId = $request->therapist_id;

        // 3. Race Condition / Integrity Check (Availability)
        // Check for overlapping bookings for the same therapist on the same date
        // Logic: (RequestedStart < ExistingEnd) AND (RequestedEnd > ExistingStart)
        
        // 3.0 Check IF Holiday (Added)
        $isHoliday = \App\Models\Schedule::where('therapist_id', $therapistId)
            ->where('type', 'holiday')
            ->where('specific_date', '<=', $bookingDate)
            ->where('end_date', '>=', $bookingDate)
            ->exists();

        if ($isHoliday) {
            return ResponseFormatter::error(
                null, 
                'Tidak dapat melakukan booking. Terapis sedang libur pada tanggal tersebut.',
                422
            );
        }

        // We need to fetch existing bookings for this therapist & date
        // Note: Existing 'booking_time' is start time. We need to calculate existing end time.
        // Since database only stores 'booking_time', we must join services to get duration for each existing booking.
        
        $conflictingBooking = Booking::where('therapist_id', $therapistId)
            ->where('booking_date', $bookingDate)
            ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi']) // Exclude cancelled/completed? User said "melebihi jam kerja" too.
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'batal')
            ->with('service')
            ->get()
            ->filter(function ($existingBooking) use ($startTime, $endTime) {
                if (!$existingBooking->service) return false;

                $existingStart = \Carbon\Carbon::createFromFormat('H:i:s', $existingBooking->booking_time); // DB usually H:i:s
                $existingDuration = $existingBooking->service->duration_minutes;
                $existingEnd = (clone $existingStart)->addMinutes($existingDuration);

                // Overlap Check
                // Note: strict inequality for exact boundaries? Use simple logic:
                // Start A < End B  AND  End A > Start B
                return $startTime->lessThan($existingEnd) && $endTime->greaterThan($existingStart);
            })
            ->first();

        if ($conflictingBooking) {
             return ResponseFormatter::error(
                null,
                'Jadwal terapis bentrok dengan booking lain pada jam tersebut.',
                409 // Conflict
            );
        }
        
        // TODO: Check 'end_time' melebihi jam kerja (e.g. 21:00)? 
        // Assuming Clinic closes at 21:00
        $closingTime = \Carbon\Carbon::createFromFormat('H:i', '21:00');
        if ($endTime->greaterThan($closingTime)) {
             return ResponseFormatter::error(
                null,
                'Durasi layanan melebihi jam operasional (Tutup 21:00).',
                422
            );
        }

        // 4. Database Transaction
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $service, $price) {
            $booking = Booking::create([
                'patient_id' => $request->patient_id,
                'service_id' => $request->service_id,
                'therapist_id' => $request->therapist_id,
                'booking_date' => $request->booking_date,
                'booking_time' => $request->start_time, // Map start_time to booking_time
                'location_type' => $request->location_type,
                'status' => 'pending', // Default to pending even for Admin
                'address' => $request->address,
                'total_price' => $service->price, // Snapshot price
                // 'notes' => $request->notes // Add notes if column exists, for now omit or add migration
            ]);

            return ResponseFormatter::success($booking, 'Booking berhasil dibuat oleh Admin', 201);
        });
    }

    public function update(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ada', 404);
        }

        $booking->update($request->all());

        return ResponseFormatter::success($booking, 'Booking berhasil diupdate');
    }

    public function availableSlots(Request $request)
    {
        $request->validate([
            'therapist_id' => 'required|exists:users,id',
            'booking_date' => 'required|date',
            'service_id'   => 'required|exists:services,id', // Needed for duration
        ]);

        $therapistId = $request->therapist_id;
        $date = $request->booking_date;
        $serviceId = $request->service_id;

        // 1. Get Service Duration
        $service = \App\Models\Service::findOrFail($serviceId);
        $duration = $service->duration_minutes;

        // 1.5 CHECK FOR HOLIDAYS (Added)
        // Check if the requested date falls within any holiday range for this therapist
        $isHoliday = \App\Models\Schedule::where('therapist_id', $therapistId)
            ->where('type', 'holiday')
            ->where('specific_date', '<=', $date) // Start <= Date
            ->where('end_date', '>=', $date)       // End >= Date
            ->exists();

        if ($isHoliday) {
            return ResponseFormatter::success(
                [], // Return empty slots
                "Terapis sedang libur pada tanggal ini."
            );
        }

        // 2. Define Operational Hours (Dynamic from Schedule)
        $dayOfWeekEn = \Carbon\Carbon::parse($date)->locale('en')->dayName; // e.g. "Monday"
        
        // Map English to Indonesian
        $daysMap = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        $dayOfWeek = $daysMap[$dayOfWeekEn] ?? $dayOfWeekEn;

        error_log("Booking Check: Therapist $therapistId, Date $date ($dayOfWeek)");

        $schedule = \App\Models\Schedule::where('therapist_id', $therapistId)
            ->where('day', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if ($schedule) {
            error_log("Schedule Found: $dayOfWeek, {$schedule->start_time} - {$schedule->end_time}");
            $openTime = \Carbon\Carbon::createFromFormat('H:i:s', $schedule->start_time);
            $closeTime = \Carbon\Carbon::createFromFormat('H:i:s', $schedule->end_time);
        } else {
            error_log("No Schedule Found for $dayOfWeek. Using Default 09:00 - 21:00");
            
            // Debug: Print ALL schedules for this therapist to see what's wrong
            $allSchedules = \App\Models\Schedule::where('therapist_id', $therapistId)->get();
            error_log("DEBUG: Existing Schedules for Therapist $therapistId: " . $allSchedules->toJson());

            // Default Operational Hours if no specific schedule found
            // Or should we return empty? Let's keep default for now or strict?
            // User requested "ambil dari situ", implies strictness.
            // If no schedule, maybe therapist doesn't work that day?
            // Let's assume default 09-21 ONLY if no schedule entry exists at all to avoid breaking existing data
            $openTime = \Carbon\Carbon::createFromFormat('H:i', '09:00');
            $closeTime = \Carbon\Carbon::createFromFormat('H:i', '21:00');
        }

        // 3. Get Existing Bookings
        $existingBookings = Booking::where('therapist_id', $therapistId)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi'])
            ->with('service') // Need service to know their duration
            ->get();

        // 4. Generate Slots
        // Assumption: Slots start every 30 minutes? Or every 60?
        // Let's use 60 minutes for simplicity, or strictly based on Service Duration?
        // Common practice: Intervals of 30 or 60 mins. User asked for "Time Slots".
        // Let's try 30 minute intervals for flexibility.
        $interval = 60; 
        $slots = [];

        $current = $openTime->copy();

        while ($current->copy()->addMinutes($duration)->lessThanOrEqualTo($closeTime)) {
             $startSlot = $current->copy();
             $endSlot = $startSlot->copy()->addMinutes($duration);
             
             $isConflict = false;

             foreach ($existingBookings as $booking) {
                 if (!$booking->service) continue;

                 $bookedStart = \Carbon\Carbon::createFromFormat('H:i:s', $booking->booking_time);
                 $bookedEnd = $bookedStart->copy()->addMinutes($booking->service->duration_minutes);

                 // Check overlap: StartA < EndB && EndA > StartB
                 if ($startSlot->lessThan($bookedEnd) && $endSlot->greaterThan($bookedStart)) {
                     $isConflict = true;
                     break;
                 }
             }

             if (!$isConflict) {
                 $slots[] = $startSlot->format('H:i');
             }

             // Next slot
             $current->addMinutes($interval); 
        }

        return ResponseFormatter::success(
            $slots, 
            "Available slots retrieved ({$openTime->format('H:i')} - {$closeTime->format('H:i')})"
        );
    }

    public function destroy($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ada', 404);
        }

        $booking->delete();

        return ResponseFormatter::success(null, 'Booking berhasil dihapus');
    }

    public function cancel($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ada', 404);
        }

        $booking->update(['status' => 'canceled']);

        return ResponseFormatter::success($booking, 'Booking berhasil dibatalkan');
    }
}
