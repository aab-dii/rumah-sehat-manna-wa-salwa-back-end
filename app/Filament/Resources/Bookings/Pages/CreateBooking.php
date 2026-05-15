<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;
    protected function afterCreate(): void
    {
        $booking = $this->record;
        $booking->load(['service', 'therapist', 'patient', 'transaction']);
        event(new \App\Events\MyEvent($booking));
    }
}
