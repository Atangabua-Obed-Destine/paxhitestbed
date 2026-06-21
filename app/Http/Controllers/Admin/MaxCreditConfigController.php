<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Program;
use App\Models\ProgramSessionMaxCredit;
use App\Models\Session;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

class MaxCreditConfigController extends Controller
{
    protected string $title;
    protected string $route;
    protected string $view;
    protected string $access;

    public function __construct()
    {
        $this->title = __('Max Credits Config');
        $this->route = 'admin.max-credit-config';
        $this->view = 'admin.max-credit-config';
        $this->access = 'student-enroll';

        $this->middleware('permission:' . $this->access . '-single|' . $this->access . '-group|' . $this->access . '-adddrop');
    }

    public function index(Request $request)
    {
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $faculties = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $programs = Program::where('status', '1')->orderBy('title', 'asc')->get();
        $sessions = Session::where('status', '1')->orderBy('id', 'desc')->get();

        $configsQuery = ProgramSessionMaxCredit::with(['faculty', 'program', 'session'])->orderBy('id', 'desc');

        if ($request->filled('faculty')) {
            $configsQuery->where('faculty_id', (int) $request->faculty);
        }

        if ($request->filled('program')) {
            $configsQuery->where('program_id', (int) $request->program);
        }

        if ($request->filled('session')) {
            $configsQuery->where('session_id', (int) $request->session);
        }

        $configs = $configsQuery->get();

        return view($this->view . '.index', [
            'title' => $this->title,
            'route' => $this->route,
            'faculties' => $faculties,
            'programs' => $programs,
            'sessions' => $sessions,
            'configs' => $configs,
            'filters' => [
                'faculty' => $request->input('faculty', ''),
                'program' => $request->input('program', ''),
                'session' => $request->input('session', ''),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'session_id' => ['required', 'integer', 'exists:sessions,id'],
            'max_credit_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $program = Program::findOrFail($validated['program_id']);

        if ((int) $program->faculty_id !== (int) $validated['faculty_id']) {
            return redirect()->back()->withInput()->withErrors([
                'program_id' => __('The selected program does not belong to the chosen faculty.'),
            ]);
        }

        $limit = $validated['max_credit_hours'];
        $limit = ($limit === null || (float) $limit <= 0) ? null : (int) ceil($limit);

        ProgramSessionMaxCredit::updateOrCreate(
            [
                'faculty_id' => (int) $validated['faculty_id'],
                'program_id' => (int) $validated['program_id'],
                'session_id' => (int) $validated['session_id'],
            ],
            [
                'max_credit_hours' => $limit,
            ]
        );

        Flasher::addSuccess(__('Maximum credit configuration saved.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    public function update(Request $request, ProgramSessionMaxCredit $maxCreditConfig)
    {
        $validated = $request->validate([
            'max_credit_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $limit = $validated['max_credit_hours'];
        $limit = ($limit === null || (float) $limit <= 0) ? null : (int) ceil($limit);

        $maxCreditConfig->update([
            'max_credit_hours' => $limit,
        ]);

        Flasher::addSuccess(__('Maximum credit configuration updated.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    public function destroy(ProgramSessionMaxCredit $maxCreditConfig)
    {
        $maxCreditConfig->delete();

        Flasher::addSuccess(__('Maximum credit configuration removed.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }
}
