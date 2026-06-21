<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationBoardSignature extends Model
{
    protected $fillable = [
        'board_review_id',
        'name',
        'position',
        'signature_path',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(ApplicationBoardReview::class, 'board_review_id');
    }
}
