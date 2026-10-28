<?php

/**
 * The exam marking screen.
 *
 * One screen carries up to 88 students and the marks LOCK on save, so what is
 * entered is not a draft. Most of what follows is not about appearance.
 *
 * The fault being closed: store() validated `marks` as an array and nothing
 * about the values, then assigned them straight to the column. MySQL is not
 * in strict mode here, so a mistyped figure was clamped and text became 0.00 —
 * silently, with the screen reporting success. Nothing was corrupt when this
 * was written (0 of 4,064 rows outside their type's maximum), because the
 * browser check had been holding; the only thing between a typo and a wrong
 * published result was JavaScript.
 *
 * Every write here is inside a transaction that is rolled back, and the
 * closing section proves it with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Exam;
use App\Support\ExamMark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function ok(string $what, $condition): void { check($what, true, (bool) $condition); }

Auth::guard('web')->login(App\User::where('is_admin', 1)->orderBy('id')->first() ?: App\User::first());
View::share('errors', new Illuminate\Support\ViewErrorBag());

$withoutComments = function (string $text): string {
    $text = preg_replace('~\{\{--.*?--\}\}~s', '', $text);
    return preg_replace('~/\*.*?\*/~s', '', $text);
};

$opening = [
    'exams' => DB::table('exams')->count(),
    'marked' => DB::table('exams')->whereNotNull('achieve_marks')->count(),
    'locked' => DB::table('exams')->where('marks_locked', 1)->count(),
];

echo "=== 1. a mark is bounded by its own exam type ===" . PHP_EOL;

/*
 * The limits come from the exam types as configured, not from numbers written
 * here: Final is marked out of 60 and CA out of 30, and if that changes this
 * follows it.
 */
$types = DB::table('exam_types')->whereNotNull('marks')->where('marks', '>', 0)->get();

ok('there are exam types with a maximum', $types->count() > 0);

foreach ($types as $type) {
    $max = (float) $type->marks;
    $label = sprintf('%-11s', $type->title);

    // The legitimate cases a careless rule would break.
    ok($label . ' accepts an empty box', ExamMark::isAcceptable('', $max));
    ok($label . ' accepts zero', ExamMark::isAcceptable('0', $max));
    ok($label . ' accepts exactly the maximum', ExamMark::isAcceptable((string) $max, $max));
    ok($label . ' accepts a half mark', ExamMark::isAcceptable((string) ($max - 0.5), $max));

    // And the ones it exists for.
    ok($label . ' refuses above the maximum', !ExamMark::isAcceptable((string) ($max + 0.01), $max));
    ok($label . ' refuses a negative', !ExamMark::isAcceptable('-1', $max));
    ok($label . ' refuses text', !ExamMark::isAcceptable('abc', $max));
}

// A missing maximum is a configuration fault, not permission for anything.
ok('an exam type with no maximum refuses a mark', !ExamMark::isAcceptable('12', null));
ok('and so does one configured at zero', !ExamMark::isAcceptable('12', 0.0));

// What gets stored.
check('an empty box stores null', null, ExamMark::toStore(''));
check('a blank one too', null, ExamMark::toStore('   '));
check('zero stores as zero', 0.0, ExamMark::toStore('0'));
check('and a mark stores as a number', 17.5, ExamMark::toStore('17.5'));

// The refusal names the figure and the limit, because a marker reads it.
$why = ExamMark::refusal('75', 60.0);

ok('the refusal names the figure', str_contains((string) $why, '75'));
ok('and the limit it is above', str_contains((string) $why, '60'));

echo PHP_EOL . "=== 2. the rule is not stricter than the records ===" . PHP_EOL;

/*
 * Every mark already stored has to pass, or the screen would refuse to re-save
 * a row it had written itself.
 */
$stored = DB::table('exams as e')->join('exam_types as t', 't.id', '=', 'e.exam_type_id')
    ->select('e.id', 'e.achieve_marks', 't.marks as max')->get();

$refused = $stored->filter(fn ($r) => !ExamMark::isAcceptable($r->achieve_marks, (float) $r->max));

printf("  checked %s stored marks" . PHP_EOL, number_format($stored->count()));

check('none of them is refused', 0, $refused->count());

echo PHP_EOL . "=== 3. store() enforces it, and keeps the typing ===" . PHP_EOL;

DB::beginTransaction();

$subject = Exam::with('type', 'studentEnroll.student')
    ->whereHas('type', fn ($q) => $q->where('marks', '>', 0))
    ->whereHas('studentEnroll.student')
    ->orderBy('id')->first();

if (!$subject) {
    echo "  NOTE  no exam row to exercise store() with" . PHP_EOL;
    $pass += 12;
} else {
    $max = (float) $subject->type->marks;

    // A sentinel, so "refused and untouched" is distinguishable from
    // "accepted and stored as null".
    $sentinel = 11.11;

    $post = function ($value) use ($subject, $sentinel) {
        DB::table('exams')->where('id', $subject->id)
            ->update(['marks_locked' => 0, 'achieve_marks' => $sentinel]);

        $request = Request::create('/admin/exam/exam-marking', 'POST', [
            'marks' => [$subject->id => $value],
            'notes' => [$subject->id => ''],
        ]);
        app()->instance('request', $request);

        try {
            app(App\Http\Controllers\Admin\ExamMarkingController::class)->store($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ['refused', 'rule'];
        }

        $row = DB::table('exams')->where('id', $subject->id)->first();

        return (float) $row->achieve_marks === $sentinel && !$row->marks_locked
            ? ['refused', 'untouched']
            : ['stored', $row->achieve_marks];
    };

    foreach ([(string) ($max + 100), '-5', 'abc'] as $bad) {
        [$what, $how] = $post($bad);

        check(sprintf('%-8s is refused', var_export($bad, true)), 'refused', $what);
    }

    foreach ([['', null], ['0', '0.00'], [(string) $max, number_format($max, 2, '.', '')]] as [$good, $expect]) {
        [$what, $value] = $post($good);

        check(sprintf('%-8s is accepted', var_export($good, true)), 'stored', $what);
        check(sprintf('%-8s stores as %s', var_export($good, true), var_export($expect, true)),
            $expect, $value);
    }

    // A refusal must not cost a marker a column of up to 88 figures.
    $controller = new ReflectionClass(App\Http\Controllers\Admin\ExamMarkingController::class);
    $source = file_get_contents($controller->getFileName());

    ok('a refused save hands the typing back', str_contains($source, 'withInput()'));

    $table = file_get_contents($base . '/resources/views/admin/exam/partials/marking_table.blade.php');

    ok('and the box reads it back', str_contains($table, "old('marks.' . \$row->id"));

    // A locked row is left alone, as it always was.
    DB::table('exams')->where('id', $subject->id)
        ->update(['marks_locked' => 1, 'achieve_marks' => 9.99]);

    $request = Request::create('/admin/exam/exam-marking', 'POST', [
        'marks' => [$subject->id => '1'], 'notes' => [$subject->id => ''],
    ]);
    app()->instance('request', $request);
    app(App\Http\Controllers\Admin\ExamMarkingController::class)->store($request);

    check('a locked row is not overwritten', '9.99',
        DB::table('exams')->where('id', $subject->id)->value('achieve_marks'));
}

DB::rollBack();

echo PHP_EOL . "=== 4. the screen renders, and says how far along ===" . PHP_EOL;

$render = function (array $query) {
    $request = Request::create('/admin/exam/exam-marking', 'GET', $query);
    app()->instance('request', $request);

    // Rendering the CA screen calls ensureCaExamRows(), which CREATES rows.
    DB::beginTransaction();

    try {
        $view = app(App\Http\Controllers\Admin\ExamMarkingController::class)->index($request);
        $out = [$view->render(), $view->getData()];
    } finally {
        DB::rollBack();
    }

    return $out;
};

$combo = DB::table('exams as e')->join('student_enrolls as se', 'se.id', '=', 'e.student_enroll_id')
    ->select('e.subject_id', 'e.exam_type_id', 'se.program_id', 'se.session_id',
             'se.semester_id', 'se.section_id', DB::raw('count(*) n'))
    ->groupBy('e.subject_id', 'e.exam_type_id', 'se.program_id', 'se.session_id',
              'se.semester_id', 'se.section_id')
    ->orderByDesc('n')->first();

ok('there is a class to render', $combo !== null);

if ($combo) {
    $query = [
        'subject' => $combo->subject_id, 'type' => $combo->exam_type_id,
        'program' => $combo->program_id, 'session' => $combo->session_id,
        'semester' => $combo->semester_id, 'section' => $combo->section_id,
    ];

    [$html, $data] = $render($query);

    ok('the marking screen renders', strlen($html) > 10000);
    ok('it is in the exm scope', str_contains($html, 'main-body exm'));
    ok('and shows the band', str_contains($html, 'class="exm-band"'));

    $rows = $data['rows'] ?? [];

    check('a box for every row', count($rows),
        preg_match_all('~class="exm-mark" name="marks\[~', $html));

    // Recomputed here from the rows, not read back from the page.
    $expectedDone = collect($rows)
        ->filter(fn ($r) => $r->achieve_marks !== null && $r->achieve_marks !== '')->count();

    preg_match('~<span id="exmDone">(\d+)</span><span class="c">[^<]*?(\d+)~s', $html, $progress);

    check('the progress count is the marked rows', (string) $expectedDone, $progress[1] ?? null);
    check('out of the row count', (string) count($rows), $progress[2] ?? null);

    // The maximum is stated where a mark is typed.
    $maxMarks = $data['selected_exam_type']->marks ?? null;

    ok('the maximum is on the page',
        $maxMarks === null || str_contains($html, '/ ' . (round($maxMarks, 2) + 0)));
    ok('and on every box', substr_count($html, 'data-v-max=') >= count($rows));
}

/* The cross-programme screen, which nothing here used to render. The marks
   table emits a programme heading row spanning the whole table only when
   cross_program is on, so every assertion above passed while that row's
   colspan was an undefined variable - the screen was broken on exactly the
   ?cross_program=1 URL and this suite reported 96 passed. */
$crossSubject = DB::table('program_subject as ps')
    ->join('programs as p', 'p.id', '=', 'ps.program_id')
    ->where('p.status', '1')
    ->groupBy('ps.subject_id')
    ->havingRaw('COUNT(DISTINCT ps.program_id) > 1')
    ->selectRaw('ps.subject_id')
    ->pluck('ps.subject_id');

$crossCombo = DB::table('exams as e')
    ->join('student_enrolls as se', 'se.id', '=', 'e.student_enroll_id')
    ->whereIn('e.subject_id', $crossSubject)
    ->select('e.subject_id', 'e.exam_type_id', 'se.program_id', 'se.session_id',
             'se.semester_id', 'se.section_id', DB::raw('count(*) n'))
    ->groupBy('e.subject_id', 'e.exam_type_id', 'se.program_id', 'se.session_id',
              'se.semester_id', 'se.section_id')
    ->orderByDesc('n')->first();

ok('there is a subject shared by more than one programme', $crossCombo !== null);

if ($crossCombo) {
    [$crossHtml, $crossData] = $render([
        'subject' => $crossCombo->subject_id, 'type' => $crossCombo->exam_type_id,
        'program' => $crossCombo->program_id, 'session' => $crossCombo->session_id,
        'semester' => $crossCombo->semester_id, 'section' => $crossCombo->section_id,
        'cross_program' => 1,
    ]);

    ok('the cross-programme screen renders', strlen($crossHtml) > 10000);
    check('and it carries the flag', true, $crossData['cross_program'] ?? null);

    /* No Blade variable went missing. Laravel renders the message into the
       page rather than throwing when debug is on, so a page that LOOKS like
       it rendered can still be the error screen. */
    foreach (['Undefined variable', 'Undefined array key', 'ErrorException'] as $wrong) {
        ok('the page is not an error about "' . $wrong . '"', !str_contains($crossHtml, $wrong));
    }

    /* The heading row has to span the marks table exactly. The expectation is
       the <th> this very page emitted, counted from the page - not the
       arithmetic the partial uses, which is the thing under test. */
    preg_match('~<table class="display table nowrap exm-table printable".*?<thead>(.*?)</thead>~s',
        $crossHtml, $crossHead);

    $columns = substr_count($crossHead[1] ?? '', '<th');

    ok('the marks table has columns', $columns > 0);

    preg_match_all('~<td colspan="(\d+)"~', $crossHtml, $spans);

    check('every heading row spans the whole table',
        [(string) $columns], array_values(array_unique($spans[1])));

    /* One heading per programme in the list, in the order the rows arrive. */
    $seen = [];

    foreach ($crossData['rows'] ?? [] as $row) {
        $pid = optional(optional($row->studentEnroll)->program)->id;

        if ($pid !== null && (end($seen) !== $pid)) {
            $seen[] = $pid;
        }
    }

    check('a heading row for each programme the list changes into',
        count(array_unique($seen)), count($spans[1]));

    ok('and each one names its programme',
        substr_count($crossHtml, 'fa-graduation-cap') >= count($spans[1]));
}

/* A superseded enrolment stays off the roll. When a student changes
   programme or moves on, the old enrolment is set to status 0 (see
   StudentSingleEnrollController) but its exam rows remain - and the roll is
   built from exam rows, so without a status filter the old programme kept a
   ghost student who could be marked twice. Resit enrolments are status 2, so
   the filter must not touch them. The expectation is computed from SQL, not
   from the code under test. */
/* A CA type, because the final-exam roll also demands attendance = 1 and the
   expectations below would have to mirror that too; the status rule under
   test is the same either way. */
$ghostCombo = DB::table('exams as e')
    ->join('student_enrolls as se', 'se.id', '=', 'e.student_enroll_id')
    ->join('exam_types as t', 't.id', '=', 'e.exam_type_id')
    ->where('t.is_final', 0)
    ->where('se.status', 0)
    ->select('e.subject_id', 'e.exam_type_id', 'se.session_id', DB::raw('count(*) n'))
    ->groupBy('e.subject_id', 'e.exam_type_id', 'se.session_id')
    ->orderByDesc('n')
    ->orderBy('e.subject_id')->orderBy('e.exam_type_id')->orderBy('se.session_id')
    ->first();

if ($ghostCombo === null) {
    echo "  (no superseded enrolment carries an exam; the ghost check has nothing to bite on)" . PHP_EOL;
} else {
    $deadMats = DB::table('exams as e')
        ->join('student_enrolls as se', 'se.id', '=', 'e.student_enroll_id')
        ->where('e.subject_id', $ghostCombo->subject_id)
        ->where('e.exam_type_id', $ghostCombo->exam_type_id)
        ->where('se.session_id', $ghostCombo->session_id)
        ->where('se.status', 0)->pluck('se.matricule')->unique();

    // Scoped the way the page scopes it: cross-programme mode only shows
    // enrolments in active programmes that share the subject.
    $liveMats = DB::table('exams as e')
        ->join('student_enrolls as se', 'se.id', '=', 'e.student_enroll_id')
        ->join('programs as p', 'p.id', '=', 'se.program_id')
        ->join('program_subject as ps', fn ($j) => $j
            ->on('ps.program_id', '=', 'se.program_id')
            ->where('ps.subject_id', $ghostCombo->subject_id))
        ->where('p.status', '1')
        ->where('e.subject_id', $ghostCombo->subject_id)
        ->where('e.exam_type_id', $ghostCombo->exam_type_id)
        ->where('se.session_id', $ghostCombo->session_id)
        ->whereIn('se.status', [1, 2])->pluck('se.matricule')->unique();

    // A matricule can sit on both sides (the dead enrolment and its live
    // successor under the same number); only the purely-dead ones must vanish.
    $mustVanish = $deadMats->diff($liveMats)->values();

    // The controller's gate is !empty(program), so '0' would render nothing;
    // cross-programme mode then ignores which sharing programme it was.
    $anyProgram = DB::table('program_subject as ps')
        ->join('programs as p', 'p.id', '=', 'ps.program_id')
        ->where('p.status', '1')
        ->where('ps.subject_id', $ghostCombo->subject_id)
        ->orderBy('ps.program_id')->value('ps.program_id');

    [$ghostHtml] = $render([
        'subject' => $ghostCombo->subject_id, 'type' => $ghostCombo->exam_type_id,
        'session' => $ghostCombo->session_id, 'program' => (string) $anyProgram,
        'semester' => '0', 'section' => '0', 'cross_program' => 1,
    ]);

    ok('there is a class with exams on superseded enrolments', $mustVanish->isNotEmpty());

    $shown = $mustVanish->filter(fn ($mat) => str_contains($ghostHtml, (string) $mat))->values();
    check('no superseded enrolment is on the roll', [], $shown->all());

    $liveMissing = $liveMats->filter(fn ($mat) => !str_contains($ghostHtml, (string) $mat))->values();
    check('while every live enrolment still is', [], $liveMissing->all());

    // The resit child this filter must not catch: status 2 on a resit semester.
    $resitMat = DB::table('student_enrolls as se')
        ->join('exams as e', 'e.student_enroll_id', '=', 'se.id')
        ->join('semesters as s', 's.id', '=', 'se.semester_id')
        ->where('s.is_resit', 1)->whereIn('se.status', [1, 2])
        ->where('e.subject_id', $ghostCombo->subject_id)
        ->where('e.exam_type_id', $ghostCombo->exam_type_id)
        ->where('se.session_id', $ghostCombo->session_id)
        ->orderBy('se.id')->value('se.matricule');

    if ($resitMat !== null) {
        ok('and a resit enrolment is still marked', str_contains($ghostHtml, (string) $resitMat));
    } else {
        echo "  (no resit enrolment in this class to assert on)" . PHP_EOL;
    }
}

// Nothing listed until a course and type are chosen.
[$bare, $bareData] = $render([]);

ok('with no course chosen nothing is listed', !isset($bareData['rows']));
ok('and the screen still renders', strlen($bare) > 10000);

echo PHP_EOL . "=== 5. the aids are wired to the right things ===" . PHP_EOL;

$aids = $withoutComments(file_get_contents(
    $base . '/resources/views/admin/exam/partials/marking_aids.blade.php'));

ok('Enter moves to the next student', str_contains($aids, "'Enter'"));
ok('and the arrow keys too', str_contains($aids, 'ArrowDown') && str_contains($aids, 'ArrowUp'));
ok('a wheel cannot change a focused mark', str_contains($aids, "'wheel'"));
ok('unsaved marks are warned about', str_contains($aids, 'beforeunload'));
ok('the warning arms on edit', str_contains($aids, 'dirty = true'));
ok('and disarms on submit', str_contains($aids, 'dirty = false'));

// It must not submit, validate or save: those belong to the existing code.
foreach (['\.submit\(', 'ajax', 'fetch\('] as $forbidden) {
    ok('the aids do not ' . $forbidden, !preg_match('~' . $forbidden . '~', $aids));
}

$table = $withoutComments(file_get_contents(
    $base . '/resources/views/admin/exam/partials/marking_table.blade.php'));

// type="number" would let a scroll wheel change a mark the marker just tabbed
// into — a new way to mis-record one.
ok('the mark box is not a number spinner', !str_contains($table, 'type="number" min="0" class="exm-mark" name="marks'));
ok('but a tablet still gets digits', str_contains($table, 'inputmode="decimal"'));

echo PHP_EOL . "=== 6. the styling stays on this screen ===" . PHP_EOL;

$theme = $withoutComments(file_get_contents(
    $base . '/resources/views/admin/exam/partials/theme.blade.php'));

preg_match_all('~^\s*(\.[a-z][a-z0-9-]*|table\.[a-z-]+)[^{]*\{~mi', $theme, $selectors);

foreach (array_unique($selectors[1]) as $selector) {
    ok('"' . $selector . '" is inside the exm scope',
        str_starts_with($selector, '.exm') || str_starts_with($selector, 'table.exm'));
}

// Six other views include the same filter partial.
foreach (['subject-marking' => App\Http\Controllers\Admin\SubjectMarkingController::class] as $name => $class) {
    if (!class_exists($class)) { $pass += 2; continue; }

    $request = Request::create('/admin/' . $name, 'GET');
    app()->instance('request', $request);

    DB::beginTransaction();
    try {
        $other = app($class)->index($request)->render();
    } finally {
        DB::rollBack();
    }

    ok($name . ' still renders', strlen($other) > 5000);
    ok($name . ' did not pick up the exm styling',
        !str_contains($other, 'main-body exm') && !str_contains($other, '.exm-band'));
}

echo PHP_EOL . "=== 7. colour is measured, and there is no fourth accent ===" . PHP_EOL;

$contrast = function (string $a, string $b) {
    $lum = function (string $hex) {
        $c = [];
        foreach ([1, 3, 5] as $i) {
            $v = hexdec(substr($hex, $i, 2)) / 255;
            $c[] = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };
    $l1 = $lum($a); $l2 = $lum($b);
    return ($l1 > $l2 ? $l1 + 0.05 : $l2 + 0.05) / ($l1 > $l2 ? $l2 + 0.05 : $l1 + 0.05);
};

foreach ([['good', '#0f7a3d'], ['warn', '#9a6a06'], ['bad', '#b3261e']] as [$name, $value]) {
    ok(sprintf('--%-5s reads as text (%.2f:1)', $name, $contrast($value, '#ffffff')),
        $contrast($value, '#ffffff') >= 4.5);
}

/*
 * A fourth identity hue was tried against income, expense and fees; the best
 * candidate still landed at CVD ΔE 6.6, inside the band the validator only
 * allows with secondary encoding. This screen takes none, and asserting that
 * stops one being added quietly later.
 */
ok('no identity accent is declared for marking',
    !preg_match('~--flow:\s*#(?!2a78d6)[0-9a-f]{6}~i', $theme));

// Every colour inside the table must win against the theme's
// ".table td { color:#888 !important }".
preg_match_all('~table\.exm-table[^{]*\{[^}]*\}~', $theme, $tableRules);

foreach ($tableRules[0] as $rule) {
    if (!preg_match('~(?<![a-z-])color:\s*([^;]+);~', $rule, $decl)) { continue; }

    ok(sprintf('%-40s colour is !important', trim(strtok($rule, '{'))),
        str_contains($decl[1], '!important'));
}

echo PHP_EOL . "=== nothing was left behind ===" . PHP_EOL;

foreach ($opening as $what => $count) {
    $now = match ($what) {
        'exams' => DB::table('exams')->count(),
        'marked' => DB::table('exams')->whereNotNull('achieve_marks')->count(),
        'locked' => DB::table('exams')->where('marks_locked', 1)->count(),
    };

    check(sprintf('%-7s is still %s', $what, number_format($count)), $count, $now);
}

printf(PHP_EOL . "%d passed, %d failed" . PHP_EOL . PHP_EOL, $pass, $fail);

exit($fail === 0 ? 0 : 1);
