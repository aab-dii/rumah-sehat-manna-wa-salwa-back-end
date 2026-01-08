<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('patient_id')
                    ->numeric(),
                TextEntry::make('service_id')
                    ->numeric(),
                TextEntry::make('schedule_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('therapist_id')
                    ->numeric(),
                TextEntry::make('booking_date')
                    ->date(),
                TextEntry::make('booking_time')
                    ->time(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('address'),
                TextEntry::make('total_price')
                    ->money(),
                TextEntry::make('cancellation_reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
