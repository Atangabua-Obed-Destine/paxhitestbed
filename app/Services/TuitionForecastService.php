<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Session;
use Illuminate\Support\Facades\DB;

/**
 * Budgeting tuition from the enrolment you expect, rather than a typed number.
 *
 * A Bursar plans by reasoning "about sixty students in Software Engineering, and
 * they pay 350,000 a year". The amount is a consequence of that assumption, and
 * a sheet that records only the amount throws the reasoning away: nobody can
 * tell later whether a figure was forecast or guessed, and nothing recalculates
 * when fees change.
 *
 * The fee a student pays is configured per programme and semester in
 * `program_semester_fees` (Academic > Programme Semester Fee). Budget lines are
 * per FACULTY, so a faculty needs one rate — and a plain average of its
 * programmes is wrong wherever they differ in price. Sixty of Computer
 * Engineering's sixty-three students are in Software Engineering at 350,000
 * while Computer Science charges 150,000: the simple mean understates that
 * faculty by 5.7 million, twenty-seven per cent.
 *
 * So the rate is weighted by enrolment, which is exact whenever the forecast
 * keeps today's programme mix, and the mix it came from is handed back with it
 * so the screen can show its working instead of asserting a number.
 *
 * WHO IS COUNTED. The headcount is the same population as Admission > Student
 * shows for a faculty and a session: one per student, with an active
 * enrolment, for a student still on the books, in the session the budget is
 * for. It used to count enrolment rows with no filter at all, which took a
 * student with two semesters and a resit as three or four, kept counting
 * students who had withdrawn or finished, and counted applicants who never
 * became students: the Business school read 47 on the sheet against 2 on the
 * student list.
 */
class TuitionForecastService
{
    /**
     * Tuition excludes admission and resit fees.
     *
     * They are real money but they are not tuition: admission lands on the
     * registration line and resits on their own, so counting them here would
     * both overstate tuition and double-count them against those lines.
     */
    protected function tuitionCategoryIds(): array
    {
        return DB::table('fees_categories')
            ->where('is_admission', 0)
            ->where('is_resit', 0)
            ->pluck('id')
            ->all();
    }

    /**
     * What one student on a programme pays in tuition across a year.
     *
     * @return array<int, array{fee: float, terms: int}> keyed by program id
     */
    public function programmeFees(): array
    {
        $categories = $this->tuitionCategoryIds();

        if ($categories === []) {
            return [];
        }

        return DB::table('program_semester_fees')
            ->whereIn('fees_category_id', $categories)
            ->where('status', 1)
            ->selectRaw('program_id, SUM(amount) as fee, COUNT(*) as terms')
            ->groupBy('program_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->program_id => [
                'fee' => (float) $r->fee,
                'terms' => (int) $r->terms,
            ]])
            ->all();
    }

    /**
     * The session a budget is for.
     *
     * A budget carries no session of its own, only a title and a pair of
     * dates. The title is tried first, because budgets are named after the
     * academic year they fund ("2026/2027"); failing that, the session whose
     * dates overlap the budget's the most; failing that, the current session,
     * so a budget named unconventionally still gets a count rather than none.
     */
    public function sessionForBudget(?Budget $budget): ?Session
    {
        if ($budget) {
            $title = trim((string) $budget->title);

            if ($title !== '') {
                $byTitle = Session::where('status', 1)->where('title', $title)->first();

                if ($byTitle) {
                    return $byTitle;
                }
            }

            if ($budget->start_date && $budget->end_date) {
                $best = null;
                $bestDays = 0;

                $overlapping = Session::where('status', 1)
                    ->whereNotNull('start_date')
                    ->whereNotNull('end_date')
                    ->where('start_date', '<=', $budget->end_date)
                    ->where('end_date', '>=', $budget->start_date)
                    ->get();

                foreach ($overlapping as $session) {
                    $from = max(strtotime((string) $session->start_date), strtotime((string) $budget->start_date));
                    $to = min(strtotime((string) $session->end_date), strtotime((string) $budget->end_date));
                    $days = (int) floor(($to - $from) / 86400) + 1;

                    if ($days > $bestDays) {
                        $best = $session;
                        $bestDays = $days;
                    }
                }

                if ($best) {
                    return $best;
                }
            }
        }

        return Session::where('current', 1)->where('status', 1)->first();
    }

    /** The session before this one, by start date where there is one. */
    public function previousSession(?Session $session): ?Session
    {
        if (!$session) {
            return null;
        }

        $query = Session::where('status', 1)->where('id', '!=', $session->id);

        if ($session->start_date) {
            return $query->whereNotNull('start_date')
                ->where('start_date', '<', $session->start_date)
                ->orderByDesc('start_date')
                ->first();
        }

        return $query->where('id', '<', $session->id)->orderByDesc('id')->first();
    }

    /**
     * Students per programme of a faculty in one session, counted the way the
     * student list counts them.
     *
     * One per student: an active enrolment (status 1) of a student who is
     * still on the books (students.status 1), in this session. A student with
     * a semester row and a resit row is one student; a withdrawn (0) or
     * completed (2) enrolment is not counted, which is the list's own rule;
     * an applicant row has no student behind it and does not join.
     *
     * Resit rows are deliberately not excluded. A student whose only active
     * row in a session is a resit is on the student list for that session,
     * and the figure here has to be the figure the Bursar can check there.
     *
     * @return array<int, int> keyed by program id
     */
    public function headcountByProgramme(int $facultyId, ?int $sessionId): array
    {
        if (!$sessionId) {
            return [];
        }

        return DB::table('student_enrolls as se')
            ->join('students as s', 's.id', '=', 'se.student_id')
            ->join('programs as p', 'p.id', '=', 'se.program_id')
            ->where('p.faculty_id', $facultyId)
            ->where('se.session_id', $sessionId)
            ->where('se.status', 1)
            ->where('s.status', 1)
            ->selectRaw('se.program_id, COUNT(DISTINCT se.student_id) as students')
            ->groupBy('se.program_id')
            ->pluck('students', 'program_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * The rate for a faculty, and the programme mix behind it.
     *
     * The headcount is the budget session's. When nobody is enrolled for that
     * session yet — a budget drawn up before intake — there is nothing to
     * weight by, so the previous session's mix weights the rate instead, and
     * `weighted_by` says so; the count offered is still the budget session's,
     * because that is the fact, and the previous session's is handed back
     * beside it as the reference a Bursar actually reasons from.
     *
     * @return array{
     *   rate: float, students: int, previous_students: int, simple_rate: float,
     *   expected_total: float, weighted_by: string,
     *   session: ?array, previous_session: ?array,
     *   programmes: array<int, array>, warnings: array<int, string>
     * }
     */
    public function facultyRate(int $facultyId, ?Session $session = null, ?Session $previous = null): array
    {
        $session = $session ?: Session::where('current', 1)->where('status', 1)->first();
        $previous = $previous ?: $this->previousSession($session);

        $fees = $this->programmeFees();
        $now = $this->headcountByProgramme($facultyId, $session?->id);
        $before = $this->headcountByProgramme($facultyId, $previous?->id);

        $programmes = DB::table('programs')
            ->where('faculty_id', $facultyId)
            ->select('id', 'title')
            ->orderBy('title')
            ->get();

        // The commonest term count in this faculty is the yardstick: a
        // programme configured for fewer terms than its siblings is almost
        // always an unfinished fee setup, and would quietly halve that
        // programme's contribution to the forecast.
        $termCounts = [];
        foreach ($programmes as $programme) {
            if (isset($fees[$programme->id])) {
                $termCounts[] = $fees[$programme->id]['terms'];
            }
        }
        $expectedTerms = $termCounts === [] ? 0 : max($termCounts);

        $rows = [];
        $warnings = [];
        $totalStudents = 0;
        $totalPrevious = 0;
        $weightedNow = 0.0;      // Σ students × fee, this session
        $weightedBefore = 0.0;   // Σ students × fee, the previous session
        $simpleSum = 0.0;
        $priced = 0;

        foreach ($programmes as $programme) {
            $fee = $fees[$programme->id]['fee'] ?? 0.0;
            $terms = $fees[$programme->id]['terms'] ?? 0;
            $students = $now[$programme->id] ?? 0;
            $previousStudents = $before[$programme->id] ?? 0;

            $rows[] = [
                'id' => (int) $programme->id,
                'title' => $programme->title,
                'students' => $students,
                'previous_students' => $previousStudents,
                'fee' => $fee,
                'terms' => $terms,
                'incomplete' => $terms > 0 && $terms < $expectedTerms,
            ];

            $totalStudents += $students;
            $totalPrevious += $previousStudents;

            if ($fee <= 0) {
                $warnings[] = __(':programme has no tuition fee configured, so it adds nothing to this rate.', [
                    'programme' => $programme->title,
                ]);
                continue;
            }

            if ($terms < $expectedTerms) {
                $warnings[] = __(':programme has :terms of :expected terms configured, so its fee may be understated.', [
                    'programme' => $programme->title,
                    'terms' => $terms,
                    'expected' => $expectedTerms,
                ]);
            }

            $simpleSum += $fee;
            $priced++;
            $weightedNow += $students * $fee;
            $weightedBefore += $previousStudents * $fee;
        }

        $simpleRate = $priced > 0 ? $simpleSum / $priced : 0.0;

        // Weighted by enrolment, so it is exact for the mix on record. With
        // nobody in the budget's session yet, last session's mix is the best
        // evidence of what the mix will be; with no students at all there is
        // nothing to weight by, and the plain average is the honest fallback.
        if ($totalStudents > 0) {
            $rate = $weightedNow / $totalStudents;
            $weightedBy = 'session';
        } elseif ($totalPrevious > 0) {
            $rate = $weightedBefore / $totalPrevious;
            $weightedBy = 'previous';
        } else {
            $rate = $simpleRate;
            $weightedBy = 'average';
        }

        return [
            'rate' => $rate,
            'simple_rate' => $simpleRate,
            'students' => $totalStudents,
            'previous_students' => $totalPrevious,
            'expected_total' => $weightedNow,
            'weighted_by' => $weightedBy,
            'session' => $session ? ['id' => $session->id, 'title' => $session->title] : null,
            'previous_session' => $previous ? ['id' => $previous->id, 'title' => $previous->title] : null,
            'programmes' => $rows,
            'warnings' => $warnings,
        ];
    }

    /** Income lines tied to a faculty — the ones student numbers can drive. */
    public function forecastableLines()
    {
        return BudgetLine::whereNotNull('faculty_id')
            ->where('section', BudgetLine::SECTION_INCOME)
            ->where('is_header', false)
            ->with('faculty')
            ->get();
    }

    /**
     * Rates for every forecastable line, keyed by line id.
     *
     * Gathered once for the sheet rather than per row: four faculties would
     * otherwise mean four repetitions of the same queries. The session is
     * resolved once from the budget, so every line on a sheet counts the same
     * year.
     */
    public function ratesByLine(?Budget $budget = null): array
    {
        $session = $this->sessionForBudget($budget);
        $previous = $this->previousSession($session);

        $rates = [];

        foreach ($this->forecastableLines() as $line) {
            $rates[$line->id] = $this->facultyRate((int) $line->faculty_id, $session, $previous) + [
                'faculty' => optional($line->faculty)->title,
            ];
        }

        return $rates;
    }

    /** Students × rate, rounded to the franc. */
    public function forecast(int $students, float $rate): float
    {
        return round(max(0, $students) * max(0.0, $rate), 2);
    }
}
