<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Session extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'start_date', 'end_date', 'current', 'status', 'applications_open',
        'progression_open', 'progression_note',
    ];

    protected $casts = [
        'applications_open' => 'boolean',
        'progression_open' => 'boolean',
    ];

    /**
     * May a student move into this academic year?
     *
     * Closed while the year's courses are still being set up: a student
     * progressing into a year with no courses lands in an empty semester, and
     * the portal reads as though their programme were over.
     */
    public function allowsProgression(): bool
    {
        return (bool) $this->progression_open;
    }

    /**
     * Why it is closed, in words a student can act on.
     *
     * The school's own note where it has set one, so it can say what is
     * actually happening and roughly when; a plain fallback otherwise, so the
     * student is never shown a closed door with nothing written on it.
     */
    public function progressionNote(): string
    {
        $note = trim((string) $this->progression_note);

        if ($note !== '') {
            return $note;
        }

        return __('The courses for this academic year are still being set up. Progression will open once that is done.');
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_session', 'session_id', 'program_id');
    }

    public function studentEnrolls()
    {
        return $this->hasMany(StudentEnroll::class, 'session_id');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoutine::class, 'session_id', 'id');
    }

    public function contents()
    {
        return $this->hasMany(Content::class, 'session_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $startDate = $this->start_date ?? 'N/A';
        $endDate = $this->end_date ?? 'N/A';
        $current = $this->current == '1' ? 'Yes' : 'No';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Session {$event}: {$title} ({$startDate} - {$endDate}), Current: {$current}, Status: {$status}";
    }
}
