<?php

namespace App\Services\EdutrustPay;

use App\Models\Fee;
use Carbon\Carbon;
use EdutrustPay\Contract\Money;
use Illuminate\Support\Facades\DB;

/**
 * The fee position for the academic year a month falls in, as at the end of
 * that month: what is expected, collected, still owed and overpaid, class by
 * class and fee by fee, and how many students have paid. No student is named.
 *
 * WHICH FEES
 *
 * The same ones the fees charged part uses, so the two always agree: fees on
 * an enrolment in the session the month falls in (the latest one started by
 * month end, as for students), assigned by month end, naming a student, and
 * not cancelled. A fee counts as cancelled only when it is status 2 or 3 and
 * nothing was paid on it: the application also uses 2 for part paid.
 *
 * EXPECTED, COLLECTED, OWED
 *
 * Expected is what the fees report calls the total: the fee, plus any fine,
 * less any discount. Collected is the net paid amount (Fee::netPaidSql()): an
 * overpayment that has since been moved to another fee as credit counts on
 * that fee only. Owed and overpaid are worked out fee by fee, so one student's
 * overpayment never hides another's debt — the same as the fees report's
 * "remaining" and "overpaid".
 *
 * AS AT THE END OF THE MONTH
 *
 * This system keeps one running paid amount per fee, dated by the last
 * payment (pay_date), the rule its daybook and budget sheet use too. A fee
 * last paid by month end counts in full. A fee last paid AFTER month end counts
 * only the approved payment receipts dated by month end, at most its net paid:
 * October's report is built in November, and November's money is not
 * October's. A part payment made in October without a receipt and topped up
 * in November is the one case this cannot see, and it reads as unpaid.
 *
 * Amounts are worked in whole francs-and-cents (integers) and only turned into
 * Money at the end, so no sum drifts.
 */
class FeeYearService
{
    private string $period;

    private string $end;

    public function __construct(string $period)
    {
        $this->period = $period;
        $this->end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->toDateString();
    }

    /**
     * @return array{
     *     session: string,
     *     rows: list<array{level: string, category: string, expected: Money, collected: Money, owed: Money, overpaid: Money}>,
     *     students: array{paid: int, part_paid: int, not_paid: int, not_charged: int}
     * }|null  null before any session has started
     */
    public function position(): ?array
    {
        $people = new PeopleSummaryService($this->period);
        $session = $people->session();

        if (! $session) {
            return null;
        }

        $fees = DB::table('fees as f')
            ->join('student_enrolls as e', 'e.id', '=', 'f.student_enroll_id')
            ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
            ->leftJoin('fees_categories as c', 'c.id', '=', 'f.category_id')
            ->where('e.session_id', $session->id)
            ->whereNotNull('e.student_id')
            ->whereDate('f.assign_date', '<=', $this->end)
            ->whereRaw('NOT (f.status IN (2, 3) AND f.paid_amount = 0)')
            ->selectRaw('f.id, e.student_id, p.title as level, c.title as category, f.pay_date,
                         (f.fee_amount + COALESCE(f.fine_amount, 0) - COALESCE(f.discount_amount, 0)) as expected,
                         '.Fee::netPaidSql('f').' as net_paid')
            ->orderBy('f.id')
            ->get();

        // Approved receipts dated by month end, for fees last paid after it.
        $later = $fees->filter(fn ($fee) => $fee->pay_date && $fee->pay_date > $this->end)->pluck('id');
        $receipted = $later->isEmpty() ? collect() : DB::table('payment_receipts')
            ->whereIn('fee_id', $later)
            ->where('verification_status', 'approved')
            ->whereDate('payment_date', '<=', $this->end)
            ->groupBy('fee_id')
            ->selectRaw('fee_id, SUM(amount) as amount')
            ->pluck('amount', 'fee_id');

        $rows = [];
        $students = [];

        foreach ($fees as $fee) {
            $expected = self::cents($fee->expected);
            $netPaid = max(0, self::cents($fee->net_paid));

            $collected = ($fee->pay_date === null || $fee->pay_date <= $this->end)
                ? $netPaid
                : min($netPaid, max(0, self::cents($receipted[$fee->id] ?? 0)));

            $owed = max(0, $expected - $collected);
            $overpaid = max(0, $collected - $expected);

            $level = trim((string) $fee->level) !== '' ? (string) $fee->level : 'No programme';
            $category = trim((string) $fee->category) !== '' ? (string) $fee->category : 'Uncategorised';
            $key = $level."\x1F".$category;

            $rows[$key] ??= ['level' => $level, 'category' => $category, 'expected' => 0, 'collected' => 0, 'owed' => 0, 'overpaid' => 0];
            $rows[$key]['expected'] += $expected;
            $rows[$key]['collected'] += $collected;
            $rows[$key]['owed'] += $owed;
            $rows[$key]['overpaid'] += $overpaid;

            $students[$fee->student_id] ??= ['collected' => 0, 'owed' => 0];
            $students[$fee->student_id]['collected'] += $collected;
            $students[$fee->student_id]['owed'] += $owed;
        }

        $counts = ['paid' => 0, 'part_paid' => 0, 'not_paid' => 0, 'not_charged' => 0];

        foreach ($students as $money) {
            $counts[$money['owed'] === 0 ? 'paid' : ($money['collected'] > 0 ? 'part_paid' : 'not_paid')]++;
        }

        // On the roll for the session, but no fee at all.
        $counts['not_charged'] = count(array_diff_key($people->studentProgrammes(), $students));

        return [
            'session' => (string) $session->title,
            'rows' => array_values(array_map(fn (array $row) => [
                'level' => $row['level'],
                'category' => $row['category'],
                'expected' => self::money($row['expected']),
                'collected' => self::money($row['collected']),
                'owed' => self::money($row['owed']),
                'overpaid' => self::money($row['overpaid']),
            ], $rows)),
            'students' => $counts,
        ];
    }

    /** A database amount in cents, rounded once. */
    private static function cents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private static function money(int $cents): Money
    {
        return Money::of(sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100));
    }
}
