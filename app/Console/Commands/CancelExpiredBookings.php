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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = \Carbon\Carbon::now();

        // 1. Cancel Unpaid Transfer Bookings past deadline
        $expiredUnpaid = \App\Models\Booking::whereHas('transaction', function ($q) {
                $q->where('status', 'unpaid')
                  ->where('payment_method', 'transfer');
            })
            ->where('payment_deadline', '<', $now)
            ->whereNotIn('status', ['cancelled', 'completed', 'confirmed']) // Safety check
            ->get();

        foreach ($expiredUnpaid as $booking) {
            $booking->update([
                'status' => 'canceled',
                'cancellation_reason' => 'Batas waktu pembayaran telah habis (Auto-System).'
            ]);
            event(new \App\Events\BookingStatusUpdated($booking));
            $this->info("Canceled Unpaid Booking ID: {$booking->id}");
        }

        // 2. Cancel Rejected Bookings that timeout (30 seconds for demo/request)
        // Check transactions updated_at < now - 30s
        $rejectedTimeout = \App\Models\Booking::whereHas('transaction', function ($q) use ($now) {
                $q->where('status', 'rejected')
                  ->where('updated_at', '<', $now->subSeconds(30));
            })
            ->whereNotIn('status', ['canceled', 'completed']) 
            ->get();

        foreach ($rejectedTimeout as $booking) {
             $booking->update([
                'status' => 'canceled',
                'cancellation_reason' => 'Batas waktu upload ulang bukti pembayaran habis (30 detik).'
            ]);
            event(new \App\Events\BookingStatusUpdated($booking));
            $this->info("Canceled Rejected Booking ID: {$booking->id}");
        }
    }
}
