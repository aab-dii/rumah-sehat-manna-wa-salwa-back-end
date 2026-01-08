<?php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $therapists = User::where('role', 'terapis')->get();
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        foreach ($therapists as $therapist) {
            // 1. Jadwal Klinik (Senin - Jumat)
            foreach ($days as $day) {
                Schedule::create([
                    'therapist_id' => $therapist->id,
                    'type' => 'routine',
                    'day' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'location_type' => 'clinic',
                    'is_active' => true,
                ]);
            }

            // 2. Jadwal Home Care (Sabtu)
            Schedule::create([
                'therapist_id' => $therapist->id,
                'type' => 'routine',
                'day' => 'Sabtu',
                'start_time' => '09:00:00',
                'end_time' => '14:00:00',
                'location_type' => 'home',
                'is_active' => true,
            ]);
        }
    }
}
