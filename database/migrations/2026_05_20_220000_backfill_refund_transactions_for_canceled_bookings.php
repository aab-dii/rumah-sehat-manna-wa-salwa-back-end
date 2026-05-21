<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Booking;
use App\Models\Transaction;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $historicalBookings = Booking::whereIn('id', [78, 103])
            ->where('status', 'canceled')
            ->get();

        foreach ($historicalBookings as $booking) {
            // Check if a paid transfer transaction exists
            $originalTx = Transaction::where('booking_id', $booking->id)
                ->where('status', 'paid')
                ->where('payment_method', 'transfer')
                ->first();

            if ($originalTx) {
                // Ensure no refund transaction already exists to avoid duplicate entries
                $stornoExists = Transaction::where('booking_id', $booking->id)
                    ->where('status', 'refund')
                    ->exists();

                if (!$stornoExists) {
                    Transaction::create([
                        'booking_id' => $booking->id,
                        'patient_id' => $originalTx->patient_id,
                        'amount' => $originalTx->amount,
                        'payment_method' => 'transfer',
                        'status' => 'refund',
                        'payment_proof' => $originalTx->payment_proof,
                        'verified_at' => $originalTx->verified_at,
                        'refunded_at' => $booking->canceled_at ?: now(),
                        'handled_by' => $originalTx->handled_by,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Transaction::whereIn('booking_id', [78, 103])
            ->where('status', 'refund')
            ->delete();
    }
};
