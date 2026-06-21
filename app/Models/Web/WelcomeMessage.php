<?php

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Language;

class WelcomeMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id',
        'title',
        'message',
        'image',
        'designation',
        'sort_order',
        'status',
    ];

    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('uploads/welcome-message/' . $this->image);
        }
        return asset('web/img/default-avatar.png');
    }
}
