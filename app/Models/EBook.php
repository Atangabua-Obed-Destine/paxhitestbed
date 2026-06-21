<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;

class EBook extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'source', 'openlibrary_id', 'gutenberg_id', 'google_books_id', 'archive_id', 'isbn', 'isbn_13', 'title', 'subtitle',
        'authors', 'publisher', 'publish_date', 'number_of_pages', 'description',
        'cover_image', 'cover_image_large', 'category_id', 'subjects', 'language',
        'file_path', 'file_type', 'file_size', 'preview_link', 'read_online_link', 'info_link',
        'is_downloadable', 'views_count', 'downloads_count', 'favorites_count',
        'rating_avg', 'rating_count', 'featured', 'status', 'uploaded_by'
    ];
    
    protected $casts = [
        'authors' => 'array',
        'subjects' => 'array',
        'is_downloadable' => 'boolean',
        'featured' => 'boolean',
        'status' => 'boolean',
    ];
    
    // Relationships
    public function category()
    {
        return $this->belongsTo(EBookCategory::class, 'category_id');
    }
    
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    
    public function readings()
    {
        return $this->hasMany(EBookReading::class, 'e_book_id');
    }
    
    public function favorites()
    {
        return $this->hasMany(EBookFavorite::class, 'e_book_id');
    }
    
    public function reviews()
    {
        return $this->hasMany(EBookReview::class, 'e_book_id');
    }
    
    // Helper methods
    public function isFavorited($userId)
    {
        return $this->favorites()->where('user_id', $userId)->exists();
    }
    
    public function getUserReading($userId)
    {
        return $this->readings()->where('user_id', $userId)->first();
    }
    
    public function incrementViews()
    {
        $this->increment('views_count');
    }
    
    public function incrementDownloads()
    {
        $this->increment('downloads_count');
    }
    
    public function getAuthorsListAttribute()
    {
        if (is_array($this->authors)) {
            return implode(', ', $this->authors);
        }
        return $this->authors ?? 'Unknown';
    }
}
