<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;

class EBookReview extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id', 'student_id', 'e_book_id', 'rating', 'review', 'helpful_count', 'status'
    ];
    
    protected $casts = [
        'status' => 'boolean',
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    
    public function book()
    {
        return $this->belongsTo(EBook::class, 'e_book_id');
    }
}
