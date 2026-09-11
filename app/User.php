<?php

namespace App;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\Auditable;

class User extends Authenticatable
{
    use Notifiable, HasRoles, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'staff_id', 'department_id', 'designation_id', 'first_name', 'last_name', 'father_name', 'mother_name', 'email', 'password', 'password_text', 'gender', 'dob', 'joining_date', 'ending_date', 'phone', 'emergency_phone', 'religion', 'caste', 'mother_tongue', 'marital_status', 'blood_group', 'nationality', 'national_id', 'passport_no', 'country', 'present_province', 'present_district', 'present_village', 'present_address', 'permanent_province', 'permanent_district', 'permanent_village', 'permanent_address', 'education_level', 'graduation_academy', 'year_of_graduation', 'graduation_field', 'experience', 'note', 'basic_salary', 'contract_type', 'work_shift', 'salary_type', 'epf_no', 'bank_account_name', 'bank_account_no', 'bank_name', 'ifsc_code', 'bank_brach', 'tin_no', 'photo', 'signature', 'resume', 'joining_letter', 'is_admin', 'login', 'status', 'created_by', 'updated_by', 'blocked_at', 'block_reason', 'blocked_by', 'failed_login_attempts', 'two_factor_enabled', 'two_factor_enabled_at', 'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'blocked_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'two_factor_enabled_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];


    /**
     * Computed validity period for the staff ID card, e.g. "2026-2029".
     * Single source of truth used by every ID-card render site
     * (print, download, multi-print, ZIP generation, verify page).
     * Start year is the staff member's joining year (falls back to the
     * current year when no joining_date is set); end year is the ending
     * year (falls back to start + 4 when no ending_date is set).
     */
    public function getIdCardValidityAttribute(): string
    {
        $start = $this->joining_date ? \Carbon\Carbon::parse($this->joining_date)->year : (int) date('Y');
        $end = $this->ending_date ? \Carbon\Carbon::parse($this->ending_date)->year : ($start + 4);

        return $start . '-' . $end;
    }

    public function department()
    {
        return $this->belongsTo('App\Models\Department', 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo('App\Models\Designation', 'designation_id');
    }

    public function presentProvince()
    {
        return $this->belongsTo('App\Models\Province', 'present_province');
    }

    public function presentDistrict()
    {
        return $this->belongsTo('App\Models\District', 'present_district');
    }

    public function permanentProvince()
    {
        return $this->belongsTo('App\Models\Province', 'permanent_province');
    }

    public function permanentDistrict()
    {
        return $this->belongsTo('App\Models\District', 'permanent_district');
    }

    public function workShift()
    {
        return $this->belongsTo('App\Models\WorkShiftType', 'work_shift', 'id');
    }

    public function programs()
    {
        return $this->belongsToMany('App\Models\Program', 'user_program', 'user_id', 'program_id');
    }

    public function classes()
    {
        return $this->hasMany('App\Models\ClassRoutine', 'teacher_id', 'id');
    }

    public function attendances()
    {
        return $this->hasMany('App\Models\StaffAttendance', 'user_id', 'id');
    }

    public function payrolls()
    {
        return $this->hasMany('App\Models\Payroll', 'user_id', 'id');
    }

    public function leaves()
    {
        return $this->hasMany('App\Models\Leave', 'user_id', 'id');
    }

    public function leaveReviews()
    {
        return $this->hasMany('App\Models\Leave', 'review_by', 'id');
    }

    public function examRoutines()
    {
        return $this->belongsToMany('App\Models\ExamRoutine', 'exam_routine_user', 'user_id', 'exam_routine_id');
    }

    public function assignments()
    {
        return $this->hasMany('App\Models\Assignment', 'assign_by', 'id');
    }

    // Polymorphic relations
    public function documents()
    {
        return $this->morphToMany('App\Models\Document', 'docable');
    }

    public function contents()
    {
        return $this->morphToMany('App\Models\Content', 'contentable');
    }

    public function notices()
    {
        return $this->morphToMany('App\Models\Notice', 'noticeable');
    }

    public function member()
    {
        return $this->morphOne('App\Models\LibraryMember', 'memberable');
    }

    public function hostelRoom()
    {
        return $this->morphOne('App\Models\HostelMember', 'hostelable');
    }

    public function transport()
    {
        return $this->morphOne('App\Models\TransportMember', 'transportable');
    }

    public function notes()
    {
        return $this->morphMany('App\Models\Note', 'noteable');
    }

    public function roles()
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles', 'model_id', 'role_id', 'id', 'id');
    }
    
    /**
     * Get the staff assignments for this user
     */
    public function staffAssignments()
    {
        return $this->hasMany('App\Models\StaffAssignment', 'user_id');
    }

    public function transactions()
    {
        return $this->morphMany('App\Models\Transaction', 'transactionable');
    }

    /**
     * Get the tax exemptions for this staff member.
     */
    public function taxExemptions()
    {
        return $this->hasMany('App\Models\StaffTaxExemption', 'user_id');
    }

    /**
     * Get the tax settings this staff is exempt from.
     */
    public function exemptTaxes()
    {
        return $this->belongsToMany('App\Models\TaxSetting', 'staff_tax_exemptions', 'user_id', 'tax_setting_id')
                    ->withPivot('reason', 'custom_percentage', 'custom_fixed_amount', 'expires_at')
                    ->withTimestamps();
    }

    /**
     * Get the bank accounts for this staff member.
     */
    public function bankAccounts()
    {
        return $this->hasMany('App\Models\StaffBankAccount', 'user_id');
    }

    /**
     * Get the user who blocked this user.
     */
    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Check if user is currently blocked.
     */
    public function isBlocked(): bool
    {
        return !is_null($this->blocked_at);
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Generate staff ID in format: PAX00001, PAX00002, etc.
     *
     * @return string
     */
    public static function generateStaffId()
    {
        try {
            // The school's own code, from Settings → Academy Code. Both the
            // search and the offset below are taken from its length: this read
            // the digits after character 3, which is only right while the code
            // happens to be three letters long.
            $prefix = \App\Models\Setting::matriculePrefix();

            $lastStaff = self::where('staff_id', 'LIKE', $prefix . '%')
                            ->orderBy('staff_id', 'desc')
                            ->first();

            if ($lastStaff) {
                // Extract the numeric part and increment
                $lastNumber = (int) substr($lastStaff->staff_id, strlen($prefix));
                $newNumber = $lastNumber + 1;
            } else {
                // First staff member
                $newNumber = 1;
            }

            // Format with leading zeros (5 digits)
            $staffId = $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

            return $staffId;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Staff ID Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
