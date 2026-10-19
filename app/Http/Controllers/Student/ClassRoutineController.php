<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentEnroll;
use App\Models\ClassRoutine;
use App\Models\Session;
use Carbon\Carbon;

class ClassRoutineController extends Controller
{
    protected $title, $route, $view, $path;

    /**
     * The week as this system numbers it: 1 is Saturday, 7 is Friday.
     *
     * The same order the admin timetable uses, kept here so the student screen
     * and the staff screen never disagree about what "day 3" means.
     */
    public const DAYS = [
        1 => 'day_saturday',
        2 => 'day_sunday',
        3 => 'day_monday',
        4 => 'day_tuesday',
        5 => 'day_wednesday',
        6 => 'day_thursday',
        7 => 'day_friday',
    ];

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
                        ->with(['subject', 'room', 'teacher'])
                        ->orderBy('start_time', 'asc')
                        ->get();
        } else {
            $data['rows'] = collect();
        }

        $data['enroll'] = $enroll;
        $data['days'] = static::DAYS;
        $data['today'] = static::todayNumber();

        // One bucket per weekday, so the view never has to filter in a loop and
        // an empty day is a visible fact rather than a blank cell.
        // all(), not toArray(): toArray() recurses and would turn each day's
        // models into plain arrays, and the view reads them as models.
        $data['byDay'] = collect(static::DAYS)
            ->map(fn ($label, $number) => $data['rows']->where('day', (string) $number)
                ->sortBy('start_time')
                ->values())
            ->all();

        $data['nowNext'] = static::nowAndNext($data['rows'], $data['today']);

        // The earliest and latest the week runs, for the day summaries.
        $data['weekStart'] = $data['rows']->min('start_time');
        $data['weekEnd'] = $data['rows']->max('end_time');

        return view($this->view.'.index', $data);
    }

    /**
     * Today, in this system's numbering.
     *
     * PHP counts Sunday as 0 and Saturday as 6; this timetable counts Saturday
     * as 1 and Friday as 7.
     */
    public static function todayNumber(?Carbon $when = null): int
    {
        $weekday = (int) ($when ?? Carbon::now())->format('w');

        return $weekday === 6 ? 1 : $weekday + 2;
    }

    /**
     * The class happening now, and the one after it.
     *
     * Only ever looks at today: a timetable repeats every week, so "next" means
     * next today. When today is finished, the view says so rather than pointing
     * at a class six days away.
     *
     * @return array{current: ?ClassRoutine, next: ?ClassRoutine, done: bool}
     */
    public static function nowAndNext($rows, int $today, ?Carbon $when = null): array
    {
        $when = $when ?? Carbon::now();
        $clock = $when->format('H:i:s');

        $todays = collect($rows)->where('day', (string) $today)->sortBy('start_time')->values();

        if ($todays->isEmpty()) {
            return ['current' => null, 'next' => null, 'done' => false];
        }

        $current = $todays->first(
            fn ($row) => $row->start_time <= $clock && $clock < $row->end_time
        );

        $next = $todays->first(fn ($row) => $row->start_time > $clock);

        return [
            'current' => $current,
            'next' => $next,
            // Every class today has finished.
            'done' => !$current && !$next,
        ];
    }
}
