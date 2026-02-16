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
        'image',
    ];

    public function instructors()
    {
        return $this->hasMany(Instructor::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
