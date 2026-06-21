<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentEnroll;
use App\Models\ClassRoutine;
use App\Models\Session;

class ClassRoutineController extends Controller
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
        $this->title    = trans_choice('module_class_routine', 1);
        $this->route    = 'student.class-routine';
        $this->view     = 'student.class-routine';
        $this->path     = 'class-routine';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

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

        // Class Routine
        if(isset($enroll)){
        $data['rows'] = ClassRoutine::where('status', '1')
                        ->where('session_id', $enroll->session_id)
                        ->where('program_id', $enroll->program_id)
                        ->where('semester_id', $enroll->semester_id)
                        ->where('section_id', $enroll->section_id)
                        ->orderBy('start_time', 'asc')
                        ->get();
        } else {
            $data['rows'] = collect();
        }


        return view($this->view.'.index', $data);
    }
}
