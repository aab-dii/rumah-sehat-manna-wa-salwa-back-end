<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firebase_uid',
        'name',
        'email',
        'password',
        'phone_number',
        'gender',
        'role',
        'job',
        'address',
        'birth_date',
        'specialization',
        'profile_photo_path',
        'photo_url',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'date' cast menyebabkan Laravel mengkonversi ke Carbon/UTC sehingga
            // tanggal bergeser -1 hari bagi user di timezone WIB (UTC+7).
            // Solusi: gunakan 'string' agar tanggal disimpan & dibaca apa adanya (YYYY-MM-DD).
            'birth_date' => 'string',
            'specialization' => 'array',
            'password' => 'hashed',
        ];
    }
    public function medicalRecords()
    {
        return $this->hasMany(TherapyRecord::class, 'patient_id');
    }

    public function therapistBookings()
    {
        return $this->hasMany(Booking::class, 'therapist_id');
    }

    protected $appends = [
        'profile_photo_url',
    ];

    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo_path) {
            return url('storage/' . $this->profile_photo_path);
        }

        return $this->photo_url;
    }
}
