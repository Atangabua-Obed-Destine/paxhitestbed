<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentAttendance;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Models\Session;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    protected $title, $route, $view, $path;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title    = trans_choice('module_attendance', 1);
        $this->route    = 'student.attendance';
        $this->view     = 'student.attendance';
        $this->path     = 'attendance';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;


        if(!empty($request->month) || $request->month != null){
            $data['selected_month'] = $month = $request->month;
        }
        else{
            $data['selected_month'] = $month = date("m", strtotime(Carbon::today()));
        }

        if(!empty($request->year) || $request->year != null){
            $data['selected_year'] = $year = $request->year;
        }
        else{
            $data['selected_year'] = $year = date("Y", strtotime(Carbon::today()));
        }

        // Get selected enrollment from session (set by SelectEnrollmentMiddleware)
        $selectedEnrollmentId = session('selected_enrollment_id');
        $student_id = Auth::guard('student')->user()->id;
        
        $enroll = null;
        if($selectedEnrollmentId) {
            $enroll = StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $student_id)
                            ->first();
        }
        
        // Fallback: If no enrollment found via session, try current session (backward compatibility)
        if(!$enroll) {
            $session = Session::where('status', '1')->where('current', '1')->first();
            if(isset($session)){
                $enroll = StudentEnroll::where('student_id', $student_id)
                                ->where('session_id', $session->id)
                                ->orderBy('id', 'desc')
                                ->first();
            }
        }
        
        $data['student'] = $enroll;

        // Attendances
        if(isset($enroll)){
        $data['attendances'] = StudentAttendance::where('student_enroll_id', $enroll->id)->whereYear('date', $year)->whereMonth('date', $month)->orderBy('id', 'asc')->get();
        } else {
            $data['attendances'] = collect();
        }


        return view($this->view.'.index', $data);
    }
}
