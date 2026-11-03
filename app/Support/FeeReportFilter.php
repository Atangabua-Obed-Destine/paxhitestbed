<?php

namespace App\Support;

use App\Models\Fee;
use App\Models\FeesCategory;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The filters on the fees report, in one place.
 *
 * The report SCREEN and the two documents it exports both answer "which fees
 * match this form?". Written twice, the two answers drift apart the first time
 * somebody adds a filter to one of them, and a Bursar would hand a board
 * figures that do not match the screen they are reading them off. The screen
 * and both exports therefore read from here.
 *
 * Every rule below is the screen's own, carried over unchanged. Two of them
 * look like mistakes and are not:
 *
 *   * The academic scope is applied only when at least one of faculty,
 *     programme, session, semester or section is NON-EMPTY -- and in PHP the
 *     string "0" IS empty. So the all-zero form that the sidebar link opens
 *     applies no academic constraint at all, rather than constraining to
 *     faculty 0. "Fixing" this would silently empty the report.
 *
 *   * The closing whereHas on studentEnroll.student carries nothing but an
 *     orderBy, so it reads as decoration. It is not: whereHas is a constraint,
 *     and it EXCLUDES every fee whose enrolment has no student record. On this
 *     installation that is 33 fees worth 822,000 -- 31 admission fees raised
 *     against applicants, one more with no applicant either, and one whose
 *     enrolment row no longer exists. That money is real, so outside() counts
 *     it and the exports say so on their face rather than quietly losing it.
 */
class FeeReportFilter
{
    /** What the screen loads for each row. */
    public const RELATIONS = [
        'studentEnroll.student', 'studentEnroll.session', 'studentEnroll.semester',
        'studentEnroll.program.faculty', 'category', 'approvedReceipts', 'paymentPlan',
        'creditApplications', 'generatedCredits', 'resitRequest.subject',
        'resitRequest.session', 'resitRequest.resitSession', 'resitRequest.resitSemester',
    ];

    /** The payment statuses the form offers, in the order it offers them. */
    public const STATUSES = ['0', '1', '2', '3', '4', '5'];

    /** The form values, under the names the report view reads. */
    public array $selected = [];

    protected Request $request;

    protected function __construct(Request $request)
    {
        $this->request = $request;
        $r = $request;

        $this->selected = [
            'selected_faculty' => $this->given('faculty') ? $r->faculty : '0',
            'selected_program' => $this->given('program') ? $r->program : '0',
            'selected_session' => $this->given('session') ? $r->session : '0',
            'selected_semester' => $this->given('semester') ? $r->semester : '0',
            'selected_section' => $this->given('section') ? $r->section : '0',
            'selected_student_id' => $this->given('student_id') ? $r->student_id : null,

            // Fees type: any number of categories. None chosen (or "All")
            // means every category. A single value from an old link still works.
            'selected_category' => collect((array) $r->input('category', []))
                ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),

            // Payment status: any number of statuses, shown if a fee matches
            // any of them. None chosen (or "all") means every status.
            'selected_payment_status' => collect((array) $r->input('payment_status', []))
                ->map(fn ($value) => (string) $value)
                ->filter(fn ($value) => in_array($value, self::STATUSES, true))
                ->unique()->values()->all(),
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request);
    }

    /** The screen's own test for whether a value was given. */
    protected function given(string $key): bool
    {
        $value = $this->request->{$key};

        return !empty($value) || $value != null;
    }

    /** The report stays empty until a search has been made. */
    public function isSearching(): bool
    {
        $r = $this->request;

        return isset($r->faculty) || isset($r->program) || isset($r->session)
            || isset($r->semester) || isset($r->section) || isset($r->category)
            || isset($r->student_id);
    }

    /**
     * The matching fees.
     *
     * @param bool $requireStudent  false drops the student-record constraint,
     *                              so outside() can see what it removes
     */
    public function query(bool $requireStudent = true): Builder
    {
        $r = $this->request;
        $s = $this->selected;

        $fees = Fee::with(self::RELATIONS)->withCreditMovedOut();

        // Paid and overpaid are judged on the net paid amount: overpayment
        // credit applied to another fee counts on that fee, not on this one
        // as well. See Fee::netPaidSql().
        $netPaid = Fee::netPaidSql('fees');
        $netDue = '(fees.fee_amount - COALESCE(fees.discount_amount, 0) + COALESCE(fees.fine_amount, 0))';

        $statusFilters = [
            // Unpaid
            '0' => fn ($q) => $q->where('status', 0),
            // Fully paid but NOT overpaid
            '1' => fn ($q) => $q->where('status', 1)->whereRaw("{$netPaid} <= {$netDue}"),
            // Partially paid
            '2' => fn ($q) => $q->where('status', 2),
            // Cancelled
            '3' => fn ($q) => $q->where('status', 3),
            // On an active payment plan
            '4' => fn ($q) => $q->whereNotNull('payment_plan_id')
                ->whereHas('paymentPlan', fn ($plan) => $plan->where('status', 'active')),
            // Overpaid: still holding an excess that has not been applied to
            // another fee.
            '5' => fn ($q) => $q->whereRaw("{$netPaid} > {$netDue}"),
        ];

        if (!empty($s['selected_payment_status'])) {
            $fees->where(function ($any) use ($s, $statusFilters) {
                foreach ($s['selected_payment_status'] as $status) {
                    $any->orWhere(fn ($q) => $statusFilters[$status]($q));
                }
            });
        } else {
            // All statuses (including status 0 = unpaid)
            $fees->whereIn('status', [0, 1, 2, 3]);
        }

        if (!empty($r->faculty) || !empty($r->program) || !empty($r->session) || !empty($r->semester) || !empty($r->section)) {
            $fees->whereHas('studentEnroll.program', function ($query) use ($s) {
                if ($s['selected_faculty'] != 0) {
                    $query->where('faculty_id', $s['selected_faculty']);
                }
            });

            $fees->whereHas('studentEnroll', function ($query) use ($s) {
                if ($s['selected_program'] != 0) {
                    $query->where('program_id', $s['selected_program']);
                }
                if ($s['selected_session'] != 0) {
                    $query->where('session_id', $s['selected_session']);
                }
                if ($s['selected_semester'] != 0) {
                    $query->where('semester_id', $s['selected_semester']);
                }
                if ($s['selected_section'] != 0) {
                    $query->where('section_id', $s['selected_section']);
                }
            });
        }

        if (!empty($s['selected_category'])) {
            $fees->whereIn('category_id', $s['selected_category']);
        }

        if (!empty($r->student_id)) {
            $studentId = $s['selected_student_id'];

            $fees->whereHas('studentEnroll', function ($query) use ($studentId) {
                if ($studentId != 0) {
                    $query->where('matricule', 'LIKE', '%' . $studentId . '%')
                          ->orWhereHas('student', function ($q) use ($studentId) {
                              $q->where('student_id', 'LIKE', '%' . $studentId . '%');
                          });
                }
            });
        }

        if ($requireStudent) {
            $fees->whereHas('studentEnroll.student', function ($query) {
                $query->orderBy('matricule', 'asc');
            });
        }

        return $fees;
    }

    /**
     * The matching fees in the order the screen lists them: by matricule,
     * which is what a Bursar reads down.
     */
    public function rows(): Collection
    {
        return $this->query()->orderBy('updated_at', 'desc')->get()
            ->sortBy(fn ($row) => $row->studentEnroll->matricule ?? $row->studentEnroll->student->student_id)
            ->values();
    }

    /**
     * What these filters match but the report cannot show, because the fee has
     * no student record behind it.
     *
     * Counted with one aggregate rather than by fetching the rows: this is a
     * footnote, and it must not cost what the report itself costs.
     *
     * @return array{fees:int, billed:float}
     */
    public function outside(): array
    {
        $netDue = '(fees.fee_amount - COALESCE(fees.discount_amount, 0) + COALESCE(fees.fine_amount, 0))';
        $count = "count(*) as fees, COALESCE(SUM({$netDue}), 0) as billed";

        $all = $this->query(false)->toBase()->selectRaw($count)->first();
        $shown = $this->query(true)->toBase()->selectRaw($count)->first();

        return [
            'fees' => (int) $all->fees - (int) $shown->fees,
            'billed' => round((float) $all->billed - (float) $shown->billed, 2),
        ];
    }

    /**
     * The filters in force, in words, for the head of a report -- so a printed
     * copy says what it is a report of.
     *
     * @return array<string, string>
     */
    public function describe(): array
    {
        $s = $this->selected;
        $out = [];

        $title = function (string $model, $id) {
            $row = $model::find($id);

            return $row ? (string) $row->title : __('Not found');
        };

        $out[__('Faculty')] = $s['selected_faculty'] != 0
            ? $title(Faculty::class, $s['selected_faculty']) : __('All faculties');

        $out[__('Programme')] = $s['selected_program'] != 0
            ? $title(Program::class, $s['selected_program']) : __('All programmes');

        $out[__('Session')] = $s['selected_session'] != 0
            ? $title(Session::class, $s['selected_session']) : __('All sessions');

        $out[__('Semester')] = $s['selected_semester'] != 0
            ? $title(Semester::class, $s['selected_semester']) : __('All semesters');

        if ($s['selected_section'] != 0) {
            $out[__('Section')] = $title(Section::class, $s['selected_section']);
        }

        $out[__('Fees type')] = empty($s['selected_category'])
            ? __('All fee types')
            : FeesCategory::whereIn('id', $s['selected_category'])
                ->orderBy('title')->pluck('title')->implode(', ');

        $out[__('Payment status')] = empty($s['selected_payment_status'])
            ? __('All payment statuses')
            : collect($s['selected_payment_status'])
                ->map(fn ($status) => self::statusLabel($status))->implode(', ');

        if (!empty($s['selected_student_id'])) {
            $out[__('Matricule or student ID contains')] = (string) $s['selected_student_id'];
        }

        return $out;
    }

    /** What one of the form's payment statuses is called. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            '0' => __('filter_unpaid_fees'),
            '1' => __('filter_fully_paid_fees'),
            '2' => __('filter_partially_paid_fees'),
            '3' => __('status_canceled'),
            '4' => __('payment_plan'),
            '5' => __('filter_overpaid_fees'),
            default => $status,
        };
    }
}
