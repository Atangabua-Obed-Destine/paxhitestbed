<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CourseClassListExport;
use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Services\Academic\CourseRoster;
use App\Services\LetterheadService;
use App\Support\CourseClassFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A lecturer's own course: who is in it, and how they have been attending.
 *
 * The two older screens each answer half of this and both force a programme,
 * which splits a service course into one list per programme -- CPT 127 is one
 * register taught to twelve of them. The Course Students report additionally
 * filters enrolment status = '1', which empties the list for any course whose
 * students have since moved up a semester: 42 subject+semester pairs on this
 * install, all with real attendance behind them. Neither screen is changed;
 * registry still uses both.
 *
 * Granted on either of the two permissions a lecturer already holds, so nobody
 * has to be given anything new to use it, and nobody who could not already see
 * a class list gains one.
 */
class CourseClassListController extends Controller
{
    protected $title;
    protected $route;
    protected $view;
    protected $path;

    public function __construct()
    {
        $this->title = __('Course Class List');
        $this->route = 'admin.course-class-list';
        $this->view = 'admin.course-class-list';
        $this->path = 'course-class-list';

        // Either permission opens it: both the class list and the attendance
        // report already show this data to whoever holds one of them, so this
        // screen widens nobody's reach. The subject itself is checked again in
        // CourseClassFilter::authorise(), because a permission says which
        // SCREEN you may open, not which COURSE is yours.
        $this->middleware('permission:report-subject-students|student-attendance-report');
    }

    public function index(Request $request)
    {
        $data = $this->common();
        $filter = CourseClassFilter::fromRequest($request);

        $data = array_merge($data, $filter->selected);
        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();
        $data['subjects'] = $filter->subjects();
        $data['classes'] = $filter->classesForSubject();
        $data['filter'] = $filter;

        if ($filter->isSearching()) {
            $subject = $filter->authorise();
            $data['subject'] = $subject;
            $data['report'] = app(CourseRoster::class)->build($filter, $subject);
            $data['query'] = $filter->toQuery();
        }

        return view($this->view . '.index', $data);
    }

    public function pdf(Request $request)
    {
        [$report, $filter] = $this->reportData($request);
        $letterhead = app(LetterheadService::class);

        // Filesystem image paths rather than URLs, which dompdf cannot fetch;
        // font subsetting so a long register does not embed both DejaVu faces
        // whole. Landscape because the attendance strip is the wide part.
        $pdf = Pdf::loadView($this->view . '.pdf', [
            'report' => $report,
            'letterhead' => $letterhead->render(true),
            'letterheadStyles' => $letterhead->styles(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download($this->filename($report) . '.pdf');
    }

    public function excel(Request $request)
    {
        [$report] = $this->reportData($request);

        return Excel::download(
            new CourseClassListExport($report),
            $this->filename($report) . '.xlsx'
        );
    }

    /**
     * The same array the screen reads, built the same way.
     *
     * A document that recomputed its own figures would agree with the screen
     * today and drift the first time either changed.
     */
    protected function reportData(Request $request): array
    {
        $filter = CourseClassFilter::fromRequest($request);

        abort_unless($filter->isSearching(), 404, __('Choose a course first.'));

        $subject = $filter->authorise();

        return [app(CourseRoster::class)->build($filter, $subject), $filter];
    }

    protected function filename(array $report): string
    {
        $code = $report['subject']->code ?? 'course';

        return 'class-list-' . preg_replace('~[^A-Za-z0-9]+~', '-', $code) . '-' . now()->format('Y-m-d');
    }

    protected function common(): array
    {
        return [
            'title' => $this->title,
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
        ];
    }
}
