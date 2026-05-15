<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoCancelPastBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:auto-cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancel bookings that have passed their scheduled date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = \Carbon\Carbon::now()->format('Y-m-d');
        
        $this->info("Checking for bookings before $today...");

        // statuses to check
        $activeStatuses = ['pending', 'confirmed', 'menunggu', 'konfirmasi'];

        // Get bookings to cancel
        $bookings = \App\Models\Booking::where('booking_date', '<', $today)
            ->whereIn('status', $activeStatuses)
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'canceled',
                'cancellation_reason' => 'Otomatis Dibatalkan: Melewati jadwal (System)'
            ]);
            
            // Trigger notification
            event(new \App\Events\BookingStatusUpdated($booking));
            $count++;
        }

        $this->info("Success! $count past bookings have been cancelled and notified.");
    }
}
