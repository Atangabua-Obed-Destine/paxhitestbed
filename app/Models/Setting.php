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
    /**
     * The code every student matricule and staff id begins with.
     *
     * This used to be the literal 'PAX', written into three separate
     * generators, so every school running this system issued matricules under
     * one school's name. It is the Academy Code on Settings → General, which
     * already exists and already appears on the letterhead.
     *
     * Refused rather than guessed when it is not set: a matricule is printed on
     * documents and quoted by the student for years, and issuing one under the
     * wrong code cannot be taken back once anybody is enrolled.
     *
     * @throws \RuntimeException when no Academy Code has been set
     */
    public static function matriculePrefix(): string
    {
        $code = strtoupper(trim((string) optional(static::first())->academy_code));

        if ($code === '') {
            throw new \RuntimeException(__('Set your Academy Code in Settings before creating student or staff records. It is the start of every matricule — for example the PAX in PAX25AF001.'));
        }

        return $code;
    }

    protected $fillable = [
        'title', 'site_subtitle', 'academy_code', 'meta_title', 'meta_description', 'meta_keywords', 'logo_path', 'favicon_path', 'phone', 'email', 'fax', 'address', 'language', 'date_format', 'time_format', 'week_start', 'time_zone', 'currency', 'currency_symbol', 'decimal_place', 'copyright_text', 'status', 'staff_attendance_min_hours',
    ];

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $academyCode = $this->academy_code ?? 'N/A';
        // (kept as-is; see matriculePrefix() below for the code used by IDs)
        
        return "Setting {$event}: {$title} (Code: {$academyCode})";
    }
}
