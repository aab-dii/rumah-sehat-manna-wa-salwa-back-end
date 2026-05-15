<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TherapyRecord extends Model
{
    protected $table = 'therapy_records';

    protected $fillable = [
        'booking_id',
        'patient_id',
        'therapist_id',
        'patient_complaint',
        'therapist_action',
        'additional_notes',
        'examination_date',
    ];

    protected $casts = [
        'examination_date' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }
}
