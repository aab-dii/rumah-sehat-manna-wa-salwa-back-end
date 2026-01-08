<?php

namespace App\Filament\Resources\PatientRecordResource\Pages;

use App\Filament\Resources\PatientRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListPatientRecords extends ListRecords
{
    protected static string $resource = PatientRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tidak ada create action karena user dibuat di menu Pengguna
        ];
    }
}
