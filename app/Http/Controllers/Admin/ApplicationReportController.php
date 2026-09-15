<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ApplicationReportExport;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\ApplicationDemandReport;
use App\Services\LetterheadService;
use App\Support\ApplicationListFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The admissions board report: demand for every programme, from the
 * applications matching the filters on the applications list.
 *
 * Anyone who can open the applications list can export it — it reports on
 * nothing that list does not already show.
 */
class ApplicationReportController extends Controller
{
    protected ApplicationDemandReport $report;

    public function __construct(ApplicationDemandReport $report)
    {
        $this->report = $report;

        $this->middleware('permission:application-view|application-create|application-edit|application-delete');
    }

    /** The report as a document, for printing and handing round the board. */
    public function pdf(Request $request)
    {
        $report = $this->reportData($request);
        $letterhead = app(LetterheadService::class);

        // Rendered for PDF: filesystem image paths, which dompdf can read,
        // rather than URLs, which it cannot fetch.
        //
        // Font subsetting embeds only the characters the report uses. Without
        // it dompdf embeds both DejaVu faces whole, and the report came to
        // 1.2 MB — heavy for a document that gets emailed round a board.
        $pdf = Pdf::loadView('admin.application.report.pdf', [
            'report' => $report,
            'letterhead' => $letterhead->render(true),
            'letterheadStyles' => $letterhead->styles(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download($this->filename() . '.pdf');
    }

    /** The same figures as a workbook, for anyone who wants to work with them. */
    public function excel(Request $request)
    {
        return Excel::download(new ApplicationReportExport($this->reportData($request)), $this->filename() . '.xlsx');
    }

    public function reportData(Request $request): array
    {
        $filter = ApplicationListFilter::fromRequest($request);

        $applications = $filter
            ->query(null, ['admissionFee.paymentReceipts', 'degreeType', 'session', 'approvals'])
            ->orderBy('registration_no')
            ->get();

        // Unfinished drafts under the same filters, shown beside the demand as
        // interest that has not become an application — unless drafts are what
        // the form is already asking about.
        $drafts = $filter->reportingOnDrafts()
            ? null
            : $filter->query('draft', [])->get(['id', 'first_program_choice_id']);

        $minClass = (int) $request->input('min_class', ApplicationDemandReport::DEFAULT_MIN_CLASS);
        $minClass = max(1, min(500, $minClass));

        $filters = $filter->describe();
        $filters[__('Minimum class size')] = trans_choice(':count first-choice applicant|:count first-choice applicants', $minClass, ['count' => $minClass]);

        return $this->report->build(
            $applications,
            $drafts,
            Program::with('faculty')->get(),
            $minClass,
            $filters,
            $filter->degreeTypeId()
        );
    }

    protected function filename(): string
    {
        return 'admissions-demand-report-' . now()->format('Y-m-d');
    }
}
