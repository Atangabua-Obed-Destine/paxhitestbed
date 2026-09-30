<?php
/**
 * What the audit log says about an enrolment row.
 *
 * An application submitted online creates a placeholder enrolment so the
 * admission fee has a well-formed row to hang on: no student, no session, no
 * semester. The log described it as "Student enrolled: Unknown Student ...
 * Unknown Semester", which reads as a real enrolment that had gone wrong, and
 * sent the school looking for a problem that was not there.
 *
 * A placeholder is now described as what it is, and a real enrolment reads
 * exactly as it always did.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/enrollment_audit_wording_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Program;
use App\Models\StudentEnroll;
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

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();
Auth::guard('web')->login($admin);

/** The description the audit log stored for this row's latest event. */
$logged = fn ($enrollId) => (string) DB::table('audit_logs')
    ->where('auditable_type', 'App\Models\StudentEnroll')
    ->where('auditable_id', $enrollId)
    ->orderByDesc('id')->value('description');

$program = Program::where('status', '1')->first();

echo "\n== A placeholder from an online application ==\n";

DB::beginTransaction();

try {
    // Exactly what Web\ApplicationController creates when the admission fee is
    // raised: a programme and nothing else.
    $stub = new StudentEnroll();
    $stub->student_id = null;
    $stub->program_id = $program->id;
    $stub->session_id = null;
    $stub->semester_id = null;
    $stub->section_id = null;
    $stub->status = 0;
    $stub->save();

    $created = $logged($stub->id);

    check('it is not described as a student being enrolled',
        !str_contains($created, 'Student enrolled'), $created);
    check('it says it is an admission fee placeholder', str_contains($created, 'placeholder'), $created);
    check('and names the programme it is for', str_contains($created, $program->title), $created);
    check('with no "Unknown Student" or "Unknown Semester" to chase',
        !str_contains($created, 'Unknown Student') && !str_contains($created, 'Unknown Semester'), $created);

    $stub->delete();
    $deleted = $logged($stub->id);

    check('removing it says the application became a student record',
        str_contains($deleted, 'placeholder removed') && !str_contains($deleted, 'Unknown Student'), $deleted);
} finally {
    DB::rollBack();
}

echo "\n== A real enrolment reads as it always did ==\n";

$sample = StudentEnroll::with(['student', 'program', 'semester', 'session'])
    ->whereNotNull('student_id')->whereNotNull('semester_id')->whereNotNull('session_id')->first();

if (!$sample) {
    echo "  SKIP  no complete enrolment to compare against\n";
} else {
    DB::beginTransaction();

    try {
        $copy = $sample->replicate();
        $copy->save();

        $created = $logged($copy->id);

        check('it still says the student was enrolled', str_contains($created, 'Student enrolled:'), $created);
        check('naming the student',
            str_contains($created, trim($sample->student->first_name . ' ' . $sample->student->last_name)), $created);
        check('the programme and the semester',
            str_contains($created, $sample->program->title) && str_contains($created, $sample->semester->title), $created);
        check('and the session', str_contains($created, $sample->session->title), $created);
    } finally {
        DB::rollBack();
    }
}

echo "\n== An enrolment missing only its semester ==\n";

if (!$sample) {
    echo "  SKIP  no enrolment to work from\n";
} else {
    DB::beginTransaction();

    try {
        // Half-filled, but a real student: this is a genuine problem and must
        // not be dressed up as a placeholder.
        $odd = $sample->replicate();
        $odd->semester_id = null;
        $odd->save();

        $created = $logged($odd->id);

        check('it is still reported as an enrolment, not a placeholder',
            str_contains($created, 'Student enrolled:') && !str_contains($created, 'placeholder'), $created);
        check('and still names the student, so the gap can be found',
            str_contains($created, trim($sample->student->first_name . ' ' . $sample->student->last_name)), $created);
    } finally {
        DB::rollBack();
    }
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
