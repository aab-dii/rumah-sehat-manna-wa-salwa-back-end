<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CancelExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:cancel-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membatalkan booking yang kedaluwarsa (pembayaran timeout, no-show, atau melewati jadwal).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = \Carbon\Carbon::now();
        $today = $now->format('Y-m-d');

        // 1. Batalkan booking TRANSFER yang BELUM DIBAYAR (unpaid) setelah 1 jam dari waktu pembuatan
        $expiredUnpaid = \App\Models\Booking::where('status', 'pending')
            ->whereHas('transaction', function ($q) {
                $q->where('status', 'unpaid')
                  ->where('payment_method', 'transfer');
            })
            ->where('created_at', '<', $now->subHour())
            ->get();

        foreach ($expiredUnpaid as $booking) {
            $booking->update([
                'status' => 'canceled',
                'cancellation_reason' => 'Batas waktu pembayaran habis (1 jam setelah pemesanan).'
            ]);
            event(new \App\Events\BookingStatusUpdated($booking));
            $this->info("Canceled Unpaid Booking (Timeout 1h) ID: {$booking->id}");
        }

        // 2. Batalkan booking yang melewati jadwal (Cleaning up past dates)
        $pastBookings = \App\Models\Booking::where('booking_date', '<', $today)
            ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi', 'terjadwal'])
            ->get();

        foreach ($pastBookings as $booking) {
            $booking->update([
                'status' => 'canceled',
                'cancellation_reason' => 'Otomatis Dibatalkan: Melewati jadwal tanggal pelayanan.'
            ]);
            event(new \App\Events\BookingStatusUpdated($booking));
            $this->info("Canceled Past Booking ID: {$booking->id}");
        }

        // 3. Batalkan booking yang NO-SHOW (15 Menit setelah jadwal mulai tapi status belum berubah)
        // Kita hanya cek booking hari ini yang statusnya masih confirmed/pending
        $noShowBookings = \App\Models\Booking::where('booking_date', $today)
            ->whereIn('status', ['pending', 'confirmed', 'menunggu', 'konfirmasi', 'terjadwal'])
            ->get();

        foreach ($noShowBookings as $booking) {
            try {
                $bookingDateTime = \Carbon\Carbon::parse($booking->booking_date . ' ' . $booking->booking_time);
                if ($now->greaterThan($bookingDateTime->addMinutes(15))) {
                    $booking->update([
                        'status' => 'canceled',
                        'cancellation_reason' => 'Otomatis Dibatalkan: Pasien tidak hadir (No-Show > 15 Menit).'
                    ]);
                    event(new \App\Events\BookingStatusUpdated($booking));
                    $this->info("Canceled No-Show Booking ID: {$booking->id}");
                }
            } catch (\Exception $e) {
                $this->error("Error checking No-Show ID {$booking->id}: " . $e->getMessage());
            }
        }

        // 4. Batalkan booking yang melewati payment_deadline (Keamanan tambahan)
        $pastDeadline = \App\Models\Booking::where('status', 'pending')
            ->where('payment_deadline', '<', $now)
            ->get();

        foreach ($pastDeadline as $booking) {
            $booking->update([
                'status' => 'canceled',
                'cancellation_reason' => 'Melewati batas waktu pembayaran janji temu.'
            ]);
            event(new \App\Events\BookingStatusUpdated($booking));
            $this->info("Canceled Past Deadline Booking ID: {$booking->id}");
        }

        // 5. Batalkan booking yang ditolak (Rejected) dan tidak di-update dalam waktu singkat (demo/safety)
        $rejectedTimeout = \App\Models\Booking::whereHas('transaction', function ($q) use ($now) {
                $q->where('status', 'rejected')
                  ->where('updated_at', '<', $now->subSeconds(60)); // Naikkan jadi 60s biar lebih logis
            })
            ->where('status', 'pending') 
            ->get();

        foreach ($rejectedTimeout as $booking) {
             $booking->update([
                'status' => 'canceled',
                'cancellation_reason' => 'Batas waktu perbaikan bukti pembayaran habis.'
            ]);
            event(new \App\Events\BookingStatusUpdated($booking));
            $this->info("Canceled Rejected Timeout Booking ID: {$booking->id}");
        }
    }
}
