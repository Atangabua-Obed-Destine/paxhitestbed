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

        return $builder->build();
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

        $buckets = [
            '0_30' => Money::fromNumeric((string) ($data['totals']['bracket_0'] ?? 0)),
            '31_60' => Money::fromNumeric((string) ($data['totals']['bracket_1'] ?? 0)),
            '61_90' => Money::fromNumeric((string) ($data['totals']['bracket_2'] ?? 0)),
            '90_plus' => Money::fromNumeric((string) ($data['totals']['bracket_3'] ?? 0)),
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
