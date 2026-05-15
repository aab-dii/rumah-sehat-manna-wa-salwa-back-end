<?php

use App\Models\User;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Schedule;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Cari User berdasarkan Email
$user = User::where('email', 'mabdillahabdi600@gmail.com')->first();

if (!$user) {
    echo "Gagal: User dengan email gwehlagu@gmail.com tidak ditemukan.\n";
    exit;
}

if (empty($user->fcm_token)) {
    echo "PERINGATAN: User {$user->name} ditemukan, tapi TIDAK memiliki fcm_token.\n";
    echo "Pastikan Anda sudah login di aplikasi Android dengan akun ini agar token tersinkron.\n";
} else {
    echo "Info: User ditemukan dengan token: " . substr($user->fcm_token, 0, 15) . "...\n";
}

// 2. Cari Layanan & Terapis Sembarang (untuk data dummy)
$service = Service::first();
$therapist = User::where('role', 'terapis')->first();

if (!$service || !$therapist) {
    echo "Gagal: Data Layanan atau Terapis belum tersedia.\n";
    exit;
}

// 3. Buat Booking untuk BESOK
$booking = Booking::create([
    'patient_id' => $user->id,
    'service_id' => $service->id,
    'therapist_id' => $therapist->id,
    'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
    'booking_time' => '10:00:00',
    'location_type' => 'clinic',
    'address' => 'Jl. Pengujian No. 123',
    'status' => 'confirmed', 
    'total_price' => $service->price,
    'reminder_sent_at' => null, 
]);

echo "Berhasil! Janji temu untuk {$user->name} pada tanggal {$booking->booking_date} telah dibuat.\n";
echo "ID Booking: {$booking->id}\n";
echo "Silakan jalankan: php artisan reminders:send\n";
