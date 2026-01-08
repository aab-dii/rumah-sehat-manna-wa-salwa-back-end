<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Akun')
                    ->schema([
                        TextInput::make('firebase_uid')
                            ->label('Firebase UID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('phone_number')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Informasi Pribadi')
                    ->schema([
                        Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options([
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                            ])
                            ->required()
                            ->native(false),
                        Select::make('role')
                            ->label('Peran')
                            ->options([
                                'admin' => 'Admin',
                                'terapis' => 'Terapis',
                                'pasien' => 'Pasien',
                            ])
                            ->default('pasien')
                            ->required()
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(2),

                Section::make('Informasi Pasien')
                    ->schema([
                        TextInput::make('job')
                            ->label('Pekerjaan')
                            ->maxLength(255),
                        DatePicker::make('birth_date')
                            ->label('Tanggal Lahir')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => $get('role') === 'pasien'),

                Section::make('Informasi Terapis')
                    ->schema([
                        TagsInput::make('specialization')
                            ->label('Spesialisasi')
                            ->placeholder('Tambahkan spesialisasi...')
                            ->helperText('Tekan Enter untuk menambahkan spesialisasi baru')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($get) => $get('role') === 'terapis'),
            ]);
    }
}
