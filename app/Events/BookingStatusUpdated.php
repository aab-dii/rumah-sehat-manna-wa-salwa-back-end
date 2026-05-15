<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function broadcastOn()
    {
        // Public channel for simplicity as requested, specific to booking ID
        return new Channel('booking.' . $this->booking->id);
    }

    public function broadcastAs(): string
    {
        return 'booking.status.updated';
    }

    public function broadcastWith(): array
    {
        // Consistent with API response structure generally, or specifically what's needed
        // Wrapping in 'booking' key to match Android parsing logic in PusherService
        return [
            'booking' => [
                'id' => $this->booking->id,
                'status' => $this->booking->status,
                'payment_deadline' => $this->booking->payment_deadline,
                'booking_date' => $this->booking->booking_date, // needed for sorting/lists
                'booking_time' => $this->booking->booking_time,
                'updated_at' => $this->booking->updated_at, // IMPORTANT for Timer
                'transaction' => $this->booking->transaction ? [
                    'id' => $this->booking->transaction->id,
                    'status' => $this->booking->transaction->status,
                    'rejection_note' => $this->booking->transaction->rejection_note,
                    'proof_of_transfer' => $this->booking->transaction->proof_of_transfer,
                    'updated_at' => $this->booking->transaction->updated_at, // IMPORTANT
                ] : null,
                // Include minimal relations to prevent crashes if Android expects them
                'service' => [
                    'id' => $this->booking->service->id,
                    'nama' => $this->booking->service->nama,
                    'is_active' => $this->booking->service->is_active,
                    'harga' => $this->booking->service->harga,
                    'durasi' => $this->booking->service->durasi,
                    'deskripsi' => $this->booking->service->deskripsi,
                    'image_path' => $this->booking->service->image_path,
                ],
                'therapist' => $this->booking->therapist ? [
                    'id' => $this->booking->therapist->id,
                    'name' => $this->booking->therapist->name,
                    'profile_photo_path' => $this->booking->therapist->profile_photo_path,
                    'specialization' => $this->booking->therapist->specialization,
                ] : null,
                'patient' => $this->booking->patient ? [
                    'id' => $this->booking->patient->id,
                    'name' => $this->booking->patient->name,
                ] : null,
            ]
        ];
    }
}
