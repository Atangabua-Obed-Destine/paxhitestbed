<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\StudentEnroll;
use App\Models\ProgramSemesterFee;
use App\Models\FeesCategory;
use App\Models\FormA2Setting;
use App\Models\Setting;
use App\Services\StaffAssignmentService;

class StudentFormA2Controller extends Controller
{
    /**
     * Display listing of student enrollments for Form A2
     */
    public function index(Request $request)
    {
        $data['title'] = 'Student Form A2';
        
        // Get faculties (filtered by staff assignment if applicable)
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        
        // Initialize
        $data['programs'] = collect();
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        
        // Load programs when faculty is selected
        if (!empty($request->faculty) && $request->faculty != '0') {
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')
                ->orderBy('title', 'asc')
                ->get();
        }
        
        // Build enrollments query - Only show Y1S1 (First Year, First Semester) enrollments
        // Form A2 is given once per program enrollment at initial admission
        $enrollmentsQuery = StudentEnroll::with(['student', 'program.faculty', 'semester', 'session'])
            ->whereNotNull('matricule') // Only enrollments with matricule
            ->whereHas('student', function($query) {
                $query->where('status', '!=', 0); // Exclude disabled students
            })
            // Only First Year, First Semester (non-resit) enrollments
            ->whereHas('semester', function($query) {
                $query->where('year', 1)
                      ->where('semester_type', 1) // TYPE_FIRST
                      ->where(function($q) {
                          $q->where('is_resit', 0)->orWhereNull('is_resit');
                      });
            });
        
        // Apply faculty filter
        if (!empty($request->faculty) && $request->faculty != '0') {
            $enrollmentsQuery->whereHas('program', function($query) use ($faculty) {
                $query->where('faculty_id', $faculty);
            });
        }
        
        // Apply program filter
        if (!empty($request->program) && $request->program != '0') {
            $enrollmentsQuery->where('program_id', $program);
        }
        
        $data['enrollments'] = $enrollmentsQuery->orderBy('matricule', 'asc')->get();
        
        return view('admin.student-form-a2.index', $data);
    }
    
    /**
     * Preview Form A2 for a specific enrollment
     */
    public function preview(Request $request, $enrollmentId)
    {
        $enrollment = StudentEnroll::with([
            'student', 
            'program.faculty', 
            'program.academicDepartment',
            'semester',
            'session'
        ])->findOrFail($enrollmentId);
        
        // Get Program Semester Fee for First Installment
        $programSemesterFee = null;
        $breakdowns = [];
        $totalAmount = 0;
        
        // Get First Installment category
        $firstInstallmentCategory = FeesCategory::where('is_first_installment', 1)->first();
        
        if ($firstInstallmentCategory) {
            $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                ->where('semester_id', $enrollment->semester_id)
                ->where('fees_category_id', $firstInstallmentCategory->id)
                ->with('breakdowns')
                ->first();
        }
        
        // Fallback: try to get any fee for this program/semester
        if (!$programSemesterFee) {
            $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                ->where('semester_id', $enrollment->semester_id)
                ->with('breakdowns')
                ->first();
        }
        
        if ($programSemesterFee) {
            $breakdowns = $programSemesterFee->breakdowns;
            $totalAmount = $programSemesterFee->amount;
        }
        
        $data['student'] = $enrollment->student;
        $data['enrollment'] = $enrollment;
        $data['breakdowns'] = $breakdowns;
        $data['totalAmount'] = $totalAmount;
        $data['settings'] = FormA2Setting::first();
        $data['generalSetting'] = Setting::first();
        $data['totalAmountWords'] = ucwords($this->numberToWords($totalAmount));
        $data['is_preview'] = true;
        $data['is_admin'] = true;
        
        return view('admin.student-form-a2.pdf', $data);
    }
    
    /**
     * Download/Print Form A2 for a specific enrollment
     */
    public function download(Request $request, $enrollmentId)
    {
        $enrollment = StudentEnroll::with([
            'student', 
            'program.faculty', 
            'program.academicDepartment',
            'semester',
            'session'
        ])->findOrFail($enrollmentId);
        
        // Get Program Semester Fee for First Installment
        $programSemesterFee = null;
        $breakdowns = [];
        $totalAmount = 0;
        
        $firstInstallmentCategory = FeesCategory::where('is_first_installment', 1)->first();
        
        if ($firstInstallmentCategory) {
            $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                ->where('semester_id', $enrollment->semester_id)
                ->where('fees_category_id', $firstInstallmentCategory->id)
                ->with('breakdowns')
                ->first();
        }
        
        if (!$programSemesterFee) {
            $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                ->where('semester_id', $enrollment->semester_id)
                ->with('breakdowns')
                ->first();
        }
        
        if ($programSemesterFee) {
            $breakdowns = $programSemesterFee->breakdowns;
            $totalAmount = $programSemesterFee->amount;
        }
        
        $data['student'] = $enrollment->student;
        $data['enrollment'] = $enrollment;
        $data['breakdowns'] = $breakdowns;
        $data['totalAmount'] = $totalAmount;
        $data['settings'] = FormA2Setting::first();
        $data['generalSetting'] = Setting::first();
        $data['totalAmountWords'] = ucwords($this->numberToWords($totalAmount));
        $data['is_preview'] = false;
        $data['is_admin'] = true;
        
        return view('admin.student-form-a2.pdf', $data);
    }
    
    /**
     * Bulk preview/download multiple Form A2s
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'enrollment_ids' => 'required|array',
            'enrollment_ids.*' => 'exists:student_enrolls,id',
        ]);
        
        $enrollments = StudentEnroll::with([
            'student', 
            'program.faculty', 
            'program.academicDepartment',
            'semester',
            'session'
        ])->whereIn('id', $request->enrollment_ids)->get();
        
        $data['forms'] = [];
        
        foreach ($enrollments as $enrollment) {
            $programSemesterFee = null;
            $breakdowns = [];
            $totalAmount = 0;
            
            $firstInstallmentCategory = FeesCategory::where('is_first_installment', 1)->first();
            
            if ($firstInstallmentCategory) {
                $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                    ->where('semester_id', $enrollment->semester_id)
                    ->where('fees_category_id', $firstInstallmentCategory->id)
                    ->with('breakdowns')
                    ->first();
            }
            
            if (!$programSemesterFee) {
                $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                    ->where('semester_id', $enrollment->semester_id)
                    ->with('breakdowns')
                    ->first();
            }
            
            if ($programSemesterFee) {
                $breakdowns = $programSemesterFee->breakdowns;
                $totalAmount = $programSemesterFee->amount;
            }
            
            $data['forms'][] = [
                'student' => $enrollment->student,
                'enrollment' => $enrollment,
                'breakdowns' => $breakdowns,
                'totalAmount' => $totalAmount,
                'totalAmountWords' => ucwords($this->numberToWords($totalAmount)),
            ];
        }
        
        $data['settings'] = FormA2Setting::first();
        $data['generalSetting'] = Setting::first();
        $data['is_preview'] = $request->has('preview');
        $data['is_admin'] = true;
        
        return view('admin.student-form-a2.bulk-pdf', $data);
    }
    
    /**
     * Convert number to words (copied from Student FormA2Controller)
     */
    private function numberToWords($number) {
        $hyphen      = '-';
        $conjunction = ' and ';
        $separator   = ', ';
        $negative    = 'negative ';
        $decimal     = ' point ';
        $dictionary  = array(
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty',
            30                  => 'thirty',
            40                  => 'forty',
            50                  => 'fifty',
            60                  => 'sixty',
            70                  => 'seventy',
            80                  => 'eighty',
            90                  => 'ninety',
            100                 => 'hundred',
            1000                => 'thousand',
            1000000             => 'million',
            1000000000          => 'billion',
            1000000000000       => 'trillion',
            1000000000000000    => 'quadrillion',
            1000000000000000000 => 'quintillion'
        );

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            trigger_error(
                'numberToWords only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );
            return false;
        }

        if ($number < 0) {
            return $negative . $this->numberToWords(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens   = ((int) ($number / 10)) * 10;
                $units  = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds  = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[(int)$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . $this->numberToWords($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= $this->numberToWords($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }
}
