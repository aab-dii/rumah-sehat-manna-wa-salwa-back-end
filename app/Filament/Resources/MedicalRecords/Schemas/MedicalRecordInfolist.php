<?php

namespace App\Filament\Resources\MedicalRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MedicalRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('booking_id')
                    ->numeric(),
                TextEntry::make('patient_id')
                    ->numeric(),
                TextEntry::make('therapist_id')
                    ->numeric(),
                TextEntry::make('patient_complaint')
                    ->columnSpanFull(),
                TextEntry::make('diagnosis')
                    ->columnSpanFull(),
                TextEntry::make('therapist_action')
                    ->columnSpanFull(),
                TextEntry::make('additional_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('examination_date')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
