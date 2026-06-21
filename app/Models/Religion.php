<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Religion extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'title',
        'slug',
        'is_catholic',
        'status',
    ];

    protected $casts = [
        'is_catholic' => 'boolean',
        'status' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($religion) {
            if (empty($religion->slug)) {
                $religion->slug = Str::slug($religion->title);
            }
        });

        static::updating(function ($religion) {
            if ($religion->isDirty('title')) {
                $religion->slug = Str::slug($religion->title);
            }
        });
    }
}
