<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use App\Models\User;

echo "Starting backfill...\n";
$count = 0;
Notification::whereNull('for_role')->chunk(100, function ($notifications) use (&$count) {
    foreach ($notifications as $notification) {
        $user = User::find($notification->user_id);
        if ($user) {
            $notification->update(['for_role' => $user->role]);
            $count++;
        }
    }
});

echo "Backfill complete. Updated $count records.\n";
