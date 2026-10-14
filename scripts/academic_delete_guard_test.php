<?php
/**
 * Nothing may be deleted out from under a student.
 *
 * Every write happens inside a transaction that is rolled back; the last section
 * proves the database was left as it was found.
 *
 * The first section is the important one: it DEMONSTRATES the fault rather than
 * assuming it. Delete a programme with the guard bypassed and the enrolment is
 * gone, with no audit row anywhere to say so.
 *
 * Run: php scripts/academic_delete_guard_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\AcademicDependencyGuard;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

$guard = app(AcademicDependencyGuard::class);

/** A throwaway programme with one student enrolled on it. */
function buildProgrammeWithEnrolment(): array
{
    $sample = Program::first();
    $student = Student::where('status', '1')->first();
    $existing = StudentEnroll::where('student_id', $student->id)->firstOrFail();

    $programme = Program::create([
        'title' => 'DELETE GUARD TEST PROGRAMME',
        'shortcode' => 'DGT',
        'slug' => 'delete-guard-test-programme',
        'faculty_id' => $sample->faculty_id,
        'degree_type_id' => $sample->degree_type_id,
        'status' => '1',
    ]);

    $enroll = StudentEnroll::create([
        'student_id' => $student->id,
        'program_id' => $programme->id,
        'session_id' => $existing->session_id,
        'semester_id' => $existing->semester_id,
        'section_id' => $existing->section_id,
        'matricule' => 'GUARD-TEST-001',
        'status' => '1',
    ]);

    return [$programme, $enroll, $student];
}

// ---------------------------------------------------------------------------
section('The fault itself, demonstrated');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    [$programme, $enroll, $student] = buildProgrammeWithEnrolment();

    check('the enrolment exists to begin with',
        StudentEnroll::whereKey($enroll->id)->exists());

    // Exactly what ProgramController@destroy did before the guard.
    $programme->delete();

    check('deleting the programme ERASES the enrolment',
        !StudentEnroll::whereKey($enroll->id)->exists(),
        'if this passes, the cascade is real and a single click can hide a student');

    check('and nothing in the audit log records that deletion',
        DB::table('audit_logs')
            ->where('auditable_type', 'App\\Models\\StudentEnroll')
            ->where('auditable_id', $enroll->id)
            ->where('event', 'deleted')
            ->count() === 0,
        'MySQL cascades without Eloquent seeing it, so the Auditable trait writes nothing');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The guard refuses to let that happen');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    [$programme, $enroll, $student] = buildProgrammeWithEnrolment();

    $reasons = $guard->blockers(Program::find($programme->id));

    check('a programme with enrolments cannot be deleted',
        $reasons !== [],
        'the guard allowed it');

    check('and the refusal names how many students are affected',
        (bool) array_filter($reasons, fn ($r) => str_contains($r, '1 student')),
        implode(' ', $reasons));

    check('and says what would be lost',
        (bool) array_filter($reasons, fn ($r) => str_contains(strtolower($r), 'enrolment')),
        implode(' ', $reasons));
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    $sample = Program::first();

    $empty = Program::create([
        'title' => 'DELETE GUARD TEST EMPTY',
        'shortcode' => 'DGE',
        'slug' => 'delete-guard-test-empty',
        'faculty_id' => $sample->faculty_id,
        'degree_type_id' => $sample->degree_type_id,
        'status' => '1',
    ]);

    check('a programme nobody is enrolled on can still be deleted',
        $guard->canDelete($empty),
        implode(' ', $guard->blockers($empty)) . ' — the guard must not block ordinary tidying up');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The same rule on every screen that cascades');
// ---------------------------------------------------------------------------
// A rule enforced on the programme screen and forgotten on the session screen is
// not a rule. These three cascade onto student_enrolls exactly as program_id
// does - deleting session #2 here would take 207 enrolments with it.
foreach ([
    'session' => Session::class,
    'semester' => Semester::class,
    'section' => Section::class,
] as $noun => $class) {
    $column = $noun . '_id';
    $busiest = StudentEnroll::select($column)
        ->groupBy($column)
        ->orderByRaw('COUNT(*) DESC')
        ->value($column);

    if (!$busiest) {
        skip('no ' . $noun . ' has enrolments on it');
        continue;
    }

    $record = $class::find($busiest);
    $reasons = $guard->blockers($record);

    check('a ' . $noun . ' with enrolments cannot be deleted',
        $reasons !== [],
        'the guard allowed ' . $noun . ' #' . $busiest);

    check('and the ' . $noun . ' refusal names it as a ' . $noun,
        (bool) array_filter($reasons, fn ($r) => str_contains($r, $noun)),
        implode(' ', $reasons));
}

// ---------------------------------------------------------------------------
section('Through the screen, not just the service');
// ---------------------------------------------------------------------------
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no Super Admin to act as');
} else {
    Illuminate\Support\Facades\Auth::guard('web')->login($admin);

    DB::beginTransaction();

    try {
        [$programme, $enroll, $student] = buildProgrammeWithEnrolment();

        $request = Illuminate\Http\Request::create(
            '/admin/academic/program/' . $programme->id, 'DELETE', ['_token' => csrf_token()]);
        $request->setLaravelSession(app('session.store'));
        $kernel->handle($request);

        check('the delete screen refuses too',
            Program::whereKey($programme->id)->exists(),
            'the programme was deleted through the screen');

        check('and the enrolment survives',
            StudentEnroll::whereKey($enroll->id)->exists(),
            'the student would have disappeared from the student list');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('no test programme remains',
    Program::where('slug', 'like', 'delete-guard-test%')->count() === 0,
    Program::where('slug', 'like', 'delete-guard-test%')->count() . ' remain');

check('no test enrolment remains',
    StudentEnroll::where('matricule', 'GUARD-TEST-001')->count() === 0);

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
