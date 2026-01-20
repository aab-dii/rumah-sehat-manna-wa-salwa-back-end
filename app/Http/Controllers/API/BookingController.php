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
        $booking = Booking::with(['patient', 'therapist', 'service', 'transaction'])->find($id);

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

        $booking = Booking::with(['service', 'therapist', 'patient', 'transaction']);

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
            'payment_method' => 'required|in:transfer,later',
            'proof_of_transfer' => 'nullable|image|max:2048', // Allow null for later upload
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
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
                'created_by' => Auth::id(),
            ]);

            // Handling Payment / Transaction
            $paymentMethod = $request->payment_method;
            $paymentStatus = ($paymentMethod === 'later') ? 'unpaid' : 'paid'; // Transfer assumed paid/pending verification
            // If transfer, status is technically 'pending' verification, but for transaction table 'paid' or 'pending'?
            // Admin flow uses 'paid' if transfer? Let's check store method.
            // Store method: $paymentStatus = $request->payment_status ?? 'unpaid';
            // Here we infer. If transfer, it's pending verif. Let's use 'pending' or 'paid'.
            // Usually 'unpaid' for 'later'. 'paid' (or pending_verification) for transfer.
            // Let's use 'pending' for transfer to be safe? Or 'paid' and let Admin reject?
            // User said "reset to pending" on reupload. So maybe initial is 'pending'?
            // Transaction status enum: unpaid, paid, failed, pending.
            
            $transactionStatus = 'unpaid';
            if ($paymentMethod === 'transfer') {
                // If proof exists, it's pending verification. If not, it's unpaid (waiting for upload).
                $transactionStatus = ($request->hasFile('proof_of_transfer')) ? 'pending' : 'unpaid';
            }

            $proofPath = null;
            if ($paymentMethod === 'transfer' && $request->hasFile('proof_of_transfer')) {
                $file = $request->file('proof_of_transfer');
                $proofPath = $file->store('proofs', 'public');
            }
            
            \App\Models\Transaction::create([
                'booking_id' => $booking->id,
                'payment_method' => $paymentMethod,
                'status' => $transactionStatus,
                'amount' => $request->total_price,
                'proof_of_transfer' => $proofPath
            ]);

            return ResponseFormatter::success($booking->load('transaction'), 'Booking berhasil dibuat');
        });
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

        // 3.1 Check IF Past Time (Added for Security)
        $serverNow = \Carbon\Carbon::now();
        $requestDate = \Carbon\Carbon::parse($bookingDate);
        
        if ($requestDate->isSameDay($serverNow)) {
             // Create Carbon instance for requested time on today
             $requestedDateTime = \Carbon\Carbon::parse($bookingDate . ' ' . $request->start_time);
             
             // Check if requested time is in past (with 5 min buffer)
             if ($requestedDateTime->lessThan($serverNow->subMinutes(5))) {
                  return ResponseFormatter::error(
                    null, 
                    'Tidak dapat memesan waktu yang sudah lewat.',
                    422
                );
             }
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
                'location_type' => $request->location_type ?? 'clinic',
                'booking_time' => $request->start_time, // Map start_time to booking_time
                'location_type' => $request->location_type ?? 'clinic',
                'status' => $request->status ?? 'pending', // Allow Admin to set status
                'address' => $request->address ?? 'Klinik Rumah Sehat Manna wa Salwa', // Default Address
                'total_price' => $service->price, // Snapshot price
                'total_price' => $service->price, // Snapshot price
                'created_by' => Auth::id(), // Admin or User creating via store endpoint
                // 'notes' => $request->notes // Add notes if column exists, for now omit or add migration
            ]);

            // Handling Payment / Transaction
            $paymentStatus = $request->payment_status ?? 'unpaid';
            $paymentMethod = $request->payment_method;
            $proofPath = null;

            if ($paymentMethod === 'transfer' && $request->hasFile('proof_of_transfer')) {
                // Upload file to 'public/proofs'
                $file = $request->file('proof_of_transfer');
                $proofPath = $file->store('proofs', 'public');
            }
            
            // Create Transaction Record
            \App\Models\Transaction::create([
                'booking_id' => $booking->id,
                'payment_method' => $paymentMethod,
                'status' => $paymentStatus,
                'amount' => $price,
                'proof_of_transfer' => $proofPath
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
            error_log("No Active Schedule Found for $dayOfWeek. Returning Empty.");
            return ResponseFormatter::success(
                [], 
                "Terapis tidak memiliki jadwal operasional pada hari $dayOfWeek."
            );
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

        // 5. Filter Past Slots (Server-Side Security)
        // Ensure we don't return slots that have already passed if the date is Today
        $serverNow = \Carbon\Carbon::now();
        $requestDate = \Carbon\Carbon::parse($date);
        
        if ($requestDate->isSameDay($serverNow)) {
            $slots = array_filter($slots, function($slot) use ($serverNow) {
                // slot format is H:i
                $slotTime = \Carbon\Carbon::createFromFormat('H:i', $slot);
                // We add a buffer (e.g. 15 mins) or strict check? 
                // Let's strict check: if 10:00 passed, 10:00 is invalid.
                // Or maybe allow if it's 10:05 and slot is 10:00? No, that's late.
                // Strict: slotTime > now
                return $slotTime->greaterThan($serverNow->addMinutes(5)); // 5 min buffer for latency
            });
            // Re-index array
            $slots = array_values($slots);
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

    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $booking = Booking::with('transaction')->find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Booking tidak ditemukan', 404);
        }

        if (!$booking->transaction) {
            return ResponseFormatter::error(null, 'Transaksi tidak ditemukan', 404);
        }

        $booking->transaction->update([
            'status' => 'rejected',
            'rejection_note' => $request->reason,
        ]);

        return ResponseFormatter::success($booking->load('transaction'), 'Pembayaran berhasil ditolak. Menunggu upload ulang.');
    }
    public function reuploadProof(Request $request, $id)
    {
        $request->validate([
            'proof_of_transfer' => 'required|image|max:2048',
        ]);

        $booking = Booking::with('transaction')->find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Booking tidak ditemukan', 404);
        }

        if (!$booking->transaction) {
            return ResponseFormatter::error(null, 'Transaksi tidak ditemukan', 404);
        }

        if ($request->hasFile('proof_of_transfer')) {
            $file = $request->file('proof_of_transfer');
            $path = $file->store('proofs', 'public');

            $booking->transaction->update([
                'proof_of_transfer' => $path,
                'status' => 'pending', // Reset to pending verification
                // Optional: Clear rejection note if you want to wipe history
                'rejection_note' => null 
            ]);

            return ResponseFormatter::success($booking->load('transaction'), 'Bukti transfer berhasil diupload ulang');
        }

        return ResponseFormatter::error(null, 'File bukti transfer tidak ditemukan', 400);
    }
}
