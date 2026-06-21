<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Models\ClassRoutine;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Models\ClassRoom;
use App\Models\Semester;
use App\Models\Faculty;
use App\Models\Session;
use App\Models\Program;
use App\Models\Section;
use App\Models\Subject;
use App\User;
use App\Services\StaffAssignmentService;

class ClassRoutineController extends Controller
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
        $this->title = trans_choice('module_class_routine', 1);
        $this->route = 'admin.class-routine';
        $this->view = 'admin.class-routine';
        $this->path = 'class-routine';
        $this->access = 'class-routine';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-print', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store','destroy']]);
        $this->middleware('permission:'.$this->access.'-teacher', ['only' => ['teacher']]);
        $this->middleware('permission:'.$this->access.'-print', ['only' => ['print']]);
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


        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = '0';
            $program = null;
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
            $session = null;
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = '0';
            $semester = null;
        }

        if(!empty($request->semester_year) || $request->semester_year != null){
            $data['selected_semester_year'] = $selected_semester_year = $request->semester_year;
        }
        else{
            $data['selected_semester_year'] = $selected_semester_year = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = '0';
            $section = null;
        }


        $data['print'] = PrintSetting::where('slug', 'class-routine')->first();
        

        // Search Filter
        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['semesterOptions'] = [];
        $data['sections'] = collect();
        
        // Apply staff assignment filter to faculties
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $facultyQuery = StaffAssignmentService::filterFaculties($facultyQuery);
        $data['faculties'] = $facultyQuery->get();

        if(!empty($faculty)){
            $programQuery = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc');
            $programQuery = StaffAssignmentService::filterPrograms($programQuery);
            $data['programs'] = $programQuery->get();
        }

        if(!empty($program)){
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
            $data['semesters'] = $semesterCollection;
            $data['semesterOptions'] = $semesterCollection
                ->filter(function ($semesterItem) {
                    return !is_null($semesterItem->year);
                })
                ->groupBy('year')
                ->sortKeys()
                ->map(function ($items) {
                    return $items->map(function ($semesterItem) {
                        return [
                            'id' => $semesterItem->id,
                            'title' => $semesterItem->title,
                        ];
                    })->values();
                })
                ->toArray();
        }

        if(!empty($program) && !empty($semester)){
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }


        // Routine Filter
        if(!empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section)){

            $routines = ClassRoutine::where('status', '1');

            if(!empty($request->program)){
                $routines->where('program_id', $request->program);
            }
            if(!empty($request->session)){
                $routines->where('session_id', $request->session);
            }
            if(!empty($request->semester)){
                $routines->where('semester_id', $request->semester);
            }
            if(!empty($request->section)){
                $routines->where('section_id', $request->section);
            }
            $data['rows'] = $routines->orderBy('start_time', 'asc')->get();
        }

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = '0';
            $program = null;
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
            $session = null;
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = '0';
            $semester = null;
        }

        if(!empty($request->semester_year) || $request->semester_year != null){
            $data['selected_semester_year'] = $selected_semester_year = $request->semester_year;
        }
        else{
            $data['selected_semester_year'] = $selected_semester_year = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = '0';
            $section = null;
        }


        // Search Filter
        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['semesterOptions'] = [];
        $data['sections'] = collect();
        $data['subjects'] = collect();
        $data['faculties'] = Faculty::where('status', '1')->orderBy('title', 'asc')->get();

        if(!empty($faculty)){
            $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();
        }

        if(!empty($program)){
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
            $data['semesters'] = $semesterCollection;
            $data['semesterOptions'] = $semesterCollection
                ->filter(function ($semesterItem) {
                    return !is_null($semesterItem->year);
                })
                ->groupBy('year')
                ->sortKeys()
                ->map(function ($items) {
                    return $items->map(function ($semesterItem) {
                        return [
                            'id' => $semesterItem->id,
                            'title' => $semesterItem->title,
                        ];
                    })->values();
                })
                ->toArray();
        }

        if(!empty($program) && !empty($semester)){
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();

            if(!empty($section)){
                $subjects = Subject::where('status', 1);
                $subjects->with('subjectEnrolls')->whereHas('subjectEnrolls', function ($query) use ($program, $semester, $section){
                    $query->where('program_id', $program);
                    $query->where('semester_id', $semester);
                    $query->where('section_id', $section);
                });
                $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
            }
        }


        $data['rooms'] = ClassRoom::where('status', '1')->orderBy('title', 'asc')->get();

        $teachers = User::where('status', '1');
        $teachers->with('roles')->whereHas('roles', function ($query){
            $query->where('slug', 'teacher');
        });
        $data['teachers'] = $teachers->orderBy('staff_id', 'asc')->get();


        // Routine Filter
        if(!empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section)){

            $routines = ClassRoutine::where('status', '1');

            if(!empty($request->program)){
                $routines->where('program_id', $request->program);
            }
            if(!empty($request->session)){
                $routines->where('session_id', $request->session);
            }
            if(!empty($request->semester)){
                $routines->where('semester_id', $request->semester);
            }
            if(!empty($request->section)){
                $routines->where('section_id', $request->section);
            }
            $data['rows'] = $routines->orderBy('start_time', 'asc')->get();
        }

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'session' => 'required',
            'program' => 'required',
            'semester' => 'required',
            'section' => 'required',
            'subject' => 'required',
            'teacher' => 'required',
            'room' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        DB::beginTransaction();

        if($request->subject){
            $data = $request->except('_token');
            $subject_count = count($data['subject']);
            $day = $request->day;
            $program = $request->program;
            $session = $request->session;
            $section = $request->section;
            $semester = $request->semester;


            for($j = 0; $j < $subject_count; $j++){
                $start = $data['start_time'][$j];
                $end = $data['end_time'][$j];
                // Check Routine
                /*$check = ClassRoutine::where('subject_id', $data['subject'][$j])->where('teacher_id', $data['teacher'][$j])->where('session_id', $session)->where('program_id', $program)->where('semester_id', $semester)->where('section_id', $section)
                ->where('room_id', $data['room'][$j])->where('day', $day)
                ->whereBetween('start_time', [$start, $end])
                ->orwhereBetween('end_time', [$start, $end])
                ->first();*/

                //Teacher Check - Allow same teacher at same time for different programs (joint classes)
                //Only block if same teacher, same time, same day, AND same program/semester/section
                $teacher_check = ClassRoutine::where('teacher_id', $data['teacher'][$j])
                ->where('session_id', $session)
                ->where('program_id', $program)
                ->where('semester_id', $semester)
                ->where('section_id', $section)
                ->where('start_time', $start)
                ->where('day', $day)
                ->first();

                //Room Check - Allow same room at same time for different programs (joint classes)
                //Only block if same room, same time, same day, AND same program/semester/section
                $room_check = ClassRoutine::where('room_id', $data['room'][$j])
                ->where('session_id', $session)
                ->where('program_id', $program)
                ->where('semester_id', $semester)
                ->where('section_id', $section)
                ->where('start_time', $start)
                ->where('day', $day)
                ->first();

                //Period Check
                $period_check = ClassRoutine::where('session_id', $session)->where('program_id', $program)->where('semester_id', $semester)->where('section_id', $section)
                ->where('start_time', $start)
                ->where('day', $day)
                ->first();

                //Subject Check
                /*$subject_check = ClassRoutine::where('subject_id', $data['subject'][$j])->where('session_id', $session)->where('program_id', $program)->where('semester_id', $semester)->where('section_id', $section)
                ->where('day', $day)
                ->first();*/


                if(!empty($data['routine_id'][$j]))
                {
                    // Update Routine
                    $classRoutine = ClassRoutine::find($data['routine_id'][$j]);
                    $classRoutine->subject_id = $data['subject'][$j];
                    $classRoutine->teacher_id = $data['teacher'][$j];
                    $classRoutine->room_id= $data['room'][$j];
                    $classRoutine->session_id = $session;
                    $classRoutine->program_id = $program;
                    $classRoutine->semester_id = $semester;
                    $classRoutine->section_id = $section;
                    $classRoutine->start_time= $data['start_time'][$j];
                    $classRoutine->end_time= $data['end_time'][$j];
                    $classRoutine->day= $day;
                    $classRoutine->save();

                    Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
                }
                else{
                    // Create Routine
                    if(!empty($teacher_check) || !empty($room_check) || !empty($period_check))
                    {
                        Flasher::addError(__('msg_data_already_exists'), __('msg_error'));
                    }
                    else{
                        $classRoutine = new ClassRoutine;
                        $classRoutine->subject_id = $data['subject'][$j];
                        $classRoutine->teacher_id = $data['teacher'][$j];
                        $classRoutine->room_id= $data['room'][$j];
                        $classRoutine->session_id = $session;
                        $classRoutine->program_id = $program;
                        $classRoutine->semester_id = $semester;
                        $classRoutine->section_id = $section;
                        $classRoutine->start_time= $data['start_time'][$j];
                        $classRoutine->end_time= $data['end_time'][$j];
                        $classRoutine->day= $day;
                        $classRoutine->save();

                        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
                    }

                }
            }

            // Delete Routine
            if(!empty($request->delete_routine) && isset($request->delete_routine)){
            $delete_routine_count = count($data['delete_routine']);
            for($i = 0; $i < $delete_routine_count; $i++)
            {
                $classRoutine = ClassRoutine::find($data['delete_routine'][$i]);
                $classRoutine->delete();

                Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));
            }}
        }

        DB::commit();


        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(ClassRoutine $classRoutine)
    {
        // Delete Data
        $classRoutine->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function teacher(Request $request)
    {
        //
        $data['title'] = trans_choice('module_teacher_routine', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        // Teacher Filter
        $teachers = User::where('status', '1');
        $teachers->with('roles')->whereHas('roles', function ($query){
            $query->where('slug', 'teacher');
        });
        $data['teachers'] = $teachers->orderBy('staff_id', 'asc')->get();


        if(!empty($request->teacher) && $request->teacher != Null){

            $data['selected_staff'] = $request->teacher;

            $session = Session::where('status', '1')->where('current', '1')->first();

            if(isset($session)){
            $data['rows'] = ClassRoutine::where('status', '1')
                        ->where('session_id', $session->id)
                        ->where('teacher_id', $request->teacher)
                        ->orderBy('start_time', 'asc')
                        ->get();
            }
        }
        else {
            $data['selected_staff'] = Null;
        }

        return view($this->view.'.teacher', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function print(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = 'print-setting';

        // View
        $data['print'] = PrintSetting::where('slug', 'class-routine')->firstOrFail();

        // Filter Routine
        if(!empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section)){

            $routines = ClassRoutine::where('status', '1');

            if(!empty($request->program)){
                $routines->where('program_id', $request->program);
            }
            if(!empty($request->session)){
                $routines->where('session_id', $request->session);
            }
            if(!empty($request->semester)){
                $routines->where('semester_id', $request->semester);
            }
            if(!empty($request->section)){
                $routines->where('section_id', $request->section);
            }
            $data['rows'] = $routines->orderBy('start_time', 'asc')->get();
        }


        return view($this->view.'.print', $data);
    }

    /**
     * Show the form for creating joint class routines.
     *
     * @return \Illuminate\Http\Response
     */
    public function jointCreate(Request $request)
    {
        $data['title'] = __('Joint Class Schedule');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        // Get all active faculties
        $data['faculties'] = Faculty::where('status', '1')->orderBy('title', 'asc')->get();

        // Get all active programs grouped by faculty
        $data['all_programs'] = Program::where('status', '1')
            ->with('faculty')
            ->orderBy('faculty_id')
            ->orderBy('title', 'asc')
            ->get();

        // Get current session
        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();

        // Get all semesters
        $data['semesters'] = Semester::where('status', '1')->orderBy('year', 'asc')->orderBy('id', 'asc')->get();

        // Get all sections
        $data['sections'] = Section::where('status', '1')->orderBy('title', 'asc')->get();

        // Get all subjects
        $data['subjects'] = Subject::where('status', '1')->orderBy('code', 'asc')->get();

        // Get all rooms
        $data['rooms'] = ClassRoom::where('status', '1')->orderBy('title', 'asc')->get();

        // Get all teachers
        $teachers = User::where('status', '1');
        $teachers->with('roles')->whereHas('roles', function ($query){
            $query->where('slug', 'teacher');
        });
        $data['teachers'] = $teachers->orderBy('staff_id', 'asc')->get();

        return view($this->view.'.joint', $data);
    }

    /**
     * Store joint class routines for multiple programs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function jointStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'programs' => 'required|array|min:1',
            'programs.*' => 'required|exists:programs,id',
            'session' => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
            'section' => 'required|exists:sections,id',
            'subject' => 'required|exists:subjects,id',
            'teacher' => 'required|exists:users,id',
            'room' => 'required|exists:class_rooms,id',
            'day' => 'required|integer|min:1|max:7',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ], [
            'programs.required' => __('Please select at least one program'),
            'programs.min' => __('Please select at least one program'),
            'programs.*.exists' => __('One or more selected programs are invalid'),
            'session.required' => __('Please select a session'),
            'session.exists' => __('Selected session is invalid'),
            'semester.required' => __('Please select a semester'),
            'semester.exists' => __('Selected semester is invalid'),
            'section.required' => __('Please select a section'),
            'section.exists' => __('Selected section is invalid'),
            'subject.required' => __('Please select a subject'),
            'subject.exists' => __('Selected subject is invalid'),
            'teacher.required' => __('Please select a teacher'),
            'teacher.exists' => __('Selected teacher is invalid'),
            'room.required' => __('Please select a room'),
            'room.exists' => __('Selected room is invalid'),
            'day.required' => __('Please select a day'),
            'day.integer' => __('Invalid day selected'),
            'day.min' => __('Invalid day selected'),
            'day.max' => __('Invalid day selected'),
            'start_time.required' => __('Please enter start time'),
            'start_time.date_format' => __('Invalid start time format'),
            'end_time.required' => __('Please enter end time'),
            'end_time.date_format' => __('Invalid end time format'),
            'end_time.after' => __('End time must be after start time'),
        ]);

        DB::beginTransaction();

        try {
            $programs = $request->programs;
            $session = $request->session;
            $semester = $request->semester;
            $section = $request->section;
            $subject = $request->subject;
            $teacher = $request->teacher;
            $room = $request->room;
            $day = $request->day;
            $start_time = $request->start_time;
            $end_time = $request->end_time;

            $created = 0;
            $skipped = 0;
            $skippedPrograms = [];
            $errors = [];

            foreach ($programs as $program) {
                try {
                    // Verify program still exists and is active
                    $programModel = Program::where('id', $program)->where('status', '1')->first();
                    if (!$programModel) {
                        $errors[] = "Program #$program not found or inactive";
                        continue;
                    }

                    // Check if this exact routine already exists for this program
                    $exists = ClassRoutine::where('session_id', $session)
                        ->where('program_id', $program)
                        ->where('semester_id', $semester)
                        ->where('section_id', $section)
                        ->where('day', $day)
                        ->where('start_time', $start_time)
                        ->first();

                    if ($exists) {
                        $skipped++;
                        $skippedPrograms[] = $programModel->title;
                        continue;
                    }

                    // Create the routine for this program
                    $classRoutine = new ClassRoutine;
                    $classRoutine->subject_id = $subject;
                    $classRoutine->teacher_id = $teacher;
                    $classRoutine->room_id = $room;
                    $classRoutine->session_id = $session;
                    $classRoutine->program_id = $program;
                    $classRoutine->semester_id = $semester;
                    $classRoutine->section_id = $section;
                    $classRoutine->start_time = $start_time;
                    $classRoutine->end_time = $end_time;
                    $classRoutine->day = $day;
                    $classRoutine->save();

                    $created++;
                } catch (\Exception $e) {
                    $programTitle = isset($programModel) ? $programModel->title : "Program #$program";
                    $errors[] = "Failed to create routine for $programTitle: " . $e->getMessage();
                    \Log::error("Joint class routine creation failed for program $program: " . $e->getMessage());
                }
            }

            DB::commit();

            // Show appropriate messages
            if ($created > 0) {
                Flasher::addSuccess(__('Joint class scheduled for') . ' ' . $created . ' ' . __('program(s) successfully'), __('msg_success'));
            }

            if ($skipped > 0) {
                $skippedList = count($skippedPrograms) > 5 
                    ? implode(', ', array_slice($skippedPrograms, 0, 5)) . ' (+' . (count($skippedPrograms) - 5) . ' more)'
                    : implode(', ', $skippedPrograms);
                Flasher::addWarning(__('Skipped') . ' ' . $skipped . ' ' . __('program(s) - already scheduled') . ': ' . $skippedList, __('msg_warning'));
            }

            if (count($errors) > 0) {
                foreach (array_slice($errors, 0, 3) as $error) {
                    Flasher::addError($error, __('msg_error'));
                }
                if (count($errors) > 3) {
                    Flasher::addError(__('And') . ' ' . (count($errors) - 3) . ' ' . __('more errors occurred'), __('msg_error'));
                }
            }

            // If nothing was created and there were no skips, show a general error
            if ($created === 0 && $skipped === 0 && count($errors) === 0) {
                Flasher::addError(__('No routines were created. Please check your selections.'), __('msg_error'));
            }

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Joint class routine creation failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            Flasher::addError(__('Error creating joint class') . ': ' . $e->getMessage(), __('msg_error'));
        }

        return redirect()->back()->withInput();
    }
}
