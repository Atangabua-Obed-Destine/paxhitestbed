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
use App\Services\Academic\ClassTimetable;
use App\Exports\ClassTimetableExport;
use Maatwebsite\Excel\Facades\Excel;

class ClassRoutineController extends Controller
{
    protected $title, $route, $view, $path, $access;

    /**
     * What a timetable row has to drag along to be printable.
     *
     * Named once because five methods load the same rows, and a missing
     * relation here costs one query per class on a 400-row session.
     */
    protected $relations = ['subject', 'teacher', 'room', 'program', 'semester', 'section'];

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


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-print', ['only' => ['index','show','overview']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store','destroy']]);
        $this->middleware('permission:'.$this->access.'-teacher', ['only' => ['teacher']]);
        // Printing and exporting both produce a document that leaves the
        // screen, so they sit behind the same permission.
        $this->middleware('permission:'.$this->access.'-print', ['only' => ['print','export']]);
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
            // The academic year in progress is what an admin almost always
            // wants, so it is offered rather than an empty dropdown. A
            // session named in the request always wins over this default.
            $currentSession = Session::where('status', '1')->where('current', '1')->first();
            $data['selected_session'] = $session = $currentSession->id ?? '0';
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

        // With no programme chosen the cascade leaves the Session dropdown
        // empty, which hides the current-session default until two other
        // dropdowns have been used. The sessions are listed straight away so
        // the default is visible on arrival; choosing a programme narrows the
        // list to the sessions that programme actually runs in.
        if(empty($program)){
            $data['sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();
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
            $data['rows'] = $routines->with($this->relations)
                ->orderBy('start_time', 'asc')->get();

            $data = array_merge($data, $this->readingOf($data['rows'], $request->session));
        }

        return view($this->view.'.index', $data);
    }

    /**
     * Everything the screen needs to show a timetable and say what is wrong.
     *
     * Conflicts are judged against the wider session, not just these rows: a
     * room booked at the same hour by another programme is a double-booking
     * that neither timetable can see on its own. The scope is narrowed in the
     * database to the days, rooms and lecturers this timetable actually uses,
     * so the page does not pay for the whole session to find out.
     */
    protected function readingOf($rows, $sessionId): array
    {
        $timetable = new ClassTimetable();

        if ($rows->isEmpty()) {
            return [
                'timetable' => $timetable,
                'grid' => $timetable->grid($rows),
                'conflicts' => [],
                'summary' => $timetable->summary($rows),
            ];
        }

        $scope = ClassRoutine::where('status', '1')
            ->where('session_id', $sessionId)
            ->whereIn('day', $rows->pluck('day')->unique()->values())
            ->where(function ($query) use ($rows) {
                $query->whereIn('id', $rows->pluck('id'))
                    ->orWhereIn('room_id', $rows->pluck('room_id')->filter()->unique()->values())
                    ->orWhereIn('teacher_id', $rows->pluck('teacher_id')->filter()->unique()->values());
            })
            ->with($this->relations)
            ->get();

        $conflicts = $timetable->conflicts($scope);

        return [
            'timetable' => $timetable,
            'grid' => $timetable->grid($rows),
            'conflicts' => $conflicts,
            'summary' => $timetable->summary($rows, $conflicts),
        ];
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

        // A letterhead is optional. This used to be firstOrFail(), so with no
        // class-routine row configured — and there is none — the Print button
        // answered 404 instead of a timetable. The sheet now falls back to the
        // institution's own name and logo, and a configured row still wins.
        $data['print'] = PrintSetting::where('slug', 'class-routine')->first();

        // A printed sheet is one class group's week. Without all four of them
        // named there is no such group, and printing every class in the
        // institution instead would be worse than printing nothing.
        $wanted = ['program' => 'program_id', 'session' => 'session_id',
                   'semester' => 'semester_id', 'section' => 'section_id'];

        $complete = collect($wanted)->keys()
            ->every(fn ($field) => !empty($request->$field));

        if ($complete) {
            $routines = ClassRoutine::where('status', '1');

            foreach ($wanted as $field => $column) {
                $routines->where($column, $request->$field);
            }

            $rows = $routines->with($this->relations)->orderBy('start_time', 'asc')->get();
        } else {
            $rows = ClassRoutine::whereRaw('1 = 0')->get();
        }

        $data['rows'] = $rows;

        $data = array_merge($data, $this->readingOf($rows, $request->session));

        // Named on the sheet itself, because a printed timetable with no
        // programme on it is just a grid of times.
        $data['heading'] = $rows->isNotEmpty()
            ? $data['timetable']->label($rows->first())
            : __('No classes scheduled');
        $data['session_title'] = $rows->first()->session->title ?? '—';

        return view($this->view.'.print', $data);
    }

    /**
     * Every timetable in one session, on one page.
     *
     * The question this answers is the one no existing screen could: is the
     * session ready to teach? The per-timetable screen needs a faculty, a
     * programme, a semester and a section chosen before it shows anything, so
     * finding the programme nobody has scheduled meant opening all of them.
     */
    public function overview(Request $request)
    {
        $data['title'] = __('Timetable Overview');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();

        $current = Session::where('status', '1')->where('current', '1')->first();
        $data['selected_session'] = $request->session ?: ($current->id ?? null);

        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['selected_faculty'] = $request->faculty ?: null;

        $data = array_merge($data, $this->overviewOf(
            $data['selected_session'],
            $data['selected_faculty']
        ));

        $data['current_session'] = $current;

        return view($this->view.'.overview', $data);
    }

    /**
     * The session's timetables, each reduced to one readable line.
     *
     * Shared with the workbook so the screen and the export can never tell
     * the registry two different stories about the same session.
     */
    protected function overviewOf($sessionId, $facultyId = null): array
    {
        $timetable = new ClassTimetable();

        $query = ClassRoutine::where('status', '1')->where('session_id', $sessionId);

        if ($facultyId) {
            $query->whereHas('program', fn ($inner) => $inner->where('faculty_id', $facultyId));
        }

        $rows = $query->with(array_merge($this->relations, ['program.faculty']))
            ->orderBy('start_time', 'asc')->get();
        $conflicts = $timetable->conflicts($rows);

        $groups = $rows->groupBy(fn ($row) =>
            $row->program_id . '-' . $row->semester_id . '-' . $row->section_id);

        $timetables = $groups->map(function ($group) use ($timetable, $conflicts) {
            $first = $group->first();

            return [
                'key' => $first->program_id . '-' . $first->semester_id . '-' . $first->section_id,
                'program' => $first->program,
                'semester' => $first->semester,
                'section' => $first->section,
                'faculty' => $first->program->faculty->title ?? '—',
                'rows' => $group,
                'days' => $timetable->daysUsed($group),
                'summary' => $timetable->summary($group, $conflicts),
            ];
        })->sortBy(function ($entry) {
            // semesters.year is a nullable VARCHAR, so it has to be padded
            // before it is compared or year 10 sorts ahead of year 2.
            return $entry['faculty']
                . '|' . ($entry['program']->title ?? '')
                . '|' . str_pad((string) ($entry['semester']->year ?? '0'), 3, '0', STR_PAD_LEFT)
                . '|' . ($entry['semester']->title ?? '')
                . '|' . ($entry['section']->title ?? '');
        })->values();

        // Programmes with a timetable, against programmes that could have one:
        // the gap is the finding. Reported, never invented — this screen does
        // not schedule anything.
        $programQuery = Program::where('status', '1');

        if ($facultyId) {
            $programQuery->where('faculty_id', $facultyId);
        }

        $allProgrammes = StaffAssignmentService::filterPrograms($programQuery)
            ->with('faculty')->orderBy('title')->get();

        $scheduled = $rows->pluck('program_id')->unique();

        return [
            'timetable' => $timetable,
            'timetables' => $timetables,
            'conflicts' => $conflicts,
            'all_rows' => $rows,
            'unscheduled' => $allProgrammes->whereNotIn('id', $scheduled)->values(),
            'programme_count' => $allProgrammes->count(),
            'totals' => $timetable->summary($rows, $conflicts),
        ];
    }

    /**
     * The whole session as one workbook.
     *
     * An overview sheet, a sheet naming every conflict, and then one formatted
     * grid per timetable — so the registry gets the lot in a single file
     * instead of opening and printing thirty-nine screens one at a time.
     */
    public function export(Request $request)
    {
        // A full session is forty-odd formatted sheets and takes the better
        // part of half a minute to build, which is longer than the default
        // execution limit. Raised for this request only.
        if (!ini_get('safe_mode')) {
            @set_time_limit(300);
        }

        $current = Session::where('status', '1')->where('current', '1')->first();
        $sessionId = $request->session ?: ($current->id ?? null);
        $session = Session::find($sessionId);

        $reading = $this->overviewOf($sessionId, $request->faculty ?: null);

        $faculty = $request->faculty ? Faculty::find($request->faculty) : null;

        $name = 'class-timetable-'
            . str_replace(['/', ' '], '-', strtolower($session->title ?? 'session'))
            . ($faculty ? '-' . str_replace(' ', '-', strtolower($faculty->shortcode ?? $faculty->id)) : '')
            . '-' . now()->format('Ymd-Hi') . '.xlsx';

        return Excel::download(new ClassTimetableExport([
            'session' => $session,
            'faculty' => $faculty,
            'generated_at' => now(),
        ] + $reading), $name);
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
