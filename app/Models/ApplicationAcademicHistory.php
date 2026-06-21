<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationAcademicHistory extends Model
{
    protected $fillable = [
        'application_id',
        'institution_name',
        'city',
        'country',
        'instruction_language',
        'date_from',
        'date_to',
        'certificate_obtained',
        'gce_ol_detail',
        'gce_al_detail',
        'probatoire_detail',
        'baccalaureate_detail',
        'notes',
        'display_order',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'display_order' => 'integer',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
