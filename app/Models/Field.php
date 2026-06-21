<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Field extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug', 'status',
    ];

    // Get Record
    public static function field($slug)
    {
        $field = Field::where('slug', $slug)->first();

        return $field;
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $slug = $this->slug ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Field {$event}: {$slug}, Status: {$status}";
    }
}
