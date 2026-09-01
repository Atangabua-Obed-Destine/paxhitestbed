<?php

namespace App\Services;

use App\Models\BudgetLine;
use App\Models\Faculty;
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
     * The rate for a faculty, and the programme mix behind it.
     *
     * @return array{
     *   rate: float, students: int, simple_rate: float,
     *   programmes: array<int, array>, warnings: array<int, string>
     * }
     */
    public function facultyRate(int $facultyId): array
    {
        $fees = $this->programmeFees();

        $programmes = DB::table('programs as p')
            ->leftJoin('student_enrolls as se', 'se.program_id', '=', 'p.id')
            ->where('p.faculty_id', $facultyId)
            ->selectRaw('p.id, p.title, COUNT(se.id) as students')
            ->groupBy('p.id', 'p.title')
            ->orderBy('p.title')
            ->get();

        $rows = [];
        $totalStudents = 0;
        $totalFees = 0.0;
        $simpleSum = 0.0;
        $priced = 0;

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

        $warnings = [];

        foreach ($programmes as $programme) {
            $fee = $fees[$programme->id]['fee'] ?? 0.0;
            $terms = $fees[$programme->id]['terms'] ?? 0;
            $students = (int) $programme->students;

            $rows[] = [
                'id' => (int) $programme->id,
                'title' => $programme->title,
                'students' => $students,
                'fee' => $fee,
                'terms' => $terms,
                'incomplete' => $terms > 0 && $terms < $expectedTerms,
            ];

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

            $totalStudents += $students;
            $totalFees += $students * $fee;
        }

        return [
            // Weighted by enrolment, so it is exact for today's mix. With no
            // students on record there is nothing to weight by, and the plain
            // average is the honest fallback.
            'rate' => $totalStudents > 0
                ? $totalFees / $totalStudents
                : ($priced > 0 ? $simpleSum / $priced : 0.0),
            'simple_rate' => $priced > 0 ? $simpleSum / $priced : 0.0,
            'students' => $totalStudents,
            'expected_total' => $totalFees,
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
     * otherwise mean four repetitions of the same two queries.
     */
    public function ratesByLine(): array
    {
        $rates = [];

        foreach ($this->forecastableLines() as $line) {
            $rates[$line->id] = $this->facultyRate((int) $line->faculty_id) + [
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
