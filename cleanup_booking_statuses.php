<?php

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Memulai penyeragaman status...\n";

// 1. Seragamkan CANCELED
$affectedCanceled = DB::table('bookings')
    ->whereIn(DB::raw('LOWER(status)'), ['cancelled', 'batal', 'batalkan', 'cancel', 'batal_sistem'])
    ->update(['status' => 'canceled']);

// 2. Seragamkan CONFIRMED
$affectedConfirmed = DB::table('bookings')
    ->whereIn(DB::raw('LOWER(status)'), ['konfirmasi', 'terjadwal', 'approved'])
    ->update(['status' => 'confirmed']);

// 3. Seragamkan COMPLETED
$affectedCompleted = DB::table('bookings')
    ->whereIn(DB::raw('LOWER(status)'), ['selesai', 'done', 'tuntas'])
    ->update(['status' => 'completed']);

echo "Hasil Penyeragaman:\n";
echo "- Canceled: $affectedCanceled data diperbarui.\n";
echo "- Confirmed: $affectedConfirmed data diperbarui.\n";
echo "- Completed: $affectedCompleted data diperbarui.\n";
echo "\nSekarang database Anda sudah bersih dan seragam.\n";
