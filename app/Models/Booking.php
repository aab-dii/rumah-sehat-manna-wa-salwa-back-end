<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'patient_id',
        'service_id',
        'schedule_id',
        'therapist_id',
        'booking_date',
        'booking_time',
        'location_type',
        'status',
        'address',
        'total_price',
        'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'booking_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
