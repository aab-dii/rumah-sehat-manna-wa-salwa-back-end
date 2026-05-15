<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MyEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function broadcastOn()
    {
        return [
            new Channel('my-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'my-event';
    }

    /**
     * Data yang dikirim ke Pusher (Client).
     * Kita filter supaya tidak mengirim semua data user/therapist (seperti NIK, Alamat, dll)
     * dan memastikan format tanggal benar.
     */
    public function broadcastWith(): array
    {
        return [
            'booking' => [
                'id' => $this->booking->id,
                'booking_date' => \Carbon\Carbon::parse($this->booking->booking_date)->format('Y-m-d'), // Pastikan String YYYY-MM-DD
                'booking_time' => $this->booking->booking_time,
                'status' => $this->booking->status,
                'cancellation_reason' => $this->booking->cancellation_reason,
                'total_price' => $this->booking->total_price,
                'patient' => [
                    'name' => $this->booking->patient->name ?? 'Tamu',
                ],
                'therapist' => [
                    'name' => $this->booking->therapist->name ?? 'Belum Ditentukan',
                ],
                'service' => [
                    'nama' => $this->booking->service->name ?? ($this->booking->service->nama ?? 'Layanan'), // Safety check column name
                    'durasi' => $this->booking->service->duration_minutes ?? ($this->booking->service->durasi ?? 60),
                ],
                // Data transaksi jika perlu
                'transaction' => $this->booking->transaction ? [
                    'status' => $this->booking->transaction->status,
                    'payment_method' => $this->booking->transaction->payment_method
                ] : null
            ]
        ];
    }
}