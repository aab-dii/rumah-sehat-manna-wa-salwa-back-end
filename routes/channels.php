<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('booking.{id}', function ($user, $id) {
    return true; // Public for now/Demo
});
