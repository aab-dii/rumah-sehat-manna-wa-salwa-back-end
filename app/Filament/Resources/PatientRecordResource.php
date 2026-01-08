<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientRecordResource\Pages;
use App\Filament\Resources\Users\RelationManagers\MedicalRecordsRelationManager;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

use BackedEnum;

class PatientRecordResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Rekam Medis';
    
    protected static ?string $modelLabel = 'Pasien';

    protected static ?string $slug = 'patient-records';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'pasien');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Form kosong atau readonly info user
            \Filament\Forms\Components\TextInput::make('name')->disabled(),
            \Filament\Forms\Components\TextInput::make('email')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pasien')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('No. Telepon')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('edit')
                    ->label('Lihat Rekam Medis')
                    ->icon('heroicon-m-eye')
                    ->url(fn (User $record): string => Pages\EditPatientRecord::getUrl(['record' => $record])),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MedicalRecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatientRecords::route('/'),
            'edit' => Pages\EditPatientRecord::route('/{record}/edit'),
        ];
    }
}
