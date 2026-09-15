<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Application extends Authenticatable
{
    use Auditable, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'registration_no',
        'applicant_id',
        'degree_type_id',
        'session_id',
        'batch_id',
        'program_id',
        'apply_date',
        'academic_year',
        'first_name',
        'last_name',
        'other_names',
        'father_name',
        'mother_name',
        'father_occupation',
        'mother_occupation',
        'father_photo',
        'mother_photo',
        'country',
        'present_province',
        'present_district',
        'present_village',
        'present_address',
        'permanent_province',
        'permanent_district',
        'permanent_village',
        'permanent_address',
        'postal_address_line1',
        'postal_address_line2',
        'gender',
        'dob',
        'birth_city',
        'birth_division',
        'birth_region',
        'birth_country',
        'email',
        'phone',
        'alternate_phone',
        'emergency_phone',
        'religion',
        'is_catholic_baptised',
        'is_confirmed',
        'has_first_communion',
        'caste',
        'mother_tongue',
        'studied_in_english',
        'instruction_language_secondary',
        'marital_status',
        'blood_group',
        'nationality',
        'national_id',
        'national_id_issue_date',
        'national_id_issue_place',
        'national_id_expiry_date',
        'passport_no',
        'passport_issue_date',
        'passport_issue_country',
        'passport_expiry_date',
        'school_name',
        'school_exam_id',
        'school_graduation_field',
        'school_graduation_year',
        'school_graduation_point',
        'school_transcript',
        'school_certificate',
        'collage_name',
        'collage_exam_id',
        'collage_graduation_field',
        'collage_graduation_year',
        'collage_graduation_point',
        'collage_transcript',
        'collage_certificate',
        'photo',
        'signature',
        'declaration_name',
        'declaration_signed_date',
        'registration_fee_bank',
        'registration_fee_reference',
        'first_program_choice_id',
        'second_program_choice_id',
        'third_program_choice_id',
        'fee_amount',
        'pay_status',
        'payment_method',
        'admission_fee_id',
        'status',
        'stage',
        'progress',
        'draft_progress',
        'draft_last_saved_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'apply_date' => 'date',
        'dob' => 'date',
        'email_verified_at' => 'datetime',
        'portal_last_login_at' => 'datetime',
        'completed_at' => 'datetime',
        'decision_at' => 'datetime',
        'draft_last_saved_at' => 'datetime',
        'portal_meta' => 'array',
        'national_id_issue_date' => 'date',
        'national_id_expiry_date' => 'date',
        'passport_issue_date' => 'date',
        'passport_expiry_date' => 'date',
        'declaration_signed_date' => 'date',
        'is_catholic_baptised' => 'boolean',
        'is_confirmed' => 'boolean',
        'has_first_communion' => 'boolean',
        'studied_in_english' => 'boolean',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function degreeType()
    {
        return $this->belongsTo(DegreeType::class, 'degree_type_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function preferredProgramFirst()
    {
        return $this->belongsTo(Program::class, 'first_program_choice_id');
    }

    public function preferredProgramSecond()
    {
        return $this->belongsTo(Program::class, 'second_program_choice_id');
    }

    public function preferredProgramThird()
    {
        return $this->belongsTo(Program::class, 'third_program_choice_id');
    }

    public function guardians()
    {
        return $this->hasMany(ApplicationGuardian::class);
    }

    public function academicHistories()
    {
        return $this->hasMany(ApplicationAcademicHistory::class)->orderBy('display_order');
    }

    public function languages()
    {
        return $this->hasMany(ApplicationLanguage::class);
    }

    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function boardReview()
    {
        return $this->hasOne(ApplicationBoardReview::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /*
    public function presentProvince()
    {
        return $this->belongsTo(Province::class, 'present_province');
    }

    public function presentDistrict()
    {
        return $this->belongsTo(District::class, 'present_district');
    }

    public function permanentProvince()
    {
        return $this->belongsTo(Province::class, 'permanent_province');
    }

    public function permanentDistrict()
    {
        return $this->belongsTo(District::class, 'permanent_district');
    }
    */

    public function admissionFee()
    {
        return $this->belongsTo(Fee::class, 'admission_fee_id');
    }

    public function religionDetail()
    {
        return $this->belongsTo(Religion::class, 'religion');
    }

    public function statusUpdates()
    {
        return $this->hasMany(ApplicationStatusUpdate::class)->latest();
    }

    public function recordStatus(string $stage, ?string $note = null, ?int $status = null, ?string $title = null, ?int $createdBy = null, ?string $createdByType = null, bool $visible = true): ApplicationStatusUpdate
    {
        $update = $this->statusUpdates()->create([
            'stage' => $stage,
            'status' => $status,
            'title' => $title,
            'note' => $note,
            'is_visible_to_applicant' => $visible,
            'created_by' => $createdBy,
            'created_by_type' => $createdByType,
        ]);

        $this->stage = $stage;
        if (!is_null($status)) {
            $this->status = $status;
        }
        $this->progress = static::stageProgressMap()[$stage] ?? $this->progress;
        if ($stage === 'decision_approved') {
            $this->decision_at = now();
            $this->completed_at = now();
        }
        if ($stage === 'decision_rejected') {
            $this->decision_at = now();
        }
        $this->save();

        return $update;
    }

    public function getProgressLabelAttribute(): string
    {
        return static::stageLabelMap()[$this->stage] ?? ucfirst(str_replace('_', ' ', $this->stage));
    }

    public static function stageLabelMap(): array
    {
        return [
            'draft' => __('application_stage.draft'),
            'submitted' => __('application_stage.submitted'),
            'under_review' => __('application_stage.under_review'),
            'documents_required' => __('application_stage.documents_required'),
            'interview' => __('application_stage.interview'),
            'decision_pending' => __('application_stage.decision_pending'),
            'decision_approved' => __('application_stage.decision_approved'),
            'decision_rejected' => __('application_stage.decision_rejected'),
        ];
    }

    public static function stageProgressMap(): array
    {
        return [
            'draft' => 0,
            'submitted' => 10,
            'under_review' => 30,
            'documents_required' => 45,
            'interview' => 60,
            'decision_pending' => 75,
            'decision_approved' => 100,
            'decision_rejected' => 100,
        ];
    }

    /**
     * The approvals an application must collect before a student record can be
     * created from it, in the order they are given.
     *
     * This replaces a printed block on the application form — received by,
     * documents verified by, the decision, the registrar's signature — that was
     * filled in by hand and never came back into the system. Each step now
     * names the permission that authorises it, so the school decides on the
     * Roles screen who signs where.
     *
     *  - can_reject is false for the first two on purpose. A document that is
     *    missing or unreadable is a Return, not a rejection of the applicant.
     *  - visible says whether the applicant is shown the step on their own
     *    timeline. The board's deliberation is not; that a decision is pending
     *    is.
     *  - status is the application's decision status the step settles, or null
     *    to leave it alone. Only the final approval marks an application
     *    approved, and that matters beyond this screen: the acceptance letter
     *    on the applications list and the "Approved" filter both read it.
     */
    public static function approvalStepMap(): array
    {
        return [
            'received' => [
                'sequence' => 1,
                'permission' => 'application-approve-receipt',
                'stage' => 'under_review',
                'can_reject' => false,
                'status' => null,
                'visible' => true,
            ],
            'documents' => [
                'sequence' => 2,
                'permission' => 'application-approve-documents',
                'stage' => 'under_review',
                'can_reject' => false,
                'status' => null,
                'visible' => true,
            ],
            'board' => [
                'sequence' => 3,
                'permission' => 'application-approve-board',
                'stage' => 'decision_pending',
                'can_reject' => true,
                'status' => null,
                'visible' => false,
            ],
            'final' => [
                'sequence' => 4,
                'permission' => 'application-approve-final',
                'stage' => 'decision_approved',
                'can_reject' => true,
                'status' => 2,
                'visible' => true,
            ],
        ];
    }

    /** The step keys in the order they are approved. */
    public static function approvalStepKeys(): array
    {
        return array_keys(static::approvalStepMap());
    }

    /** The last step. Nothing can be created from an application until it is given. */
    public static function finalApprovalStep(): string
    {
        $keys = static::approvalStepKeys();

        return end($keys);
    }

    public static function approvalStepTitle(string $step): string
    {
        return __('application_approval.' . $step);
    }

    /**
     * Every decision ever taken, oldest first. The table is append-only, so
     * this is the whole history — a returned step keeps the approval it had
     * before it was returned, the way a paper file keeps its crossings-out.
     */
    public function approvals()
    {
        return $this->hasMany(ApplicationApproval::class)->orderBy('decided_at')->orderBy('id');
    }

    /**
     * The decisions that still count.
     *
     * A return clears the step it goes back to and every step after it — but
     * not the steps before it. Sending an application back because a transcript
     * is unreadable does not unsay that the application was received; that
     * signature stands, and asking for it again would be asking somebody to
     * sign the same thing twice.
     *
     * So: everything decided since the last return counts, plus anything
     * decided before it that sits earlier in the chain than the step it was
     * returned to. A refusal is different — it clears everything, because the
     * application is finished until somebody reopens it.
     */
    public function liveApprovals()
    {
        $approvals = $this->relationLoaded('approvals')
            ? $this->approvals
            : $this->approvals()->get();

        $reset = $approvals->last(fn ($approval) => in_array($approval->decision, ['rejected', 'returned'], true));

        if (!$reset) {
            return $approvals;
        }

        $after = $approvals->filter(fn ($approval) => $approval->id > $reset->id);

        if ($reset->decision === 'rejected') {
            return $after->values();
        }

        $map = static::approvalStepMap();
        $clearedFrom = $map[$reset->returned_to_step]['sequence'] ?? 1;

        $survived = $approvals->filter(function ($approval) use ($reset, $map, $clearedFrom) {
            if ($approval->id > $reset->id) {
                return false;
            }

            $sequence = $map[$approval->step]['sequence'] ?? PHP_INT_MAX;

            return $sequence < $clearedFrom;
        });

        return $survived->concat($after)->values();
    }

    /** Has this step been approved, and does that approval still stand? */
    public function hasApproved(string $step): bool
    {
        return $this->liveApprovals()
            ->contains(fn ($approval) => $approval->step === $step && $approval->decision === 'approved');
    }

    /**
     * The step waiting to be decided, or null when there is nothing left to
     * decide. A rejected application has no current step until it is returned.
     */
    public function currentApprovalStep(): ?string
    {
        if ($this->isApprovalRejected() || $this->isFullyApproved()) {
            return null;
        }

        foreach (static::approvalStepKeys() as $step) {
            if (!$this->hasApproved($step)) {
                return $step;
            }
        }

        return null;
    }

    public function isApprovalRejected(): bool
    {
        $approvals = $this->relationLoaded('approvals')
            ? $this->approvals
            : $this->approvals()->get();

        return (string) optional($approvals->last())->decision === 'rejected';
    }

    /**
     * The application is approved. This is what creating a student record is
     * gated on, and it is checked on the server rather than by hiding a button.
     *
     * The test is the FINAL approval, not all four. Going forward that amounts
     * to the same thing — the service refuses a step out of turn, so the final
     * approval cannot be given until the ones before it have been. It differs
     * for the applications that were already approved when this was built:
     * they carry one backfilled final approval saying exactly that, rather than
     * four fabricated signatures for meetings that never happened. Anchoring on
     * the last step keeps them convertible and keeps the record honest.
     */
    public function isFullyApproved(): bool
    {
        if ($this->isApprovalRejected()) {
            return false;
        }

        return $this->hasApproved(static::finalApprovalStep());
    }

    /**
     * Where the application stands in the approval chain, in one line — for the
     * applications list, where an admin needs to see at a glance which
     * applications are waiting, and on whom.
     *
     * Load the approvals relation before calling this over a list; every check
     * here reads the loaded collection rather than querying again.
     *
     * @return array{state: string, label: string, tone: string, step: ?string, done: int, total: int, steps: array, detail: string}
     */
    public function approvalSummary(): array
    {
        $keys = static::approvalStepKeys();
        $total = count($keys);

        $approvals = $this->relationLoaded('approvals') ? $this->approvals : $this->approvals()->get();
        $live = $this->liveApprovals();

        // A refusal clears every live approval, so the tracker would show a
        // board refusal with nothing approved before it — which cannot happen.
        // For a refused application, show the chain as it stood the moment
        // before it was refused.
        if ($this->isApprovalRejected()) {
            $refusal = $approvals->last();
            $before = clone $this;
            $before->setRelation('approvals', $approvals->filter(fn ($row) => $row->id < $refusal->id)->values());
            $live = $before->liveApprovals();
        }

        // Per step: approved or not, and who signed, for the tracker and the
        // tooltip that names them.
        $steps = [];
        $done = 0;

        foreach ($keys as $key) {
            $signed = $live->last(fn ($row) => $row->step === $key && $row->decision === 'approved');

            if ($signed) {
                $done++;
            }

            $steps[$key] = [
                'title' => static::approvalStepTitle($key),
                'approved' => $signed !== null,
                'by' => $signed ? $signed->signatory() : null,
                'at' => $signed ? optional($signed->decided_at)->format('d M Y') : null,
            ];
        }

        $detail = collect($steps)->map(function ($step) {
            return $step['approved']
                ? $step['title'] . ': ' . $step['by'] . ($step['at'] ? ' (' . $step['at'] . ')' : '')
                : $step['title'] . ': ' . __('not yet');
        })->implode("\n");

        $summary = fn (string $state, string $label, string $tone, ?string $step = null) => [
            'state' => $state,
            'label' => $label,
            'tone' => $tone,
            'step' => $step,
            'done' => $done,
            'total' => $total,
            'steps' => $steps,
            'detail' => $detail,
        ];

        // A draft has not been submitted; there is nothing to approve yet, and
        // "waiting on Application received" would read as though it were late.
        if ($this->stage === 'draft' && $approvals->isEmpty()) {
            return $summary('not_submitted', __('Not submitted'), 'secondary');
        }

        if ($this->isApprovalRejected()) {
            $refusal = $approvals->last();

            return $summary('refused', __('Refused at :step', ['step' => static::approvalStepTitle($refusal->step)]), 'danger', $refusal->step);
        }

        if ($this->isFullyApproved()) {
            $final = $live->last(fn ($row) => $row->step === static::finalApprovalStep() && $row->decision === 'approved');

            // The applications approved before this flow existed carry a single
            // backfilled final approval with nobody's name on it. Say so, rather
            // than showing them as though four people had signed.
            if ($final && $final->decided_by === null) {
                return $summary('approved_before_flow', __('Approved before approval flow'), 'success');
            }

            return $summary('approved', __('Approved'), 'success');
        }

        $current = $this->currentApprovalStep();
        $last = $approvals->last();

        if ($last && $last->decision === 'returned') {
            return $summary(
                'returned',
                __('Returned for :step', ['step' => static::approvalStepTitle($last->returned_to_step ?? $current)]),
                'warning',
                $current
            );
        }

        return $summary(
            'waiting',
            __('Waiting on :step', ['step' => static::approvalStepTitle($current)]),
            'info',
            $current
        );
    }
}
