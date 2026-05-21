<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\TherapyRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TherapyRecordController extends Controller
{
    public function all(Request $request)
    {
        $id = $request->input('id');
        $patientId = $request->input('patient_id');
        $limit = $request->input('limit', 10);
        $user = Auth::user();

        // --- JALUR 1: DETAIL (Data Lengkap) ---
        if ($id) {
            $record = TherapyRecord::with(['therapist', 'booking.service', 'patient'])->find($id);

            if ($record && ($record->patient_id == $user->id || $user->role == 'terapis' || $user->role == 'admin')) {
                // Simplified data transformation
                $data = [
                    'id'                => $record->id,
                    'patient_complaint' => $record->patient_complaint,
                    'therapist_action'  => $record->therapist_action,
                    'additional_notes'  => $record->additional_notes,
                    'examination_date'  => $record->examination_date->format('Y-m-d H:i:s'),
                    'patient' => $record->patient ? [
                        'id'                 => $record->patient->id,
                        'name'               => $record->patient->name,
                        'profile_photo_url'  => $record->patient->profile_photo_url,
                    ] : null,
                    'therapist' => $record->therapist ? [
                        'id'                 => $record->therapist->id,
                        'name'               => $record->therapist->name,
                        'profile_photo_url'  => $record->therapist->profile_photo_url,
                    ] : null,
                    'booking' => $record->booking ? [
                        'id'           => $record->booking->id,
                        'booking_date' => $record->booking->booking_date,
                        'booking_time' => $record->booking->booking_time,
                        'status'       => $record->booking->status,
                        'service'      => $record->booking->service ? [
                            'name'           => $record->booking->service->name,
                            'full_image_url' => $record->booking->service->full_image_url,
                        ] : null,
                    ] : null,
                ];

                return ResponseFormatter::success(
                    $data,
                    'Data detail rekam medis berhasil diambil'
                );
            }
            return ResponseFormatter::error(null, 'Data tidak ditemukan', 404);
        }

        // --- JALUR 2: LIST (Data Ringkas) ---
        $query = TherapyRecord::with(['patient', 'therapist', 'booking.service']);

        // Filter berdasarkan Role & Parameter
        if ($user->role === 'pasien') {
            // Pasien hanya boleh melihat miliknya sendiri
            $query->where('patient_id', $user->id);
        } else {
            // Admin atau Terapis
            if ($patientId) {
                // Jika ada parameter patient_id, filter berdasarkan itu
                $query->where('patient_id', $patientId);
            } elseif ($user->role === 'terapis') {
                // Jika terapis buka list umum, hanya lihat pasien yang pernah dia tangani
                $query->where('therapist_id', $user->id);
            }
            // Admin tanpa patient_id akan melihat semua (untuk dashboard admin)
        }

        // Search: cari berdasarkan nama layanan saja
        $search = $request->input('search');
        if ($search) {
            $query->whereHas('booking.service', fn($s) => $s->where('name', 'like', "%{$search}%"));
        }

        // Filter rentang tanggal
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        if ($dateFrom) {
            $query->whereDate('examination_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('examination_date', '<=', $dateTo);
        }

        $records = $query->orderBy('examination_date', 'desc')->paginate($limit);

        // ✨ DI SINI LETAK PENGOLAHANNYA (TRANSFORM) ✨
        $records->getCollection()->transform(function ($item) {
            return [
                'id'             => $item->id,
                'patient_name'   => $item->patient->name ?? 'Unknown',
                'patient_photo'  => $item->patient->profile_photo_url ?? null,
                'service_name'   => $item->booking->service->name ?? 'Layanan Umum',
                'therapist_name' => $item->therapist->name ?? '-',
                'booking_time'   => $item->booking->booking_time ?? '-',
                'examination_date' => $item->examination_date->format('Y-m-d'),
                'day'            => $item->examination_date->translatedFormat('l'), // Senin, Selasa, dst
                'day_number'     => $item->examination_date->format('d'),
                'month'          => strtoupper($item->examination_date->translatedFormat('M')),
            ];
        });

        return ResponseFormatter::success(
            $records,
            'Data list rekam medis berhasil diambil'
        );
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'patient_id' => 'required|exists:users,id',
            'patient_complaint' => 'required|string',
            'therapist_action' => 'required|string',
            'additional_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(
                ['error' => $validator->errors()],
                'Gagal menyimpan rekam medis',
                422
            );
        }

        $user = Auth::user();
        
        // Ensure only Therapist (or Admin/Super Admin) can create
        if ($user->role !== 'terapis' && !$user->isAdminOrSuperAdmin()) {
             return ResponseFormatter::error(null, 'Hanya Terapis yang dapat membuat rekam medis', 403);
        }

        // Verify that the booking actually belongs to this therapist (security)
        $booking = \App\Models\Booking::find($request->booking_id);
        if ($user->role === 'terapis' && $booking->therapist_id !== $user->id) {
             return ResponseFormatter::error(null, 'Anda tidak berhak mengisi rekam medis untuk booking ini', 403);
        }

        // Verify booking status
        if (!in_array($booking->status, ['confirmed', 'in_progress', 'force_completed'])) {
            return ResponseFormatter::error(null, 'Rekam medis hanya dapat diisi untuk janji temu yang sedang berjalan atau terjadwal.', 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $booking) {
            $record = TherapyRecord::create([
                'booking_id'        => $request->booking_id,
                'patient_id'        => $request->patient_id,
                'therapist_id'      => $user->id,
                'patient_complaint' => $request->patient_complaint,
                'therapist_action'  => $request->therapist_action,
                'additional_notes'  => $request->additional_notes,
                'examination_date'  => now()
            ]);

            // Update status booking menjadi completed secara otomatis dan catat tanggal selesai
            $booking->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Trigger event notifikasi
            try {
                event(new \App\Events\BookingStatusUpdated($booking->fresh()));
            } catch (\Exception $e) {
                \Log::error("FCM Notification Error on Record Save: " . $e->getMessage());
            }

            return ResponseFormatter::success($record, 'Rekam medis berhasil disimpan dan janji temu diselesaikan');
        });
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        // 1. Temukan rekam medis dengan relasi booking
        $record = TherapyRecord::with('booking')->find($id);

        if (!$record) {
            return ResponseFormatter::error(null, 'Rekam medis tidak ditemukan', 404);
        }

        // 2. Security Check: Mencegah manipulasi ID via Postman
        // Hanya Admin/Super Admin atau Terapis yang membuat catatan tersebut yang boleh mengedit
        if (!$user->isAdminOrSuperAdmin() && $record->therapist_id !== $user->id) {
            return ResponseFormatter::error(null, 'Akses ditolak. Anda tidak berhak mengubah catatan ini.', 403);
        }

        // 3. LOCK CHECK (Sprint 1.4 Poin 10)
        // Jika status booking adalah 'completed', maka catatan dikunci
        if ($record->booking && $record->booking->status === 'completed') {
            return ResponseFormatter::error(
                null, 
                'Akses ditolak. Catatan terapi telah dikunci karena sesi telah selesai.', 
                403
            );
        }

        // 4. Validasi Data
        $validated = $request->validate([
            'patient_complaint' => 'sometimes|string',
            'therapist_action' => 'sometimes|string',
            'additional_notes' => 'sometimes|nullable|string',
        ]);

        // 5. Eksekusi Update
        $record->update($validated);

        return ResponseFormatter::success($record, 'Rekam medis berhasil diperbarui');
    }
}
