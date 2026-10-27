<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Fee;
use Illuminate\Support\Facades\DB;

/**
 * Who has paid, for a budget year.
 *
 * The analysis page says how money moved. This says who still owes — and it
 * counts PEOPLE, not fees, which is the whole reason it exists as its own
 * class rather than another figure on the fees report.
 *
 * A student with three instalments, two of them paid, is ONE student who is
 * partly paid. The fees report would count that as two paid fees and one
 * unpaid, which is the right answer to a different question. A board meeting
 * asks the first one.
 *
 * ---------------------------------------------------------------------------
 * Four ways to get this wrong, all of which this installation's data would
 * actually produce:
 *
 *   1. Grouping on student_enrolls.student_id alone. 32 fees here sit on
 *      enrolments with no student — 31 of them applicants paying admission
 *      fees — and they all fold into one NULL bucket, inventing a student and
 *      losing 31. The person is the student if there is one, else the
 *      applicant, and applicants are reported separately because they are not
 *      students yet.
 *
 *   2. Summing paid_amount. There are 88 overpayment credits worth ~7m on this
 *      install, so an instalment settled by credit moved from another fee
 *      looks unpaid, and the fee the overpayment came from looks overpaid.
 *      Fee::netPaidSql() already answers this; it is not re-derived here.
 *
 *   3. Joining student_enrolls inner. Fee 34 points at enrolment 34, which
 *      does not exist — 150,000 unpaid, and an inner join drops it without a
 *      word. Every join below is a LEFT join and what cannot be attributed is
 *      counted and shown, exactly as the daybook shows money on no budget
 *      line. Money that cannot be placed is a question for the Bursar.
 *
 *   4. Bucketing on fees.status. status is per fee; the question is per person.
 *      The bucket comes from that person's summed due against their summed net
 *      paid, so it cannot disagree with the money beside it.
 *
 * ---------------------------------------------------------------------------
 * The cohort is fees ASSIGNED inside the budget window. Not paid inside it:
 * pay_date is null on every unpaid fee, so scoping by payment would make "has
 * paid nothing" uncountable — the one figure this was asked for.
 *
 * Fees assigned before the window and still owed are reported on their own, as
 * arrears. Without that, a student owing from last session appears nowhere.
 *
 * Every figure is a fixed number of aggregate queries. There is no per-student
 * query anywhere in this class, and scripts/fee_collection_test.php asserts
 * it: 73 students is nothing, but a query per student is how this screen stops
 * working at two thousand.
 */
class FeeCollection
{
    /** Cancelled. Not a debt, so out of every figure here. */
    public const CANCELLED = 3;

    /**
     * Money is equal when it is within half a cent, matching
     * Fee::isNetOverpaid(). Floating-point sums of doubles do not land on zero.
     */
    public const EPSILON = 0.005;

    /** How many debtors the ranking shows. */
    protected const DEBTOR_LIMIT = 15;

    public function forBudget(Budget $budget): array
    {
        $from = $this->day($budget->start_date);
        $to = $this->day($budget->end_date);

        if (!$from || !$to) {
            return $this->nothing($from, $to);
        }

        $people = $this->peopleInWindow($from, $to);

        // Students and applicants are tallied apart, and the headline is the
        // STUDENT tally. Mixing them is not a rounding difference, it inverts
        // the answer: on this install the 2026/2027 window holds 11 students
        // and 26 applicants, and the applicants have nearly all paid a 21,000
        // admission fee. Pooled, the page would report "25 of 37 fully paid",
        // which reads as a healthy year. The students alone are 21% collected.
        //
        // An applicant is also not yet a student, which is the question that
        // was asked. So: students lead, applicants are reported beside them,
        // and a combined figure exists for anyone who wants the gross.
        $byKind = ['student' => [], 'applicant' => []];

        foreach ($people as $person) {
            $byKind[$person->kind][] = $person;
        }

        $tally = [
            'student' => $this->tally($byKind['student']),
            'applicant' => $this->tally($byKind['applicant']),
        ];

        $total = count($people);

        return [
            'window' => ['from' => $from, 'to' => $to],

            // The three figures this was asked for, plus the denominator —
            // "3 have paid nothing" means nothing without "of 12".
            'students' => $tally['student'],
            'applicants' => $tally['applicant'],
            'combined' => $this->tally($people),

            'arrears' => $this->arrears($from),
            'plans' => $this->activePlans(),
            'by_programme' => $this->byProgramme($from, $to),
            'by_category' => $this->byCategory($from, $to),
            'by_month' => $this->byMonth($budget, $from, $to),
            'top_debtors' => $this->topDebtors($people),
            'unattributed' => $this->unattributed(),

            'has_data' => $total > 0,
        ];
    }

    /* ===================================================================
     |  The cohort
     |===================================================================*/

    /**
     * One row per person with a fee assigned in the window.
     *
     * The grouping key is the heart of this class. COALESCE down to the
     * applicant, and prefix the kind, so that student 7 and applicant 7 are
     * never the same person — which a bare numeric key would make them.
     *
     * @return array<int, object>
     */
    protected function peopleInWindow(string $from, string $to): array
    {
        $net = Fee::netPaidSql('f');
        $due = $this->dueSql('f');

        return DB::table('fees as f')
            ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
            ->leftJoin('students as s', 's.id', '=', 'se.student_id')
            ->leftJoin('applications as ap', 'ap.id', '=', 'f.applicant_id')
            ->leftJoin('programs as p', 'p.id', '=', 'se.program_id')
            ->whereRaw($this->personColumn() . ' IS NOT NULL')
            ->where('f.status', '<>', self::CANCELLED)
            ->whereDate('f.assign_date', '>=', $from)
            ->whereDate('f.assign_date', '<=', $to)
            ->groupBy('person')
            ->selectRaw($this->personKeySql() . ' as person')
            ->selectRaw($this->personKindSql() . ' as kind')
            ->selectRaw("SUM({$due}) as due")
            ->selectRaw("SUM({$net}) as paid")
            ->selectRaw('COUNT(*) as fees')
            // MIN over a per-person constant: the name does not vary within a
            // group, and MIN keeps this legal under ONLY_FULL_GROUP_BY, which
            // a bare column would not.
            ->selectRaw("MIN(TRIM(CONCAT(COALESCE(s.first_name, ap.first_name, ''), ' ',
                                         COALESCE(s.last_name,  ap.last_name,  '')))) as name")
            ->selectRaw("MIN(COALESCE(se.matricule, s.student_id, ap.registration_no, '')) as reference")
            ->selectRaw('MIN(se.id) as enroll_id')
            ->selectRaw("MIN(COALESCE(p.title, '')) as programme")
            ->get()
            ->map(function ($row) {
                $row->due = (float) $row->due;
                $row->paid = (float) $row->paid;
                $row->owed = round(max(0.0, $row->due - $row->paid), 2);
                $row->bucket = $this->bucket($row->due, $row->paid);

                return $row;
            })
            ->all();
    }

    /**
     * One cohort's figures: the three counts, and the money behind them.
     *
     * Counts and money come from the same pass over the same people, so the
     * band cannot say "8 partly paid" above a total that disagrees.
     *
     * @param array<int, object> $people
     */
    protected function tally(array $people): array
    {
        $buckets = ['full' => 0, 'part' => 0, 'none' => 0];
        $billed = 0.0;
        $collected = 0.0;
        $outstanding = 0.0;
        $overpaidCount = 0;
        $overpaidAmount = 0.0;

        foreach ($people as $person) {
            $buckets[$person->bucket]++;

            $billed += $person->due;
            $collected += $person->paid;
            $outstanding += max(0.0, $person->due - $person->paid);

            if ($person->paid - $person->due > self::EPSILON) {
                $overpaidCount++;
                $overpaidAmount += $person->paid - $person->due;
            }
        }

        return [
            'full' => $buckets['full'],
            'part' => $buckets['part'],
            'none' => $buckets['none'],
            'total' => count($people),
            'billed' => round($billed, 2),
            'collected' => round($collected, 2),
            'outstanding' => round($outstanding, 2),
            'rate' => $billed > 0 ? min(100.0, round($collected / $billed * 100, 1)) : 0.0,
            'overpaid_people' => $overpaidCount,
            'overpaid_amount' => round($overpaidAmount, 2),
        ];
    }

    /**
     * Which of the three a person is in.
     *
     * Nothing received at all is its own state and not a degenerate "partly":
     * a student who has paid nothing needs a different conversation from one
     * who has paid most of it.
     *
     * Someone who has paid MORE than they owe is fully paid and holding
     * credit — counted here as full, with the credit reported separately. The
     * fees report draws the same line in fully_paid_count.
     */
    protected function bucket(float $due, float $paid): string
    {
        if ($paid <= self::EPSILON) {
            // Nothing received. A fee billed at zero with nothing paid is
            // settled, not unpaid — otherwise a waived fee reads as a debt.
            return $due <= self::EPSILON ? 'full' : 'none';
        }

        return $due - $paid <= self::EPSILON ? 'full' : 'part';
    }

    /** The largest balances, worst first. */
    protected function topDebtors(array $people): array
    {
        $owing = array_values(array_filter($people, fn ($p) => $p->owed > self::EPSILON));

        usort($owing, fn ($a, $b) => $b->owed <=> $a->owed);

        return array_map(fn ($p) => [
            'name' => $p->name !== '' ? $p->name : __('Unnamed'),
            'reference' => $p->reference,
            'programme' => $p->programme,
            'kind' => $p->kind,
            'enroll_id' => $p->enroll_id ? (int) $p->enroll_id : null,
            'due' => round($p->due, 2),
            'paid' => round($p->paid, 2),
            'owed' => $p->owed,
            'share' => $p->due > 0 ? round($p->owed / $p->due * 100, 1) : 0.0,
        ], array_slice($owing, 0, self::DEBTOR_LIMIT));
    }

    /* ===================================================================
     |  Brought forward
     |===================================================================*/

    /**
     * Still owed on fees assigned BEFORE this budget year.
     *
     * Its own figure rather than folded into the cohort, because mixing the
     * two would hide both: this year's collection rate stops meaning anything
     * once last year's debts are in the denominator, and the arrears stop
     * being visible as arrears.
     */
    protected function arrears(string $from): array
    {
        $net = Fee::netPaidSql('f');
        $due = $this->dueSql('f');

        $rows = DB::table('fees as f')
            ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
            ->whereRaw($this->personColumn() . ' IS NOT NULL')
            ->where('f.status', '<>', self::CANCELLED)
            ->whereDate('f.assign_date', '<', $from)
            ->groupBy('person')
            ->selectRaw($this->personKeySql() . ' as person')
            ->selectRaw("SUM({$due}) - SUM({$net}) as owed")
            ->selectRaw('MIN(f.assign_date) as oldest')
            ->get();

        $people = 0;
        $amount = 0.0;
        $oldest = null;

        foreach ($rows as $row) {
            if ((float) $row->owed <= self::EPSILON) {
                continue;
            }

            $people++;
            $amount += (float) $row->owed;

            if ($oldest === null || $row->oldest < $oldest) {
                $oldest = $row->oldest;
            }
        }

        return [
            'people' => $people,
            'amount' => round($amount, 2),
            'oldest' => $oldest,
        ];
    }

    /** Students working to an agreed schedule rather than simply behind. */
    protected function activePlans(): array
    {
        $row = DB::table('payment_plans')
            ->where('status', 'active')
            ->selectRaw('COUNT(DISTINCT student_id) as people, COALESCE(SUM(total_amount), 0) as amount')
            ->first();

        return [
            'people' => (int) ($row->people ?? 0),
            'amount' => round((float) ($row->amount ?? 0), 2),
        ];
    }

    /* ===================================================================
     |  Breakdowns
     |===================================================================*/

    /**
     * Collection by programme, worst rate first.
     *
     * Counted in fees and money rather than people: a programme's student
     * belongs to the programme, but a person with fees under two programmes
     * would otherwise have to be split or double-counted, and neither is
     * honest. The headline people figures are the place for people.
     */
    protected function byProgramme(string $from, string $to): array
    {
        $net = Fee::netPaidSql('f');
        $due = $this->dueSql('f');

        $rows = DB::table('fees as f')
            ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
            ->leftJoin('programs as p', 'p.id', '=', 'se.program_id')
            ->leftJoin('faculties as fac', 'fac.id', '=', 'p.faculty_id')
            ->where('f.status', '<>', self::CANCELLED)
            ->whereDate('f.assign_date', '>=', $from)
            ->whereDate('f.assign_date', '<=', $to)
            ->groupBy('p.id', 'p.title', 'fac.title')
            ->selectRaw('p.id as programme_id, p.title as programme, fac.title as faculty')
            ->selectRaw("SUM({$due}) as due, SUM({$net}) as paid")
            // Distinct PEOPLE, by the same key the cohort uses — not distinct
            // student_id, which counted an applicant's admission fee as zero
            // people and showed programmes with 5 fees and 0 students.
            ->selectRaw('COUNT(*) as fees, COUNT(DISTINCT ' . $this->personKeySql() . ') as people')
            ->get();

        return $this->rank($rows, fn ($row) => [
            'programme' => $row->programme ?: __('No programme recorded'),
            'faculty' => $row->faculty ?: null,
            'people' => (int) $row->people,
            'fees' => (int) $row->fees,
        ]);
    }

    /** Collection by fee category — is it the second instalment that lags? */
    protected function byCategory(string $from, string $to): array
    {
        $net = Fee::netPaidSql('f');
        $due = $this->dueSql('f');

        $rows = DB::table('fees as f')
            ->leftJoin('fees_categories as fc', 'fc.id', '=', 'f.category_id')
            ->where('f.status', '<>', self::CANCELLED)
            ->whereDate('f.assign_date', '>=', $from)
            ->whereDate('f.assign_date', '<=', $to)
            ->groupBy('fc.id', 'fc.title')
            ->selectRaw('fc.id as category_id, fc.title as category')
            ->selectRaw("SUM({$due}) as due, SUM({$net}) as paid")
            ->selectRaw('COUNT(*) as fees')
            ->get();

        return $this->rank($rows, fn ($row) => [
            'category' => $row->category ?: __('No category'),
            'fees' => (int) $row->fees,
        ]);
    }

    /**
     * Shared shaping for the two rankings: due, collected, owed, rate, then
     * sorted by the worst rate so the row that needs chasing is first.
     */
    protected function rank($rows, callable $identity): array
    {
        $out = [];

        foreach ($rows as $row) {
            $due = (float) $row->due;
            $paid = (float) $row->paid;

            if ($due <= self::EPSILON && $paid <= self::EPSILON) {
                continue;
            }

            $out[] = $identity($row) + [
                'due' => round($due, 2),
                'collected' => round($paid, 2),
                'owed' => round(max(0.0, $due - $paid), 2),
                'rate' => $due > 0 ? min(100.0, round($paid / $due * 100, 1)) : 100.0,
            ];
        }

        usort($out, fn ($a, $b) => [$a['rate'], -$a['owed']] <=> [$b['rate'], -$b['owed']]);

        return $out;
    }

    /**
     * Fee cash received, month by month across the budget year.
     *
     * Deliberately NOT net paid, and deliberately dated by pay_date: this is
     * the same arithmetic as DaybookService::feeRows(), so this curve and the
     * money-in bars already on the page cannot disagree. Net paid would move
     * an overpayment out of the month the cash actually arrived and show the
     * later credit as fresh money.
     *
     * The limitation is in the column, not here: fees.pay_date is one date, so
     * a fee paid in instalments dates all of its cash to the last payment. The
     * daybook has always worked this way; the page says so rather than
     * implying a precision the column does not have.
     */
    protected function byMonth(Budget $budget, string $from, string $to): array
    {
        $cash = Fee::cashReceivedSql('f');

        $rows = DB::table('fees as f')
            ->where('f.status', '<>', self::CANCELLED)
            ->where('f.paid_amount', '>', 0)
            ->whereNotNull('f.pay_date')
            ->whereDate('f.pay_date', '>=', $from)
            ->whereDate('f.pay_date', '<=', $to)
            ->groupBy('bucket')
            ->selectRaw("DATE_FORMAT(f.pay_date, '%Y-%m') as bucket, SUM({$cash}) as cash")
            ->pluck('cash', 'bucket');

        // Walked from the budget's own periods, so an empty month is a zero in
        // the series rather than a gap the chart closes up.
        $months = [];

        foreach (app(DaybookService::class)->periodsFor($budget) as $period) {
            $start = $this->day($period->start_date);
            $key = substr((string) $start, 0, 7);

            $months[] = [
                'label' => $this->shortLabel($period->name),
                'key' => $key,
                'cash' => round((float) ($rows[$key] ?? 0), 2),
            ];
        }

        $running = 0.0;
        foreach ($months as $i => $month) {
            $running += $month['cash'];
            $months[$i]['cumulative'] = round($running, 2);
        }

        return $months;
    }

    /** "September 2026" -> "Sep 26", to fit an axis. */
    protected function shortLabel(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));

        if (count($parts) === 2 && strlen($parts[1]) === 4) {
            return substr($parts[0], 0, 3) . ' ' . substr($parts[1], 2);
        }

        return $name;
    }

    /* ===================================================================
     |  What cannot be placed
     |===================================================================*/

    /**
     * Fees that belong to nobody this code can name.
     *
     * Two real cases on this install: a fee whose student_enroll_id points at
     * an enrolment that no longer exists, and an enrolment carrying neither a
     * student nor an applicant. Both are excluded from every figure above —
     * which is correct, because they cannot be attributed to a person — and so
     * both are reported here. The alternative is a page whose totals quietly
     * omit 150,000.
     *
     * Not scoped to the window. A broken link is worth knowing about whenever
     * it was assigned.
     */
    protected function unattributed(): array
    {
        $net = Fee::netPaidSql('f');
        $due = $this->dueSql('f');

        $rows = DB::table('fees as f')
            ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
            ->leftJoin('fees_categories as fc', 'fc.id', '=', 'f.category_id')
            ->where('f.status', '<>', self::CANCELLED)
            ->whereRaw($this->personColumn() . ' IS NULL')
            ->orderByDesc('f.assign_date')
            ->selectRaw('f.id, f.student_enroll_id, f.assign_date')
            ->selectRaw("fc.title as category, {$due} as due, {$net} as paid")
            ->selectRaw('CASE WHEN f.student_enroll_id IS NOT NULL AND se.id IS NULL
                              THEN \'missing_enrolment\' ELSE \'no_person\' END as reason')
            ->get();

        return [
            'count' => $rows->count(),
            'amount' => round($rows->sum(fn ($r) => (float) $r->due), 2),
            'owed' => round($rows->sum(fn ($r) => max(0.0, (float) $r->due - (float) $r->paid)), 2),
            'rows' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'enroll_id' => $r->student_enroll_id ? (int) $r->student_enroll_id : null,
                'category' => $r->category ?: __('No category'),
                'assign_date' => $r->assign_date,
                'due' => round((float) $r->due, 2),
                'owed' => round(max(0.0, (float) $r->due - (float) $r->paid), 2),
                'reason' => $r->reason,
            ])->all(),
        ];
    }

    /* ===================================================================
     |  Shared SQL
     |===================================================================*/

    /**
     * What a fee is worth, matching Fee::getTotalAmountAttribute().
     *
     * Not fee_amount: on this install budget 2025/2026 shows 20,371,000 billed
     * against 26,604,000 received, which only reconciles once fines are in.
     */
    protected function dueSql(string $alias = 'f'): string
    {
        return "({$alias}.fee_amount - COALESCE({$alias}.discount_amount, 0)"
            . " + COALESCE({$alias}.fine_amount, 0))";
    }

    /**
     * The person a fee belongs to: the student, else the applicant.
     *
     * Returned as SQL for whereRaw rather than as a column name, because
     * whereNotNull cannot take an expression.
     */
    protected function personColumn(): string
    {
        return 'COALESCE(se.student_id, f.applicant_id)';
    }

    /**
     * The grouping key, prefixed by kind.
     *
     * Without the prefix, student 7 and applicant 7 group together as one
     * person — two different people, one of whom is not enrolled.
     */
    protected function personKeySql(): string
    {
        return "CASE WHEN se.student_id IS NOT NULL
                     THEN CONCAT('s', se.student_id)
                     ELSE CONCAT('a', f.applicant_id) END";
    }

    protected function personKindSql(): string
    {
        return "CASE WHEN se.student_id IS NOT NULL THEN 'student' ELSE 'applicant' END";
    }

    protected function day($value): ?string
    {
        if (!$value) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }

    /** A budget with no dates still has to render a page. */
    protected function nothing(?string $from, ?string $to): array
    {
        return [
            'window' => ['from' => $from, 'to' => $to],
            'students' => $this->tally([]),
            'applicants' => $this->tally([]),
            'combined' => $this->tally([]),
            'arrears' => ['people' => 0, 'amount' => 0.0, 'oldest' => null],
            'plans' => ['people' => 0, 'amount' => 0.0],
            'by_programme' => [],
            'by_category' => [],
            'by_month' => [],
            'top_debtors' => [],
            'unattributed' => ['count' => 0, 'amount' => 0.0, 'owed' => 0.0, 'rows' => []],
            'has_data' => false,
        ];
    }
}
