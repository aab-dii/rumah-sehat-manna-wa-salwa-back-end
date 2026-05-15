<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use App\Models\User;

echo "Refining backfill to fix leaked notifications...\n";

$patientTitles = [
    'Janji Temu Dikonfirmasi!',
    'Jadwal Dibatalkan Admin',
    'Pembayaran Ditolak',
    'Terapi Selesai',
    'Bukti Pembayaran Diterima'
];

$adminTitles = [
    'Booking Baru Masuk',
    'Re-Upload Bukti Bayar',
    'Pasien Batal',
    'Permintaan Verifikasi'
];

$count = 0;
Notification::chunk(100, function ($notifications) use ($patientTitles, $adminTitles, &$count) {
    foreach ($notifications as $n) {
        $newRole = null;
        
        // Cek berdasarkan judul
        foreach ($patientTitles as $pt) {
            if (str_contains($n->title, $pt)) {
                $newRole = 'pasien';
                break;
            }
        }
        
        if (!$newRole) {
            foreach ($adminTitles as $at) {
                if (str_contains($n->title, $at)) {
                    $newRole = 'admin';
                    break;
                }
            }
        }
        
        // Jika tidak ketemu dari judul, pakai role asli user_id (fallback)
        if (!$newRole) {
            $user = User::find($n->user_id);
            $newRole = $user ? $user->role : null;
        }

        if ($newRole && $n->for_role !== $newRole) {
            $n->update(['for_role' => $newRole]);
            $count++;
        }
    }
});

echo "Refinement complete. Corrected role for $count records.\n";
