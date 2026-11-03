<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\PrintSetting;
use App\Models\Setting;
use App\Support\FeeReportFilter;
use App\Support\FeesPaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The fees report: what was billed, what came in, and who still owes.
 *
 * The screen answers that as a flat list of fees with a band of totals above
 * it. This builds the same answer as a DOCUMENT — the totals, then the same
 * money broken down the four ways a Bursar is actually asked for it (by fee
 * type, by programme and faculty, by term, by who owes), then the full
 * register underneath — so the PDF and the workbook are one report in two
 * formats rather than two reports.
 *
 * WHERE THE FIGURES COME FROM. stats() returns the screen's own statistics, in
 * the screen's own shape, and the screen now calls it instead of computing
 * them itself. So the band on the screen and the headline on the exports are
 * not two computations that agree today; they are one computation.
 *
 * THE FIVE RULES THE ARITHMETIC FOLLOWS. Each is a way of getting the wrong
 * number that this installation's data would really produce:
 *
 *  1. Billed is fee - discount + fine (Fee::getTotalAmountAttribute), never the
 *     raw fee_amount. Across all 304 fees on this install the two differ by
 *     fines and discounts, and a report that mixes them does not reconcile.
 *
 *  2. Collected is NET paid (Fee::netPaidSql) — paid_amount less any
 *     overpayment credit since moved to another fee, because that money is
 *     counted on the fee it went to. There are 91 credits worth 7,382,000 here
 *     and 87 applications worth 6,823,000, so a figure built on raw
 *     paid_amount is wrong on this data, not in theory.
 *
 *  3. Outstanding sums only POSITIVE balances. Letting an overpaid fee carry a
 *     negative balance into the total would net somebody's excess against
 *     somebody else's debt, and the total would understate what is owed.
 *
 *  4. The collection rate is capped at 100%. Held overpayment can push cash
 *     received above what was billed, and a rate over 100 reads as a fault.
 *     The excess has its own figure. This matches the band exactly
 *     (App\Support\FeesSummary).
 *
 *  5. The monthly curve dates cash by pay_date and uses
 *     Fee::cashReceivedSql(), not net paid — exactly as the daybook does, so
 *     this report and the daybook cannot disagree about which month the money
 *     arrived in. Its one limit is stated on the report itself: fees.pay_date
 *     is a single column, so a fee paid in instalments dates all of its cash
 *     to the last payment.
 */
class FeeReport
{
    /**
     * The screen's statistics, in the shape its view and band read.
     *
     * Computed from the rows already fetched rather than by running the query
     * a second time, which is what the screen used to do.
     *
     * @param  Collection<int, Fee>  $rows
     */
    public function stats(Collection $rows): array
    {
        return [
            'total_fees' => $rows->count(),

            // A First Instalment whose overpayment has been moved to the
            // Second is fully paid, not overpaid — it used to be left out.
            'fully_paid_count' => $rows->filter(fn ($fee) => $fee->status == 1 && !$fee->isNetOverpaid())->count(),
            'partially_paid_count' => $rows->where('status', 2)->count(),
            'cancelled_count' => $rows->where('status', 3)->count(),
            'overpaid_count' => $rows->filter(fn ($fee) => $fee->isNetOverpaid())->count(),

            'total_amount' => $rows->sum(fn ($fee) => (float) $fee->total_amount),

            // Cash actually received: overpayment credit applied to another
            // fee is counted on that fee and no longer on this one as well.
            'total_collected' => $rows->sum(fn ($fee) => $fee->net_paid_amount),
            'total_remaining' => $rows->sum(fn ($fee) => max(0, $fee->net_remaining_balance)),
            'total_overpayment' => $rows->sum(fn ($fee) => $fee->net_overpayment_amount),
        ];
    }

    /**
     * The whole report.
     *
     * @param  Collection<int, Fee>  $rows  the fees the filters matched
     */
    public function build(Collection $rows, FeeReportFilter $filter): array
    {
        $setting = Setting::first();
        $prefix = optional(PrintSetting::where('slug', 'fees-receipt')->first())->prefix ?? '';
        $anomalies = $this->anomalies($rows, $prefix);

        return [
            'institution' => $setting->title ?? '',
            'generated_at' => Carbon::now(),
            'currency' => $setting->currency_symbol ?? '',
            'decimals' => (int) ($setting->decimal_place ?? 2),
            'date_format' => $setting->date_format ?? 'Y-m-d',

            'filters' => $filter->describe(),
            'outside' => $filter->outside(),
            'has_data' => $rows->isNotEmpty(),

            'stats' => $this->stats($rows),
            'totals' => $this->figures($rows),

            'by_category' => $this->breakdown($rows, fn ($fee) => $fee->category->title ?? __('Uncategorised')),
            'by_faculty' => $this->breakdown($rows, fn ($fee) => $this->facultyOf($fee)),
            'by_programme' => $this->breakdown(
                $rows,
                fn ($fee) => $this->programmeOf($fee),
                fn ($fee) => ['faculty' => $this->facultyOf($fee)]
            ),
            'by_term' => $this->breakdown(
                $rows,
                fn ($fee) => trim(($fee->studentEnroll->session->title ?? '—') . '  ·  ' . ($fee->studentEnroll->semester->title ?? '—')),
                fn ($fee) => [
                    'session' => $fee->studentEnroll->session->title ?? '—',
                    'semester' => $fee->studentEnroll->semester->title ?? '—',
                ]
            ),
            'by_status' => $this->byStatus($rows),
            'by_method' => $this->byMethod($rows),
            'by_month' => $this->byMonth($rows),

            'students' => $this->students($rows),
            'register' => $this->register($rows, $prefix),
            'anomalies' => $anomalies,

            'notes' => $this->notes($filter, $anomalies),
        ];
    }

    /**
     * Billed, collected, outstanding and the rate, for any set of fees.
     *
     * @param  Collection<int, Fee>  $group
     */
    protected function figures(Collection $group): array
    {
        $billed = round($group->sum(fn ($fee) => (float) $fee->total_amount), 2);
        $collected = round($group->sum(fn ($fee) => $fee->net_paid_amount), 2);

        return [
            'fees' => $group->count(),
            'students' => $group->pluck('studentEnroll.student_id')->filter()->unique()->count(),
            'billed' => $billed,
            'collected' => $collected,
            'outstanding' => round($group->sum(fn ($fee) => max(0, $fee->net_remaining_balance)), 2),
            'overpaid' => round($group->sum(fn ($fee) => $fee->net_overpayment_amount), 2),

            // Capped, for the reason given in rule 4 above.
            'rate' => $billed > 0 ? min(100.0, round($collected / $billed * 100, 1)) : 0.0,
        ];
    }

    /**
     * The same figures per group, worst debt first.
     *
     * Ordered by what is still owed rather than alphabetically: the line a
     * Bursar needs is the one with the biggest hole in it.
     *
     * @param  Collection<int, Fee>  $rows
     * @param  callable  $key    the group's name
     * @param  callable|null  $extra  further columns, from any fee in the group
     */
    protected function breakdown(Collection $rows, callable $key, ?callable $extra = null): array
    {
        return $rows->groupBy($key)
            ->map(function ($group, $name) use ($extra) {
                $line = ['title' => (string) $name] + $this->figures($group);

                return $extra ? $line + $extra($group->first()) : $line;
            })
            ->sortByDesc('outstanding')
            ->values()
            ->all();
    }

    /** How many fees stand in each state, and what they carry. */
    protected function byStatus(Collection $rows): array
    {
        return $rows->groupBy(fn ($fee) => $fee->payment_state)
            ->map(fn ($group, $state) => [
                'title' => Fee::PAYMENT_STATES[$state][0] ?? (string) $state,
                'state' => (string) $state,
            ] + $this->figures($group))
            ->sortByDesc('outstanding')
            ->values()
            ->all();
    }

    /**
     * What came in by the way it was taken.
     *
     * Only fees carrying money: the method on an unpaid fee records nothing.
     * The vocabulary is App\Support\FeesPaymentMethod, so this cannot report
     * cash-office receipts as arriving through a payment gateway the way the
     * report screen once did.
     *
     * The test is abs(), not > 0, so that a fee carrying a NEGATIVE net paid
     * is still on this table. One does on this installation — see
     * anomalies() — and skipping it put this table 50,000 above the Collected
     * headline, which is a report disagreeing with itself.
     */
    protected function byMethod(Collection $rows): array
    {
        return $rows->filter(fn ($fee) => abs($fee->net_paid_amount) > 0.005)
            ->groupBy(fn ($fee) => FeesPaymentMethod::label($fee->payment_method) ?: __('Not recorded'))
            ->map(fn ($group, $method) => [
                'title' => (string) $method,
                'fees' => $group->count(),
                'collected' => round($group->sum(fn ($fee) => $fee->net_paid_amount), 2),
            ])
            ->sortByDesc('collected')
            ->values()
            ->all();
    }

    /**
     * Cash received month by month.
     *
     * One aggregate over the matching fees, through Fee::cashReceivedSql() —
     * see rule 5. Reimplementing that arithmetic here is how this curve and
     * the daybook's bars would come to disagree.
     */
    protected function byMonth(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $cash = Fee::cashReceivedSql('fees');

        return DB::table('fees')
            ->whereIn('id', $rows->pluck('id'))
            ->whereNotNull('pay_date')
            ->selectRaw("DATE_FORMAT(pay_date, '%Y-%m') as ym, count(*) as fees, COALESCE(SUM({$cash}), 0) as collected")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => [
                'month' => Carbon::createFromFormat('Y-m-d', $row->ym . '-01')->format('M Y'),
                'ym' => $row->ym,
                'fees' => (int) $row->fees,
                'collected' => round((float) $row->collected, 2),
            ])
            ->all();
    }

    /**
     * One line per student, worst debt first.
     *
     * Aggregated to the PERSON, which is the question a board asks: a student
     * with three instalments, two paid, is one student who is partly paid —
     * not two paid and one unpaid.
     *
     * @param  Collection<int, Fee>  $rows
     */
    protected function students(Collection $rows): array
    {
        return $rows->groupBy(fn ($fee) => $fee->studentEnroll->student_id)
            ->map(function ($group) {
                $enroll = $group->first()->studentEnroll;
                $student = $enroll->student ?? null;

                return [
                    'matricule' => $enroll->matricule ?? $student->student_id ?? '',
                    'student_id' => $student->student_id ?? '',
                    'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'programme' => $this->programmeOf($group->first()),
                    'faculty' => $this->facultyOf($group->first()),
                    'session' => $enroll->session->title ?? '—',
                    'semester' => $enroll->semester->title ?? '—',
                    'phone' => $student->phone ?? '',
                    'email' => $student->email ?? '',
                ] + $this->figures($group);
            })
            // Biggest debt first; then alphabetically, so the settled tail of
            // the list is still something you can look a name up in.
            ->sortBy([['outstanding', 'desc'], ['matricule', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Every fee, in the order the screen lists them.
     *
     * "Paid" here is the NET paid and is not capped at what was billed, so the
     * column sums to the Collected headline exactly — a workbook whose columns
     * do not add up to its own totals is worse than one that shows a figure
     * above the amount due. Where that happens the excess is named in its own
     * column, and the screen's table caps the same cell because it has a
     * tooltip to explain itself and a printed column has not.
     *
     * @param  Collection<int, Fee>  $rows
     */
    protected function register(Collection $rows, string $prefix): array
    {
        return $rows->values()->map(function ($fee, $i) use ($prefix) {
            $enroll = $fee->studentEnroll;
            $student = $enroll->student ?? null;
            $paidOn = in_array((int) $fee->status, [1, 2], true) && $fee->pay_date
                ? Carbon::parse($fee->pay_date)
                : null;

            return [
                'no' => $i + 1,
                'receipt' => $prefix . str_pad((string) $fee->id, 6, '0', STR_PAD_LEFT),
                'matricule' => $enroll->matricule ?? $student->student_id ?? '',
                'student_id' => $student->student_id ?? '',
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'faculty' => $this->facultyOf($fee),
                'programme' => $this->programmeOf($fee),
                'session' => $enroll->session->title ?? '—',
                'semester' => $enroll->semester->title ?? '—',
                'category' => $fee->category->title ?? '—',
                'resit' => $fee->resitRequest->subject->title ?? '',

                'gross' => round((float) ($fee->fee_amount ?? 0), 2),
                'discount' => round((float) ($fee->discount_amount ?? 0), 2),
                'fine' => round((float) ($fee->fine_amount ?? 0), 2),
                'due' => round((float) $fee->total_amount, 2),
                'paid' => round($fee->net_paid_amount, 2),
                'balance' => round(max(0, $fee->net_remaining_balance), 2),
                'held' => round($fee->net_overpayment_amount, 2),
                'moved_out' => round($fee->credit_moved_out, 2),

                'status' => $fee->status_label,
                'plan' => $fee->paymentPlan ? (string) $fee->paymentPlan->status : '',
                'method' => FeesPaymentMethod::label($fee->payment_method),
                'pay_date' => $paidOn,
                'note' => $this->plain($fee->note),
            ];
        })->all();
    }

    /**
     * Fees whose figures cannot be right, so somebody can go and look.
     *
     * A fee can only raise overpayment credit out of money paid on it, so the
     * credit moved off a fee can never exceed what was paid on it. Where it
     * does, the fee's net paid goes NEGATIVE and the institution's Collected
     * figure is understated by the difference.
     *
     * There is one such fee on this installation: 150,000 paid against a
     * 150,000 charge, carrying 250,000 of overpayment credit with 200,000 of
     * it applied to another fee. That looks like a payment that was recorded,
     * raised credit, and was then reduced without the credit being reversed.
     *
     * This is a DATA fault, not an arithmetic one, so the report does not
     * quietly correct it: correcting it would make the report disagree with
     * the screen and with the ledger, and would hide the thing that needs
     * fixing. It names it instead.
     *
     * @param  Collection<int, Fee>  $rows
     * @return array{fees:int, amount:float, rows:list<array<string, mixed>>}
     */
    protected function anomalies(Collection $rows, string $prefix): array
    {
        $bad = $rows->filter(fn ($fee) => $fee->net_paid_amount < -0.005);

        return [
            'fees' => $bad->count(),
            'amount' => round($bad->sum(fn ($fee) => -$fee->net_paid_amount), 2),
            'rows' => $bad->values()->map(fn ($fee) => [
                'receipt' => $prefix . str_pad((string) $fee->id, 6, '0', STR_PAD_LEFT),
                'matricule' => $fee->studentEnroll->matricule ?? $fee->studentEnroll->student->student_id ?? '',
                'name' => trim(($fee->studentEnroll->student->first_name ?? '') . ' ' . ($fee->studentEnroll->student->last_name ?? '')),
                'category' => $fee->category->title ?? '—',
                'due' => round((float) $fee->total_amount, 2),
                'paid_raw' => round((float) ($fee->paid_amount ?? 0), 2),
                'moved_out' => round($fee->credit_moved_out, 2),
                'shortfall' => round(-$fee->net_paid_amount, 2),
            ])->all(),
        ];
    }

    /**
     * What the report does and does not count, said on its face.
     *
     * A reader who does not know that admission fees are missing will read
     * this as the whole of the institution's fees, so the report says so
     * rather than leaving them to notice.
     */
    protected function notes(FeeReportFilter $filter, array $anomalies): array
    {
        $outside = $filter->outside();

        $notes = [
            __('Billed is the fee less any discount plus any fine. Collected is what has been paid and still belongs to the fee: an overpayment moved to another fee as credit is counted on the fee it went to, never on both.'),
            __('Outstanding counts only fees that are short. A fee holding an excess is not netted against somebody else\'s debt — that excess is reported on its own, as Overpayment held.'),
            __('The collection rate is capped at 100%, because held overpayment can push cash received above what was billed.'),
        ];

        if ($outside['fees'] > 0) {
            $notes[] = __(
                'Not counted here: :fees fee(s) worth :amount that these filters match but which have no student record behind them — admission fees raised against applicants, and any fee whose enrolment no longer exists. Admission fees are reported on their own screen, Admission Fees Report.',
                ['fees' => number_format($outside['fees']), 'amount' => number_format($outside['billed'], 2)]
            );
        }

        $notes[] = __('Collected by month dates cash by the fee\'s payment date, the same way the daybook does. A fee paid in instalments carries one payment date, so all of its cash is dated to the last payment.');

        if ($anomalies['fees'] > 0) {
            $notes[] = __(
                'Needs checking: :fees fee(s) carry more overpayment credit moved to another fee than was ever paid on them, which understates Collected by :amount. A fee cannot raise credit out of money it never received, so these figures are wrong in the records rather than in this report — see "Needs checking" above.',
                ['fees' => number_format($anomalies['fees']), 'amount' => number_format($anomalies['amount'], 2)]
            );
        }

        return $notes;
    }

    protected function facultyOf(Fee $fee): string
    {
        return $fee->studentEnroll->program->faculty->title ?? __('No faculty');
    }

    protected function programmeOf(Fee $fee): string
    {
        return $fee->studentEnroll->program->title ?? __('No programme');
    }

    /** A note with its markup taken off, for a cell that cannot render HTML. */
    protected function plain(?string $value): string
    {
        return trim(preg_replace('~\s+~', ' ', strip_tags((string) $value)));
    }
}
