<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'booking_id',
        'payment_method',
        'status',
        'amount',
        'proof_of_transfer',
        'rejection_note',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
