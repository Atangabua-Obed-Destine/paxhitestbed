<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Program;
use App\Models\Session;
use App\Models\Student;
use App\Models\Batch;

class StudentAlumniController extends Controller
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
        $this->title = trans_choice('module_student_alumni', 1);
        $this->route = 'admin.student-alumni';
        $this->view = 'admin.student-alumni';
        $this->path = 'student';
        $this->access = 'student-enroll';


        $this->middleware('permission:'.$this->access.'-alumni');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->batch) || $request->batch != null){
            $data['selected_batch'] = $batch = $request->batch;
        }
        else{
            $data['selected_batch'] = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
        }


        // Search Filter
        $data['batches'] = Batch::where('status', '1')->orderBy('id', 'desc')->get();
        $data['programs'] = Program::where('status', '1')->orderBy('title', 'asc')->get();
        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();


        if(isset($request->batch) || isset($request->program) || isset($request->session)){
            // Student Filter
            $students = Student::where('status', '>=', '2');
            if(!empty($request->batch)){
                $students->where('batch_id', $batch);
            }
            if(!empty($request->program)){
                $students->where('program_id', $program);
            }
            if(!empty($request->session)){
            $students->with('lastEnroll')->whereHas('lastEnroll', function ($query) use ($session){
                    $query->where('session_id', $session);
                });
            }
            $students->with('studentEnrolls');
            $rows = $students->get();
            $data['rows'] = $rows->sortBy(function($student){
                return $student->studentEnrolls->sortByDesc('id')->first()->matricule ?? $student->student_id;
            });
        }


        return view($this->view.'.index', $data);
    }
}
