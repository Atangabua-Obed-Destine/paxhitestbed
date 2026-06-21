<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use App\Models\StudentAssignment;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Student;
use Carbon\Carbon;

class AssignmentController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title    = trans_choice('module_assignment', 1);
        $this->route    = 'student.assignment';
        $this->view     = 'student.assignment';
        $this->path     = 'assignment';
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


        $data['user'] = $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get selected enrollment from session (set by SelectEnrollmentMiddleware)
        $selectedEnrollmentId = session('selected_enrollment_id');
        
        $enroll = null;
        if($selectedEnrollmentId) {
            $enroll = StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $user->id)
                            ->first();
        }

        // Filter sessions and semesters based on selected enrollment if available
        if ($enroll) {
            $data['sessions'] = StudentEnroll::where('student_id', $user->id)
                                    ->where('program_id', $enroll->program_id)
                                    ->groupBy('session_id')
                                    ->get();
            $data['semesters'] = StudentEnroll::where('student_id', $user->id)
                                    ->where('program_id', $enroll->program_id)
                                    ->groupBy('semester_id')
                                    ->get();
        } else {
            $data['sessions'] = StudentEnroll::where('student_id', $user->id)->groupBy('session_id')->get();
            $data['semesters'] = StudentEnroll::where('student_id', $user->id)->groupBy('semester_id')->get();
        }


        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = $session = '0';
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = $semester = '0';
        }


        // Filter Assignment
        $assignments = StudentAssignment::with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($user, $session, $semester, $enroll){
                $query->where('student_id', $user->id);
            
            // Filter by program if selected enrollment exists
            if($enroll){
                $query->where('program_id', $enroll->program_id);
            }

            if($session != 0){
                $query->where('session_id', $session);
            }
            if($semester != 0){
                $query->where('semester_id', $semester);
            }
        });
        $assignments->with('assignment')->whereHas('assignment', function ($query){
            $query->where('start_date', '<=', Carbon::today())
                  ->where('status', '1');
        });
        $data['rows'] = $assignments->orderBy('id', 'desc')->get();


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
            $enroll = StudentEnroll::find($selectedEnrollmentId);
            if($enroll) $programId = $enroll->program_id;
        }

        $data['row'] = $stuAss = StudentAssignment::where('id', $id)
                    ->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($user, $programId){
                        $query->where('student_id', $user->id);
                        if($programId) {
                            $query->where('program_id', $programId);
                        }
                    })
                    ->with('assignment')->whereHas('assignment', function ($query){
                        $query->where('start_date', '<=', Carbon::today())
                              ->where('status', '1');
                    })
                    ->firstOrFail();


        // Read Notifications
        foreach ($user->unreadNotifications as $notification) {
            if($notification->data['type'] == 'assignment' && $notification->data['id'] == $stuAss->assignment_id) {
                $notification->markAsRead();
            }
        }


        return view($this->view.'.show', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'attach' => 'required|mimes:pdf,docx,zip,xlsx,ppt|max:20480',
        ]);

        // Update Data
        $assignment = StudentAssignment::find($id);
        $assignment->attendance = 1;
        $assignment->date = Carbon::today();
        $assignment->attach = $this->updateMedia($request, 'attach', $this->path, $assignment);
        $assignment->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
