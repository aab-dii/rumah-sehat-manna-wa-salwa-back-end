<?php

use App\Models\User;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Transaction;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Cari User, Layanan & Terapis
$user = User::where('email', 'mabdillahabdi600@gmail.com')->first() ?? User::first();
$service = Service::first();
$therapist = User::where('role', 'terapis')->first();

if (!$user || !$service || !$therapist) {
    echo "Gagal: Data User, Layanan, atau Terapis belum tersedia.\n";
    exit;
}

echo "Membuat Data Dummy untuk Uji Coba Cancel-Expired...\n";
echo "-----------------------------------------------------\n";

// ==========================================
// TEST KASUS 1: Telat Bayar (Unpaid Transfer)
// ==========================================
$booking1 = Booking::create([
    'patient_id' => $user->id,
    'service_id' => $service->id,
    'therapist_id' => $therapist->id,
    'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
    'booking_time' => '10:00:00',
    'location_type' => 'clinic',
    'address' => 'Klinik',
    'status' => 'pending', 
    'total_price' => $service->price,
    'payment_deadline' => Carbon::now()->subMinutes(5), // Dibuat kadaluarsa 5 menit yang lalu!
]);

Transaction::create([
    'booking_id' => $booking1->id,
    'payment_method' => 'transfer',
    'status' => 'unpaid', // Masih belum bayar
    'amount' => $service->price + 2500,
]);

echo "1. Dibuat Kasus: Telat Bayar Transfer (Booking ID: {$booking1->id})\n";
echo "   - Status Booking: pending\n";
echo "   - Status Transaksi: unpaid\n";
echo "   - Deadline: " . $booking1->payment_deadline . " (Sudah Lewat)\n\n";

// ==========================================
// TEST KASUS 2: Telat Upload Ulang Bukti (Rejected)
// ==========================================
$booking2 = Booking::create([
    'patient_id' => $user->id,
    'service_id' => $service->id,
    'therapist_id' => $therapist->id,
    'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
    'booking_time' => '11:00:00',
    'location_type' => 'clinic',
    'address' => 'Klinik',
    'status' => 'waiting_verification', 
    'total_price' => $service->price,
    'payment_deadline' => Carbon::now()->addHours(1), 
]);

$transaction2 = Transaction::create([
    'booking_id' => $booking2->id,
    'payment_method' => 'transfer',
    'status' => 'rejected', 
    'amount' => $service->price + 2500,
    'rejection_note' => 'Gambar buram',
]);
// Memanipulasi updated_at agar seolah-olah sudah ditolak sejak 45 detik yang lalu
// (Syarat auto-cancel untuk rejected adalah telat 30 detik)
$transaction2->updated_at = Carbon::now()->subSeconds(45);
$transaction2->save(['timestamps' => false]);

echo "2. Dibuat Kasus: Telat Upload Ulang setelah Ditolak (Booking ID: {$booking2->id})\n";
echo "   - Status Booking: waiting_verification\n";
echo "   - Status Transaksi: rejected\n";
echo "   - Waktu Ditolak: " . $transaction2->updated_at . " (Lebih dari 30 detik yang lalu)\n\n";

echo "-----------------------------------------------------\n";
echo "Selesai! Silakan lihat terminal 'php artisan schedule:work' Anda.\n";
echo "Dalam hitungan detik (saat menit berganti), kedua booking di atas akan otomatis dibatalkan (Canceled).\n";
