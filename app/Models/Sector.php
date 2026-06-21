<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'description', 'status'
    ];

    public function faculties()
    {
        return $this->hasMany(Faculty::class, 'sector_id', 'id');
    }
}
