<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\MedicalRecord;

class MedicalRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalRecords';

    protected static ?string $title = 'Rekam Medis';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('booking_id')
                    ->label('ID Booking')
                    ->relationship('booking', 'id')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $booking = \App\Models\Booking::find($state);
                        if ($booking) {
                            $set('therapist_id', $booking->therapist_id);
                            $set('examination_date', \Carbon\Carbon::parse($booking->booking_date . ' ' . $booking->booking_time)->toDateTimeString());
                        }
                    }),

                Forms\Components\Select::make('therapist_id')
                    ->label('Terapis')
                    ->relationship('therapist', 'name')
                    ->disabled()
                    ->required()
                    ->dehydrated(),

                Forms\Components\DateTimePicker::make('examination_date')
                    ->label('Waktu Pemeriksaan')
                    ->required(),

                Forms\Components\Section::make('Detail Medis')
                    ->schema([
                        Forms\Components\Textarea::make('patient_complaint')
                            ->label('Keluhan Pasien')
                            ->required()
                            ->rows(3),
                        Forms\Components\Textarea::make('diagnosis')
                            ->label('Diagnosa')
                            ->required()
                            ->rows(3),
                        Forms\Components\Textarea::make('therapist_action')
                            ->label('Tindakan Terapis')
                            ->required()
                            ->rows(3),
                        Forms\Components\Textarea::make('additional_notes')
                            ->label('Catatan Tambahan')
                            ->rows(3),
                    ])
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('booking_id')
                    ->label('ID Booking')
                    ->sortable(),
                Tables\Columns\TextColumn::make('therapist.name')
                    ->label('Terapis')
                    ->sortable(),
                Tables\Columns\TextColumn::make('examination_date')
                    ->label('Waktu Pemeriksaan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('diagnosis')
                    ->label('Diagnosa')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
