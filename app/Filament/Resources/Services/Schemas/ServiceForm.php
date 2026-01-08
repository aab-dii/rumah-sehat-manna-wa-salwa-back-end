<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Layanan')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Harga')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0),
                TextInput::make('duration_minutes')
                    ->label('Durasi (Menit)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->step(5),
                FileUpload::make('image_url')
                    ->label('Gambar Layanan')
                    ->image()
                    ->directory('services')
                    ->visibility('public')
                    ->columnSpanFull(),
            ]);
    }
}
