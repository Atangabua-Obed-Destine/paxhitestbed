<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\Auditable;

class Student extends Authenticatable
{
    use Notifiable, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_id', 'registration_no', 'batch_id', 'program_id', 'admission_date', 'first_name', 'last_name', 'father_name', 'mother_name', 'father_occupation', 'mother_occupation', 'father_photo', 'mother_photo', 'email', 'password', 'password_text', 'country', 'present_province', 'present_district', 'present_village', 'present_address', 'permanent_province', 'permanent_district', 'permanent_village', 'permanent_address', 'gender', 'dob', 'phone', 'emergency_phone', 'religion', 'is_catholic_baptised', 'is_confirmed', 'has_first_communion', 'caste', 'mother_tongue', 'marital_status', 'blood_group', 'nationality', 'national_id', 'passport_no', 'school_name', 'school_exam_id', 'school_graduation_field', 'school_graduation_year', 'school_graduation_point', 'school_transcript', 'school_certificate', 'collage_name', 'collage_exam_id', 'collage_graduation_field', 'collage_graduation_year', 'collage_graduation_point', 'collage_transcript', 'collage_certificate', 'photo', 'signature', 'login', 'status', 'is_transfer', 'created_by', 'updated_by', 'blocked_at', 'block_reason', 'blocked_by', 'failed_login_attempts', 'two_factor_enabled', 'two_factor_enabled_at', 'last_seen_at',
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
        'is_catholic_baptised' => 'boolean',
        'is_confirmed' => 'boolean',
        'has_first_communion' => 'boolean',
    ];

    public function religionDetail()
    {
        return $this->belongsTo(Religion::class, 'religion');
    }


    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function studentEnrolls()
    {
        return $this->hasMany(StudentEnroll::class, 'student_id');
    }

    public function enrolls()
    {
        return $this->hasMany(StudentEnroll::class, 'student_id');
    }

    public function currentEnroll()
    {
        return $this->hasOne(StudentEnroll::class, 'student_id')->ofMany([
            'id' => 'max',
        ], function ($query) {
            $query->where('status', '1');
        });
    }

    public function firstEnroll()
    {
        // return $this->hasOne(StudentEnroll::class, 'student_id')->oldest();
        return $this->hasOne(StudentEnroll::class, 'student_id')->ofMany([
            'id' => 'min',
        ]);
    }

    public function lastEnroll()
    {
        // return $this->hasOne(StudentEnroll::class, 'student_id')->latest();
        return $this->hasOne(StudentEnroll::class, 'student_id')->ofMany([
            'id' => 'max',
        ]);
    }

    public function relatives()
    {
        return $this->hasMany(StudentRelative::class, 'student_id', 'id');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class, 'student_id', 'id');
    }

    public function leaves()
    {
        return $this->hasMany(StudentLeave::class, 'student_id', 'id');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'student_id', 'id');
    }

    /*
    public function province()
    {
        return $this->belongsTo(Province::class, 'present_province');
    }

    public function district()
    {
        return $this->belongsTo(District::class, 'present_district');
    }

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

    public function statuses()
    {
        return $this->belongsToMany(StatusType::class, 'status_type_student', 'student_id', 'status_type_id');
    }

    public function studentTransfer()
    {
        return $this->hasOne(StudentTransfer::class, 'student_id');
    }

    public function transferCreadits()
    {
        return $this->hasMany(TransferCreadit::class, 'student_id');
    }


    // Polymorphic relations
    public function documents()
    {
        return $this->morphToMany(Document::class, 'docable');
    }

    public function contents()
    {
        return $this->morphToMany(Content::class, 'contentable');
    }

    public function notices()
    {
        return $this->morphToMany(Notice::class, 'noticeable');
    }

    public function member()
    {
        return $this->morphOne(LibraryMember::class, 'memberable');
    }

    public function hostelRoom()
    {
        return $this->morphOne(HostelMember::class, 'hostelable');
    }

    public function transport()
    {
        return $this->morphOne(TransportMember::class, 'transportable');
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function transcriptReleases()
    {
        return $this->hasMany(TranscriptRelease::class, 'student_id');
    }

    
    // Get Current Enroll
    public static function enroll($id)
    {
        $enroll = StudentEnroll::where('student_id', $id)
                                ->where('status', '1')
                                ->orderBy('id', 'desc')
                                ->first();

        return $enroll;
    }

    /**
     * Generate student ID in format: PAX + Last 2 digits of Batch + Faculty Matric Code + Degree Type Code + Sequential Number
     * Example: PAX25BFHND001, PAX26MGTBSC001
     *
     * @param int $facultyId
     * @param int $batchId
     * @return string
     */
    /**
     * Does this look like a matricule rather than a numeric record id?
     *
     * Code used to ask "does it start with PAX", which stopped being true the
     * moment a school set its own code — and would have stopped finding the
     * matricules already issued here. Any matricule carries letters; a record
     * id never does.
     */
    public static function looksLikeMatricule($value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && !ctype_digit($value);
    }

    /**
     * Can a matricule be issued for this faculty, batch and program — and if
     * not, what has not been set?
     *
     * A matricule is built out of three pieces of configuration: the school's
     * Academy Code, the faculty's matricule code and the batch's year. When one
     * of them is missing, generation throws, and an admin filling in a student
     * record has no way of knowing which one. So this asks the same questions
     * generateStudentId() asks, but collects the answers instead of stopping at
     * the first.
     *
     * It deliberately returns the shape a matricule will take and NOT a
     * matricule. Handing out a number before it is written down invites two
     * admins to enrol two students on the same one; the number is claimed at
     * the moment the record is saved and not a second earlier.
     *
     * @return array{ready: bool, format: ?string, problems: array<int, array{what: string, where: string}>}
     */
    public static function matriculeReadiness($facultyId, $batchId, $programId = null): array
    {
        $problems = [];
        $prefix = null;
        $batchDigits = null;
        $facultyCode = null;

        try {
            $prefix = Setting::matriculePrefix();
        } catch (\Throwable $e) {
            $problems[] = [
                'what' => __('The school\'s Academy Code has not been set.'),
                'where' => __('Settings → General → Academy Code'),
            ];
        }

        $faculty = $facultyId ? Faculty::find($facultyId) : null;

        if (!$faculty) {
            $problems[] = [
                'what' => __('No faculty has been chosen.'),
                'where' => __('Choose a programme so its faculty is known.'),
            ];
        } else {
            $facultyCode = !empty($faculty->matric_code) ? $faculty->matric_code : $faculty->shortcode;

            if (empty($facultyCode)) {
                $problems[] = [
                    'what' => __('The faculty :faculty has no Matricule Code or Shortcode.', ['faculty' => $faculty->title ?? $faculty->id]),
                    'where' => __('Faculties → :faculty → Matricule Code', ['faculty' => $faculty->title ?? $faculty->id]),
                ];
            }
        }

        $batch = $batchId ? Batch::find($batchId) : null;

        if (!$batch) {
            $problems[] = [
                'what' => __('No batch has been chosen.'),
                'where' => __('Choose a batch.'),
            ];
        } else {
            $batchDigits = self::batchYearDigits($batch->title);

            if ($batchDigits === null) {
                $problems[] = [
                    'what' => __('The batch :batch has no year in its name, so the year digits cannot be read.', ['batch' => $batch->title]),
                    'where' => __('Batches → :batch → Title', ['batch' => $batch->title]),
                ];
            }
        }

        if ($problems) {
            return ['ready' => false, 'format' => null, 'problems' => $problems];
        }

        $suffix = '';

        if ($programId) {
            $program = \App\Models\Program::with('degreeType')->find($programId);

            if ($program && $program->degreeType && $program->degreeType->code_append_to_student_matricule) {
                $suffix = strtoupper($program->degreeType->code_append_to_student_matricule);
            }
        }

        return [
            'ready' => true,
            'format' => $prefix . $batchDigits . strtoupper($facultyCode) . $suffix . '###',
            'problems' => [],
        ];
    }

    /**
     * The two year digits a batch title carries, or null when it carries none.
     */
    protected static function batchYearDigits($batchTitle): ?string
    {
        if (preg_match('/(\d{2})$/', (string) $batchTitle, $matches)) {
            return $matches[1];
        }

        if (preg_match('/20(\d{2})/', (string) $batchTitle, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Give this student a freshly generated matricule and save it, claiming the
     * number in the same breath.
     *
     * Every caller used to generate an id, look to see whether it was taken and
     * then insert — with as much time in between as the admin took to fill in
     * the rest of the form. Two admissions running side by side would both be
     * told the same number was free and both use it. students.student_id is
     * unique, so the second insert is refused by the database; that refusal is
     * caught here and the id generated again, this time with the first one on
     * file. The loser of the race gets the next number rather than an error.
     *
     * MySQL rolls back the failed statement only, not the surrounding
     * transaction, so this is safe to call inside one — which it must be,
     * because the record and its matricule have to appear together.
     */
    public static function saveWithIssuedId(self $student, $facultyId, $batchId, $programId = null, int $attempts = 5): self
    {
        for ($attempt = 1; ; $attempt++) {
            $student->student_id = self::generateStudentId($facultyId, $batchId, $programId);

            try {
                $student->save();

                return $student;
            } catch (\Illuminate\Database\QueryException $e) {
                $isDuplicateId = ($e->errorInfo[1] ?? null) === 1062
                    && str_contains($e->getMessage(), 'student_id');

                if (!$isDuplicateId || $attempt >= $attempts) {
                    throw $e;
                }

                // Somebody else took this number between generating it and
                // writing it. Ask for the next one.
                $student->exists = false;
            }
        }
    }

    public static function generateStudentId($facultyId, $batchId, $programId = null)
    {
        try {
            // Get faculty with shortcode
            $faculty = Faculty::findOrFail($facultyId);
            
            // Use matric_code if available, otherwise shortcode
            $facultyCode = !empty($faculty->matric_code) ? $faculty->matric_code : $faculty->shortcode;

            if (empty($facultyCode)) {
                throw new \Exception("Faculty Matricule Code (or Shortcode) is not set for faculty ID: {$facultyId}");
            }

            // Get batch and extract last 2 digits from title
            $batch = Batch::findOrFail($batchId);
            $batchTitle = $batch->title;
            
            // Extract last 2 digits from batch title (e.g., "2025" -> "25", "Batch 2026" -> "26")
            preg_match('/(\d{2})$/', $batchTitle, $matches);
            if (!isset($matches[1])) {
                // If no 2 digits at end, try to find any 4-digit year and take last 2 digits
                preg_match('/20(\d{2})/', $batchTitle, $matches);
                if (!isset($matches[1])) {
                    throw new \Exception("Unable to extract year digits from batch title: {$batchTitle}");
                }
            }
            $batchDigits = $matches[1];

            // Build base prefix: PAX + Batch Digits + Faculty Code
            // The school's own code, from Settings → Academy Code, not a
            // hardcoded 'PAX'.
            $basePrefix = Setting::matriculePrefix() . $batchDigits . strtoupper($facultyCode);

            // Append degree type code if program is provided
            $degreeTypeSuffix = '';
            if ($programId) {
                $program = \App\Models\Program::with('degreeType')->find($programId);
                if ($program && $program->degreeType && $program->degreeType->code_append_to_student_matricule) {
                    $degreeTypeSuffix = strtoupper($program->degreeType->code_append_to_student_matricule);
                }
            }

            // New Format: Prefix includes Degree Type Code
            $prefix = $basePrefix . $degreeTypeSuffix;

            // Find the last student with this prefix
            // Get all matching student IDs to find the highest number
            $existingIds = self::where('student_id', 'LIKE', $prefix . '%')
                              ->pluck('student_id')
                              ->toArray();

            $maxNumber = 0;
            foreach ($existingIds as $id) {
                // Extract numeric part (remove prefix)
                // The ID is Prefix + Sequence (e.g. PAX25BFHND001)
                // So we just remove the prefix
                $numericPart = substr($id, strlen($prefix));
                
                // Ensure we only have digits (in case there's some old format garbage)
                if (is_numeric($numericPart)) {
                    $number = (int) $numericPart;
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                    }
                }
            }

            // Increment to get new number
            $newNumber = $maxNumber + 1;

            // Format the sequential number with leading zeros (3 digits)
            $sequentialNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            // Build complete student ID
            $studentId = $prefix . $sequentialNumber;

            // Final check: ensure this ID doesn't exist in BOTH students.student_id AND student_enrolls.matricule
            $attempts = 0;
            while ($attempts < 100) {
                $existsInStudents = self::where('student_id', $studentId)->exists();
                $existsInEnrollments = StudentEnroll::where('matricule', $studentId)->exists();
                
                if (!$existsInStudents && !$existsInEnrollments) {
                    break; // Found a unique ID
                }
                
                // ID conflicts, increment and try again
                $newNumber++;
                $sequentialNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
                $studentId = $prefix . $sequentialNumber;
                $attempts++;
            }
            
            if ($attempts >= 100) {
                throw new \Exception("Unable to generate unique student ID after 100 attempts");
            }            // Return the complete student ID
            return $studentId;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Student ID Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate enrollment-specific matricule for a student enrolling in a new program level
     * 
     * This is used when a student transitions from one academic level to another
     * (e.g., Bachelor to Masters, Masters to PhD)
     * 
     * Format examples:
     * - Undergraduate: PAX25BFHND001
     * - Masters: PAX25MFMSC001
     * - Doctoral: PAX25DFPHD001
     * 
     * @param int $studentId - The internal student ID (students.id)
     * @param int $programId - The program being enrolled into
     * @param int $batchId - The batch for this enrollment
     * @return string - The generated matricule
     */
    public static function generateEnrollmentMatricule($studentId, $programId, $batchId)
    {
        try {
            // Get student
            $student = self::findOrFail($studentId);
            
            // Get program with relationships
            $program = Program::with(['degreeType', 'faculty'])->findOrFail($programId);
            
            if (!$program->faculty) {
                throw new \Exception("Program must belong to a faculty");
            }
            
            $faculty = $program->faculty;
            
            // Use matric_code if available, otherwise shortcode
            $facultyCode = !empty($faculty->matric_code) ? $faculty->matric_code : $faculty->shortcode;

            if (empty($facultyCode)) {
                throw new \Exception("Faculty Matricule Code (or Shortcode) is not set for faculty ID: {$faculty->id}");
            }
            
            // Get batch year digits
            $batch = Batch::findOrFail($batchId);
            $batchTitle = $batch->title;
            
            preg_match('/(\d{2})$/', $batchTitle, $matches);
            if (!isset($matches[1])) {
                preg_match('/20(\d{2})/', $batchTitle, $matches);
                if (!isset($matches[1])) {
                    throw new \Exception("Unable to extract year digits from batch title: {$batchTitle}");
                }
            }
            $batchDigits = $matches[1];
            
            // Get academic level
            $level = $program->academic_level ?? 'A';
            
            // Build base prefix based on academic level
            if ($level === 'A') {
                // Undergraduate - use regular faculty code
                $basePrefix = Setting::matriculePrefix() . $batchDigits . strtoupper($facultyCode);
            } else {
                // Masters or Doctoral - incorporate level into prefix
                $basePrefix = Setting::matriculePrefix() . $batchDigits . $level . strtoupper($facultyCode);
            }
            
            // Append degree type matricule code if available
            $degreeTypeSuffix = '';
            if ($program->degreeType && $program->degreeType->code_append_to_student_matricule) {
                $degreeTypeSuffix = strtoupper($program->degreeType->code_append_to_student_matricule);
            }
            // For backward compatibility: if no matricule code and undergraduate level, append 'A'
            // NOTE: User requested to move degree code to middle. If no degree code, we might just skip it or use 'A' in middle?
            // Let's stick to the requested logic: attach after faculty code.
            elseif ($level === 'A') {
                $degreeTypeSuffix = 'A';
            }
            
            // New Format: Prefix includes Degree Type Code
            $prefix = $basePrefix . $degreeTypeSuffix;
            
            // Get all matching IDs from BOTH tables to find the highest number
            $existingMatricules = StudentEnroll::where('matricule', 'LIKE', $prefix . '%')
                                               ->whereNotNull('matricule')
                                               ->pluck('matricule')
                                               ->toArray();
            
            // Also check student_id from students table (they might conflict)
            $existingStudentIds = self::where('student_id', 'LIKE', $prefix . '%')
                                      ->pluck('student_id')
                                      ->toArray();
            
            // Merge both arrays to check all possible conflicts
            $allExistingIds = array_merge($existingMatricules, $existingStudentIds);
            
            $maxNumber = 0;
            foreach ($allExistingIds as $id) {
                // Remove prefix to get the rest
                $remainder = substr($id, strlen($prefix));
                
                // Extract just the numbers
                if (is_numeric($remainder)) {
                    $number = (int)$remainder;
                    if ($number > $maxNumber) {
                        $maxNumber = $number;
                    }
                }
            }
            
            // Increment to get new number
            $newNumber = $maxNumber + 1;
            
            // Format the sequential number with leading zeros (3 digits)
            $sequentialNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
            
            // Build matricule
            $matricule = $prefix . $sequentialNumber;
            
            // Final check: ensure this matricule doesn't exist in BOTH student_enrolls.matricule AND students.student_id
            $attempts = 0;
            while ($attempts < 100) {
                $existsInEnrollments = StudentEnroll::where('matricule', $matricule)->exists();
                $existsInStudents = self::where('student_id', $matricule)->exists();
                
                if (!$existsInEnrollments && !$existsInStudents) {
                    break; // Found a unique matricule
                }
                
                // Matricule conflicts, increment and try again
                $newNumber++;
                $sequentialNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
                $matricule = $prefix . $sequentialNumber;
                $attempts++;
            }
            
            if ($attempts >= 100) {
                throw new \Exception("Unable to generate unique matricule after 100 attempts");
            }
            
            return $matricule;
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Enrollment Matricule Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the user who blocked this student.
     */
    public function blocker()
    {
        return $this->belongsTo(\App\User::class, 'blocked_by');
    }

    /**
     * Check if student is currently blocked.
     */
    public function isBlocked(): bool
    {
        return !is_null($this->blocked_at);
    }
}
