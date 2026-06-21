<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Models\ExamRoutine;
use App\Models\ClassRoutine;
use App\Models\ClassRoom;
use App\Models\ExamType;
use App\Models\Semester;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Session;
use App\Models\Program;
use App\Models\Section;
use App\Models\Subject;
use App\User;

class ExamRoutineController extends Controller
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
        $this->title = trans_choice('module_exam_routine', 1);
        $this->route = 'admin.exam-routine';
        $this->view = 'admin.exam-routine';
        $this->path = 'exam-routine';
        $this->access = 'exam-routine';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete|'.$this->access.'-print', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store','edit','update']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
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

        // Get current session and teacher's assigned courses for quick access
        $data['myAssignedCourses'] = $this->getMyAssignedCourses();
        $data['examTypes'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['currentSession'] = Session::where('status', '1')->where('current', '1')->first();

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

        if(!empty($request->type) || $request->type != null){
            $data['selected_type'] = $type = $request->type;
        }
        else{
            $data['selected_type'] = '0';
        }


        $data['print'] = PrintSetting::where('slug', 'exam-routine')->first();


        // Filter Search
        $data['types'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['semesterOptions'] = [];
        $data['sections'] = collect();
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

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
        }


        // Filter Routine
        if(!empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section) && !empty($request->type)){

            $routines = ExamRoutine::where('status', '1');

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
            if(!empty($request->type)){
                $routines->where('exam_type_id', $request->type);
            }

            $data['rows'] = $routines->orderBy('date', 'asc')
                            ->orderBy('start_time', 'asc')->get();
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

        if(!empty($request->type) || $request->type != null){
            $data['selected_type'] = $type = $request->type;
        }
        else{
            $data['selected_type'] = '0';
        }


        // Filter Routine
        $data['subjects'] = collect();
        if(!empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section) && !empty($request->type)){

            $routines = ExamRoutine::where('status', '1');

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
            if(!empty($request->type)){
                $routines->where('exam_type_id', $request->type);
            }
            $data['rows'] = $routines->orderBy('date', 'asc')
                            ->orderBy('start_time', 'asc')->get();

            $routine = [];
            foreach($data['rows'] as $row){
                $routine[] = $row->subject_id;
            }

            $subjects = Subject::where('status', 1)->whereNotIn('id', $routine);

            $subjects->with('subjectEnrolls')->whereHas('subjectEnrolls', function ($query) use ($program, $semester, $section){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
                $query->where('section_id', $section);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }


        // Filter Search
        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['semesterOptions'] = [];
        $data['sections'] = collect();
        $data['editSubjects'] = collect();

        if(!empty($faculty))
        {
            $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();
        }

        if(!empty($program))
        {
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

        if(!empty($program) && !empty($semester))
        {
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();

            if(!empty($section)){
                $editSubjects = Subject::where('status', 1);
                $editSubjects->with('subjectEnrolls')->whereHas('subjectEnrolls', function ($query) use ($program, $semester, $section){
                    $query->where('program_id', $program);
                    $query->where('semester_id', $semester);
                    $query->where('section_id', $section);
                });
                $data['editSubjects'] = $editSubjects->orderBy('code', 'asc')->get();
            }
        }


        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['types'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['rooms'] = ClassRoom::where('status', '1')->orderBy('title', 'asc')->get();

        $teachers = User::where('status', '1');
        $teachers->with('roles')->whereHas('roles', function ($query){
            $query->where('slug', 'teacher');
        });
        $data['teachers'] = $teachers->orderBy('staff_id', 'asc')->get();


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
            'start_time' => 'required',
            'end_time' => 'required',
            'type' => 'required',
            'date' => 'required|date|after_or_equal:today',
            'teachers' => 'required',
            'rooms' => 'required',
        ]);


        DB::beginTransaction();
        // Insert Data
        $examRoutine = new ExamRoutine;
        $examRoutine->subject_id = $request->subject;
        $examRoutine->exam_type_id = $request->type;
        $examRoutine->session_id = $request->session;
        $examRoutine->program_id = $request->program;
        $examRoutine->semester_id = $request->semester;
        $examRoutine->section_id = $request->section;
        $examRoutine->date = $request->date;
        $examRoutine->start_time= $request->start_time;
        $examRoutine->end_time= $request->end_time;
        $examRoutine->save();


        // Attach Data
        $examRoutine->users()->attach($request->teachers);
        $examRoutine->rooms()->attach($request->rooms);
        DB::commit();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
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
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        $request->validate([
            'session' => 'required',
            'program' => 'required',
            'semester' => 'required',
            'section' => 'required',
            'subject' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
            'type' => 'required',
            'date' => 'required|date|after_or_equal:today',
            'teachers' => 'required',
            'rooms' => 'required',
        ]);


        DB::beginTransaction();
        // Update Data
        $examRoutine = ExamRoutine::findOrFail($id);
        $examRoutine->subject_id = $request->subject;
        $examRoutine->exam_type_id = $request->type;
        $examRoutine->date = $request->date;
        $examRoutine->start_time= $request->start_time;
        $examRoutine->end_time= $request->end_time;
        $examRoutine->save();


        // Attach Update
        $examRoutine->users()->sync($request->teachers);
        $examRoutine->rooms()->sync($request->rooms);
        DB::commit();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        $examRoutine = ExamRoutine::findOrFail($id);

        // Detach
        $examRoutine->users()->detach();
        $examRoutine->rooms()->detach();

        // Delete Data
        $examRoutine->delete();
        DB::commit();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
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


        $data['print'] = PrintSetting::where('slug', 'exam-routine')->firstOrFail();

        // Filter Routine
        if(!empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section) && !empty($request->type)){

            $routines = ExamRoutine::where('status', '1');

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
            if(!empty($request->type)){
                $routines->where('exam_type_id', $request->type);
            }

            $data['rows'] = $routines->orderBy('date', 'asc')
                            ->orderBy('start_time', 'asc')->get();
        }

        return view($this->view.'.print', $data);
    }

    /**
     * Get courses assigned to the current lecturer via ClassRoutine for the current session.
     * Groups courses by program and year for easy navigation.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getMyAssignedCourses()
    {
        $teacherId = Auth::guard('web')->id();
        $currentSession = Session::where('status', '1')->where('current', '1')->first();
        
        if (!$currentSession) {
            return collect([]);
        }

        // Check if user is super admin or has staff assignments
        $user = User::where('id', $teacherId)->where('status', '1');
        $user->with('roles')->whereHas('roles', function ($query){
            $query->where('slug', 'super-admin');
        });
        $isSuperAdmin = $user->first() !== null;
        $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacherId)->exists();

        // Build query for class routines
        $routinesQuery = ClassRoutine::where('session_id', $currentSession->id)
            ->where('status', '1')
            ->with([
                'subject',
                'program.faculty',
                'semester',
                'section',
            ]);

        // If not super admin and doesn't have staff assignments, filter by teacher
        if (!$isSuperAdmin && !$hasAssignments) {
            $routinesQuery->where('teacher_id', $teacherId);
        }

        // Apply staff assignment filter if user has assignments
        if ($hasAssignments) {
            $assignments = \App\Models\StaffAssignment::where('user_id', $teacherId)->get();
            $allowedFacultyIds = $assignments->pluck('faculty_id')->filter()->unique();
            $allowedProgramIds = $assignments->pluck('program_id')->filter()->unique();
            $allowedSubjectIds = $assignments->pluck('subject_id')->filter()->unique();

            $routinesQuery->where(function($query) use ($allowedFacultyIds, $allowedProgramIds, $allowedSubjectIds) {
                if ($allowedFacultyIds->isNotEmpty()) {
                    $query->whereHas('program', function($q) use ($allowedFacultyIds) {
                        $q->whereIn('faculty_id', $allowedFacultyIds);
                    });
                }
                if ($allowedProgramIds->isNotEmpty()) {
                    $query->orWhereIn('program_id', $allowedProgramIds);
                }
                if ($allowedSubjectIds->isNotEmpty()) {
                    $query->orWhereIn('subject_id', $allowedSubjectIds);
                }
            });
        }

        $routines = $routinesQuery->get();

        // Group by program, then flatten subjects
        $grouped = $routines->groupBy(function($routine) {
            return $routine->program_id;
        })->map(function($programRoutines) {
            $program = $programRoutines->first()->program;
            
            $subjects = $programRoutines->groupBy('subject_id')->map(function($subjectRoutines) {
                $first = $subjectRoutines->first();
                $subject = $first->subject;
                
                $regularSemesters = $subjectRoutines->pluck('semester')->filter()->unique('id')->sortBy(['year', 'semester_type'])->values();
                
                $regularSemesterIds = $regularSemesters->pluck('id')->toArray();
                $resitSemesters = Semester::where('is_resit', true)
                    ->whereIn('parent_semester_id', $regularSemesterIds)
                    ->where('status', 1)
                    ->orderBy('year')
                    ->orderBy('semester_type')
                    ->get();
                
                $allSemesters = $regularSemesters->concat($resitSemesters)->sortBy(['year', 'semester_type', 'is_resit'])->values();
                $years = $allSemesters->pluck('year')->unique()->sort()->values();
                
                $sections = $subjectRoutines->pluck('section')->filter()->unique('id')->values();
                $firstSection = $sections->first();
                $firstSemester = $regularSemesters->first() ?? $allSemesters->first();
                
                return [
                    'subject_id' => $subject->id,
                    'subject_code' => $subject->code,
                    'subject_title' => $subject->title,
                    'semesters' => $allSemesters,
                    'years' => $years,
                    'first_year' => $years->first() ?? 1,
                    'first_semester_id' => $firstSemester->id ?? null,
                    'first_section_id' => $firstSection->id ?? null,
                    'first_section_title' => $firstSection->title ?? 'All',
                    'sections' => $sections,
                ];
            })->sortBy('subject_code')->values();

            return [
                'program_id' => $program->id,
                'program_title' => $program->title,
                'program_code' => $program->short_code ?? $program->title,
                'faculty_id' => $program->faculty_id,
                'faculty_title' => $program->faculty->title ?? 'Unknown Faculty',
                'subjects' => $subjects,
            ];
        })->sortBy('program_title')->values();

        return $grouped;
    }

    /**
     * Show the form for creating joint exam routines.
     *
     * @return \Illuminate\Http\Response
     */
    public function jointCreate()
    {
        $data['title'] = __('Joint Exam Schedule');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        // Get all faculties with staff assignment filtering
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        
        // Get all programs with staff assignment filtering
        $programQuery = Program::where('status', '1')->orderBy('title', 'asc');
        $data['all_programs'] = StaffAssignmentService::filterPrograms($programQuery)->get();

        // Get all sessions
        $data['sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();

        // Get all semesters
        $data['semesters'] = Semester::where('status', 1)->orderBy('year', 'asc')->orderBy('id', 'asc')->get();

        // Get all sections
        $data['sections'] = Section::where('status', 1)->orderBy('title', 'asc')->get();

        // Get all subjects
        $data['subjects'] = Subject::where('status', 1)->orderBy('code', 'asc')->get();

        // Get all exam types
        $data['types'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();

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
     * Store joint exam routines for multiple programs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function jointStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'programs' => 'required|array|min:1',
            'programs.*' => 'exists:programs,id',
            'session' => 'required|exists:sessions,id',
            'semester' => 'required|exists:semesters,id',
            'section' => 'required|exists:sections,id',
            'subject' => 'required|exists:subjects,id',
            'type' => 'required|exists:exam_types,id',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'teachers' => 'required|array|min:1',
            'teachers.*' => 'exists:users,id',
            'rooms' => 'required|array|min:1',
            'rooms.*' => 'exists:class_rooms,id',
        ]);

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->programs as $programId) {
                // Check if this exact routine already exists
                $exists = ExamRoutine::where('program_id', $programId)
                    ->where('session_id', $request->session)
                    ->where('semester_id', $request->semester)
                    ->where('section_id', $request->section)
                    ->where('subject_id', $request->subject)
                    ->where('exam_type_id', $request->type)
                    ->exists();

                if ($exists) {
                    $program = Program::find($programId);
                    $errors[] = ($program->shortcode ?? $program->title) . ': ' . __('Exam routine already exists');
                    $skipped++;
                    continue;
                }

                // Create the exam routine
                $examRoutine = new ExamRoutine;
                $examRoutine->program_id = $programId;
                $examRoutine->session_id = $request->session;
                $examRoutine->semester_id = $request->semester;
                $examRoutine->section_id = $request->section;
                $examRoutine->subject_id = $request->subject;
                $examRoutine->exam_type_id = $request->type;
                $examRoutine->date = $request->date;
                $examRoutine->start_time = $request->start_time;
                $examRoutine->end_time = $request->end_time;
                $examRoutine->save();

                // Attach teachers and rooms
                $examRoutine->users()->attach($request->teachers);
                $examRoutine->rooms()->attach($request->rooms);

                $created++;
            }

            DB::commit();

            if ($created > 0) {
                Flasher::addSuccess(__('Joint exam scheduled for') . ' ' . $created . ' ' . __('program(s) successfully'), __('msg_success'));
            }
            if ($skipped > 0) {
                Flasher::addWarning($skipped . ' ' . __('program(s) skipped') . ': ' . implode(', ', $errors), __('msg_warning'));
            }

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Joint exam routine creation failed: " . $e->getMessage());
            Flasher::addError(__('msg_error') . ': ' . $e->getMessage(), __('msg_error'));
        }

        return redirect()->back();
    }

    /**
     * Display comprehensive exam schedule overview
     * Simplified filters: Session, Year, Semester, Exam Type (default: Final Exam)
     * Optional: Faculty, Program
     */
    public function scheduleOverview(Request $request)
    {
        $data['title'] = __('Exam Schedule Overview');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        // Get all sessions for dropdown (ordered by most recent first)
        $data['sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();
        
        // Get exam types (for dropdown)
        $data['exam_types'] = ExamType::where('status', '1')->orderBy('title', 'asc')->get();
        
        // Find default exam type (Final Exam - is_final = 1)
        $defaultExamType = ExamType::where('is_final', 1)->where('status', '1')->first();
        
        // Get current session as default
        $currentSession = Session::where('status', '1')->where('current', '1')->first();
        
        // Get all faculties
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        // Selected values
        $data['selected_session'] = $session = $request->session ?? ($currentSession->id ?? '0');
        $data['selected_semester_year'] = $semesterYear = $request->semester_year ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_exam_type'] = $examType = $request->exam_type ?? ($defaultExamType->id ?? '0');
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';

        // Get semester years for the selected session
        $data['semester_years'] = [];
        $data['semesters'] = collect();
        $data['programs'] = collect();
        
        if (!empty($session) && $session != '0') {
            // Get all semesters for programs in this session
            $semestersInSession = Semester::where('status', 1)
                ->whereHas('programs', function($q) use ($session) {
                    $q->whereHas('sessions', function($sq) use ($session) {
                        $sq->where('session_id', $session);
                    });
                })
                ->orderBy('year', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            // Group by year
            $semestersByYear = $semestersInSession->filter(fn($s) => !is_null($s->year))->groupBy('year')->sortKeys();
            $data['semester_years'] = $semestersByYear->keys()->toArray();
            
            // If year selected, filter semesters
            if (!empty($semesterYear) && $semesterYear != '0') {
                $data['semesters'] = $semestersInSession->where('year', $semesterYear);
            }
        }
        
        // Get programs if faculty is selected
        if (!empty($faculty) && $faculty != '0') {
            $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();
        }

        // Initialize results
        $data['routines'] = collect();
        $data['routines_by_date'] = [];
        $data['statistics'] = [
            'total_exams' => 0,
            'total_programs' => 0,
            'total_subjects' => 0,
            'total_days' => 0,
            'earliest_date' => null,
            'latest_date' => null,
        ];

        // Build query only if we have required filters
        if (!empty($session) && $session != '0' && !empty($examType) && $examType != '0') {
            $query = ExamRoutine::where('status', '1')
                ->where('session_id', $session)
                ->where('exam_type_id', $examType);

            // Apply optional filters
            if (!empty($semesterYear) && $semesterYear != '0') {
                $query->whereHas('semester', function($q) use ($semesterYear) {
                    $q->where('year', $semesterYear);
                });
            }
            
            if (!empty($semester) && $semester != '0') {
                $query->where('semester_id', $semester);
            }

            if (!empty($faculty) && $faculty != '0') {
                $query->whereHas('program', function($q) use ($faculty) {
                    $q->where('faculty_id', $faculty);
                });
            }

            if (!empty($program) && $program != '0') {
                $query->where('program_id', $program);
            }

            // Get routines with relationships
            $routines = $query->with(['subject', 'program.faculty', 'semester', 'section', 'users', 'rooms'])
                ->orderBy('date', 'asc')
                ->orderBy('start_time', 'asc')
                ->get();

            $data['routines'] = $routines;

            // Group by date for the timeline view
            $data['routines_by_date'] = $routines->groupBy(function($item) {
                return \Carbon\Carbon::parse($item->date)->format('Y-m-d');
            })->sortKeys();

            // Calculate statistics
            if ($routines->count() > 0) {
                $data['statistics']['total_exams'] = $routines->count();
                $data['statistics']['total_programs'] = $routines->pluck('program_id')->unique()->count();
                $data['statistics']['total_subjects'] = $routines->pluck('subject_id')->unique()->count();
                $data['statistics']['total_days'] = $data['routines_by_date']->count();
                $data['statistics']['earliest_date'] = $routines->min('date');
                $data['statistics']['latest_date'] = $routines->max('date');
            }
        }

        return view($this->view.'.schedule-overview', $data);
    }
}

