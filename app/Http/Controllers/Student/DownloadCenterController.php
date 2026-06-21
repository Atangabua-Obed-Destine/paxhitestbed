<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Student;
use App\Models\Content;
use Carbon\Carbon;

class DownloadCenterController extends Controller
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
        $this->title    = trans_choice('module_download', 1);
        $this->route    = 'student.download';
        $this->view     = 'student.download';
        $this->path     = 'content';
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


        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get selected enrollment from session
        $selectedEnrollmentId = session('selected_enrollment_id');
        $programId = null;
        
        if($selectedEnrollmentId) {
            $enroll = \App\Models\StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $user->id)
                            ->first();
            if($enroll) {
                $programId = $enroll->program_id;
            }
        }

        // Filter Contents
        $contents = Content::where('status', '1')->where('date', '<=', Carbon::today());
        
        // Filter by program if selected
        if($programId) {
            $contents->where(function($q) use ($programId) {
                $q->where('program_id', $programId)
                  ->orWhereNull('program_id')
                  ->orWhere('program_id', 0);
            });
        }

        $contents->with('students')->whereHas('students', function ($query) use ($user){
            $query->where('contentable_id', $user->id);
            $query->where('contentable_type', 'App\Models\Student');
        });

        $data['rows'] = $contents->orderBy('date', 'desc')->get();


        return view($this->view.'.index', $data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;


        $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get selected enrollment from session
        $selectedEnrollmentId = session('selected_enrollment_id');
        $programId = null;
        
        if($selectedEnrollmentId) {
            $enroll = \App\Models\StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $user->id)
                            ->first();
            if($enroll) {
                $programId = $enroll->program_id;
            }
        }

        // Filter Contents
        $contents = Content::where('status', '1')->where('date', '<=', Carbon::today());
        
        // Filter by program if selected
        if($programId) {
            $contents->where(function($q) use ($programId) {
                $q->where('program_id', $programId)
                  ->orWhereNull('program_id')
                  ->orWhere('program_id', 0);
            });
        }

        $contents->with('students')->whereHas('students', function ($query) use ($user){
            $query->where('contentable_id', $user->id);
            $query->where('contentable_type', 'App\Models\Student');
        });

        $data['row'] = $contents->where('id', $id)->firstOrFail();


        // Read Notifications
        foreach ($user->unreadNotifications as $notification) {
            if($notification->data['type'] == 'content' && $notification->data['id'] == $id) {
                $notification->markAsRead();
            }
        }


        return view($this->view.'.show', $data);
    }
}
