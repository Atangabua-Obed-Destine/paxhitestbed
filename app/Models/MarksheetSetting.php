<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class MarksheetSetting extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'header_left', 'header_center', 'header_right', 'body', 'footer_left', 'footer_center', 'footer_right', 'logo_left', 'logo_right', 'background', 'width', 'height', 'student_photo', 'barcode', 'resit_replaces_original', 'status',
    ];
}
