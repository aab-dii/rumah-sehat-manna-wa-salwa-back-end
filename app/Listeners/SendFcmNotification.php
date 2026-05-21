<?php

namespace App\Listeners;

use App\Events\BookingStatusUpdated;
use App\Models\User;
use App\Services\FcmService;

class SendFcmNotification
{
    public function handle(BookingStatusUpdated $event): void
    {
        $booking = $event->booking;
        
        // Pastikan relasi krusial ter-load
        if (!$booking->relationLoaded('patient')) $booking->load('patient');
        if (!$booking->relationLoaded('therapist')) $booking->load('therapist');
        if (!$booking->relationLoaded('service')) $booking->load('service');
        if (!$booking->relationLoaded('transaction')) $booking->load('transaction');

        $status      = $booking->status;
        $transaction = $booking->transaction;
        $patient     = $booking->patient;
        $therapist   = $booking->therapist;
        $actor       = auth()->user();

        // Format tanggal & jam yang konsisten untuk semua pesan
        $formattedDate = \Carbon\Carbon::parse($booking->booking_date)
            ->locale('id')
            ->isoFormat('dddd, D MMMM YYYY');
        
        // Perbaikan format jam: jika null default ke '-', jika ada hilangkan detik
        $formattedTime = $booking->booking_time 
            ? \Carbon\Carbon::parse($booking->booking_time)->format('H:i') . ' WITA'
            : '-';

        // 1. STATUS: PENDING — Hanya Admin yang dikabari jika ada bukti bayar baru/booking baru
        if ($status === 'pending') {
            // BUG FIX: Notif admin jika ada booking baru (unpaid) atau upload bukti (pending).
            // Jangan notif admin jika statusnya 'rejected' (karena admin baru saja menolak).
            if ($transaction && $transaction->status !== 'rejected') {
                $isReupload = $transaction->wasChanged('proof_of_transfer');
                $titleAdmin = $isReupload ? "Re-Upload Bukti Bayar 💰" : "Booking Baru Masuk 📋";

                $pesanAdmin = ($transaction->payment_method === 'transfer')
                    ? "Pasien {$patient->name} mengirim bukti transfer. Mohon verifikasi!"
                    : "Pasien {$patient->name} memesan jadwal (Tunai). Mohon konfirmasi!";

                $this->notifyAdmins($titleAdmin, $pesanAdmin, $booking->id, 'admin_verification');
            }
        }

        // 2. STATUS: CONFIRMED — Pasien & Terapis dikabari
        elseif ($status === 'confirmed') {
            // Notif ke Pasien
            FcmService::send(
                $patient->fcm_token,
                "Janji Temu Dikonfirmasi! ✅",
                "Jadwal Anda pada $formattedDate jam $formattedTime telah disetujui. Sampai jumpa!",
                ['booking_id' => $booking->id],
                'booking_status',
                $patient->id,
                'pasien'
            );

            // Notif ke Terapis
            if ($therapist && $therapist->fcm_token) {
                FcmService::send(
                    $therapist->fcm_token,
                    "Jadwal Baru 🌿",
                    "Anda dijadwalkan untuk pasien {$patient->name} pada $formattedDate jam $formattedTime.",
                    ['booking_id' => $booking->id],
                    'booking_status',
                    $therapist->id,
                    'terapis'
                );
            }
        }

        // 3. STATUS: CANCELED
        elseif ($status === 'canceled') {
            if ($actor && $actor->role === 'admin') {
                // Admin yang batalin → kabari Pasien
                FcmService::send(
                    $patient->fcm_token,
                    "Jadwal Dibatalkan Admin ⚠️",
                    "Maaf, pihak klinik membatalkan jadwal Anda pada $formattedDate. Alasan: " . ($booking->cancellation_reason ?? 'Kendala operasional'),
                    ['booking_id' => $booking->id],
                    'booking_canceled',
                    $patient->id,
                    'pasien'
                );
            } else {
                // Pasien yang batalin → kabari Admin
                $this->notifyAdmins("Pasien Batal 📢", "Pasien {$patient->name} membatalkan janji untuk tanggal $formattedDate.", $booking->id, 'admin_update');
            }

            // Terapis selalu dikabari
            if ($therapist && $therapist->fcm_token) {
                FcmService::send(
                    $therapist->fcm_token,
                    "Jadwal Batal",
                    "Jadwal dengan {$patient->name} pada $formattedDate jam $formattedTime telah dibatalkan.",
                    ['booking_id' => $booking->id],
                    'booking_canceled',
                    $therapist->id,
                    'terapis'
                );
            }
        }

        // 4. Pembayaran REJECTED
        if ($transaction && $transaction->status === 'rejected') {
            FcmService::send(
                $patient->fcm_token,
                "Pembayaran Ditolak ❌",
                "Pembayaran kamu ditolak. Silakan upload ulang bukti. Alasan: " . $transaction->rejection_note,
                ['booking_id' => $booking->id, 'type' => 'reupload'],
                'payment',
                $patient->id,
                'pasien'
            );
        }

        // 5. STATUS: COMPLETED
        elseif ($status === 'completed') {
            // Notif ke Pasien
            FcmService::send(
                $patient->fcm_token,
                "Terapi Selesai ✨",
                "Terima kasih sudah berkunjung ke Rumah Sehat pada $formattedDate. Semoga lekas sembuh!",
                ['booking_id' => $booking->id],
                'booking_status',
                $patient->id,
                'pasien'
            );

            // KRITIKAL: Kirim notifikasi ke Admin juga agar mereka tahu terapis sudah selesai
            $this->notifyAdmins(
                "Sesi Selesai ✅",
                "Terapis {$therapist->name} telah menyelesaikan sesi dengan {$patient->name}.",
                $booking->id,
                'admin_update'
            );
        }

        // 6. STATUS: FORCE_COMPLETED
        elseif ($status === 'force_completed') {
            // Notif ke Pasien (tampil sebagai selesai)
            FcmService::send(
                $patient->fcm_token,
                "Terapi Selesai ✨",
                "Janji temu Anda pada $formattedDate telah diselesaikan. Terima kasih!",
                ['booking_id' => $booking->id],
                'booking_status',
                $patient->id,
                'pasien'
            );

            // Notif ke Terapis (perlu isi catatan)
            if ($therapist && $therapist->fcm_token) {
                FcmService::send(
                    $therapist->fcm_token,
                    "Perlu Catatan Terapi ⚠️",
                    "Admin telah menyelesaikan sesi {$patient->name}. Mohon segera lengkapi catatan terapi Anda.",
                    ['booking_id' => $booking->id],
                    'booking_status',
                    $therapist->id,
                    'terapis'
                );
            }
        }
    }

    /**
     * Kirim notifikasi ke semua akun admin.
     */
    private function notifyAdmins(string $title, string $body, int $bookingId, string $type): void
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
        foreach ($admins as $admin) {
            if ($admin->fcm_token) {
                FcmService::send(
                    $admin->fcm_token,
                    $title,
                    $body,
                    ['booking_id' => $bookingId, 'type' => $type],
                    $type,
                    $admin->id,
                    $admin->role
                );
            }
        }
    }
}