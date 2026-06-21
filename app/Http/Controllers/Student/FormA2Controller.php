<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\ProgramSemesterFee;
use App\Models\FeesCategory;
use App\Models\FormA2Setting;
use App\Models\Setting;

class FormA2Controller extends Controller
{
    public function index()
    {
        $data['title'] = 'Admission Confirmation (Form A2)';
        return view('student.form-a2.index', $data);
    }

    public function download(Request $request)
    {
        $user = Auth::guard('student')->user();
        $enrollmentId = Session::get('selected_enrollment_id');
        
        if ($enrollmentId) {
            $enrollment = StudentEnroll::find($enrollmentId);
        } else {
            $enrollment = StudentEnroll::where('student_id', $user->id)
                        ->orderBy('id', 'desc')
                        ->first();
        }

        if (!$enrollment) {
            return redirect()->back()->with('error', 'Enrollment not found.');
        }

        // Get Program Semester Fee
        // We need to find the fee configuration for this program and semester (First Semester)
        // Assuming the enrollment is for the first semester as per requirements
        
        $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                                ->where('semester_id', $enrollment->semester_id)
                                ->first();

        // If not found, maybe try to find one for the "First Semester" of the program generally?
        // But usually fee config is specific to program and semester.

        $breakdowns = [];
        $totalAmount = 0;

        if ($programSemesterFee) {
            // We need to filter breakdowns for "First Installment" category if possible
            // Or just take all breakdowns if the fee itself is for the semester.
            // The user said "based on the fee break down configured in ... program-semester-fee"
            // And "when a student pays the full first installment".
            // So we should probably show the breakdown of the First Installment.
            
            // Let's check if ProgramSemesterFee has a category_id.
            // If so, we need the one for First Installment.
            
            $firstInstallmentCategory = FeesCategory::where('is_first_installment', 1)->first();
            
            if ($firstInstallmentCategory) {
                 $programSemesterFee = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                                ->where('semester_id', $enrollment->semester_id)
                                ->where('fees_category_id', $firstInstallmentCategory->id)
                                ->with('breakdowns')
                                ->first();
            }
            
            if ($programSemesterFee) {
                $breakdowns = $programSemesterFee->breakdowns;
                $totalAmount = $programSemesterFee->amount;
            }
        }

        $data['student'] = $user;
        $data['enrollment'] = $enrollment;
        $data['breakdowns'] = $breakdowns;
        $data['totalAmount'] = $totalAmount;
        $data['settings'] = FormA2Setting::first();
        $data['generalSetting'] = Setting::first();
        
        // Number to Words
        $data['totalAmountWords'] = ucwords($this->numberToWords($totalAmount));
        
        // Check if preview mode
        $data['is_preview'] = $request->has('preview');

        return view('student.form-a2.pdf', $data);
    }

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
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
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
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
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
