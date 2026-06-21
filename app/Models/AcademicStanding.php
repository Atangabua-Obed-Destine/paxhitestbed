<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicStanding extends Model
{
    use Auditable;

    // Standing constants — GPA thresholds
    public const STANDING_DEANS_LIST            = 'deans_list';
    public const STANDING_GOOD                  = 'good_standing';
    public const STANDING_WARNING               = 'academic_warning';
    public const STANDING_PROBATION             = 'academic_probation';
    public const STANDING_RECOMMENDED_DISMISSAL = 'recommended_dismissal';

    // GPA threshold boundaries
    public const GPA_DEANS_LIST = 3.50;
    public const GPA_GOOD       = 2.00;
    public const GPA_WARNING    = 1.50; // 1.50 – 1.99
    public const GPA_PROBATION  = 1.00; // 1.00 – 1.49
    // Below 1.00 = recommended dismissal

    protected $table = 'academic_standings';

    protected $fillable = [
        'student_id',
        'student_enroll_id',
        'session_id',
        'semester_id',
        'program_id',
        'faculty_id',
        'senate_deliberation_id',
        'gpa',
        'cgpa',
        'total_credits_registered',
        'total_credits_earned',
        'courses_registered',
        'courses_passed',
        'courses_failed',
        'standing',
        'previous_standing',
        'senate_remarks',
        'conditions',
        'classified_by',
        'classified_at',
    ];

    protected $casts = [
        'gpa'           => 'decimal:2',
        'cgpa'          => 'decimal:2',
        'classified_at' => 'datetime',
    ];

    /* ─── Relationships ─── */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function deliberation(): BelongsTo
    {
        return $this->belongsTo(SenateDeliberation::class, 'senate_deliberation_id');
    }

    public function classifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

    /* ─── Scopes ─── */

    public function scopeByStanding($query, string $standing)
    {
        return $query->where('standing', $standing);
    }

    public function scopeForProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeFlagged($query)
    {
        return $query->whereIn('standing', [
            self::STANDING_WARNING,
            self::STANDING_PROBATION,
            self::STANDING_RECOMMENDED_DISMISSAL,
        ]);
    }

    /* ─── Classification Logic ─── */

    /**
     * Classify GPA into an academic standing.
     */
    public static function classifyGpa(float $gpa): string
    {
        if ($gpa >= self::GPA_DEANS_LIST) {
            return self::STANDING_DEANS_LIST;
        }
        if ($gpa >= self::GPA_GOOD) {
            return self::STANDING_GOOD;
        }
        if ($gpa >= self::GPA_WARNING) {
            return self::STANDING_WARNING;
        }
        if ($gpa >= self::GPA_PROBATION) {
            return self::STANDING_PROBATION;
        }

        return self::STANDING_RECOMMENDED_DISMISSAL;
    }

    /**
     * All standing labels for display.
     */
    public static function standingLabels(): array
    {
        return [
            self::STANDING_DEANS_LIST            => "Dean's List",
            self::STANDING_GOOD                  => 'Good Standing',
            self::STANDING_WARNING               => 'Academic Warning',
            self::STANDING_PROBATION             => 'Academic Probation',
            self::STANDING_RECOMMENDED_DISMISSAL => 'Recommended Dismissal',
        ];
    }

    /**
     * Bootstrap badge CSS class for each standing.
     */
    public static function standingBadgeClass(string $standing): string
    {
        return match ($standing) {
            self::STANDING_DEANS_LIST            => 'bg-primary',
            self::STANDING_GOOD                  => 'bg-success',
            self::STANDING_WARNING               => 'bg-warning text-dark',
            self::STANDING_PROBATION             => 'bg-orange',
            self::STANDING_RECOMMENDED_DISMISSAL => 'bg-danger',
            default                              => 'bg-secondary',
        };
    }

    /**
     * Font-awesome icon for each standing.
     */
    public static function standingIcon(string $standing): string
    {
        return match ($standing) {
            self::STANDING_DEANS_LIST            => 'fas fa-trophy',
            self::STANDING_GOOD                  => 'fas fa-check-circle',
            self::STANDING_WARNING               => 'fas fa-exclamation-triangle',
            self::STANDING_PROBATION             => 'fas fa-exclamation-circle',
            self::STANDING_RECOMMENDED_DISMISSAL => 'fas fa-times-circle',
            default                              => 'fas fa-question-circle',
        };
    }

    public function getStandingLabelAttribute(): string
    {
        return self::standingLabels()[$this->standing] ?? ucfirst(str_replace('_', ' ', $this->standing));
    }

    public function getStandingBadgeAttribute(): string
    {
        return self::standingBadgeClass($this->standing);
    }
}
