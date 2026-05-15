<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Notification;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automatic schedule reminders to patients (H-1 and J-1)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting reminder check...');
        $sentCountH1 = $this->sendH1Reminders();
        $sentCountJ1 = $this->sendJ1Reminders();

        $total = $sentCountH1 + $sentCountJ1;
        $this->info("Finished sending {$total} reminders (H-1: {$sentCountH1}, J-1: {$sentCountJ1}).");
    }

    private function sendH1Reminders()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $sentCount = 0;

        // Cari booking untuk besok yang confirmed
        $bookings = Booking::with('patient', 'service')
            ->where('status', 'confirmed')
            ->whereDate('booking_date', $tomorrow)
            ->get();

        foreach ($bookings as $booking) {
            // Cek apakah notifikasi H-1 sudah dikirim (lewat tabel Notification)
            $alreadySent = Notification::where('type', 'reminder_h1')
                ->whereJsonContains('data->booking_id', (string) $booking->id)
                ->exists();

            if ($alreadySent) {
                continue; // Sudah dikirim, skip
            }

            $patient = $booking->patient;
            if ($patient && $patient->fcm_token) {
                $serviceName = $booking->service ? $booking->service->name : 'Layanan';
                $time = substr($booking->booking_time, 0, 5);
                
                $title = "Pengingat Jadwal Terapi \u{23F0}";
                $body = "Halo {$patient->name}, pengingat H-1! Anda memiliki jadwal {$serviceName} besok pada pukul {$time} WITA.";
                
                FcmService::send(
                    to: $patient->fcm_token,
                    title: $title,
                    body: $body,
                    data: [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'type' => 'reminder_h1', // Tipe unik untuk H-1
                        'booking_id' => (string) $booking->id
                    ],
                    type: 'reminder_h1',
                    userId: $patient->id,
                    role: 'pasien'
                );
                
                $sentCount++;
                $this->info("H-1 Reminder sent to {$patient->name} (Booking ID: {$booking->id})");
            }
        }

        return $sentCount;
    }

    private function sendJ1Reminders()
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();
        // Cari jadwal dari sekarang sampai 65 menit ke depan (J-1)
        $limitTime = $now->copy()->addMinutes(65)->toTimeString();
        $nowTime = $now->toTimeString();
        $sentCount = 0;

        // Cari booking untuk hari ini yang jamnya sebentar lagi (dalam 1 jam)
        $bookings = Booking::with('patient', 'service')
            ->where('status', 'confirmed')
            ->whereDate('booking_date', $today)
            ->whereTime('booking_time', '>=', $nowTime)
            ->whereTime('booking_time', '<=', $limitTime)
            ->get();

        foreach ($bookings as $booking) {
            // Cek apakah notifikasi J-1 sudah dikirim
            $alreadySent = Notification::where('type', 'reminder_j1')
                ->whereJsonContains('data->booking_id', (string) $booking->id)
                ->exists();

            if ($alreadySent) {
                continue; // Sudah dikirim, skip
            }

            $patient = $booking->patient;
            if ($patient && $patient->fcm_token) {
                $serviceName = $booking->service ? $booking->service->name : 'Layanan';
                $time = substr($booking->booking_time, 0, 5);
                
                $title = "Jadwal Terapi Segera Dimulai \u{1F6A8}"; // 🚨 emoji
                $body = "Halo {$patient->name}, jadwal {$serviceName} Anda akan dimulai dalam 1 Jam pada pukul {$time} WITA. Harap segera menuju klinik!";
                
                FcmService::send(
                    to: $patient->fcm_token,
                    title: $title,
                    body: $body,
                    data: [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'type' => 'reminder_j1', // Tipe unik untuk J-1
                        'booking_id' => (string) $booking->id
                    ],
                    type: 'reminder_j1',
                    userId: $patient->id,
                    role: 'pasien'
                );
                
                $sentCount++;
                $this->info("J-1 Reminder sent to {$patient->name} (Booking ID: {$booking->id})");
            }
        }

        return $sentCount;
    }
}

