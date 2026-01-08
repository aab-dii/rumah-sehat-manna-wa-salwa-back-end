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
            'location_type' => 'required|in:clinic,home',
            'address' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }
}
