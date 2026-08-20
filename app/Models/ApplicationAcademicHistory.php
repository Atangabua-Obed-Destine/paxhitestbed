<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationAcademicHistory extends Model
{
    protected $fillable = [
        'application_id',
        // Binds the row to a configured qualification card; null means the
        // applicant added this qualification themselves.
        'qualification_key',
        'institution_name',
        'awarding_body',
        'institution_same_as_awarding_body',
        'city',
        'country',
        'instruction_language',
        // Legacy: superseded by start_year / end_year. Still written by older
        // admin screens, so kept nullable rather than dropped.
        'date_from',
        'date_to',
        'start_year',
        'end_year',
        'certificate_obtained',
        'certificate_file',
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
        'start_year' => 'integer',
        'end_year' => 'integer',
        'institution_same_as_awarding_body' => 'boolean',
        'display_order' => 'integer',
    ];

    /** True when this row is one of the degree type's prescribed cards. */
    public function isPrescribed(): bool
    {
        return !empty($this->qualification_key);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
