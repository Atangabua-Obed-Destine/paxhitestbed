<?php
/**
 * HND national exam codes: recording them, and printing the commission's list.
 *
 * CNOENC issues one code per student per sitting. A code on the wrong student
 * means that student sits the national exam under someone else's registration,
 * so the checks here are about identity: one code per student per sitting, never
 * the same code twice, and the printed list matching what was recorded.
 *
 * Expected figures are worked out from student_enrolls and semesters directly,
 * not through the controller's own queries, so a mistake there cannot agree with
 * itself.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/student_exam_code_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StudentEnroll;
use App\Models\StudentExamCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

// Requests are built from plain paths, not url(): before any request has been
// handled, url() uses APP_URL, which on this machine carries the site's
// sub-folder, and a fabricated request path with that folder matches no route.
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();
Auth::guard('web')->login($admin);

$render = function (string $url) use ($kernel) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent(), $response];
};

$post = function (string $url, array $payload) use ($kernel) {
    $session = app('session.store');
    $request = Request::create($url, 'POST', $payload + ['_token' => $session->token()]);
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

$snapshot = fn () => json_encode(DB::table('student_exam_codes')->selectRaw('COUNT(*) n, MAX(id) last')->first());

// The sitting with the most students, so the suite has something to work with.
$sitting = DB::table('student_enrolls as e')
    ->join('semesters as s', 's.id', '=', 'e.semester_id')
    ->selectRaw('e.session_id, s.year as level, COUNT(DISTINCT e.student_id) students')
    ->groupBy('e.session_id', 's.year')
    ->orderByDesc('students')
    ->first();

if (!$sitting) {
    echo "No enrolments at all — nothing to test.\n";
    exit(1);
}

$sessionId = (int) $sitting->session_id;
$level = (int) $sitting->level;
$base = ("/admin/admission/student-exam-codes?session_id={$sessionId}&level={$level}&program_id=0");

echo "\nSitting under test: session #{$sessionId}, Level {$level} ({$sitting->students} students)\n";

// Expected roll, worked out here rather than by the controller.
$expected = DB::table('student_enrolls as e')
    ->join('semesters as s', 's.id', '=', 'e.semester_id')
    ->join('students as st', 'st.id', '=', 'e.student_id')
    ->where('e.session_id', $sessionId)->where('s.year', $level)->where('st.status', '!=', 0)
    ->distinct()->pluck('e.student_id')->sort()->values()->all();

$students = App\Models\Student::whereIn('id', $expected)->orderBy('id')->get();

// ---------------------------------------------------------------------------

echo "\n== The entry screen lists the right students ==\n";

[$status, $html] = $render($base);

check('Super Admin can open it', $status === 200, "status $status");
check('every enrolled student at that level has a box, and nobody else',
    substr_count($html, 'name="codes[') === count($expected),
    substr_count($html, 'name="codes[') . ' boxes for ' . count($expected) . ' students');
check('a student enrolled in both a regular and a resit semester appears once',
    preg_match_all('/name="codes\[(\d+)\]"/', $html, $m) && count($m[1]) === count(array_unique($m[1])));
// The page groups by programme and orders by name, so compare the sets.
$onPage = array_values(array_unique(array_map('intval', $m[1])));
sort($onPage);
check('the students are the ones actually enrolled', $onPage === $expected,
    count(array_diff($onPage, $expected)) . ' extra, ' . count(array_diff($expected, $onPage)) . ' missing');

$disabled = App\Models\Student::where('status', 0)->pluck('id')->all();
check('disabled students are left out',
    $disabled === [] || !array_intersect($disabled, array_map('intval', $m[1])),
    'disabled: ' . implode(',', array_slice($disabled, 0, 5)));

// Every semester in this database is Year 1, so a Level 2 student is made here
// on purpose: without one, a screen that ignored the level would look right.
DB::beginTransaction();

try {
    $sample = StudentEnroll::where('session_id', $sessionId)->orderByDesc('id')->first();
    $secondYear = App\Models\Semester::create([
        'title' => 'TEST SECOND YEAR', 'year' => $level === 1 ? 2 : 1, 'semester_type' => 1,
        'is_resit' => 0, 'status' => '1',
    ]);
    $otherLevel = $sample->replicate();
    $otherLevel->semester_id = $secondYear->id;
    $otherLevel->save();

    [, $sameLevel] = $render($base);
    [, $otherLevelHtml] = $render("/admin/admission/student-exam-codes?session_id={$sessionId}&level=" . ($level === 1 ? 2 : 1) . '&program_id=0');

    check('a student at the other level is not listed under this one',
        substr_count($sameLevel, 'name="codes[') === count($expected),
        substr_count($sameLevel, 'name="codes[') . ' boxes, expected ' . count($expected));
    check('and the other level lists that student instead',
        str_contains($otherLevelHtml, 'name="codes[' . $otherLevel->student_id . ']"')
            && substr_count($otherLevelHtml, 'name="codes[') === 1,
        substr_count($otherLevelHtml, 'name="codes[') . ' boxes at the other level');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------

echo "\n== Recording codes ==\n";

$before = $snapshot();
$three = $students->take(3);

if ($three->count() < 3) {
    echo "  SKIP  fewer than three students in this sitting\n";
} else {
    [$a, $b, $c] = [$three[0], $three[1], $three[2]];

    DB::beginTransaction();

    try {
        $codes = [
            $a->id => 'HND2637757D82',
            $b->id => ' hnd2637758f7f ', // typed with spaces, in lower case
            $c->id => '',                // left blank
        ];

        $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level, 'codes' => $codes]);

        $saved = StudentExamCode::forSitting($sessionId, $level)->pluck('code', 'student_id');

        check('a column of codes is recorded, one row per student',
            $saved->count() === 2 && $saved[$a->id] === 'HND2637757D82');
        check('a code typed with spaces or in lower case is stored as the commission writes it',
            ($saved[$b->id] ?? null) === 'HND2637758F7F', $saved[$b->id] ?? 'missing');
        check('a blank box records nothing', !isset($saved[$c->id]));
        check('the programme is kept with the code',
            (int) StudentExamCode::forSitting($sessionId, $level)->where('student_id', $a->id)->value('program_id')
                === (int) StudentEnroll::where('student_id', $a->id)->where('session_id', $sessionId)->orderByDesc('id')->value('program_id'));

        // Saving again with one changed
        $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level,
            'codes' => [$a->id => 'HND2637759C88', $b->id => 'HND2637758F7F']]);

        check('saving again updates rather than duplicating',
            StudentExamCode::forSitting($sessionId, $level)->count() === 2
                && StudentExamCode::forSitting($sessionId, $level)->where('student_id', $a->id)->value('code') === 'HND2637759C88');

        // The same code on a second student is refused, and nothing is saved.
        // The refusal has to be the screen's own — a clash caught only by the
        // database unique index reaches the admin as a 500 page.
        $stateBefore = StudentExamCode::forSitting($sessionId, $level)->pluck('code', 'student_id')->toJson();
        [$clashStatus] = $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level,
            'codes' => [$c->id => 'HND2637759C88']]);

        check('a code already given to another student is refused',
            !StudentExamCode::forSitting($sessionId, $level)->where('student_id', $c->id)->exists());
        check('and the admin is sent back to the screen, not to an error page',
            $clashStatus === 302, "status $clashStatus");
        check('with the clashing code named, rather than a database error',
            collect(session('errors') ? session('errors')->all() : [])->contains(fn ($message) => str_contains($message, 'HND2637759C88'))
                && !collect(session('errors') ? session('errors')->all() : [])->contains(fn ($message) => str_contains($message, 'SQLSTATE')),
            json_encode(session('errors') ? session('errors')->all() : []));
        check('and the rest of that save is left untouched',
            StudentExamCode::forSitting($sessionId, $level)->pluck('code', 'student_id')->toJson() === $stateBefore);

        // Emptying a box removes the code.
        $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level,
            'codes' => [$a->id => '', $b->id => 'HND2637758F7F']]);

        check('emptying a box removes that student\'s code',
            !StudentExamCode::forSitting($sessionId, $level)->where('student_id', $a->id)->exists()
                && StudentExamCode::forSitting($sessionId, $level)->where('student_id', $b->id)->exists());

        // Level 1 and Level 2 for one student
        StudentExamCode::create(['student_id' => $b->id, 'session_id' => $sessionId, 'level' => $level === 1 ? 2 : 1, 'code' => 'HND26FFFFFFF1']);
        check('a student can hold a code for each level',
            StudentExamCode::where('student_id', $b->id)->count() === 2);

        $unusual = $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level,
            'codes' => [$c->id => 'XYZ123']]);
        check('a code outside the commission\'s format is saved, with a warning rather than a refusal',
            StudentExamCode::forSitting($sessionId, $level)->where('student_id', $c->id)->value('code') === 'XYZ123');
    } finally {
        DB::rollBack();
    }
}

check('the recording tests left the table as they found it', $snapshot() === $before);

// ---------------------------------------------------------------------------

echo "\n== Moving a code from the wrong student to the right one ==\n";

if ($three->count() < 3) {
    echo "  SKIP  fewer than three students in this sitting\n";
} else {
    [$a, $b] = [$three[0], $three[1]];
    $moved = 'HND26MOVED001';

    // The page submits its boxes in the order the students are listed, and the
    // code's old holder can be above or below the one it is moving to. It has
    // to work either way round: reported from use as "I deleted it but nothing
    // will save".
    foreach ([
        'the right student listed first' => [$b->id => $moved, $a->id => ''],
        'the wrong student listed first' => [$a->id => '', $b->id => $moved],
    ] as $label => $codes) {
        DB::beginTransaction();

        try {
            StudentExamCode::create(['student_id' => $a->id, 'session_id' => $sessionId, 'level' => $level, 'code' => $moved]);
            [$status] = $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level, 'codes' => $codes]);

            $holder = StudentExamCode::forSitting($sessionId, $level)->where('code', $moved)->value('student_id');
            check("clearing one student and giving the code to another works with $label",
                $status === 302 && (int) $holder === (int) $b->id
                    && !StudentExamCode::forSitting($sessionId, $level)->where('student_id', $a->id)->exists(),
                'held by ' . var_export($holder, true) . " expected {$b->id}");
        } finally {
            DB::rollBack();
        }
    }

    DB::beginTransaction();

    try {
        StudentExamCode::create(['student_id' => $a->id, 'session_id' => $sessionId, 'level' => $level, 'code' => 'HND26SWAPAAA1']);
        StudentExamCode::create(['student_id' => $b->id, 'session_id' => $sessionId, 'level' => $level, 'code' => 'HND26SWAPBBB2']);

        $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level,
            'codes' => [$a->id => 'HND26SWAPBBB2', $b->id => 'HND26SWAPAAA1']]);

        check('two students can swap codes in one save',
            StudentExamCode::forSitting($sessionId, $level)->where('student_id', $a->id)->value('code') === 'HND26SWAPBBB2'
                && StudentExamCode::forSitting($sessionId, $level)->where('student_id', $b->id)->value('code') === 'HND26SWAPAAA1');
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        $state = StudentExamCode::forSitting($sessionId, $level)->pluck('code', 'student_id')->toJson();
        [$status] = $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level,
            'codes' => [$a->id => 'HND26SAME0001', $b->id => 'HND26SAME0001']]);

        check('the same code typed on two students in one save is refused, and named',
            $status === 302
                && StudentExamCode::forSitting($sessionId, $level)->pluck('code', 'student_id')->toJson() === $state
                && collect(session('errors') ? session('errors')->all() : [])->contains(fn ($m) => str_contains($m, 'HND26SAME0001')),
            json_encode(session('errors') ? session('errors')->all() : []));
    } finally {
        DB::rollBack();
    }

    // Whoever recorded a code first keeps that credit when it is corrected.
    DB::beginTransaction();

    try {
        StudentExamCode::create(['student_id' => $a->id, 'session_id' => $sessionId, 'level' => $level,
            'code' => 'HND26ORIGIN01', 'created_by' => 999999]);
        $post(('/admin/admission/student-exam-codes'), ['session_id' => $sessionId, 'level' => $level,
            'codes' => [$a->id => 'HND26ORIGIN02']]);

        $row = StudentExamCode::forSitting($sessionId, $level)->where('student_id', $a->id)->first();
        check('correcting a code keeps who first recorded it, and notes who changed it',
            $row && $row->code === 'HND26ORIGIN02' && (int) $row->created_by === 999999 && (int) $row->updated_by === (int) $admin->id,
            json_encode($row ? $row->only('code', 'created_by', 'updated_by') : null));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------

echo "\n== The printed list ==\n";

DB::beginTransaction();

try {
    $withCodes = $students->take(4);
    $given = [];

    foreach ($withCodes as $index => $student) {
        $code = 'HND26ABCDE' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        $given[$student->id] = $code;
        StudentExamCode::create([
            'student_id' => $student->id, 'session_id' => $sessionId, 'level' => $level, 'code' => $code,
            'program_id' => StudentEnroll::where('student_id', $student->id)->where('session_id', $sessionId)->orderByDesc('id')->value('program_id'),
        ]);
    }

    // The blade, rendered with the controller's own data, so the document is
    // checked rather than a copy of its query.
    $controller = new App\Http\Controllers\Admin\StudentExamCodeController();
    $codedFor = new ReflectionMethod($controller, 'codedStudentsForSitting');
    $codedFor->setAccessible(true);
    $groups = $codedFor->invoke($controller, $sessionId, $level, 0);

    $document = view('admin.student-exam-codes.pdf', [
        'groups' => $groups, 'codes' => $given, 'level' => $level,
        'session' => App\Models\Session::find($sessionId),
    ])->render();

    check('the document carries the commission\'s heading',
        str_contains($document, 'CNOENC') && str_contains($document, 'Pre Registration Code List'));
    check('and the school\'s name', str_contains($document, institution_name()));
    check('it shows the level', str_contains($document, 'Niveau: ' . $level));
    check('every code recorded is printed', collect($given)->every(fn ($code) => str_contains($document, $code)),
        json_encode($given));
    check('students with no code are left out',
        $groups->sum(fn ($g) => $g->count()) === count($given),
        $groups->sum(fn ($g) => $g->count()) . ' printed for ' . count($given) . ' codes');

    foreach ($groups as $programme => $rows) {
        $names = $rows->map(fn ($r) => strtoupper(trim($r->student->first_name . ' ' . $r->student->last_name)))->all();
        $sorted = $names;
        sort($sorted);
        check("names are alphabetical within {$programme}", $names === $sorted, implode(' | ', $names));
        check("{$programme} is headed as an option", str_contains($document, 'Option : ' . $programme));
    }

    $first = $groups->first()->first();
    check('a student\'s row shows their own code',
        preg_match('/' . preg_quote($given[$first->student_id], '/') . '.*?' . preg_quote(strtoupper($first->student->last_name), '/') . '/s', $document) === 1);

    [$status,, $response] = $render(("/admin/admission/student-exam-codes/pdf?session_id={$sessionId}&level={$level}&program_id=0"));
    check('the PDF downloads', $status === 200 && str_contains(strtolower($response->headers->get('content-type') ?? ''), 'pdf'),
        "status $status type " . $response->headers->get('content-type'));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------

echo "\n== Importing the commission's file ==\n";

$file = __DIR__ . '/../COMMISSION NATIONALE HND CODES.docx';

if (!is_file($file)) {
    echo "  SKIP  the commission's file is not in the project root\n";
} else {
    $before = $snapshot();

    Artisan::call('exam-codes:import', ['file' => $file, '--session' => $sessionId, '--level' => $level]);
    $dryRun = Artisan::output();

    check('a dry run reports what it found', str_contains($dryRun, '65 codes in the file'));
    check('and says it wrote nothing', str_contains($dryRun, 'Nothing was written'));
    check('and truly wrote nothing', $snapshot() === $before);

    DB::beginTransaction();

    try {
        Artisan::call('exam-codes:import', ['file' => $file, '--session' => $sessionId, '--level' => $level, '--apply' => true]);
        $applied = Artisan::output();

        $recorded = StudentExamCode::forSitting($sessionId, $level)->count();
        check('applying records the codes it matched', $recorded === 64, "$recorded recorded\n$applied");
        check('the one name that is not a student is reported, not invented',
            str_contains($applied, 'CHE PHILIP') || str_contains($dryRun, 'CHE PHILIP'));

        // Spot-check a student whose name the commission spells differently.
        $gonah = App\Models\Student::where('last_name', 'like', '%GONAH%')->orWhere('first_name', 'like', '%NYING%')->first();
        if ($gonah) {
            check('a name the commission spells differently still reaches the right student',
                StudentExamCode::forSitting($sessionId, $level)->where('student_id', $gonah->id)->value('code') === 'HND263775AEAC',
                (string) StudentExamCode::forSitting($sessionId, $level)->where('student_id', $gonah->id)->value('code'));
        }

        $after = $snapshot();
        Artisan::call('exam-codes:import', ['file' => $file, '--session' => $sessionId, '--level' => $level, '--apply' => true]);
        check('running it again changes nothing', $snapshot() === $after);
    } finally {
        DB::rollBack();
    }

    check('the import tests left the table as they found it', $snapshot() === $before);
}

// ---------------------------------------------------------------------------

echo "\n== On the transcript ==\n";

// A student whose transcript actually has marks on it, so the page renders as
// it does in use rather than as an empty shell.
$transcriptEnroll = StudentEnroll::whereIn('student_id', $expected)
    ->whereHas('subjectMarks')
    ->orderByDesc('id')
    ->first();

if (!$transcriptEnroll) {
    echo "  SKIP  no enrolment with marks to print a transcript for\n";
} else {
    $studentId = $transcriptEnroll->student_id;
    $printUrl = "/admin/transcript/marksheet-print/{$studentId}?enrollment_id={$transcriptEnroll->id}";

    DB::beginTransaction();

    try {
        [$status, $before] = $render($printUrl);
        check('the transcript prints', $status === 200, "status $status");
        check('and carries no exam code line before one is recorded',
            !str_contains($before, 'HND Exam Code'));

        StudentExamCode::create([
            'student_id' => $studentId, 'session_id' => $transcriptEnroll->session_id, 'level' => $level,
            'code' => 'HND26TRANSCR1', 'program_id' => $transcriptEnroll->program_id,
        ]);

        [$status, $after] = $render($printUrl);
        check('once recorded, the code appears on the printed transcript',
            $status === 200 && str_contains($after, 'HND Exam Code') && str_contains($after, 'HND26TRANSCR1'));

        [$status, $download] = $render("/admin/transcript/marksheet-download/{$studentId}?enrollment_id={$transcriptEnroll->id}");
        check('and on the downloaded copy', $status === 200 && str_contains($download, 'HND26TRANSCR1'), "status $status");

        [$status, $bulk] = $render("/admin/transcript/marksheet-bulk?students={$transcriptEnroll->id}");
        check('and on a batch printed from the list', $status === 200 && str_contains($bulk, 'HND26TRANSCR1'), "status $status");

        // A code belongs to the programme it was issued for.
        $otherProgram = App\Models\Program::where('id', '!=', $transcriptEnroll->program_id)->value('id');

        if ($otherProgram) {
            StudentExamCode::where('student_id', $studentId)->update(['program_id' => $otherProgram]);
            [, $otherHtml] = $render($printUrl);
            check('a code issued for another programme is not shown on this transcript',
                !str_contains($otherHtml, 'HND26TRANSCR1'));
        }

        // Both years of HND, each against its level.
        StudentExamCode::where('student_id', $studentId)->update(['program_id' => $transcriptEnroll->program_id]);
        StudentExamCode::create([
            'student_id' => $studentId, 'session_id' => $transcriptEnroll->session_id, 'level' => $level === 1 ? 2 : 1,
            'code' => 'HND26TRANSCR2', 'program_id' => $transcriptEnroll->program_id,
        ]);

        [, $bothHtml] = $render($printUrl);
        check('a student with a code for each level shows both, labelled',
            str_contains($bothHtml, 'HND26TRANSCR1') && str_contains($bothHtml, 'HND26TRANSCR2')
                && str_contains($bothHtml, 'Level 1') && str_contains($bothHtml, 'Level 2'));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------

echo "\n== Permissions ==\n";

[$status, $html] = $render(('/admin/admission/student-form-a2'));
check('Super Admin sees it in the menu', str_contains($html, 'student-exam-codes'));

$outsider = App\User::where('status', '1')->where('id', '!=', $admin->id)->get()
    ->first(fn ($u) => !$u->can('student-exam-code-view') && !$u->can('student-exam-code-manage'));

if (!$outsider) {
    echo "  SKIP  every user holds the exam code permissions\n";
} else {
    Auth::guard('web')->login($outsider);

    try {
        [$status] = $render($base);
        check('a user without the permission is refused the page', $status === 403, "status $status");

        [$status] = $render(("/admin/admission/student-exam-codes/pdf?session_id={$sessionId}&level={$level}"));
        check('and cannot print the list either', $status === 403, "status $status");

        // In a transaction: if the guard were missing, this POST would write.
        DB::beginTransaction();

        try {
            $before = $snapshot();
            [$status] = $post(('/admin/admission/student-exam-codes'), [
                'session_id' => $sessionId, 'level' => $level, 'codes' => [$students->first()->id => 'HND26DEADBEEF'],
            ]);
            check('a save without permission is refused', $status === 403, "status $status");
            check('and writes nothing', $snapshot() === $before);
        } finally {
            DB::rollBack();
        }

        [, $menu] = $render(('/admin/dashboard'));
        check('and the menu item is hidden', !str_contains($menu, 'student-exam-codes'));
    } finally {
        Auth::guard('web')->login($admin);
    }
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
