<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class BookingDetailResource extends JsonResource
{
    public function toArray($request)
    {
        $duration = $this->service->duration_minutes ?? 60; 
        $startTime = \Carbon\Carbon::parse($this->booking_time);
        $endTime = $startTime->copy()->addMinutes($duration);
        return [
            'appointment' => [
                'id' => $this->id,
                'booking_date' => Carbon::parse($this->booking_date)->translatedFormat('l, d F Y'), 
                'booking_time' => $startTime->format('H:i') . ' - ' . $endTime->format('H:i'),
                'status' => $this->status_baru,
                'total_price' => (int) $this->total_price,
                'admin_fee' => (int) config('clinic.admin_fee', 1000),
                'therapy_record_id' => $this->therapyRecord->id ?? null,
                'cancellation_reason' => $this->cancellation_reason,
                'payment_deadline' =>$this->payment_deadline 
                    ? Carbon::parse($this->payment_deadline)->toIso8601String() 
                    : null,
                'payment_remaining_seconds' => $this->payment_deadline 
                    ? (int) max(0, Carbon::now()->diffInSeconds(Carbon::parse($this->payment_deadline), false)) 
                    : 0,
                'is_expired_warning' => $this->status === 'pending' && $this->payment_deadline && Carbon::now()->greaterThan(Carbon::parse($this->payment_deadline)),
                'queue_number' => $this->queue_number ?? null,
                'queue_info' => $this->queue_number ? "Antrian ke-{$this->queue_number} hari ini untuk terapis ini" : null,
                'updated_at' => $this->updated_at,
            ],
        
            'patient' => [
                'id' => $this->patient->id,
                'name' => $this->patient->name,
                'phone_number' => $this->patient->phone_number, 
                'profile_photo_path' => $this->patient->profile_photo_path 
                    ? url('storage/' . $this->patient->profile_photo_path) 
                    : null,
                'foto_url' => $this->patient->photo_url, // Foto dari Google
            ],

            // 3. Data Terapis
            'therapist' => [
                'id' => $this->therapist->id,
                'name' => $this->therapist->name,
                'phone_number' => $this->therapist->phone_number ?? null,
                'profile_photo_path' => $this->therapist->profile_photo_path 
                    ? url('storage/' . $this->therapist->profile_photo_path) 
                    : null,
                'foto_url' => $this->therapist->photo_url, // Foto dari Google
            ],

            // 4. Data Layanan
            'service' => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'price' => (int) $this->service->price,
                'duration_minutes' => (int) $this->service->duration_minutes,
                'full_image_url' => $this->service->full_image_url,
            ],

            // 5. Data Transaksi (Penting untuk cek bukti & status bayar)
            'transaction' => $this->transaction ? [
                'id' => $this->transaction->id,
                'payment_method' => $this->transaction->payment_method,
                'status' => $this->transaction->status,
                'amount' => (int) $this->transaction->amount, // Total harga (layanan + admin fee)
                'proof_of_transfer' => $this->transaction->proof_of_transfer 
                    ? url('storage/' . $this->transaction->proof_of_transfer) 
                    : null,
                'rejection_note' => $this->transaction->rejection_note,
                'updated_at' => $this->transaction->updated_at->toIso8601String(),
            ] : null,
        ];
    }
}