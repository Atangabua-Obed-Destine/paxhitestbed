<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EBookCategory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'color', 'sort_order', 'status'
    ];
    
    protected $casts = [
        'status' => 'boolean',
    ];
    
    // Relationships
    public function books()
    {
        return $this->hasMany(EBook::class, 'category_id');
    }
    
    public function activeBooks()
    {
        return $this->hasMany(EBook::class, 'category_id')->where('status', 1);
    }
}
