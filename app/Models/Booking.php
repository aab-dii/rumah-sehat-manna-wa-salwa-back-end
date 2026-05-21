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
        'payment_deadline',
        'reminder_sent_at',
        'handled_by',       // Sprint 2.1: audit trail admin yang menangani
        'canceled_at',
        'completed_at',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'payment_deadline' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'canceled_at' => 'datetime',
        'completed_at' => 'datetime',
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
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function therapyRecord()
    {
        return $this->hasOne(TherapyRecord::class);
    }

    /**
     * Sprint 2.1: Relasi ke admin yang menangani booking ini.
     */
    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected static function booted()
    {
        static::updating(function ($booking) {
            if ($booking->isDirty('status')) {
                if ($booking->status === 'canceled' && is_null($booking->canceled_at)) {
                    $booking->canceled_at = now();
                }
                if ($booking->status === 'completed' && is_null($booking->completed_at)) {
                    $booking->completed_at = now();
                }
            }
        });

        static::updated(function ($booking) {
            try {
                event(new \App\Events\MyEvent($booking));
            } catch (\Exception $e) {
                // Log error silently to not break the app flow
                \Illuminate\Support\Facades\Log::error("Pusher Updated Event Failed: " . $e->getMessage());
            }

            // Double-entry bookkeeping auto-refund for canceled bookings paid via transfer
            if ($booking->status === 'canceled') {
                $hasPaidTransfer = \App\Models\Transaction::where('booking_id', $booking->id)
                    ->where('status', 'paid')
                    ->where('payment_method', 'transfer')
                    ->exists();

                if ($hasPaidTransfer) {
                    $alreadyRefunded = \App\Models\Transaction::where('booking_id', $booking->id)
                        ->where('status', 'refund')
                        ->exists();

                    if (!$alreadyRefunded) {
                        \App\Models\Transaction::create([
                            'booking_id' => $booking->id,
                            'payment_method' => 'transfer',
                            'status' => 'refund',
                            'amount' => $booking->total_price,
                            'refunded_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    protected $appends = ['status_baru']; 

    public function getStatusBaruAttribute()
    {
        // 1. AMBIL NILAI ASLI DARI DATABASE
        $originalStatus = $this->getRawOriginal('status');
    
        $rawStatus = $originalStatus ?? 'pending'; 
        $bStatus = strtoupper($rawStatus); 

        $transaction = $this->transaction;
        
        if (!$transaction) {
            return strtolower($rawStatus);
        }

        $tStatus = strtolower($transaction->status);
        $method = strtolower($transaction->payment_method);

        // Logic 1: Cek Pembatalan/Selesai
        if ($bStatus === 'CANCELED') return 'canceled';
        if ($bStatus === 'COMPLETED') return 'completed';
        if ($bStatus === 'FORCE_COMPLETED') {
            if (auth()->check() && auth()->user()->role === 'pasien') {
                return 'completed';
            }
            return 'force_completed';
        }

        // Logic 2: Alur Transfer
        if ($method === 'transfer') {
            if ($tStatus === 'unpaid') return 'waiting_payment';
            if ($tStatus === 'pending') return 'waiting_verification';
            if ($tStatus === 'rejected') return 'payment_rejected';
        }

        // Logic 3: Status Terjadwal
        if ($bStatus === 'CONFIRMED') return 'confirmed';
        
        return strtolower($rawStatus);
    }
}
