<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('patient_id')
                    ->label('Pasien')
                    ->relationship('patient', 'name', fn ($query) => $query->where('role', 'pasien'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('address', \App\Models\User::find($state)?->address)),
                
                Select::make('service_id')
                    ->label('Layanan')
                    ->relationship('service', 'name')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('total_price', \App\Models\Service::find($state)?->price)),

                Select::make('therapist_id')
                    ->label('Terapis')
                    ->relationship('therapist', 'name', fn ($query) => $query->where('role', 'terapis'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive(),

                Select::make('location_type')
                    ->label('Lokasi Layanan')
                    ->options([
                        'clinic' => 'Klinik',
                        'home' => 'Home Care',
                    ])
                    ->required()
                    ->reactive()
                    ->default('clinic'),

                DatePicker::make('booking_date')
                    ->label('Tanggal Booking')
                    ->required()
                    ->reactive()
                    ->minDate(now()),

                Select::make('booking_time')
                    ->label('Waktu Booking')
                    ->options(function (callable $get) {
                        $therapistId = $get('therapist_id');
                        $serviceId = $get('service_id');
                        $date = $get('booking_date');
                        $locationType = $get('location_type');

                        if (!$therapistId || !$serviceId || !$date || !$locationType) {
                            return [];
                        }

                        $service = \App\Models\Service::find($serviceId);
                        $duration = $service->duration_minutes;
                        $dayOfWeek = \Carbon\Carbon::parse($date)->locale('id')->dayName;
                        
                        $daysMap = [
                            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
                        ];
                        $englishDay = \Carbon\Carbon::parse($date)->format('l');
                        $dayName = $daysMap[$englishDay];

                        // Cari jadwal yang cocok dengan Terapis, Hari, DAN Lokasi
                        $schedule = \App\Models\Schedule::where('therapist_id', $therapistId)
                            ->where('is_active', true)
                            ->where('location_type', $locationType) // Filter lokasi
                            ->where(function ($query) use ($dayName, $date) {
                                $query->where('day', $dayName)
                                      ->orWhere('specific_date', $date);
                            })
                            ->first();

                        if (!$schedule || $schedule->type === 'unavailable') {
                            return [];
                        }

                        $slots = [];
                        $startTime = \Carbon\Carbon::parse($date . ' ' . $schedule->start_time);
                        $endTime = \Carbon\Carbon::parse($date . ' ' . $schedule->end_time);
                        
                        $existingBookings = \App\Models\Booking::where('therapist_id', $therapistId)
                            ->where('booking_date', $date)
                            ->where('status', '!=', 'canceled')
                            ->get();

                        while ($startTime->copy()->addMinutes($duration) <= $endTime) {
                            $slotStart = $startTime->format('H:i');
                            $slotEnd = $startTime->copy()->addMinutes($duration)->format('H:i');
                            
                            $isAvailable = true;
                            foreach ($existingBookings as $booking) {
                                $bookingStart = \Carbon\Carbon::parse($date . ' ' . $booking->booking_time);
                                $bookingEnd = $bookingStart->copy()->addMinutes($booking->service->duration_minutes + 30);

                                $proposedStart = $startTime;
                                $proposedEnd = $startTime->copy()->addMinutes($duration + 30);

                                if ($proposedStart < $bookingEnd && $proposedEnd > $bookingStart) {
                                    $isAvailable = false;
                                    break;
                                }
                            }

                            if ($isAvailable) {
                                $slots[$slotStart] = $slotStart . ' - ' . $slotEnd;
                            }

                            $startTime->addMinutes(30);
                        }

                        return $slots;
                    })
                    ->required()
                    ->reactive(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Menunggu Konfirmasi',
                        'confirmed' => 'Dikonfirmasi',
                        'completed' => 'Selesai',
                        'canceled' => 'Dibatalkan',
                    ])
                    ->default('pending')
                    ->required(),

                TextInput::make('address')
                    ->label('Alamat')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('total_price')
                    ->label('Total Harga')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->readOnly(),

                Textarea::make('cancellation_reason')
                    ->label('Alasan Pembatalan')
                    ->visible(fn ($get) => $get('status') === 'canceled')
                    ->columnSpanFull(),
            ]);
    }
}
