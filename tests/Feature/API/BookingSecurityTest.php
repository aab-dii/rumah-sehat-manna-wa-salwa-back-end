<?php

namespace Tests\Feature\API;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $patientA;
    protected $patientB;
    protected $therapistX;
    protected $therapistY;
    protected $admin;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Users
        $this->patientA = User::create([
            'name' => 'Patient A',
            'email' => 'patienta@example.com',
            'password' => Hash::make('password'),
            'role' => 'pasien',
            'phone_number' => '08123456789',
            'firebase_uid' => 'uid_patient_a'
        ]);

        $this->patientB = User::create([
            'name' => 'Patient B',
            'email' => 'patientb@example.com',
            'password' => Hash::make('password'),
            'role' => 'pasien',
            'phone_number' => '08123456790',
            'firebase_uid' => 'uid_patient_b'
        ]);

        $this->therapistX = User::create([
            'name' => 'Therapist X',
            'email' => 'therapistx@example.com',
            'password' => Hash::make('password'),
            'role' => 'terapis',
            'phone_number' => '08123456791',
            'firebase_uid' => 'uid_therapist_x'
        ]);

        $this->therapistY = User::create([
            'name' => 'Therapist Y',
            'email' => 'therapisty@example.com',
            'password' => Hash::make('password'),
            'role' => 'terapis',
            'phone_number' => '08123456792',
            'firebase_uid' => 'uid_therapist_y'
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone_number' => '08123456793',
            'firebase_uid' => 'uid_admin'
        ]);

        // Create Service
        $this->service = Service::create([
            'name' => 'Massage Therapy',
            'description' => 'A relaxing massage',
            'price' => 150000,
            'duration_minutes' => 60
        ]);
    }

    /** @test */
    public function patient_can_access_own_booking_detail()
    {
        $booking = Booking::create([
            'patient_id' => $this->patientA->id,
            'therapist_id' => $this->therapistX->id,
            'service_id' => $this->service->id,
            'booking_date' => now()->format('Y-m-d'),
            'booking_time' => '10:00',
            'status' => 'pending',
            'address' => 'Test Address',
            'total_price' => 150000
        ]);

        $response = $this->actingAs($this->patientA, 'sanctum')
            ->getJson("/api/bookings/{$booking->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function patient_cannot_access_other_patient_booking_detail()
    {
        $bookingOfB = Booking::create([
            'patient_id' => $this->patientB->id,
            'therapist_id' => $this->therapistX->id,
            'service_id' => $this->service->id,
            'booking_date' => now()->format('Y-m-d'),
            'booking_time' => '10:00',
            'status' => 'pending',
            'address' => 'Test Address',
            'total_price' => 150000
        ]);

        $response = $this->actingAs($this->patientA, 'sanctum')
            ->getJson("/api/bookings/{$bookingOfB->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function therapist_can_access_own_assigned_booking_detail()
    {
        $bookingForX = Booking::create([
            'patient_id' => $this->patientA->id,
            'therapist_id' => $this->therapistX->id,
            'service_id' => $this->service->id,
            'booking_date' => now()->format('Y-m-d'),
            'booking_time' => '11:00',
            'status' => 'pending',
            'address' => 'Test Address',
            'total_price' => 150000
        ]);

        $response = $this->actingAs($this->therapistX, 'sanctum')
            ->getJson("/api/bookings/{$bookingForX->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function therapist_cannot_access_other_therapist_booking_detail()
    {
        $bookingForY = Booking::create([
            'patient_id' => $this->patientA->id,
            'therapist_id' => $this->therapistY->id,
            'service_id' => $this->service->id,
            'booking_date' => now()->format('Y-m-d'),
            'booking_time' => '11:00',
            'status' => 'pending',
            'address' => 'Test Address',
            'total_price' => 150000
        ]);

        $response = $this->actingAs($this->therapistX, 'sanctum')
            ->getJson("/api/bookings/{$bookingForY->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function admin_can_access_any_booking_detail()
    {
        $booking = Booking::create([
            'patient_id' => $this->patientA->id,
            'therapist_id' => $this->therapistX->id,
            'service_id' => $this->service->id,
            'booking_date' => now()->format('Y-m-d'),
            'booking_time' => '12:00',
            'status' => 'pending',
            'address' => 'Test Address',
            'total_price' => 150000
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/bookings/{$booking->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function patient_list_only_shows_own_bookings()
    {
        // Create 1 own booking
        Booking::create([
            'patient_id' => $this->patientA->id,
            'therapist_id' => $this->therapistX->id,
            'service_id' => $this->service->id,
            'booking_date' => now()->format('Y-m-d'),
            'booking_time' => '13:00',
            'status' => 'pending',
            'address' => 'Address A',
            'total_price' => 150000
        ]);

        // Create 1 other's booking
        Booking::create([
            'patient_id' => $this->patientB->id,
            'therapist_id' => $this->therapistX->id,
            'service_id' => $this->service->id,
            'booking_date' => now()->format('Y-m-d'),
            'booking_time' => '14:00',
            'status' => 'pending',
            'address' => 'Address B',
            'total_price' => 150000
        ]);

        $response = $this->actingAs($this->patientA, 'sanctum')
            ->getJson("/api/bookings");

        if ($response->status() !== 200) {
            fwrite(STDERR, "Response failed with status " . $response->status() . "\n");
            fwrite(STDERR, $response->getContent() . "\n");
        }

        $response->assertStatus(200);
        
        // Count results in the paginated data.data
        $responseData = $response->json('data.data');
        $this->assertCount(1, $responseData, "Should only see 1 booking");
        $this->assertEquals($this->patientA->id, $responseData[0]['patient_id']);
    }
}
