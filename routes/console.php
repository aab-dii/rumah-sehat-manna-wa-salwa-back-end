<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:cancel-expired')->everyMinute();
Schedule::command('bookings:auto-cancel')->daily();
Schedule::command('reminders:send')->everyThirtyMinutes();
