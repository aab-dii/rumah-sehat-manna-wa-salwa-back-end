<?php

namespace Tests\Feature\API;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Service;
use App\Models\Booking;
use Laravel\Sanctum\Sanctum;

class BookingQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_number_calculated_correctly_for_same_day()
    {
        $therapist = User::factory()->create(['role' => 'terapis']);
        $patient = User::factory()->create(['role' => 'pasien']);
        $service = Service::create([
            'name' => 'Terapi Test',
            'price' => 100000,
            'duration_minutes' => 60,
        ]);

        // Booking 1: 09:00 (queue 1)
        $booking1 = Booking::create([
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'service_id' => $service->id,
            'booking_date' => '2026-06-01',
            'booking_time' => '09:00',
            'status' => 'confirmed',
            'total_price' => 150000,
            'address' => 'Klinik',
            'created_at' => now()->subHours(2)
        ]);

        // Booking 2: 10:00 (queue 2)
        $booking2 = Booking::create([
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'service_id' => $service->id,
            'booking_date' => '2026-06-01',
            'booking_time' => '10:00',
            'status' => 'in_progress',
            'total_price' => 150000,
            'address' => 'Klinik',
            'created_at' => now()->subHour()
        ]);

        // Booking 3: 08:00 but pending (should NOT be in queue)
        $booking3 = Booking::create([
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'service_id' => $service->id,
            'booking_date' => '2026-06-01',
            'booking_time' => '08:00',
            'status' => 'pending',
            'total_price' => 150000,
            'address' => 'Klinik',
            'created_at' => now()->subHours(3)
        ]);

        Sanctum::actingAs($patient, ['*']);

        // Test Booking 1
        $response1 = $this->getJson("/api/bookings/{$booking1->id}");
        $response1->assertStatus(200)
            ->assertJsonPath('data.appointment.queue_number', 1)
            ->assertJsonPath('data.appointment.queue_info', 'Antrian ke-1 hari ini untuk terapis ini');

        // Test Booking 2
        $response2 = $this->getJson("/api/bookings/{$booking2->id}");
        $response2->assertStatus(200)
            ->assertJsonPath('data.appointment.queue_number', 2)
            ->assertJsonPath('data.appointment.queue_info', 'Antrian ke-2 hari ini untuk terapis ini');

        // Test Booking 3
        $response3 = $this->getJson("/api/bookings/{$booking3->id}");
        $response3->assertStatus(200)
            ->assertJsonPath('data.appointment.queue_number', null);
    }

    public function test_queue_number_tiebreaker_by_created_at()
    {
        $therapist = User::factory()->create(['role' => 'terapis']);
        $patient = User::factory()->create(['role' => 'pasien']);
        $service = Service::create([
            'name' => 'Terapi Test',
            'price' => 100000,
            'duration_minutes' => 60,
        ]);

        // Both at 09:00
        // Booking A created 2 hours ago
        $bookingA = Booking::create([
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'service_id' => $service->id,
            'booking_date' => '2026-06-02',
            'booking_time' => '09:00',
            'status' => 'confirmed',
            'total_price' => 150000,
            'address' => 'Klinik',
            'created_at' => now()->subHours(2)
        ]);

        // Booking B created 1 hour ago
        $bookingB = Booking::create([
            'patient_id' => $patient->id,
            'therapist_id' => $therapist->id,
            'service_id' => $service->id,
            'booking_date' => '2026-06-02',
            'booking_time' => '09:00',
            'status' => 'confirmed',
            'total_price' => 150000,
            'address' => 'Klinik',
            'created_at' => now()->subHour()
        ]);

        Sanctum::actingAs($patient, ['*']);

        $responseA = $this->getJson("/api/bookings/{$bookingA->id}");
        $responseA->assertStatus(200)
            ->assertJsonPath('data.appointment.queue_number', 1);

        $responseB = $this->getJson("/api/bookings/{$bookingB->id}");
        $responseB->assertStatus(200)
            ->assertJsonPath('data.appointment.queue_number', 2);
    }
}
