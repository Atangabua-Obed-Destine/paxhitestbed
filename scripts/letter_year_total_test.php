<?php

/**
 * The academic-year fee total placeholders on the acceptance letter.
 *
 * [fee_breakdown_total] only ever quoted the first instalment, so a letter could
 * not tell an applicant what the year costs. [fee_year_total] adds up every fee
 * configured for the programme's year under Programme Semester Fees, and
 * [fee_year_total_words] writes the same figure out in words.
 *
 * The expected figure is computed here from the fee rows directly, by a separate
 * query, rather than by calling the thing under test — otherwise a rule that
 * summed the wrong set of rows would agree with itself.
 *
 * Every write happens inside a transaction that is rolled back.
 *
 *   php scripts/letter_year_total_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\FeesCategory;
use App\Models\ProgramSemesterFee;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\AcceptanceLetterService;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

$service = app(AcceptanceLetterService::class);
$setting = Setting::where('status', '1')->first();
$dp = $setting->decimal_place ?? 0;
$money = fn ($n) => number_format((float) $n, $dp, '.', ',');

/**
 * What the year costs, worked out from the fee rows here rather than by asking
 * the service. Deliberately a plain, separate query.
 */
function expectedYearTotal(?int $programId, int $year): float
{
    if (!$programId) {
        return 0.0;
    }

    $ids = DB::table('semesters')
        ->join('program_semester', 'program_semester.semester_id', '=', 'semesters.id')
        ->where('program_semester.program_id', $programId)
        ->where('semesters.is_resit', 0)
        ->where('semesters.year', (string) $year)
        ->pluck('semesters.id');

    if ($ids->isEmpty()) {
        return 0.0;
    }

    return (float) DB::table('program_semester_fees')
        ->where('program_id', $programId)
        ->whereIn('semester_id', $ids)
        ->where('status', 1)
        ->sum('amount');
}

// A student on a programme that actually has Year-1 fees configured — found
// from the fee rows, so these checks cannot pass by testing an empty programme.
$subject = null;

foreach (Student::whereNotNull('program_id')->whereHas('studentEnrolls')->limit(200)->get() as $candidate) {
    if (expectedYearTotal((int) $candidate->program_id, 1) > 0) {
        $subject = $candidate;
        break;
    }
}

if (!$subject) {
    echo "no student is on a programme with Year-1 fees configured\n";
    exit(0);
}

$student = $subject;
$programId = (int) $student->program_id;
$expected = expectedYearTotal($programId, 1);

echo $student->student_id . ' — ' . optional($student->program)->title
    . "\nYear-1 fees configured sum to " . $money($expected) . "\n";

// ---------------------------------------------------------------------------
section('The figure');
// ---------------------------------------------------------------------------
$data = $service->getYearFeeTotalData($student);

check('the year total matches the configured fee rows',
    abs($data['raw'] - $expected) < 0.01,
    $data['raw'] . ' vs ' . $expected);

check('it is formatted with the school\'s settings',
    $data['total'] === $money($expected),
    $data['total'] . ' vs ' . $money($expected));

$installmentOnly = (float) str_replace(',', '', $service->getFeeBreakdownData($student)['total']);

if ($data['rows'] < 2) {
    skip('this programme has only one fee row, so there is no instalment to out-total');
} else {
    check('it is more than the first instalment alone',
        $data['raw'] > $installmentOnly,
        'quoting one instalment as the year total is the bug this exists to fix — '
            . $data['raw'] . ' vs ' . $installmentOnly);
}

// ---------------------------------------------------------------------------
section('In words');
// ---------------------------------------------------------------------------
check('the words are not left as a placeholder', trim($data['words']) !== '');

check('the words spell out the figure, not something else',
    $data['words'] === $service->spellOut($expected),
    $data['words']);

// Independently known cases, so a spellout that silently returned the digits or
// a rounded figure would be caught.
check('a known amount is written out correctly',
    $service->spellOut(350000) === 'Three Hundred Fifty Thousand',
    $service->spellOut(350000));
check('and one with hundreds and tens',
    $service->spellOut(138500) === 'One Hundred Thirty-eight Thousand Five Hundred',
    $service->spellOut(138500));

// ---------------------------------------------------------------------------
section('What counts towards the year');
// ---------------------------------------------------------------------------
$firstSemester = Semester::where('is_resit', 0)->where('year', '1')
    ->whereHas('programs', fn ($q) => $q->where('program_id', $programId))
    ->orderBy('id')->first();
$resitSemester = Semester::where('is_resit', 1)->where('year', '1')
    ->whereHas('programs', fn ($q) => $q->where('program_id', $programId))
    ->orderBy('id')->first();
$extraCategory = FeesCategory::where('is_first_installment', 0)
    ->where('is_second_installment', 0)->first();

if (!$firstSemester || !$extraCategory) {
    skip('no Year-1 semester or spare fee category to add a row with');
} else {
    DB::beginTransaction();
    try {
        ProgramSemesterFee::create([
            'program_id' => $programId,
            'semester_id' => $firstSemester->id,
            'fees_category_id' => $extraCategory->id,
            'amount' => 7500,
            'status' => 1,
        ]);

        $got = $service->getYearFeeTotalData($student->fresh())['raw'];
        check('a fee that is not an instalment still counts',
            abs($got - ($expected + 7500)) < 0.01,
            'got ' . $got . ', expected ' . ($expected + 7500));
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();
    try {
        ProgramSemesterFee::create([
            'program_id' => $programId,
            'semester_id' => $firstSemester->id,
            'fees_category_id' => $extraCategory->id,
            'amount' => 7500,
            'status' => 0,
        ]);

        $got = $service->getYearFeeTotalData($student->fresh())['raw'];
        check('a fee that is switched off does not',
            abs($got - $expected) < 0.01, 'got ' . $got);
    } finally {
        DB::rollBack();
    }
}

if (!$resitSemester || !$extraCategory) {
    skip('no resit semester attached to this programme');
} else {
    DB::beginTransaction();
    try {
        ProgramSemesterFee::create([
            'program_id' => $programId,
            'semester_id' => $resitSemester->id,
            'fees_category_id' => $extraCategory->id,
            'amount' => 25000,
            'status' => 1,
        ]);

        $got = $service->getYearFeeTotalData($student->fresh())['raw'];
        check('a resit fee is left out of the year total',
            abs($got - $expected) < 0.01,
            'a resit is only charged on failure, so counting it overstates the year — got ' . $got);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Which year is quoted');
// ---------------------------------------------------------------------------
$secondYear = Semester::where('is_resit', 0)->where('year', '2')
    ->whereHas('programs', fn ($q) => $q->where('program_id', $programId))
    ->orderBy('id')->first();

if (!$secondYear) {
    skip('this programme has no Year-2 semester to progress into');
} else {
    DB::beginTransaction();
    try {
        $existing = $student->studentEnrolls->first();

        StudentEnroll::create([
            'student_id' => $student->id,
            'program_id' => $programId,
            'session_id' => $existing->session_id,
            'semester_id' => $secondYear->id,
            'section_id' => $existing->section_id,
            'status' => 1,
        ]);

        // A Year-2 fee is configured on purpose and made different from Year 1,
        // so this cannot pass by the two years happening to cost the same — an
        // "unchanged total" would then prove nothing.
        if ($extraCategory) {
            ProgramSemesterFee::create([
                'program_id' => $programId,
                'semester_id' => $secondYear->id,
                'fees_category_id' => $extraCategory->id,
                'amount' => $expected + 90000,
                'status' => 1,
            ]);
        }

        $got = $service->getYearFeeTotalData($student->fresh())['raw'];
        check('progressing to Year 2 does not change the letter\'s figure',
            abs($got - $expected) < 0.01,
            'a reissued letter must quote the year the student was admitted into — got '
                . $got . ', Year 1 is ' . $expected);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A student with nothing configured');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $student->forceFill(['program_id' => null])->save();

    $none = $service->getYearFeeTotalData($student->fresh());
    check('no programme means a zero total, not a crash', abs($none['raw']) < 0.01);
    check('and the words say so', $none['words'] === 'Zero', $none['words']);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The placeholders in a real letter');
// ---------------------------------------------------------------------------
$degreeType = $service->degreeType($student);

if (!$degreeType) {
    skip('this student\'s programme has no degree type');
} else {
    DB::beginTransaction();
    try {
        $s = $degreeType->applicationSetting
            ?: $degreeType->applicationSetting()->create(['acceptance_letter_enabled' => 1]);

        $s->forceFill([
            'acceptance_letter_enabled' => 1,
            'acceptance_letter_html' => '<p>Year fee: [fee_year_total] ([fee_year_total_words]).</p>',
        ])->save();
        $degreeType->unsetRelation('applicationSetting');

        $html = $service->resolveHtml($student->fresh());

        check('the letter renders', is_string($html) && $html !== '');
        check('the figure is substituted in',
            is_string($html) && str_contains($html, $money($expected)),
            'looking for ' . $money($expected));
        check('the words are substituted in',
            is_string($html) && str_contains($html, $service->spellOut($expected)),
            'looking for ' . $service->spellOut($expected));
        check('no placeholder is left showing',
            is_string($html)
                && !str_contains($html, '[fee_year_total]')
                && !str_contains($html, '[fee_year_total_words]'),
            'an unsubstituted token goes out to the applicant as literal text');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The admin preview');
// ---------------------------------------------------------------------------
if (!$degreeType) {
    skip('no degree type to preview');
} else {
    DB::beginTransaction();
    try {
        $s = $degreeType->applicationSetting
            ?: $degreeType->applicationSetting()->create(['acceptance_letter_enabled' => 1]);
        $s->forceFill([
            'acceptance_letter_html' => '<p>Year fee: [fee_year_total] ([fee_year_total_words]).</p>',
        ])->save();
        $degreeType->unsetRelation('applicationSetting');

        $pdf = $service->previewPdf($degreeType->fresh());
        $out = $pdf->output();

        check('the preview PDF is produced', strlen($out) > 1000, strlen($out) . ' bytes');
        check('and it is a PDF', str_starts_with($out, '%PDF'));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('the fee rows are as they were',
    abs(expectedYearTotal((int) $student->fresh()->program_id, 1) - $expected) < 0.01);

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
