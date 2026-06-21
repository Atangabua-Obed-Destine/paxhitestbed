<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;

class EBookReading extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id', 'student_id', 'e_book_id', 'current_page', 'total_pages',
        'progress_percentage', 'time_spent', 'started_at',
        'last_read_at', 'completed_at'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'last_read_at' => 'datetime',
        'completed_at' => 'datetime',
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
    
    // Helper methods
    public function updateProgress($currentPage, $totalPages = null)
    {
        $this->current_page = $currentPage;
        if ($totalPages) {
            $this->total_pages = $totalPages;
        }
        
        if ($this->total_pages > 0) {
            $this->progress_percentage = round(($currentPage / $this->total_pages) * 100);
        }
        
        $this->last_read_at = now();
        
        if ($this->progress_percentage >= 100) {
            $this->completed_at = now();
        }
        
        $this->save();
    }
}
