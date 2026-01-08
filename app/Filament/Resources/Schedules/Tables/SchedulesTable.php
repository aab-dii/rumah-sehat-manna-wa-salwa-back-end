<?php

namespace App\Filament\Resources\Schedules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('therapist.name')
                    ->label('Terapis')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'routine' => 'Rutin',
                        'unavailable' => 'Libur',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'routine' => 'success',
                        'unavailable' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('day')
                    ->label('Hari')
                    ->searchable(),
                TextColumn::make('specific_date')
                    ->label('Tanggal Khusus')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('Selesai')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('location_type')
                    ->label('Lokasi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'clinic' => 'Klinik',
                        'home' => 'Home Care',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'clinic' => 'info',
                        'home' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('therapist')
                    ->relationship('therapist', 'name')
                    ->label('Terapis'),
                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options([
                        'routine' => 'Rutin',
                        'unavailable' => 'Libur',
                    ]),
                SelectFilter::make('location_type')
                    ->label('Lokasi')
                    ->options([
                        'clinic' => 'Klinik',
                        'home' => 'Home Care',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
