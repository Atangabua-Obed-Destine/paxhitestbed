<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingSchedule;
use App\Models\PostalExchange;
use App\Models\FeesCategory;
use App\Models\ItemCategory;
use App\Models\Application;
use App\Models\Complain;
use App\Models\PhoneLog;
use App\Models\Visitor;
use App\Models\Expense;
use App\Models\Enquiry;
use App\Models\Payroll;
use App\Models\Student;
use App\Models\Program;
use App\Models\Income;
use App\Models\Book;
use App\Models\Fee;
use Carbon\Carbon;
use App\User;

class DashboardController extends Controller
{
    protected $title, $route, $view, $path, $access;

	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		// Module Data
		$this->title 	= trans_choice('module_dashboard', 1);
		$this->route 	= 'admin.dashboard';
		$this->view 	= 'admin';
   	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
   	{
		//
		$data['title'] 	= $this->title;
		$data['route'] 	= $this->route;
		$data['view'] 	= $this->view;


		$today_date = Carbon::parse(Carbon::today())->format('Y-m-d');
		$year = Carbon::parse(Carbon::today())->format('Y');
		$month = Carbon::parse(Carbon::today())->format('m');


		// Counter Data
		$data['pending_applications'] = Application::where('status', '1')->count();
		$data['approved_applications'] = Application::where('status', '2')->count();
		$data['active_students'] = Student::where('status', '1')->count();
		$data['total_students'] = Student::count();
		$data['active_staffs'] = User::where('status', '1')->count();
		$data['library_books'] = Book::where('status', '1')->count();
		
		// Student Enrollment Stats
		$data['enrolled_students'] = \App\Models\StudentEnroll::where('status', '1')->count();
		$data['course_completed'] = \App\Models\Student::where('status', '1')
			->whereHas('studentEnrolls', function($q) {
				$q->where('status', '2');
			})
			->whereDoesntHave('studentEnrolls', function($q) {
				$q->where('status', '1');
			})
			->count(); // Students with all enrollments completed (none active)
		$data['alumni_count'] = Student::where('status', '>=', '2')->count(); // Status 2=Passed Out, 3=Transfer Out
		
		// Attendance Stats (1=Present, 2=Absent, 3=Leave, 4=Holiday)
		$data['today_present'] = \App\Models\StudentAttendance::whereDate('date', $today_date)
			->where('attendance', 1)->distinct('student_enroll_id')->count('student_enroll_id');
		$data['today_absent'] = \App\Models\StudentAttendance::whereDate('date', $today_date)
			->where('attendance', 2)->distinct('student_enroll_id')->count('student_enroll_id');
		
		// Class & Exam Routines
		$data['total_classes'] = \App\Models\ClassRoutine::where('status', '1')->count();
		$data['today_classes'] = \App\Models\ClassRoutine::where('status', '1')
			->where('day', date('l'))->count();
		$data['total_exam_routines'] = \App\Models\ExamRoutine::where('status', '1')->count();
		
		// Assignment Stats
		$data['active_assignments'] = \App\Models\Assignment::where('status', '1')->count();
		$data['pending_submissions'] = 0; // Will implement if model exists
		
		// Fees Stats - Match the fees report calculation
		// Include all fees: status 0=unpaid, 1=paid, 2=partial. Exclude status 3=cancelled
		$allFees = Fee::whereIn('status', [0, 1, 2])->withCreditMovedOut()->get();
		// Net of overpayment credit applied to another fee, which a plain sum
		// of paid_amount counted twice. See Fee::netPaidSql().
		$data['total_fees_collected'] = $allFees->sum(fn ($fee) => $fee->net_paid_amount);
		// Only what is still owed. An overpaid fee's negative balance used to be
		// added in, and quietly reduced what everyone else owed.
		$data['total_fees_due'] = $allFees->sum(fn ($fee) => max(0, $fee->net_remaining_balance));
		$data['pending_payments'] = Fee::whereIn('status', [0, 2])
			->where(function($q) {
				$q->whereRaw('paid_amount < (fee_amount + fine_amount - discount_amount)');
			})
			->count();
		$data['payment_verifications'] = \App\Models\PaymentReceipt::where('verification_status', 'pending')->count();
		$data['active_payment_plans'] = \App\Models\PaymentPlan::where('status', 'active')->count();
		
		// Exam Stats
		$data['pending_markings'] = \App\Models\Exam::where('status', '1')
			->whereNull('achieve_marks')->count(); // Exams without marks entered
		$data['resit_requests'] = \App\Models\ResitRequest::whereIn('workflow_state', ['requested', 'awaiting_payment'])->count();
		
		// Staff Stats
		$data['total_staff'] = User::count();
		$data['staff_on_leave'] = 0; // Will calculate if model exists
		if (class_exists('\App\Models\StaffLeave')) {
			$data['staff_on_leave'] = \App\Models\StaffLeave::whereDate('leave_from', '<=', $today_date)
				->whereDate('leave_to', '>=', $today_date)
				->where('status', '1')->count();
		}
		$data['pending_payroll'] = Payroll::where('status', '0')->count();
		
		// Library Stats
		$data['books_issued'] = 0;
		$data['books_overdue'] = 0;
		$data['pending_book_requests'] = 0;
		$data['e_library_resources'] = 0;
		
		if (class_exists('\App\Models\BookIssue')) {
			$data['books_issued'] = \App\Models\BookIssue::whereNull('return_date')
				->orWhere('return_date', '>=', $today_date)->count();
			$data['books_overdue'] = \App\Models\BookIssue::whereNull('return_date')
				->where('return_due_date', '<', $today_date)->count();
		}
		if (class_exists('\App\Models\BookRequest')) {
			$data['pending_book_requests'] = \App\Models\BookRequest::where('status', 'pending')->count();
		}
		if (class_exists('\App\Models\ELibraryResource')) {
			$data['e_library_resources'] = \App\Models\ELibraryResource::where('is_active', 1)->count();
		}
		
		// Accounting Stats (if modules exist)
		if (class_exists('\App\Models\JournalEntry')) {
			$data['unposted_entries'] = \App\Models\JournalEntry::where('is_posted', false)->count();
			$data['current_period'] = \App\Models\AccountingPeriod::where('is_closed', false)
				->orderBy('period_number', 'desc')->first();
		}
		
		// Communication Stats
		$data['upcoming_events'] = \App\Models\Event::where('status', '1')
			->where('start_date', '>=', $today_date)->count();
		$data['active_notices'] = \App\Models\Notice::where('status', '1')->count(); // Active notices
		
		// Programs & Academic
		$data['active_programs'] = Program::where('status', '1')->count();
		$data['active_faculties'] = \App\Models\Faculty::where('status', '1')->count();
		$data['active_subjects'] = \App\Models\Subject::where('status', '1')->count();

		// Active Students Breakdown
		// Fetch all active programs with their active student count (unique students only)
		$programs = Program::where('status', '1')
			->with(['faculty', 'academicDepartment'])
			->select('programs.*')
			->selectSub(function ($query) {
				$query->from('student_enrolls')
					->whereColumn('student_enrolls.program_id', 'programs.id')
					->where('student_enrolls.status', '1')
					->selectRaw('count(distinct student_id)');
			}, 'student_enrolls_count')
			->orderBy('student_enrolls_count', 'desc')
			->get();

		$data['students_by_program'] = $programs;

		// Per Faculty (Aggregated from Programs)
		$data['students_by_faculty'] = $programs->groupBy('faculty_id')
			->map(function ($group) {
				$first = $group->first();
				if (!$first || !$first->faculty) return null;
				
				$faculty = $first->faculty;
				$faculty->active_students_count = $group->sum('student_enrolls_count');
				return $faculty;
			})
			->filter()
			->sortByDesc('active_students_count');

		// Per Department (Aggregated from Programs)
		$data['students_by_department'] = $programs->groupBy('academic_department_id')
			->map(function ($group) {
				$first = $group->first();
				if (!$first || !$first->academicDepartment) return null;
				
				$dept = $first->academicDepartment;
				$dept->active_students_count = $group->sum('student_enrolls_count');
				return $dept;
			})
			->filter()
			->sortByDesc('active_students_count');
		
		// Teacher-specific data
		$user = \Auth::guard('web')->user();
		$data['is_teacher'] = false;
		$data['teacher_today_classes'] = collect();
		$data['teacher_subjects'] = collect();
		$data['teacher_total_classes'] = 0;
		$data['teacher_weekly_schedule'] = collect();
		
		if ($user && $user->roles->where('slug', 'teacher')->count() > 0) {
			$data['is_teacher'] = true;
			$current_session = \App\Models\Session::where('status', '1')->where('current', '1')->first();
			
			// Convert PHP's date('N') (1=Monday, 7=Sunday) to system's day numbering (1=Saturday, 7=Friday)
			$php_day = date('N'); // 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday, 7=Sunday
			// System mapping: 1=Saturday, 2=Sunday, 3=Monday, 4=Tuesday, 5=Wednesday, 6=Thursday, 7=Friday
			$day_mapping = [
				1 => 3, // Monday -> 3
				2 => 4, // Tuesday -> 4
				3 => 5, // Wednesday -> 5
				4 => 6, // Thursday -> 6
				5 => 7, // Friday -> 7
				6 => 1, // Saturday -> 1
				7 => 2, // Sunday -> 2
			];
			$today_day = $day_mapping[$php_day];
			
			if ($current_session) {
				// Get teacher's classes for today
				$data['teacher_today_classes'] = \App\Models\ClassRoutine::where('teacher_id', $user->id)
					->where('session_id', $current_session->id)
					->where('day', $today_day)
					->where('status', '1')
					->with(['subject', 'room', 'program', 'semester', 'section'])
					->orderBy('start_time', 'asc')
					->get();
				
				// Get all subjects the teacher teaches this session
				$data['teacher_subjects'] = \App\Models\ClassRoutine::where('teacher_id', $user->id)
					->where('session_id', $current_session->id)
					->where('status', '1')
					->with('subject')
					->get()
					->pluck('subject')
					->unique('id');
				
				// Get total weekly classes count
				$data['teacher_total_classes'] = \App\Models\ClassRoutine::where('teacher_id', $user->id)
					->where('session_id', $current_session->id)
					->where('status', '1')
					->count();
				
				// Get full weekly schedule grouped by day
				$data['teacher_weekly_schedule'] = \App\Models\ClassRoutine::where('teacher_id', $user->id)
					->where('session_id', $current_session->id)
					->where('status', '1')
					->with(['subject', 'room', 'program', 'semester', 'section'])
					->orderBy('day', 'asc')
					->orderBy('start_time', 'asc')
					->get()
					->groupBy('day');
			}
		}

		$data['daily_visitors'] = Visitor::where('date', $today_date)->where('status', '1')->get();
		$data['daily_phone_logs'] = PhoneLog::where('date', $today_date)->where('status', '1')->get();
		$data['daily_enqueries'] = Enquiry::where('date', $today_date)->where('status', '1')->get();
		$data['daily_postals'] = PostalExchange::where('date', $today_date)->where('status', '1')->get();


		$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];


		//Line Chart
		$salaries = [];
		$fees = [];
		$expenses = [];
		$incomes = [];

		for($l = 1; $l <= $month; $l++){
			$salaries[] = Payroll::where('status', '1')->whereYear('pay_date', $year)->whereMonth('pay_date', $l)->sum('net_salary');
		}
		for($i = 1; $i <= $month; $i++){
			$fees[] = Fee::where('status', '1')->whereYear('pay_date', $year)->whereMonth('pay_date', $i)->sum(\Illuminate\Support\Facades\DB::raw(Fee::cashReceivedSql('fees')));
		}
		for($j = 1; $j <= $month; $j++){
			$expenses[] = Expense::where('status', '1')->whereYear('date', $year)->whereMonth('date', $j)->sum('amount');
		}
		for($k = 1; $k <= $month; $k++){
			$incomes[] = Income::where('status', '1')->whereYear('date', $year)->whereMonth('date', $k)->sum('amount');
		}


		//Pie Chart
		$student_fee = Fee::where('status', '1')->whereYear('pay_date', $year)->sum('fee_amount');
		$discounts = Fee::where('status', '1')->whereYear('pay_date', $year)->sum('discount_amount');
		$fines = Fee::where('status', '1')->whereYear('pay_date', $year)->sum('fine_amount');
		$fee_paid = Fee::where('status', '1')->whereYear('pay_date', $year)->sum(\Illuminate\Support\Facades\DB::raw(Fee::cashReceivedSql('fees')));



		//Line Chart
		$total_allowance = [];
		$total_deduction = [];
		$total_tax = [];
		$net_salary = [];

		for($q = 1; $q <= $month; $q++){
			$total_allowance[] = Payroll::where('status', '1')->whereYear('pay_date', $year)->whereMonth('pay_date', $q)->sum('total_allowance');
		}
		for($p = 1; $p <= $month; $p++){
			$total_deduction[] = Payroll::where('status', '1')->whereYear('pay_date', $year)->whereMonth('pay_date', $p)->sum('total_deduction');
		}
		for($o = 1; $o <= $month; $o++){
			$total_tax[] = Payroll::where('status', '1')->whereYear('pay_date', $year)->whereMonth('pay_date', $o)->sum('tax');
		}
		for($m = 1; $m <= $month; $m++){
			$net_salary[] = Payroll::where('status', '1')->whereYear('pay_date', $year)->whereMonth('pay_date', $m)->sum('net_salary');
		}


		// Doughnut Chart
		$data['programs'] = Program::where('status', '1')
								->orderBy('title', 'asc')->get();
		$data['fees_types'] = FeesCategory::where('status', '1')
								->orderBy('title', 'asc')->get();
		$data['item_types'] = ItemCategory::where('status', '1')
								->orderBy('title', 'asc')->get();


		//Bar Chart
		$monthly_visitors = [];
		$monthly_phone_logs = [];
		$monthly_enqueries = [];
		$monthly_complains = [];
		$monthly_postals = [];
		$monthly_schedules = [];

		for($a = 1; $a <= $month; $a++){
			$monthly_visitors[] = Visitor::whereYear('date', $year)->whereMonth('date', $a)->count();
		}
		for($b = 1; $b <= $month; $b++){
			$monthly_phone_logs[] = PhoneLog::whereYear('date', $year)->whereMonth('date', $b)->count();
		}
		for($c = 1; $c <= $month; $c++){
			$monthly_enqueries[] = Enquiry::whereYear('date', $year)->whereMonth('date', $c)->count();
		}
		for($d = 1; $d <= $month; $d++){
			$monthly_complains[] = Complain::whereYear('date', $year)->whereMonth('date', $d)->count();
		}
		for($e = 1; $e <= $month; $e++){
			$monthly_postals[] = PostalExchange::whereYear('date', $year)->whereMonth('date', $e)->count();
		}
		for($f = 1; $f <= $month; $f++){
			$monthly_schedules[] = MeetingSchedule::whereYear('date', $year)->whereMonth('date', $f)->count();
		}


      	return view($this->view.'.index', $data)->with('months', json_encode($months,JSON_NUMERIC_CHECK))->with('fees', json_encode($fees,JSON_NUMERIC_CHECK))->with('expenses', json_encode($expenses, JSON_NUMERIC_CHECK))->with('incomes', json_encode($incomes, JSON_NUMERIC_CHECK))->with('salaries', json_encode($salaries, JSON_NUMERIC_CHECK))->with('student_fee', json_encode($student_fee, JSON_NUMERIC_CHECK))->with('discounts', json_encode($discounts, JSON_NUMERIC_CHECK))->with('fines', json_encode($fines, JSON_NUMERIC_CHECK))->with('fee_paid', json_encode($fee_paid, JSON_NUMERIC_CHECK))->with('net_salary', json_encode($net_salary, JSON_NUMERIC_CHECK))->with('total_tax', json_encode($total_tax, JSON_NUMERIC_CHECK))->with('total_deduction', json_encode($total_deduction, JSON_NUMERIC_CHECK))->with('total_allowance', json_encode($total_allowance, JSON_NUMERIC_CHECK))->with('monthly_visitors', json_encode($monthly_visitors,JSON_NUMERIC_CHECK))->with('monthly_phone_logs', json_encode($monthly_phone_logs,JSON_NUMERIC_CHECK))->with('monthly_enqueries', json_encode($monthly_enqueries,JSON_NUMERIC_CHECK))->with('monthly_complains', json_encode($monthly_complains,JSON_NUMERIC_CHECK))->with('monthly_postals', json_encode($monthly_postals,JSON_NUMERIC_CHECK))->with('monthly_schedules', json_encode($monthly_schedules,JSON_NUMERIC_CHECK));
   	}
}
