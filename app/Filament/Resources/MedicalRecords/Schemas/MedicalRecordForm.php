<?php

namespace App\Filament\Resources\MedicalRecords\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MedicalRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('booking_id')
                    ->label('ID Booking')
                    ->relationship('booking', 'id')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $booking = \App\Models\Booking::find($state);
                        if ($booking) {
                            $set('patient_id', $booking->patient_id);
                            $set('therapist_id', $booking->therapist_id);
                            $set('examination_date', \Carbon\Carbon::parse($booking->booking_date . ' ' . $booking->booking_time)->toDateTimeString());
                        }
                    }),

                \Filament\Forms\Components\Select::make('patient_id')
                    ->label('Pasien')
                    ->relationship('patient', 'name')
                    ->disabled()
                    ->required()
                    ->dehydrated(), // Agar tetap dikirim ke database meski disabled

                \Filament\Forms\Components\Select::make('therapist_id')
                    ->label('Terapis')
                    ->relationship('therapist', 'name')
                    ->disabled()
                    ->required()
                    ->dehydrated(),

                \Filament\Forms\Components\DateTimePicker::make('examination_date')
                    ->label('Waktu Pemeriksaan')
                    ->required(),

                \Filament\Forms\Components\Section::make('Detail Medis')
                    ->schema([
                        \Filament\Forms\Components\Textarea::make('patient_complaint')
                            ->label('Keluhan Pasien')
                            ->required()
                            ->rows(3),
                        \Filament\Forms\Components\Textarea::make('diagnosis')
                            ->label('Diagnosa')
                            ->required()
                            ->rows(3),
                        \Filament\Forms\Components\Textarea::make('therapist_action')
                            ->label('Tindakan Terapis')
                            ->required()
                            ->rows(3),
                        \Filament\Forms\Components\Textarea::make('additional_notes')
                            ->label('Catatan Tambahan')
                            ->rows(3),
                    ])
                    ->columns(1),
            ]);
    }
}
