<?php

namespace Tests\Feature\API;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Carbon\Carbon;

class EmergencyCloseTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $therapist;
    protected $patient;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Users
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->therapist = User::factory()->create(['role' => 'terapis']);
        $this->patient = User::factory()->create(['role' => 'pasien', 'fcm_token' => 'fake_token']);

        // 2. Setup Service
        $this->service = Service::create([
            'name' => 'Massage Test',
            'description' => 'Test Desc',
            'price' => 100000,
            'duration_minutes' => 60
        ]);

        // Fake FCM
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['message_id' => '123'], 200)
        ]);
    }

    /** @test */
    public function it_cancels_bookings_and_blocks_slots_on_emergency_close()
    {
        $date = '2026-06-01'; // Monday
        $dayName = 'Senin';

        // Ensure schedule exists
        Schedule::create([
            'therapist_id' => $this->therapist->id,
            'day' => $dayName,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
            'type' => 'routine',
            'location_type' => 'clinic'
        ]);

        // 1. Create a booking
        $booking = Booking::create([
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->therapist->id,
            'service_id' => $this->service->id,
            'booking_date' => $date,
            'booking_time' => '10:00:00',
            'status' => 'confirmed',
            'address' => 'Test Address',
            'total_price' => 100000
        ]);

        // 2. Trigger Emergency Close
        $response = $this->actingAs($this->admin)->postJson('/api/schedules/close-now', [
            'therapist_id' => $this->therapist->id,
            'date' => $date,
            'reason' => 'Terapis Berhalangan'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.cancelled_count', 1);

        // 3. Verify Booking is Canceled
        $this->assertEquals('canceled', $booking->fresh()->status);
        $this->assertStringContainsString('Terapis Berhalangan', $booking->fresh()->cancellation_reason);

        // 4. Verify Schedule override is created
        $this->assertDatabaseHas('schedules', [
            'therapist_id' => $this->therapist->id,
            'specific_date' => $date,
            'is_active' => false,
            'type' => 'emergency'
        ]);

        // 5. Verify Slots are now EMPTY for this date
        $slotResponse = $this->getJson("/api/available-slots?therapist_id={$this->therapist->id}&booking_date={$date}&service_id={$this->service->id}");
        $slotResponse->assertStatus(200);
        $this->assertEmpty($slotResponse->json('data'));
        $slotResponse->assertJsonPath('meta.message', 'Layanan tidak tersedia pada tanggal ini (Tutup/Libur).');

        // 6. Verify CALENDAR shows as unavailable
        $calendarResponse = $this->getJson("/api/check-availability?therapist_id={$this->therapist->id}&start_date={$date}&end_date={$date}&service_id={$this->service->id}");
        $calendarResponse->assertStatus(200);
        $this->assertEquals('unavailable', $calendarResponse->json("data.{$date}"));
    }

    /** @test */
    public function it_does_not_block_other_therapists()
    {
        $date = '2026-06-02'; // Tuesday
        $dayName = 'Selasa';

        $otherTherapist = User::factory()->create(['role' => 'terapis']);
        
        // Schedule for both
        Schedule::create([
            'therapist_id' => $this->therapist->id,
            'day' => $dayName, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_active' => true, 'type' => 'routine', 'location_type' => 'clinic'
        ]);
        Schedule::create([
            'therapist_id' => $otherTherapist->id,
            'day' => $dayName, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'is_active' => true, 'type' => 'routine', 'location_type' => 'clinic'
        ]);

        // Emergency Close ONLY for therapist 1
        $this->actingAs($this->admin)->postJson('/api/schedules/close-now', [
            'therapist_id' => $this->therapist->id,
            'date' => $date,
            'reason' => 'Emergency'
        ]);

        // Therapist 1 should be blocked
        $res1 = $this->getJson("/api/available-slots?therapist_id={$this->therapist->id}&booking_date={$date}&service_id={$this->service->id}");
        $this->assertEmpty($res1->json('data'));

        // Therapist 2 should STILL BE AVAILABLE
        $res2 = $this->getJson("/api/available-slots?therapist_id={$otherTherapist->id}&booking_date={$date}&service_id={$this->service->id}");
        $res2->assertStatus(200);
        
        // Debugging if empty
        if (empty($res2->json('data'))) {
            dump($res2->json());
        }

        $this->assertNotEmpty($res2->json('data'));
    }
}
