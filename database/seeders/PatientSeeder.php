<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Ahmad Dahlan',
            'email' => 'ahmad.dahlan@example.com',
            'password' => Hash::make('password123'),
            'role' => 'pasien',
            'phone_number' => '081345678901',
            'gender' => 'L',
            'job' => 'Wiraswasta',
            'address' => 'Jl. Merdeka No. 45, Jakarta Selatan',
            'birth_date' => '1985-05-20',
            'firebase_uid' => 'patient_uid_001',
        ]);

        User::create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti.nurhaliza@example.com',
            'password' => Hash::make('password123'),
            'role' => 'pasien',
            'phone_number' => '081298765432',
            'gender' => 'P',
            'job' => 'Ibu Rumah Tangga',
            'address' => 'Jl. Kebon Jeruk No. 12, Jakarta Barat',
            'birth_date' => '1990-11-15',
            'firebase_uid' => 'patient_uid_002',
        ]);

        User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso.pasien@example.com',
            'password' => Hash::make('password123'),
            'role' => 'pasien',
            'phone_number' => '085678901234',
            'gender' => 'L',
            'job' => 'Karyawan Swasta',
            'address' => 'Jl. Sudirman Kav. 50, Jakarta Pusat',
            'birth_date' => '1992-03-10',
            'firebase_uid' => 'patient_uid_003',
        ]);
    }
}
