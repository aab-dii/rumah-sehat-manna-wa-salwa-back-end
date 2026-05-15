<?php

namespace Tests\Feature\API;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Carbon\Carbon;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    protected $patient;
    protected $therapist;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patient = User::create([
            'name' => 'Patient One',
            'email' => 'patient@example.com',
            'password' => Hash::make('password'),
            'role' => 'pasien',
            'phone_number' => '08123456789',
        ]);

        $this->therapist = User::create([
            'name' => 'Therapist One',
            'email' => 'therapist@example.com',
            'password' => Hash::make('password'),
            'role' => 'terapis',
            'phone_number' => '08123456790',
        ]);

        $this->service = Service::create([
            'name' => 'Service One',
            'price' => 100000,
            'duration_minutes' => 60,
        ]);
    }

    /** @test */
    public function it_prevents_therapist_slot_conflict()
    {
        $date = now()->addDay()->format('Y-m-d');
        $time = '10:00';

        // 1. Create first booking
        Booking::create([
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->therapist->id,
            'service_id' => $this->service->id,
            'booking_date' => $date,
            'booking_time' => $time,
            'status' => 'confirmed',
            'address' => 'Klinik',
            'total_price' => 100000
        ]);

        // 2. Try to book the same slot with another patient
        $anotherPatient = User::factory()->create(['role' => 'pasien']);

        $response = $this->actingAs($anotherPatient, 'sanctum')
            ->postJson('/api/bookings', [
                'therapist_id' => $this->therapist->id,
                'service_id' => $this->service->id,
                'booking_date' => $date,
                'booking_time' => $time,
                'payment_method' => 'cash',
                'address' => 'Klinik'
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('meta.message', 'Maaf, slot waktu ini baru saja dipesan oleh pasien lain. Silakan pilih waktu yang berbeda.');
    }

    /** @test */
    public function it_prevents_patient_double_booking_at_same_time()
    {
        $date = now()->addDay()->format('Y-m-d');
        $time = '10:00';

        // 1. Create first booking for patient
        Booking::create([
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->therapist->id,
            'service_id' => $this->service->id,
            'booking_date' => $date,
            'booking_time' => $time,
            'status' => 'confirmed',
            'address' => 'Klinik',
            'total_price' => 100000
        ]);

        // 2. Try to book another therapist at the same time
        $anotherTherapist = User::factory()->create(['role' => 'terapis']);

        $response = $this->actingAs($this->patient, 'sanctum')
            ->postJson('/api/bookings', [
                'therapist_id' => $anotherTherapist->id,
                'service_id' => $this->service->id,
                'booking_date' => $date,
                'booking_time' => $time,
                'payment_method' => 'cash',
                'address' => 'Klinik'
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('meta.message', 'Anda sudah memiliki janji temu aktif pada waktu tersebut. Silakan cek kembali jadwal Anda.');
    }

    /** @test */
    public function it_respects_the_30_minute_break_time_for_therapist()
    {
        $date = now()->addDay()->format('Y-m-d');
        $time1 = '10:00'; // Duration 60m, ends at 11:00. Break until 11:15 (Controller uses 15m break).
        $time2 = '11:10'; // Should conflict because it's within break time.

        Booking::create([
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->therapist->id,
            'service_id' => $this->service->id,
            'booking_date' => $date,
            'booking_time' => $time1,
            'status' => 'confirmed',
            'address' => 'Klinik',
            'total_price' => 100000
        ]);

        $anotherPatient = User::factory()->create(['role' => 'pasien']);

        $response = $this->actingAs($anotherPatient, 'sanctum')
            ->postJson('/api/bookings', [
                'therapist_id' => $this->therapist->id,
                'service_id' => $this->service->id,
                'booking_date' => $date,
                'booking_time' => $time2,
                'payment_method' => 'cash',
                'address' => 'Klinik'
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('meta.message', 'Maaf, slot waktu ini baru saja dipesan oleh pasien lain. Silakan pilih waktu yang berbeda.');
    }
}
