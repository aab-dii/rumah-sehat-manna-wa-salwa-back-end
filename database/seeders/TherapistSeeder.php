<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TherapistSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Dr. Siti Aminah',
            'email' => 'siti.aminah@rumahsehat.com',
            'password' => Hash::make('password123'),
            'role' => 'terapis',
            'phone_number' => '081298765432',
            'gender' => 'P',
            'specialization' => ['Bekam', 'Akupuntur'],
            'firebase_uid' => 'therapist_uid_123',
        ]);
        
        User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@rumahsehat.com',
            'password' => Hash::make('password123'),
            'role' => 'terapis',
            'phone_number' => '081211223344',
            'gender' => 'L',
            'specialization' => ['Pijat Refleksi'],
            'firebase_uid' => 'therapist_uid_456',
        ]);
    }
}
