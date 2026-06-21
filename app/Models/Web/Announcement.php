<?php

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Language;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id', 'message', 'start_date', 'end_date', 'status'
    ];

    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
    }
}
