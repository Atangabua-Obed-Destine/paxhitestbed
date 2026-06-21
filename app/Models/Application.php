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
        'passport_no',
        'passport_issue_date',
        'passport_issue_country',
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
        'passport_issue_date' => 'date',
        'declaration_signed_date' => 'date',
        'is_catholic_baptised' => 'boolean',
        'is_confirmed' => 'boolean',
        'has_first_communion' => 'boolean',
        'studied_in_english' => 'boolean',
    ];

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
}
