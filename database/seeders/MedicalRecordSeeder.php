<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\MedicalRecord;
use Illuminate\Database\Seeder;

class MedicalRecordSeeder extends Seeder
{
    public function run(): void
    {
        $bookings = Booking::where('status', 'completed')->get();

        foreach ($bookings as $booking) {
            MedicalRecord::create([
                'booking_id' => $booking->id,
                'patient_id' => $booking->patient_id,
                'therapist_id' => $booking->therapist_id,
                'patient_complaint' => 'Sakit punggung dan pegal-pegal sejak 3 hari lalu.',
                'diagnosis' => 'Ketegangan otot (Muscle Strain) ringan.',
                'therapist_action' => 'Dilakukan terapi bekam basah di 5 titik punggung dan pijat relaksasi.',
                'additional_notes' => 'Disarankan minum banyak air putih dan istirahat cukup. Kembali jika keluhan berlanjut.',
                'examination_date' => $booking->booking_date->format('Y-m-d') . ' ' . $booking->booking_time,
            ]);
        }
    }
}
