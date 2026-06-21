<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenateDeliberationLog extends Model
{
    protected $table = 'senate_deliberation_logs';

    protected $fillable = [
        'senate_deliberation_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'meta',
        'performed_by',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ─── Relationships ─── */

    public function deliberation(): BelongsTo
    {
        return $this->belongsTo(SenateDeliberation::class, 'senate_deliberation_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /* ─── Action Constants ─── */

    public const ACTION_CREATED            = 'created';
    public const ACTION_STATUS_CHANGED     = 'status_changed';
    public const ACTION_DECISION_MADE      = 'decision_made';
    public const ACTION_PROGRAM_REVIEWED   = 'program_reviewed';
    public const ACTION_STUDENT_FLAGGED    = 'student_flagged';
    public const ACTION_STANDING_CLASSIFIED = 'standing_classified';
    public const ACTION_SIGNATURE_ADDED    = 'signature_added';
    public const ACTION_EXPORTED           = 'exported';

    public static function actionLabels(): array
    {
        return [
            self::ACTION_CREATED            => 'Deliberation Created',
            self::ACTION_STATUS_CHANGED     => 'Status Changed',
            self::ACTION_DECISION_MADE      => 'Decision Made',
            self::ACTION_PROGRAM_REVIEWED   => 'Program Reviewed',
            self::ACTION_STUDENT_FLAGGED    => 'Student Flagged',
            self::ACTION_STANDING_CLASSIFIED => 'Standing Classified',
            self::ACTION_SIGNATURE_ADDED    => 'Signature Added',
            self::ACTION_EXPORTED           => 'Report Exported',
        ];
    }
}
