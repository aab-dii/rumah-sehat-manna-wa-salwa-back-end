<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'therapist_id',
        'type',
        'day',
        'specific_date',
        'end_date',
        'start_time',
        'end_time',
        'location_type',
        'is_active',
        'note',
    ];

    protected $casts = [
        'specific_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }
}
