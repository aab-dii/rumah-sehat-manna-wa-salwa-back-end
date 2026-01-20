<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize()
    {
        // Check if user is admin or authorized
        // Assuming middleware handles basic auth, and we check role in controller or here
        // For simplicity, allow if logged in, but controller checks admin role usually
        return true; 
    }

    public function rules()
    {
        return [
            'patient_id' => 'required|exists:users,id',
            'therapist_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'location_type' => 'nullable|in:clinic,home',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'payment_status' => 'nullable|in:paid,unpaid,pending',
            'payment_method' => 'nullable|in:cash,transfer',
            'payment_status' => 'nullable|in:paid,unpaid,pending',
            'payment_method' => 'nullable|in:cash,transfer',
            'proof_of_transfer' => 'nullable|image|max:2048',
            'status' => 'nullable|in:pending,confirmed,completed,canceled',
        ];
    }
}
