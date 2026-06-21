<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Setting extends Model
{
    use Auditable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'academy_code', 'meta_title', 'meta_description', 'meta_keywords', 'logo_path', 'favicon_path', 'phone', 'email', 'fax', 'address', 'language', 'date_format', 'time_format', 'week_start', 'time_zone', 'currency', 'currency_symbol', 'decimal_place', 'copyright_text', 'status', 'staff_attendance_min_hours',
    ];

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $academyCode = $this->academy_code ?? 'N/A';
        
        return "Setting {$event}: {$title} (Code: {$academyCode})";
    }
}
