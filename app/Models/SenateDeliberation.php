<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SenateDeliberation extends Model
{
    use Auditable;

    // Status constants
    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_DEFERRED    = 'deferred';

    // Decision constants
    public const DECISION_PENDING                = 'pending';
    public const DECISION_APPROVED               = 'approved';
    public const DECISION_APPROVED_WITH_CONDITIONS = 'approved_with_conditions';
    public const DECISION_DEFERRED               = 'deferred';
    public const DECISION_REJECTED               = 'rejected';

    protected $table = 'senate_deliberations';

    protected $fillable = [
        'session_id',
        'semester_id',
        'meeting_number',
        'meeting_date',
        'venue',
        'chairperson',
        'registrar',
        'status',
        'overall_decision',
        'remarks',
        'conditions',
        'action_items',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
    ];

    /* ─── Relationships ─── */

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(SenateDeliberationProgram::class);
    }

    public function academicStandings(): HasMany
    {
        return $this->hasMany(AcademicStanding::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(SenateSignature::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SenateDeliberationLog::class);
    }

    /* ─── Scopes ─── */

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForSemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /* ─── Helpers ─── */

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED   => 'Completed',
            self::STATUS_DEFERRED    => 'Deferred',
        ];
    }

    public static function decisionLabels(): array
    {
        return [
            self::DECISION_PENDING                => 'Pending',
            self::DECISION_APPROVED               => 'Approved',
            self::DECISION_APPROVED_WITH_CONDITIONS => 'Approved with Conditions',
            self::DECISION_DEFERRED               => 'Deferred',
            self::DECISION_REJECTED               => 'Rejected',
        ];
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING     => 'bg-secondary',
            self::STATUS_IN_PROGRESS => 'bg-info',
            self::STATUS_COMPLETED   => 'bg-success',
            self::STATUS_DEFERRED    => 'bg-warning',
            default                  => 'bg-secondary',
        };
    }

    public static function decisionBadgeClass(string $decision): string
    {
        return match ($decision) {
            self::DECISION_APPROVED               => 'bg-success',
            self::DECISION_APPROVED_WITH_CONDITIONS => 'bg-warning text-dark',
            self::DECISION_DEFERRED               => 'bg-info',
            self::DECISION_REJECTED               => 'bg-danger',
            default                               => 'bg-secondary',
        };
    }

    /**
     * Generate a sequential meeting number.
     */
    public static function generateMeetingNumber(int $sessionId, int $semesterId): string
    {
        $session  = Session::find($sessionId);
        $semester = Semester::find($semesterId);

        $count = self::where('session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->count();

        $num = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return 'SEN/' . ($session->session_year ?? 'XXXX') . '/SEM' . ($semester->semester_number ?? 'X') . '/' . $num;
    }
}
