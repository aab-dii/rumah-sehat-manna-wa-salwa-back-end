<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_minutes',
        'image_url',
    ];

    protected $appends = ['full_image_url'];

    public function getFullImageUrlAttribute()
    {
        return $this->image_url ? url('storage/' . $this->image_url) : null;
    }
}
