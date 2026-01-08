<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin Rumah Sehat',
            'email' => 'admin@rumahsehat.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone_number' => '081234567890',
            'gender' => 'L',
        ]);
    }
}
