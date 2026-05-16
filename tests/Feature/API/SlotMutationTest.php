<?php

namespace Tests\Feature\API;

use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class SlotMutationTest extends TestCase
{
    use RefreshDatabase;

    protected $therapist;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->therapist = User::factory()->create(['role' => 'terapis']);
        $this->service = Service::create([
            'name' => 'Massase',
            'price' => 100000,
            'duration_minutes' => 60,
        ]);
    }

    /** @test */
    public function it_shows_future_slots_correctly_when_checked_in_the_afternoon()
    {
        // 1. Set current time to 15:15 on a Saturday
        $now = Carbon::parse('2026-05-16 15:15:00'); 
        Carbon::setTestNow($now);

        // 2. Setup schedule 09:00 - 19:00 for Saturday (Sabtu)
        Schedule::create([
            'therapist_id' => $this->therapist->id,
            'day' => 'Sabtu',
            'start_time' => '09:00:00',
            'end_time' => '19:00:00',
            'is_active' => true,
            'type' => 'routine',
            'location_type' => 'clinic'
        ]);

        // 3. Call availableSlots for today (acting as patient)
        $patient = User::factory()->create(['role' => 'pasien']);
        $response = $this->actingAs($patient, 'sanctum')
            ->getJson("/api/available-slots?therapist_id={$this->therapist->id}&booking_date=2026-05-16&service_id={$this->service->id}");

        $response->assertStatus(200);
        $slots = $response->json('data.slots');

        // Before fix: $slots would be empty or very few because $serverNow mutated
        // After fix: $slots should contain 15:30, 16:00, 16:30, 17:00, 17:30
        
        $this->assertNotEmpty($slots, 'Slots should not be empty in the afternoon if schedule is until 19:00');
        
        // 15:30 should be present (15:30 > 15:15 + 5 min buffer)
        $this->assertContains('15:30', $slots);
        $this->assertContains('17:30', $slots);
        
        // 09:00 should NOT be present (already passed)
        $this->assertNotContains('09:00', $slots);

        Carbon::setTestNow(); // Reset
    }

    /** @test */
    public function it_returns_available_in_check_availability_in_the_afternoon()
    {
        // 1. Set current time to 15:15
        $now = Carbon::parse('2026-05-16 15:15:00'); 
        Carbon::setTestNow($now);

        // 2. Setup schedule 09:00 - 19:00
        Schedule::create([
            'therapist_id' => $this->therapist->id,
            'day' => 'Sabtu',
            'start_time' => '09:00:00',
            'end_time' => '19:00:00',
            'is_active' => true,
            'type' => 'routine',
            'location_type' => 'clinic'
        ]);

        // 3. Check availability for today
        $patient = User::factory()->create(['role' => 'pasien']);
        $response = $this->actingAs($patient, 'sanctum')
            ->getJson("/api/check-availability?therapist_id={$this->therapist->id}&start_date=2026-05-16&end_date=2026-05-16&service_id={$this->service->id}");

        $response->assertStatus(200);
        $this->assertEquals('available', $response->json('data.2026-05-16'));

        Carbon::setTestNow(); // Reset
    }
}
