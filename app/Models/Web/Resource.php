<?php

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Language;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id',
        'title',
        'description',
        'category',
        'icon',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'download_count',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'download_count' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Get the language that owns the resource.
     */
    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    /**
     * Scope a query to only include active resources.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get the full URL for the file.
     */
    public function getFileUrlAttribute()
    {
        return asset('uploads/resources/' . $this->file_path);
    }

    /**
     * Get icon class based on file type.
     */
    public function getFileIconAttribute()
    {
        $extension = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
        
        $icons = [
            'pdf' => 'fas fa-file-pdf text-danger',
            'doc' => 'fas fa-file-word text-primary',
            'docx' => 'fas fa-file-word text-primary',
            'xls' => 'fas fa-file-excel text-success',
            'xlsx' => 'fas fa-file-excel text-success',
            'ppt' => 'fas fa-file-powerpoint text-warning',
            'pptx' => 'fas fa-file-powerpoint text-warning',
            'zip' => 'fas fa-file-archive text-secondary',
            'rar' => 'fas fa-file-archive text-secondary',
            'jpg' => 'fas fa-file-image text-info',
            'jpeg' => 'fas fa-file-image text-info',
            'png' => 'fas fa-file-image text-info',
            'gif' => 'fas fa-file-image text-info',
        ];

        return $icons[$extension] ?? 'fas fa-file text-muted';
    }

    /**
     * Category options for dropdowns.
     */
    public static function getCategoryOptions()
    {
        return [
            'student_guide' => 'Student Guide',
            'calendarium' => 'School Calendarium',
            'academic' => 'Academic Documents',
            'forms' => 'Forms & Applications',
            'policies' => 'Policies & Regulations',
            'handbook' => 'Handbooks',
            'other' => 'Other Resources',
        ];
    }

    /**
     * Get category label.
     */
    public function getCategoryLabelAttribute()
    {
        $options = self::getCategoryOptions();
        return $options[$this->category] ?? ucfirst($this->category);
    }
}
