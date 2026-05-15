<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $duration = $this->service->duration_minutes ?? 60; 
    
        $startTime = \Carbon\Carbon::parse($this->booking_time);
        $endTime = $startTime->copy()->addMinutes($duration);
        return [
            'id' => $this->id,
            'booking_date' => \Carbon\Carbon::parse($this->booking_date)->translatedFormat('d M Y'), 
            'booking_time' => $startTime->format('H:i') . ' - ' . $endTime->format('H:i'),
            'status' => $this->status_baru,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient->name ?? 'User',
            'patient_foto_url' => $this->patient->photo_url ?? null, // Foto Google pasien
            'therapist_id' => $this->therapist_id,
            'therapist_name' => $this->therapist->name ?? 'Terapis',
            'therapist_foto_url' => $this->therapist->photo_url ?? null, // Foto Google terapis
            'service_name' => $this->service->name ?? 'Layanan',
            'total_price' => $this->total_price,
        ];
    }
}
