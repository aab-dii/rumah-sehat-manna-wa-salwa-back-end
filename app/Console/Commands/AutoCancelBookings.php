<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoCancelBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:auto-cancel-v2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batalkan booking yang statusnya masih pending setelah 30 menit lewat dari jadwal.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Fetch Candidates (Pending & Confirmed)
        $bookings = \App\Models\Booking::whereIn('status', [
            'pending', 'menunggu', 
            'confirmed', 'konfirmasi', 'terjadwal'
        ])->get();

        $now = \Carbon\Carbon::now();
        $count = 0;

        foreach ($bookings as $booking) {
            try {
                // Fix Date Parsing
                $bookingDateTime = \Carbon\Carbon::parse($booking->booking_date)->setTimeFromTimeString($booking->booking_time);
                $status = strtolower($booking->status);
                $shouldCancel = false;
                $reason = '';

                // Condition 1: Pending Cleanup (Strict Deadline)
                if (in_array($status, ['pending', 'menunggu'])) {
                    $deadline = $bookingDateTime->copy()->addMinutes(1); 
                    if ($now->greaterThan($deadline)) {
                        $shouldCancel = true;
                        $reason = 'Dibatalkan otomatis oleh sistem karena melewati batas waktu booking (Pending).';
                    }
                }
                
                // Condition 2: No-Show Confirmed (Booking Time + 15 Minutes)
                elseif (in_array($status, ['confirmed', 'konfirmasi', 'terjadwal'])) {
                    $deadline = $bookingDateTime->copy()->addMinutes(15);
                    if ($now->greaterThan($deadline)) {
                        $shouldCancel = true;
                        $reason = 'Dibatalkan otomatis oleh sistem karena melewati batas waktu 15 menit (No-Show).';
                    }
                }

                if ($shouldCancel) {
                    $booking->status = 'canceled';
                    $booking->cancellation_reason = $reason;
                    $booking->save(); // Triggers MyEvent

                    $this->info("Cancelled ID {$booking->id} ($status). Reason: $reason");
                    $count++;
                }

            } catch (\Exception $e) {
                $this->error("Error ID {$booking->id}: " . $e->getMessage());
            }
        }

        $this->info("Auto-cancel check finished. Cancelled {$count} bookings.");
    }
}
