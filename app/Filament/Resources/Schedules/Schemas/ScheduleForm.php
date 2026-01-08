<?php

namespace App\Filament\Resources\Schedules\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('therapist_id')
                    ->label('Terapis')
                    ->relationship('therapist', 'name', fn ($query) => $query->where('role', 'terapis'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type')
                    ->label('Tipe Jadwal')
                    ->options([
                        'routine' => 'Rutin (Mingguan)',
                        'unavailable' => 'Tidak Tersedia / Libur',
                    ])
                    ->required()
                    ->reactive(),
                Select::make('day')
                    ->label('Hari')
                    ->options([
                        'Senin' => 'Senin',
                        'Selasa' => 'Selasa',
                        'Rabu' => 'Rabu',
                        'Kamis' => 'Kamis',
                        'Jumat' => 'Jumat',
                        'Sabtu' => 'Sabtu',
                        'Minggu' => 'Minggu',
                    ])
                    ->visible(fn ($get) => $get('type') === 'routine')
                    ->required(fn ($get) => $get('type') === 'routine'),
                DatePicker::make('specific_date')
                    ->label('Tanggal Khusus')
                    ->visible(fn ($get) => $get('type') === 'unavailable')
                    ->required(fn ($get) => $get('type') === 'unavailable'),
                TimePicker::make('start_time')
                    ->label('Jam Mulai')
                    ->required()
                    ->seconds(false),
                TimePicker::make('end_time')
                    ->label('Jam Selesai')
                    ->required()
                    ->seconds(false),
                Select::make('location_type')
                    ->label('Lokasi')
                    ->options([
                        'clinic' => 'Klinik',
                        'home' => 'Home Care',
                    ])
                    ->required(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
