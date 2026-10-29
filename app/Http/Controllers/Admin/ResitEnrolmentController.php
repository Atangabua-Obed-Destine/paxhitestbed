<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Resit\ResitEnrolmentBackfill;
use App\Services\Resit\ResitInheritanceAudit;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Raising resit enrolments for courses students carried over.
 *
 * A student who did not validate a course sits it again, and the mark needs an
 * enrolment to live on. It cannot go on the original one -- that carries the
 * fail, and the transcript must go on showing the course was registered in the
 * year it was first taken -- so the sitting gets its own enrolment in the resit
 * semester, holding only the carried-over courses.
 *
 * Until now the only way to get one was for the student to request it and the
 * workflow to approve it. A student who declined, or a cohort being settled
 * after a remedial assessment the school ran itself, had no route. This is that
 * route.
 *
 * The screen is a WORKLIST rather than a student picker: the registrar's
 * question is "who is owed a resit enrolment", not "what does this one student
 * owe". Everything it offers is read from OutstandingCourses, so it cannot
 * disagree with the carry-over list, the progression modal or the Senate
 * reports about what a student still owes.
 *
 * Nothing is written by a GET. The preview says exactly what the apply would
 * do, including that no fee is raised and the matricule is reused, and names
 * any resit request whose state it would overwrite.
 */
class ResitEnrolmentController extends Controller
{
    protected $title, $route, $view, $path, $access;

    public function __construct(
        protected ResitEnrolmentBackfill $backfill,
        protected ResitInheritanceAudit $audit
    ) {
        $this->title = __('Resit Enrolments');
        $this->route = 'admin.resit-enrolment';
        $this->view = 'admin.resit-enrolment';
        $this->path = 'student';
        $this->access = 'student-enroll-resit';

        $this->middleware('permission:' . $this->access);
    }

    /**
     * Who is owed a resit enrolment.
     *
     * Read-only. The list is built per student rather than per enrolment so a
     * student who owes courses from both semesters appears once with both.
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['programs'] = Program::where('status', '1')->orderBy('title', 'asc')->get();
        $data['semesters'] = Semester::where('status', '1')->where('is_resit', 0)
            ->orderBy('year', 'asc')->orderBy('semester_type', 'asc')->get();

        $data['selected_program'] = $request->program;
        $data['selected_semester'] = $request->semester;
        $data['search'] = trim((string) $request->search);

        // Only students with an enrolment worth checking, so the worklist does
        // not walk every student on the installation to find a handful.
        $students = Student::where('status', '1')
            ->whereHas('enrolls', function ($query) use ($request) {
                $query->whereHas('semester', fn ($q) => $q->where('is_resit', 0));

                if (!empty($request->program) && $request->program != '0') {
                    $query->where('program_id', $request->program);
                }

                if (!empty($request->semester) && $request->semester != '0') {
                    $query->where('semester_id', $request->semester);
                }
            })
            ->when($data['search'] !== '', function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->where('student_id', 'LIKE', '%' . $data['search'] . '%')
                        ->orWhere('first_name', 'LIKE', '%' . $data['search'] . '%')
                        ->orWhere('last_name', 'LIKE', '%' . $data['search'] . '%');
                });
            })
            ->with('program')
            ->orderBy('student_id', 'asc')
            ->get();

        $rows = collect();

        foreach ($students as $student) {
            foreach ($this->backfill->worklistFor($student) as $item) {
                if (!empty($request->semester) && $request->semester != '0'
                    && (int) $item['origin']->semester_id !== (int) $request->semester) {
                    continue;
                }

                if (!empty($request->program) && $request->program != '0'
                    && (int) $item['origin']->program_id !== (int) $request->program) {
                    continue;
                }

                $rows->push($item + ['student' => $student]);
            }
        }

        $data['rows'] = $rows;
        $data['course_total'] = $rows->sum('course_count');

        // Sittings already raised that never received what they inherit. This
        // is a different population from the worklist above — those students
        // have no enrolment yet, these have one that is missing its marks —
        // so it is reported separately rather than mixed into the same table.
        $data['gaps'] = $this->audit->affected();

        return view($this->view . '.index', $data);
    }

    /**
     * Carry the inheritance over for sittings that never received it.
     *
     * The repair is the same call the enrolment routes make, so a sitting put
     * right here is indistinguishable from one raised correctly. It only ever
     * adds what is missing — every step of the inheritance checks before it
     * writes — so it cannot overwrite a mark somebody has since entered.
     */
    public function repairInheritance(Request $request)
    {
        $request->validate([
            'enrolment_id' => 'nullable|integer|exists:student_enrolls,id',
        ]);

        // Repair only what the detector itself reports. Taking the id straight
        // from the form would let a stale page, or a hand-made request, run an
        // inheritance over an enrolment nobody has judged to need one.
        $affected = $this->audit->affected(
            $request->filled('enrolment_id') ? [(int) $request->enrolment_id] : null
        );

        if ($affected->isEmpty()) {
            Flasher::addInfo(
                __('Nothing to repair: every resit sitting already carries what it inherits.'),
                __('msg_info')
            );

            return redirect()->route($this->route . '.index');
        }

        $sittings = 0;
        $courses = 0;

        try {
            foreach ($affected as $row) {
                $result = $this->audit->repair($row);

                if ($result['repaired'] > 0) {
                    $sittings++;
                    $courses += $result['repaired'];
                }
            }
        } catch (\Throwable $e) {
            Log::error('Resit inheritance could not be repaired', [
                'enrolment' => $request->enrolment_id,
                'error' => $e->getMessage(),
            ]);

            Flasher::addError(__('msg_updated_error'), __('msg_error'));

            return redirect()->route($this->route . '.index');
        }

        if ($sittings === 0) {
            Flasher::addWarning(
                __('Nothing could be carried over. The semester these sittings shadow holds no continuous assessment to inherit.'),
                __('msg_warning')
            );

            return redirect()->route($this->route . '.index');
        }

        Flasher::addSuccess(__(':courses course(s) across :sittings resit sitting(s) now carry the attendance and CA mark from the semester they shadow.', [
            'courses' => $courses,
            'sittings' => $sittings,
        ]), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    /**
     * What raising it would do. Read-only, and the whole interaction.
     */
    public function show(Request $request, $id)
    {
        $origin = StudentEnroll::with(['student', 'program', 'semester', 'session', 'section'])
            ->findOrFail($id);

        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['preview'] = $this->backfill->preview($origin, $this->selectedSubjects($request));
        $data['origin'] = $origin;

        return view($this->view . '.preview', $data);
    }

    /**
     * Raise it.
     */
    public function store(Request $request)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:student_enrolls,id',
            'subjects' => 'nullable|array',
            'subjects.*' => 'integer|exists:subjects,id',
        ]);

        $origin = StudentEnroll::with(['student', 'semester'])->findOrFail($request->enrollment_id);

        try {
            $result = $this->backfill->apply(
                $origin,
                $this->selectedSubjects($request),
                Auth::guard('web')->user()->id
            );

            if ($result['registered'] === [] && $result['requests'] === 0) {
                Flasher::addInfo(
                    __('Nothing to do: every outstanding course is already registered on the resit enrolment.'),
                    __('msg_info')
                );

                return redirect()->route($this->route . '.index');
            }

            Flasher::addSuccess(__('Resit enrolment raised for :student with :count course(s), in :semester. No fee was charged.', [
                'student' => $origin->matricule ?? optional($origin->student)->student_id,
                'count' => count($result['registered']),
                'semester' => optional($result['enrolment']->semester)->title,
            ]), __('msg_success'));
        } catch (\RuntimeException $e) {
            // Raised by the service when the configuration or the student's
            // situation refuses the whole thing. It is something the registrar
            // can act on, so it is reported as itself.
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back();
        } catch (\Throwable $e) {
            Log::error('Resit enrolment could not be raised', [
                'enrolment' => $origin->id,
                'error' => $e->getMessage(),
            ]);

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back();
        }

        return redirect()->route($this->route . '.index');
    }

    /**
     * The courses the admin ticked, or null for every outstanding one.
     *
     * An empty array and "no selection at all" mean different things: the first
     * is a form submitted with nothing ticked, which must not quietly become
     * "all of them".
     */
    protected function selectedSubjects(Request $request): ?array
    {
        if (!$request->has('subjects')) {
            return null;
        }

        return collect($request->input('subjects', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
