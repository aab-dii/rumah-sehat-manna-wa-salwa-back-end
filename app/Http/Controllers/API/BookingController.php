<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Events\MyEvent;
use App\Http\Resources\BookingListResource;
use App\Http\Resources\BookingListCollection;
use App\Http\Resources\BookingDetailResource;
use App\Events\BookingStatusUpdated;
use App\Http\Requests\StoreBookingRequest;


class BookingController extends Controller
{
    private $breakDuration = 15; // Jeda istirahat antar pasien (Menit)

    public function show($id) {
        $user = Auth::user();
        $query = Booking::with(['patient', 'therapist', 'service', 'transaction', 'therapyRecord']);

        // Filter berdasarkan role untuk keamanan data
        if ($user->role === 'pasien') {
            $query->where('patient_id', $user->id);
        } else if ($user->role === 'terapis') {
            $query->where('therapist_id', $user->id);
        }
        // Admin dapat melihat semua data

        $booking = $query->find($id);

        if ($booking) {
            // Kalkulasi antrian (dinamis setiap show)
            $queueNumber = null;
            if (in_array($booking->status, ['confirmed', 'in_progress', 'force_completed'])) {
                try {
                    $dateString = substr((string)$booking->booking_date, 0, 10);
                    
                    $queue = Booking::where('therapist_id', $booking->therapist_id)
                        ->whereDate('booking_date', $dateString)
                        ->whereIn('status', ['confirmed', 'in_progress', 'force_completed'])
                        ->orderBy('booking_time', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->pluck('id')
                        ->toArray();
                    
                    $index = array_search($booking->id, $queue);
                    if ($index !== false) {
                        $booking->queue_number = $index + 1;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Gagal menghitung antrian: ' . $e->getMessage());
                }
            }

            // ✅ Gunakan resolve() agar data "rata" (Single Wrapper)
            $data = (new BookingDetailResource($booking))->resolve();
            return ResponseFormatter::success($data, 'Detail Booking berhasil diambil');
        } 
        return ResponseFormatter::error(null, 'Booking tidak ditemukan atau Anda tidak memiliki akses', 404);
    }

    public function all(Request $request)
    {
        $id = $request->input('id');
        $limit = $request->input('limit', 10);
        $status = $request->input('status');

        if ($id) {
            $user = Auth::user();
            $query = Booking::with(['service', 'therapist', 'transaction']);

            // Filter berdasarkan role
            if ($user->role === 'pasien') {
                $query->where('patient_id', $user->id);
            } else if ($user->role === 'terapis') {
                $query->where('therapist_id', $user->id);
            }

            $booking = $query->find($id);

            if ($booking) {
                return ResponseFormatter::success(
                    $booking,
                    'Data booking berhasil diambil'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data booking tidak ada atau Anda tidak memiliki akses',
                    404
                );
            }
        }

        $booking = Booking::with(['service', 'therapist', 'patient', 'transaction']);

        // Jika bukan admin, hanya ambil booking milik sendiri
        // Limit view based on Role
        $user = Auth::user();
        if ($user->role === 'terapis') {
            $booking->where('therapist_id', $user->id);
        } else if ($user->role === 'pasien') {
            $booking->where('patient_id', $user->id);
        }
        // Admin sees all (no filter)

        if ($status) {
            // Support comma separated status: "pending,confirmed,waiting_payment,..."
            $statuses = explode(',', $status);

            $booking->where(function($q) use ($statuses) {
                // 1. Standard Statuses (Database Column) - Excluding virtual ones
                $dbStatuses = array_diff($statuses, ['pending', 'waiting_payment', 'waiting_verification', 'payment_rejected']);
                if (!empty($dbStatuses)) {
                    $q->orWhereIn('status', $dbStatuses);
                }

                // 2. Special Status: Menunggu Bayar (Transfer + Pending + Unpaid)
                if (in_array('waiting_payment', $statuses)) {
                    $q->orWhere(function($sub) {
                        $sub->where('status', 'pending')
                            ->whereHas('transaction', function($t) {
                                $t->where('payment_method', 'transfer')
                                  ->where('status', 'unpaid');
                            });
                    });
                }

                // 3. Special Status: Menunggu Konfirmasi / Pending Umum (Cash/Later + Pending)
                if (in_array('pending', $statuses)) {
                    $q->orWhere(function($sub) {
                        $sub->where('status', 'pending')
                            ->whereHas('transaction', function($t) {
                                $t->whereIn('payment_method', ['cash', 'later']);
                            });
                    });
                }

                // 4. Special Status: Menunggu Verifikasi (Pending + Pending Transaction)
                if (in_array('waiting_verification', $statuses)) {
                    $q->orWhere(function($sub) {
                        $sub->where('status', 'pending')
                            ->whereHas('transaction', function($t) {
                                $t->where('status', 'pending');
                            });
                    });
                }

                // 5. Special Status: Pembayaran Ditolak (Pending + Rejected Transaction)
                if (in_array('payment_rejected', $statuses)) {
                    $q->orWhere(function($sub) {
                        $sub->where('status', 'pending')
                            ->whereHas('transaction', function($t) {
                                $t->where('status', 'rejected');
                            });
                    });
                }
            });
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
                 })->orWhereHas('service', function($sub) use ($search) {
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

        $bookings = $booking->paginate($limit);
        return ResponseFormatter::success(
            new BookingListCollection($bookings),
            'Data list booking berhasil diambil'
        );
    }

    public function store(StoreBookingRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. IDENTIFIKASI USER & PASIEN
            $user = Auth::user();
            // Jika admin, ambil patient_id dari request. Jika pasien, pakai ID sendiri.
            $patientId = ($user->role === 'admin') ? $request->patient_id : $user->id;

            // 2. AMBIL DATA LAYANAN & WAKTU
            $service = Service::findOrFail($request->service_id);
            $duration = $service->duration_minutes;
            $price = $service->price;
            $timeInput = $request->booking_time; // Konsisten pakai booking_time
            $startTime = Carbon::createFromFormat('H:i', $timeInput);
            $endTime = (clone $startTime)->addMinutes($duration);
            $endTimeWithBreak = (clone $startTime)->addMinutes($duration + $this->breakDuration); // Untuk cek jam tutup
            $bookingDate = $request->booking_date;
            $therapistId = $request->therapist_id;

            // --- 3. PENGAMAN (SECURITY GUARDS) ---

            // 3.1 Cek Libur
            $isHoliday = Schedule::where('therapist_id', $therapistId)
                ->where('type', 'holiday')
                ->where('specific_date', '<=', $bookingDate)
                ->where('end_date', '>=', $bookingDate)
                ->exists();

            if ($isHoliday) {
                return ResponseFormatter::error(null, 'Terapis sedang libur pada tanggal tersebut.', 422);
            }

            // 3.2 Cek Waktu Lampau (Jika booking untuk hari ini)
            $serverNow = Carbon::now();
            $requestDate = Carbon::parse($bookingDate);
            if ($requestDate->isSameDay($serverNow)) {
                $requestedDateTime = Carbon::parse($bookingDate . ' ' . $timeInput);
                if ($requestedDateTime->lessThan($serverNow->subMinutes(5))) {
                    return ResponseFormatter::error(null, 'Tidak dapat memesan waktu yang sudah lewat.', 422);
                }
            }

            // 3.3 Cek Bentrok (Conflict Check) + Jeda 30 Menit + LOCKING
            // KUNCI KEAMANAN: lockForUpdate() memaksa transaksi lain menunggu
            $conflictingBooking = Booking::where('therapist_id', $therapistId)
                ->where('booking_date', $bookingDate)
                ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi', 'in_progress'])
                ->lockForUpdate() 
                ->with('service')
                ->get()
                ->filter(function ($existingBooking) use ($startTime, $endTime) {
                    if (!$existingBooking->service) return false;
                    $existingStart = Carbon::parse($existingBooking->booking_time);
                    $existingEndWithBreak = (clone $existingStart)->addMinutes($existingBooking->service->duration_minutes + $this->breakDuration);
                    return $startTime->lessThan($existingEndWithBreak) && $endTime->greaterThan($existingStart);
                })
                ->first();

            if ($conflictingBooking) {
                return ResponseFormatter::error(null, 'Maaf, slot waktu ini baru saja dipesan oleh pasien lain. Silakan pilih waktu yang berbeda.', 409);
            }

            // 3.4 Cek Bentrok Pasien (Mencegah pasien double booking di jam yang sama)
            $patientConflict = Booking::where('patient_id', $patientId)
                ->where('booking_date', $bookingDate)
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->lockForUpdate()
                ->with('service')
                ->get()
                ->filter(function ($existingBooking) use ($startTime, $endTime) {
                    if (!$existingBooking->service) return false;
                    $existingStart = Carbon::parse($existingBooking->booking_time);
                    $existingEnd = (clone $existingStart)->addMinutes($existingBooking->service->duration_minutes);
                    return $startTime->lessThan($existingEnd) && $endTime->greaterThan($existingStart);
                })
                ->first();

            if ($patientConflict) {
                return ResponseFormatter::error(null, 'Anda sudah memiliki janji temu aktif pada waktu tersebut. Silakan cek kembali jadwal Anda.', 422);
            }

            // 3.5 Cek Jam Operasional (Tutup 21:00)
            $closingTime = Carbon::createFromFormat('H:i', '21:00');
            if ($endTimeWithBreak->greaterThan($closingTime)) {
                return ResponseFormatter::error(null, 'Layanan & jeda melebihi jam operasional (Tutup 21:00).', 422);
            }

            // --- 4. MATRIKS STATUS 
            $paymentDeadline = null;
            $bookingStatus = 'pending';
            $paymentStatus = 'unpaid';

            if ($user->role === 'admin') {
                $bookingStatus = 'confirmed';
                $paymentStatus = 'paid';
            } else {
                if ($request->payment_method === 'transfer'){
                    $meetingTime = Carbon::parse($request->booking_date . ' ' . $timeInput);
                    $paymentDeadline = $meetingTime->subHour();
                    $paymentStatus = $request->hasFile('proof_of_transfer') ? 'pending' : 'unpaid';
                } elseif ($request->payment_method === 'cash') {
                    $paymentStatus = 'unpaid';
                }
            }

            // --- 5. EKSEKUSI DATABASE ---
            $adminFee = config('clinic.admin_fee', 1000);
            $totalPrice = $price + $adminFee;

            $booking = Booking::create([
                'patient_id' => $patientId,
                'service_id' => $request->service_id,
                'therapist_id' => $request->therapist_id,
                'booking_date' => $request->booking_date,
                'booking_time' => $timeInput,
                'location_type' => $request->location_type ?? 'clinic',
                'status' => $bookingStatus,
                'address' => $request->address ?? 'Klinik Rumah Sehat Manna wa Salwa',
                'total_price' => $totalPrice,
                'created_by' => $user->id,
                'payment_deadline' => $paymentDeadline,
            ]);

            $proofPath = null;
            if ($request->hasFile('proof_of_transfer')) {
                $proofPath = $request->file('proof_of_transfer')->store('proofs', 'public');
            }

            $booking->transaction()->create([
                'amount' => $totalPrice,
                'status' => $paymentStatus,
                'payment_method' => $request->payment_method ?? 'later',
                'proof_of_transfer' => $proofPath
            ]);

            // Load relations for response
            $booking->load(['patient', 'therapist', 'service', 'transaction']);

            // Notification
            try {
                Notification::create([
                    'user_id' => $patientId,
                    'title' => 'Janji Temu Berhasil',
                    'message' => 'Janji temu Anda pada ' . $bookingDate . ' pukul ' . $timeInput . ' telah berhasil dibuat.',
                    'type' => 'booking_success',
                    'for_role' => 'pasien'
                ]);
            } catch (\Exception $e) {
                \Log::error("Notification Failed: " . $e->getMessage());
            }

            // Kirim notifikasi FCM ke semua admin & super admin
            // saat ada booking baru masuk dari pasien.
            // Kegagalan pengiriman tidak mempengaruhi
            // proses pembuatan booking.
            try {
                \App\Services\FcmService::sendToAdmins(
                    '🔔 Janji Temu Baru',
                    "Pasien {$booking->patient->name} membuat janji temu untuk layanan {$booking->service->name} pada {$bookingDate} pukul {$timeInput}.",
                    [
                        'type'       => 'new_booking',
                        'booking_id' => (string) $booking->id,
                        'screen'     => 'AdminAppointmentDetail',
                    ]
                );
            } catch (\Exception $e) {
                \Log::error('FCM ke admin gagal: ' . $e->getMessage());
            }

            return ResponseFormatter::success(
                $booking,
                'Booking created successfully'
            );
        });
    }


    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $query = Booking::query();

        // Security check: Pasien tidak boleh update sembarangan
        if ($user->role === 'pasien') {
            $query->where('patient_id', $user->id);
        } else if ($user->role === 'terapis') {
            $query->where('therapist_id', $user->id);
        }

        $booking = $query->find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ditemukan atau Anda tidak memiliki akses', 404);
        }

        // ── Authorization Check ───────────────────────────────────────────
        $user = Auth::user();
        if ($user->role === 'pasien') {
            return ResponseFormatter::error(null, 'Akses ditolak.', 403);
        }

        if ($user->role === 'terapis') {
            if ($booking->therapist_id !== $user->id) {
                 return ResponseFormatter::error(null, 'Akses ditolak. Anda bukan terapis untuk booking ini.', 403);
            }
            
            // Terapis hanya diizinkan mengubah status
            $validated = $request->validate([
                'status' => 'required|in:in_progress,completed'
            ]);
            
            $booking->update(['status' => $validated['status']]);
            
            try {
                event(new MyEvent($booking));
                event(new \App\Events\BookingStatusUpdated($booking));
            } catch (\Exception $e) {
                \Log::error("Pusher Event Failed: " . $e->getMessage());
            }

            return ResponseFormatter::success($booking, 'Status booking berhasil diupdate');
        }

        // Hanya admin/super admin yang bisa mengubah data booking secara penuh.
        if (!$user->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Akses ditolak.', 403);
        }

        // ── Validasi dan Whitelist Field ──────────────────────────────────
        // Hanya field ini yang boleh diubah. Field sensitif seperti patient_id
        // atau total_price hanya berubah jika ada di request DAN lolos validasi.
        $validated = $request->validate([
            'service_id'          => 'sometimes|integer|exists:services,id',
            'therapist_id'        => 'sometimes|integer|exists:users,id',
            'booking_date'        => 'sometimes|date',
            'booking_time'        => 'sometimes|date_format:H:i',
            'total_price'         => 'sometimes|integer|min:0',
            'status'              => 'sometimes|in:pending,confirmed,in_progress,completed,canceled,force_completed',
            'cancellation_reason' => 'sometimes|nullable|string|max:500',
        ]);

        $booking->update($validated);

        try {
            event(new MyEvent($booking));
            event(new \App\Events\BookingStatusUpdated($booking));
        } catch (\Exception $e) {
            \Log::error("Pusher Event Failed: " . $e->getMessage());
        }

        return ResponseFormatter::success($booking, 'Booking berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Hanya admin yang dapat menghapus data booking', 403);
        }

        $booking = Booking::find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ditemukan', 404);
        }

        $booking->delete();

        return ResponseFormatter::success(null, 'Booking berhasil dihapus');
    }

    public function cancel(Request $request, $id)
    {
        $user = Auth::user();
        $query = Booking::query();

        // Security check: Pastikan user hanya bisa membatalkan datanya sendiri (jika bukan admin)
        if ($user->role === 'pasien') {
            $query->where('patient_id', $user->id);
        } else if ($user->role === 'terapis') {
            $query->where('therapist_id', $user->id);
        }

        $booking = $query->find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ditemukan atau Anda tidak memiliki akses', 404);
        }

        $user = Auth::user();

        // ── Authorization Check ──────────────────────────────────────────
        // Hanya pemilik booking (pasien) atau admin yang boleh membatalkan.
        // Terapis tidak bisa membatalkan booking — harus lewat admin.
        $isOwner = $booking->patient_id === Auth::id();
        $isAdmin = $user->isAdminOrSuperAdmin();

        if (!$isAdmin) {
            return ResponseFormatter::error(null, 'Pembatalan janji temu harus melalui Admin. Silakan hubungi Admin melalui WhatsApp.', 403);
        }

        // ── Status Check ─────────────────────────────────────────────────
        // Booking tidak bisa dibatalkan jika sudah in_progress atau completed/canceled
        if (in_array($booking->status, ['in_progress', 'completed', 'canceled'])) {
            return ResponseFormatter::error(null, 'Booking tidak dapat dibatalkan karena status sudah ' . $booking->status, 422);
        }

        $booking->update([
            'status' => 'canceled',
            'cancellation_reason' => $request->cancellation_reason,
            'canceled_at' => now(),
        ]);

        $hasPaidTransfer = \App\Models\Transaction::where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->where('payment_method', 'transfer')
            ->exists();

        if ($hasPaidTransfer) {
            $alreadyRefunded = \App\Models\Transaction::where('booking_id', $booking->id)
                ->where('status', 'refund')
                ->exists();

            if (!$alreadyRefunded) {
                \App\Models\Transaction::create([
                    'booking_id' => $booking->id,
                    'payment_method' => 'transfer',
                    'status' => 'refund',
                    'amount' => $booking->total_price,
                    'refunded_at' => now(),
                ]);
            }
        }

        try {
            event(new MyEvent($booking));
            event(new \App\Events\BookingStatusUpdated($booking));
        } catch (\Exception $e) {
            \Log::error("Pusher Event Failed: " . $e->getMessage());
        }

        return ResponseFormatter::success($booking, 'Booking berhasil dibatalkan');
    }

    public function reuploadProof(Request $request, $id)
    {
        // 1. Validasi File
        $request->validate([
            'proof_of_transfer' => 'required|image|max:2048',
        ]);

        // 2. Cari Booking (Paket Lengkap dengan findOrFail)
        $booking = \App\Models\Booking::with('transaction')->findOrFail($id);

        // 3. Security Check (Cuma pemilik atau admin)
        if (Auth::id() !== $booking->patient_id && !Auth::user()->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Akses ditolak', 403);
        }

        if ($request->hasFile('proof_of_transfer')) {
            // 4. Simpan File
            $file = $request->file('proof_of_transfer');
            $path = $file->store('proofs', 'public');

            // 5. Update atau Buat Transaksi
            // Pertahankan amount yang sudah ada; fallback hitung ulang jika belum ada
            $existingAmount = $booking->transaction->amount
                ?? ($booking->total_price + config('clinic.admin_fee', 1000));

            $booking->transaction()->updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'payment_method'    => 'transfer',
                    'proof_of_transfer' => $path,
                    'status'            => 'pending',
                    'amount'            => $existingAmount,
                    'rejection_note'    => null
                ]
            );

            // 6. Trigger Event (Balik ke kodingan asli lo)
            try {
                $freshBooking = $booking->fresh(['transaction', 'patient', 'therapist', 'service']);
                event(new MyEvent($freshBooking));
                event(new \App\Events\BookingStatusUpdated($freshBooking));
            } catch (\Exception $e) {
                \Log::error("Pusher Event Failed: " . $e->getMessage());
            }

            return ResponseFormatter::success($booking->load('transaction'), 'Bukti transfer berhasil diupload');
        }

        return ResponseFormatter::error(null, 'File tidak ditemukan', 400);
    }

    public function rejectPayment(Request $request, $id)
    {
        // Security: Hanya admin/super_admin yang boleh menolak pembayaran
        if (!Auth::user()->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Akses ditolak. Hanya admin yang dapat memproses pembayaran.', 403);
        }

        $request->validate([
            'rejection_note' => 'required|string'
        ]);

        $booking = Booking::with('transaction')->find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ada', 404);
        }

        $transaction = $booking->transaction;

        if (!$transaction) {
            return ResponseFormatter::error(null, 'Transaksi tidak ditemukan', 404);
        }

        // Sprint 2.1: Proteksi double action — cek apakah sudah diproses admin lain
        if ($transaction->status === 'rejected') {
            return ResponseFormatter::error(null, 'Pembayaran ini sudah ditolak sebelumnya.', 409);
        }
        if ($transaction->status === 'paid') {
            return ResponseFormatter::error(null, 'Pembayaran ini sudah diterima oleh admin lain.', 409);
        }

        $transaction->update([
            'status' => 'rejected', 
            'rejection_note' => $request->rejection_note
        ]);

        // Sprint 2.1: Simpan audit trail
        $booking->update(['handled_by' => Auth::id()]);
            
        // Broadcast Event
        try {
            event(new \App\Events\BookingStatusUpdated($booking->fresh()));
            event(new MyEvent($booking));
        } catch (\Exception $e) {
            \Log::error("Pusher Event Failed: " . $e->getMessage());
        }

        return ResponseFormatter::success($booking->load('transaction'), 'Pembayaran berhasil ditolak');
    }

    public function acceptPayment(Request $request, $id)
    {
        // Security: Hanya admin/super_admin yang boleh menerima pembayaran
        if (!Auth::user()->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Akses ditolak. Hanya admin yang dapat memproses pembayaran.', 403);
        }

        $booking = Booking::with('transaction')->find($id);

        if (!$booking) {
            return ResponseFormatter::error(null, 'Data booking tidak ada', 404);
        }

        $transaction = $booking->transaction;

        if (!$transaction) {
            return ResponseFormatter::error(null, 'Transaksi tidak ditemukan', 404);
        }

        // Sprint 2.1: Proteksi double action
        if ($transaction->status === 'paid') {
            return ResponseFormatter::error(null, 'Pembayaran ini sudah diterima sebelumnya.', 409);
        }
        if ($booking->status === 'confirmed') {
            return ResponseFormatter::error(null, 'Booking ini sudah dikonfirmasi oleh admin lain.', 409);
        }

        // 1. Update status transaksi menjadi 'paid'
        $transaction->update([
            'status'         => 'paid',
            'rejection_note' => null,
            'verified_at'    => now(),
        ]);

        // 2. Update status booking menjadi 'confirmed' + audit trail
        $booking->update([
            'status'     => 'confirmed',
            'handled_by' => Auth::id(), // Sprint 2.1: audit trail
        ]);

        // 3. Broadcast event agar pasien mendapat notifikasi real-time
        try {
            event(new MyEvent($booking->fresh()));
            event(new \App\Events\BookingStatusUpdated($booking->fresh()));
        } catch (\Exception $e) {
            \Log::error("Pusher Event Failed: " . $e->getMessage());
        }

        return ResponseFormatter::success(
            $booking->load('transaction'),
            'Pembayaran berhasil diterima, booking dikonfirmasi'
        );
    }

    /**
     * Check availability for a range of dates.
     * Used for Calendar UI to gray out full dates.
     */

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

        // 1.5 CHECK FOR HOLIDAYS & EMERGENCY CLOSURES (Added)
        $isClosed = \App\Models\Schedule::where('therapist_id', $therapistId)
            ->where(function($q) use ($date) {
                $q->where(function($sub) use ($date) {
                    $sub->where('type', 'holiday')
                        ->where('specific_date', '<=', $date)
                        ->where('end_date', '>=', $date);
                })
                ->orWhere(function($sub) use ($date) {
                    $sub->where('type', 'emergency')
                        ->where('specific_date', $date);
                });
            })
            ->where('is_active', false)
            ->exists();

        if ($isClosed) {
            return ResponseFormatter::success(
                [], 
                "Layanan tidak tersedia pada tanggal ini (Tutup/Libur)."
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

        $schedule = \App\Models\Schedule::where('therapist_id', $therapistId)
            ->where('day', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if ($schedule) {
            $openTime = \Carbon\Carbon::createFromFormat('H:i:s', $schedule->start_time);
            $closeTime = \Carbon\Carbon::createFromFormat('H:i:s', $schedule->end_time);
        } else {
            return ResponseFormatter::success(
                [], 
                "Terapis tidak memiliki jadwal operasional pada hari $dayOfWeek."
            );
        }

        // 3. Get Existing Bookings (termasuk in_progress agar slot aktif tidak tampil sebagai kosong)
        $existingBookings = Booking::where('therapist_id', $therapistId)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress', 'menunggu', 'konfirmasi'])
            ->with('service')
            ->get();

        $breakTime = $this->breakDuration;
        $interval = $this->breakDuration; 
        $slots = [];

        $current = $openTime->copy();

        while ($current->copy()->addMinutes($duration + $breakTime)->lessThanOrEqualTo($closeTime)) {
             $startSlot = $current->copy();
             $endSlotWithBreak = $startSlot->copy()->addMinutes($duration + $breakTime);
             
             $isConflict = false;

             foreach ($existingBookings as $booking) {
                 if (!$booking->service) continue;

                 $bookedStart = \Carbon\Carbon::createFromFormat('H:i:s', $booking->booking_time);

                 $bookedDuration = $booking->service->duration_minutes;
                 $bookedEndWithBreak = $bookedStart->copy()->addMinutes($bookedDuration + $breakTime);

                 // Check overlap: StartA < EndB && EndA > StartB
                 if ($startSlot->lessThan($bookedEndWithBreak) && $endSlotWithBreak->greaterThan($bookedStart)) {
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
                // BUG FIX: Gunakan ->copy() agar $serverNow tidak bertambah di setiap iterasi filter
                return $slotTime->greaterThan($serverNow->copy()->addMinutes(5)); // buffer 5 menit
            });
            // Re-index array
            $slots = array_values($slots);
        }

        return ResponseFormatter::success(
            ['slots' => $slots], 
            "Available slots retrieved ({$openTime->format('H:i')} - {$closeTime->format('H:i')})"
        );
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'therapist_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'service_id' => 'required|exists:services,id',
        ]);

        $therapistId = $request->therapist_id;
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $serviceId = $request->service_id;

        // Get Service Duration
        $service = \App\Models\Service::findOrFail($serviceId);
        $duration = $service->duration_minutes;
        $breakTime = $this->breakDuration; // Gunakan variabel global
        $interval = $this->breakDuration; // Sesuaikan interval dengan jeda

        $results = [];
        $currentDate = $startDate->copy();

        // 1. Pre-fetch Schedules for all days in loop
        // To optimize, we just fetch all active schedules for this therapist
        $schedules = \App\Models\Schedule::where('therapist_id', $therapistId)->where('is_active', true)->get()->keyBy('day');
        
        // 2. Pre-fetch Closures (Holidays & Emergency) in range
        $closures = \App\Models\Schedule::where('therapist_id', $therapistId)
            ->whereIn('type', ['holiday', 'emergency'])
            ->where('is_active', false)
            ->where(function($q) use ($startDate, $endDate) {
                // For holidays (range)
                $q->where(function($sub) use ($startDate, $endDate) {
                    $sub->where('type', 'holiday')
                        ->where(function($h) use ($startDate, $endDate) {
                            $h->whereBetween('specific_date', [$startDate, $endDate])
                              ->orWhereBetween('end_date', [$startDate, $endDate])
                              ->orWhere(function($inner) use ($startDate, $endDate) {
                                  $inner->where('specific_date', '<=', $startDate)
                                        ->where('end_date', '>=', $endDate);
                              });
                        });
                })
                // For emergency (specific date)
                ->orWhere(function($sub) use ($startDate, $endDate) {
                    $sub->where('type', 'emergency')
                        ->whereBetween('specific_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                });
            })
            ->get();

        // 3. Pre-fetch all bookings in range
        // Fix: Ensure we cover full days by using start/end of day
        $bookingsInRange = Booking::where('therapist_id', $therapistId)
            ->whereBetween('booking_date', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi', 'in_progress'])
            ->with('service')
            ->get()
            ->groupBy(function($item) {
                // Fix: Ensure key is strictly Y-m-d, ignoring time part if exists
                return \Carbon\Carbon::parse($item->booking_date)->format('Y-m-d');
            });

        $serverNow = Carbon::now();
        $daysMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
        ];

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeekEn = $currentDate->locale('en')->dayName;
            $dayOfWeek = $daysMap[$dayOfWeekEn] ?? $dayOfWeekEn;

            // A. Check Closure (Holiday or Emergency)
            $isClosed = $closures->filter(function($c) use ($currentDate) {
                if ($c->type === 'holiday') {
                    $start = Carbon::parse($c->specific_date);
                    $end = Carbon::parse($c->end_date);
                    return $currentDate->between($start, $end);
                } else {
                    // Emergency is specific date
                    return $currentDate->isSameDay(Carbon::parse($c->specific_date));
                }
            })->isNotEmpty();

            if ($isClosed) {
                $results[$dateStr] = 'unavailable'; // Closed/Holiday
                $currentDate->addDay();
                continue;
            }

            // B. Check Schedule (Working Day)
            if (!isset($schedules[$dayOfWeek])) {
                $results[$dateStr] = 'unavailable'; // Off day
                $currentDate->addDay();
                continue;
            }

            $schedule = $schedules[$dayOfWeek];
            $openTime = Carbon::createFromFormat('H:i:s', $schedule->start_time);
            $closeTime = Carbon::createFromFormat('H:i:s', $schedule->end_time);

            // C. Check Existing Bookings & Simulate Slots
            $dayBookings = $bookingsInRange->get($dateStr, collect([]));
            
            $currentSlot = $openTime->copy();
            $hasAvailableSlot = false;

            // Loop slots until we find ONE available
            while ($currentSlot->copy()->addMinutes($duration + $breakTime)->lessThanOrEqualTo($closeTime)) {
                $startSlot = $currentSlot->copy();
                $endSlotWithBreak = $startSlot->copy()->addMinutes($duration + $breakTime);
                
                // BUG FIX: Gunakan copy() atau instance now yang stabil di luar loop
                if ($currentDate->isSameDay($serverNow) && $startSlot->lessThan($serverNow->copy()->addMinutes(30))) {
                     $currentSlot->addMinutes($interval);
                     continue;
                }

                $isConflict = false;
                foreach ($dayBookings as $booking) {
                    if (!$booking->service) continue;
                    $bookedStart = Carbon::createFromFormat('H:i:s', $booking->booking_time);
                    
                    // Force date to match current loop date for comparison
                    $bookedStart->setDate($currentDate->year, $currentDate->month, $currentDate->day);
                    
                    $bookedDuration = $booking->service->duration_minutes;
                    $bookedEndWithBreak = $bookedStart->copy()->addMinutes($bookedDuration + $breakTime);

                     // Set startSlot date to current loop date too (just to be safe)
                     $startSlot->setDate($currentDate->year, $currentDate->month, $currentDate->day);
                     $endSlotWithBreak->setDate($currentDate->year, $currentDate->month, $currentDate->day);

                     if ($startSlot->lessThan($bookedEndWithBreak) && $endSlotWithBreak->greaterThan($bookedStart)) {
                        $isConflict = true;
                        break;
                    }
                }

                if (!$isConflict) {
                    $hasAvailableSlot = true;
                    break; // STOP EARLY! Found a slot.
                }

                $currentSlot->addMinutes($interval);
            }

            $results[$dateStr] = $hasAvailableSlot ? 'available' : 'full';
            $currentDate->addDay();
        }

        return ResponseFormatter::success($results, 'Availability check complete');
    }
}
