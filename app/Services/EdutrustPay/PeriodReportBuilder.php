<?php

namespace App\Services\EdutrustPay;

use App\Models\AccountingPeriod;
use App\Models\Payroll;
use App\Services\Accounting\AgingReportService;
use Carbon\Carbon;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\Money;
use EdutrustPay\Contract\PayloadBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Assembles this institution's contract payload for one calendar month.
 *
 * The report is GENERATED, never authored. Nobody here types a figure into a
 * form: everything below is read from the ledger and the accounting services
 * that already exist. That is the whole of the first control — a bursar cannot
 * edit a report before submitting it if there is no report to edit.
 *
 * WHAT IS DECLARED IS WHAT CAN BE COMPUTED. A capability is added to the payload
 * only when the figures behind it are actually available; if config lists one
 * that cannot be produced, the build FAILS rather than sending a zero. On the
 * console a missing figure and a zero figure are different facts, and the wrong
 * one here would be a lie told in this institution's name.
 */
class PeriodReportBuilder
{
    public function __construct(
        private AgingReportService $ageing,
    ) {
    }

    /**
     * @param  string  $period  calendar month, "YYYY-MM"
     * @return array<string, mixed>
     */
    public function build(string $period, int $sequence = 1, ?string $status = null): array
    {
        $ledger = new LedgerSummaryService($period);
        $capabilities = (array) config('edutrustpay.capabilities', []);

        $settings = app(SettingsResolver::class)->resolve();

        if ($settings === null) {
            throw new \RuntimeException(
                'No EdutrustPay credentials are configured. They are issued by the operator at the '
                .'body and pasted in under Settings, EdutrustPay Reporting.'
            );
        }

        $builder = PayloadBuilder::for($settings['institution_ref'], $period)
            ->status($status ?? $this->statusFor($period))
            ->sequence($sequence)
            ->generatedAt(gmdate('Y-m-d\TH:i:s\Z'))
            ->currency('XAF');

        // The ledger is the one capability nothing else can stand in for.
        $income = $ledger->incomeByGroup();
        $expenditure = $ledger->expenditureByGroup();

        $builder->ledger(
            $income,
            $expenditure,
            Money::sum(array_values($income))->subtract(Money::sum(array_values($expenditure))),
            $ledger->cash(),
            $ledger->control()
        );

        // The school's own names for the groups above. Only groups with an
        // amount travel; the contract drops the rest.
        $builder->groupNames($ledger->groupNames());

        if (in_array(Capability::RECEIVABLES, $capabilities, true)) {
            $this->addAgeing($builder, 'receivables', $ledger->endDate());
        }

        if (in_array(Capability::PAYABLES, $capabilities, true)) {
            $this->addAgeing($builder, 'payables', $ledger->endDate());
        }

        if (in_array(Capability::BUDGET, $capabilities, true)) {
            // Reached only if somebody re-enables it in config. Fail loudly
            // rather than invent a monthly figure — see config/edutrustpay.php.
            throw new \RuntimeException(
                'This system has no monthly budget to report: budgets here are annual and allocations '
                .'quarterly at best. Dividing an annual budget by twelve would make every seasonal month '
                .'look like a variance. Remove "budget" from edutrustpay.capabilities.'
            );
        }

        if (in_array(Capability::PAYROLL, $capabilities, true)) {
            $this->addPayroll($builder, $period);
        }

        if (in_array(Capability::INTEGRITY, $capabilities, true)) {
            $builder->integrity(
                $ledger->ledgerHash(),
                $ledger->auditEvents(),
                $ledger->reversals(),
                $ledger->lateEntries()
            );
        }

        if (in_array(Capability::FEES_CHARGED, $capabilities, true)) {
            $this->addFeesCharged($builder, $ledger->endDate());
        }

        $people = new PeopleSummaryService($period);

        // Students per programme, in the session the month falls in. Sent on its
        // own, without the enrolment part: that part also needs fees billed,
        // collected and the share paid in cash, and this system's payment-method
        // codes cannot yet say what was cash. Before any session, nothing is sent.
        if (in_array(Capability::STUDENTS, $capabilities, true) && ($session = $people->session())) {
            $builder->students($session->title, collect($people->studentsByProgramme())
                ->map(fn (int $students, string $programme) => ['level' => $programme, 'students' => $students])
                ->values()
                ->all());
        }

        // Number, name, department and post. Never pay.
        if (in_array(Capability::STAFF, $capabilities, true)) {
            $builder->staff($people->staff());
        }

        return $builder->build();
    }

    /**
     * What each programme is actually charged, so the body can compare it with
     * the fees it has set. Amounts and head counts only: no student is named.
     *
     * Taken from the fees students were really billed, not from a price list,
     * because the bill is what a family pays. The amount is before any discount,
     * so one student's scholarship does not read as a cheaper fee.
     *
     * Cancelled fees are left out, judged the way Fee::getPaymentStateAttribute()
     * judges them: the status column is not reliable on its own. Its comment says
     * 2 is "Cancel" but the application also uses 2 for part paid, so a fee counts
     * as cancelled only when it is status 2 or 3 and nothing was paid on it.
     *
     * With no session under way, or nothing billed yet, the block is omitted and
     * the console says it cannot check. A zero would look like a fee of nothing.
     */
    private function addFeesCharged(PayloadBuilder $builder, string $asOf): void
    {
        $session = DB::table('sessions')
            ->whereDate('start_date', '<=', $asOf)
            ->orderByDesc('start_date')
            ->first();

        if (! $session) {
            return;
        }

        $rows = DB::table('fees as f')
            ->join('student_enrolls as e', 'e.id', '=', 'f.student_enroll_id')
            ->join('programs as p', 'p.id', '=', 'e.program_id')
            ->join('fees_categories as c', 'c.id', '=', 'f.category_id')
            ->where('e.session_id', $session->id)
            ->whereNotNull('e.student_id')
            ->whereDate('f.assign_date', '<=', $asOf)
            ->whereRaw('NOT (f.status IN (2, 3) AND f.paid_amount = 0)')
            ->selectRaw('p.title as level, c.title as category, f.fee_amount as amount, COUNT(DISTINCT e.student_id) as students')
            ->groupBy('p.title', 'c.title', 'f.fee_amount')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $builder->feesCharged($session->title, $rows->map(fn ($row) => [
            'level' => $row->level,
            'category' => $row->category,
            'amount' => Money::of(number_format((float) $row->amount, 2, '.', '')),
            'students' => (int) $row->students,
        ])->all());
    }

    /**
     * Receivables or payables, re-bucketed to the contract's four brackets.
     *
     * The local service defaults to FIVE brackets — 0-30, 31-60, 61-90, 91-120
     * and over 120 — while the contract has four, with everything past ninety
     * days in one. Rather than summing the last two afterwards, the brackets are
     * set to match the contract exactly, so nothing is added up twice and the
     * bucket totals reconcile with the stated total by construction.
     */
    private function addAgeing(PayloadBuilder $builder, string $kind, string $asOf): void
    {
        $report = $this->ageing
            ->setAgeBrackets([
                ['min' => 0, 'max' => 30, 'label' => '0-30 Days'],
                ['min' => 31, 'max' => 60, 'label' => '31-60 Days'],
                ['min' => 61, 'max' => 90, 'label' => '61-90 Days'],
                ['min' => 91, 'max' => null, 'label' => 'Over 90 Days'],
            ]);

        $data = $kind === 'receivables'
            ? $report->getReceivablesAging($asOf)
            : $report->getPayablesAging($asOf);

        /*
         * What students owe lives in the fees table, not the ledger: fees reach
         * the ledger only when paid, so class 41 stays empty and the ledger
         * ageing alone reported "nothing outstanding" for a school with fees
         * overdue. Same report the bursar sees under Student Fee Aging.
         */
        $fees = $kind === 'receivables' ? $report->getStudentFeeAging($asOf) : ['totals' => []];

        $bucket = fn (string $key) => Money::fromNumeric(number_format(
            (float) ($data['totals'][$key] ?? 0) + (float) ($fees['totals'][$key] ?? 0),
            2,
            '.',
            ''
        ));

        $buckets = [
            '0_30' => $bucket('bracket_0'),
            '31_60' => $bucket('bracket_1'),
            '61_90' => $bucket('bracket_2'),
            '90_plus' => $bucket('bracket_3'),
        ];

        /*
         * The total is the sum of the buckets, not the service's grand_total.
         *
         * They should agree, and if they ever do not it is a bug worth knowing
         * about here rather than one the console reports as an inconsistency in
         * this institution's figures.
         */
        $total = Money::sum(array_values($buckets));

        $kind === 'receivables'
            ? $builder->receivables($total, $buckets)
            : $builder->payables($total, $buckets);
    }

    /**
     * Staff cost and the statutory liabilities standing against it.
     *
     * Reported separately on purpose: an institution paying salaries but not
     * remitting the withholdings is a specific and common failure, and it is
     * completely invisible inside a single staff-cost total.
     *
     * salary_month is the natural grouping key here — payroll is monthly, and
     * total_cost already includes employer tax.
     */
    private function addPayroll(PayloadBuilder $builder, string $period): void
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();

        $staffCost = (string) (Payroll::query()
            ->whereBetween('salary_month', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->sum('total_cost') ?? 0);

        $statutory = (string) (DB::table('payroll_tax_lines')
            ->whereBetween('salary_month', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->selectRaw('SUM(employee_amount + employer_amount) as total')
            ->value('total') ?? 0);

        $builder->payroll(Money::fromNumeric($staffCost), Money::fromNumeric($statutory));
    }

    /**
     * final once the month's accounting period is closed, provisional before.
     *
     * The distinction is not cosmetic: restating a FINAL month is a control
     * event at the other end, while a provisional month changing is ordinary
     * bookkeeping. Reporting everything as final would turn every normal
     * month-end correction into an incident.
     *
     * Falls back to a grace period when no accounting period exists for the
     * month, because accounting_period_id is nullable in this schema and a month
     * with no period row is common enough not to be an error.
     */
    private function statusFor(string $period): string
    {
        $end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth();

        $accountingPeriod = AccountingPeriod::query()
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $end->toDateString())
            ->first();

        if ($accountingPeriod) {
            return $accountingPeriod->is_closed ? 'final' : 'provisional';
        }

        $graceDays = (int) config('edutrustpay.assume_final_after_days', 10);

        return $end->copy()->addDays($graceDays)->isPast() ? 'final' : 'provisional';
    }
}
