<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $patients = User::where('role', 'pasien')->get();
        $therapists = User::where('role', 'terapis')->get();
        $services = Service::all();

        if ($patients->isEmpty() || $therapists->isEmpty() || $services->isEmpty()) {
            return;
        }

        // Buat booking yang sudah selesai di masa lalu
        foreach ($patients as $index => $patient) {
            $therapist = $therapists->random();
            $service = $services->random();
            
            // Cari jadwal terapis (ambil sembarang jadwal rutin)
            $schedule = Schedule::where('therapist_id', $therapist->id)
                ->where('type', 'routine')
                ->first();

            Booking::create([
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'therapist_id' => $therapist->id,
                'schedule_id' => $schedule ? $schedule->id : null,
                'booking_date' => Carbon::now()->subDays(($index + 1) * 2), // 2, 4, 6 hari yang lalu
                'booking_time' => '10:00:00',
                'location_type' => 'clinic',
                'status' => 'completed',
                'address' => $patient->address ?? 'Alamat Default',
                'total_price' => $service->price,
            ]);
        }
    }
}
