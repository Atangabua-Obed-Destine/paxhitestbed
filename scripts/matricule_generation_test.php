<?php
/**
 * A matricule is claimed when it is written, never before.
 *
 * The admission screens used to ask the server for the next matricule while the
 * admin was still filling in the form, show it, and submit it back. Two admins
 * admitting students at the same time were each shown the same next number, and
 * whichever saved second enrolled a student on a matricule that had already
 * gone out. The number is now generated inside the insert itself, and the form
 * has nowhere to type one.
 *
 * What the screens show instead is whether a matricule CAN be generated — and
 * when it cannot, which setting is missing, by name and by where to set it.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/matricule_generation_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Batch;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

function source(string $relative): string
{
    return file_get_contents(__DIR__ . '/../' . $relative);
}

/** A student that satisfies the table, with no matricule of its own. */
function newStudent(int $batchId, int $programId): Student
{
    $student = new Student;
    $student->batch_id = $batchId;
    $student->program_id = $programId;
    $student->admission_date = now()->format('Y-m-d');
    $student->first_name = 'Matricule';
    $student->last_name = 'Test';
    $student->email = 'matricule.test.' . uniqid() . '@example.test';
    $student->password = Hash::make('secret-not-used');
    $student->gender = 'Male';
    $student->dob = '2000-01-01';
    $student->phone = '000000000';
    $student->status = '1';

    return $student;
}

// ---------------------------------------------------------------------------

echo "\n== No screen offers a matricule to type ==\n";

$forms = [
    'resources/views/admin/application/edit.blade.php',
    'resources/views/admin/student/create.blade.php',
    'resources/views/admin/student-transfer-in/create.blade.php',
];

foreach ($forms as $form) {
    $html = source($form);

    check(basename(dirname($form)) . '/' . basename($form) . ' has no matricule input',
        !preg_match('/<input[^>]*name="student_id"/', $html));
    check(basename(dirname($form)) . '/' . basename($form) . ' reports readiness instead',
        str_contains($html, 'matricule_status'));
}

echo "\n== And no controller reads one from the request ==\n";

foreach ([
    'app/Http/Controllers/Admin/ApplicationController.php' => 'store',
    'app/Http/Controllers/Admin/StudentController.php' => 'store',
    'app/Http/Controllers/Admin/StudentTransferInController.php' => 'store',
] as $file => $method) {
    $php = source($file);

    // Only the store() body matters; update() and the filters legitimately
    // read a student_id from the request.
    $start = strpos($php, "function {$method}(Request \$request)");
    $end = $start === false ? false : strpos($php, "\n    public function ", $start + 10);
    $body = $start === false ? '' : substr($php, $start, ($end === false ? strlen($php) : $end) - $start);

    check(basename($file) . "::{$method}() does not take a matricule from the form",
        $start !== false && !str_contains($body, 'request->student_id'),
        $start === false ? 'store() not found' : '');
    check(basename($file) . "::{$method}() issues one at the insert",
        str_contains($body, 'saveWithIssuedId'));
}

echo "\n== The endpoint hands out readiness, not a number ==\n";

$controller = source('app/Http/Controllers/Admin/StudentController.php');
$generateId = substr($controller, strpos($controller, 'function generateId'), 1500);

check('the endpoint no longer generates an id to display',
    !str_contains($generateId, 'generateStudentId('));
check('it answers with readiness', str_contains($generateId, 'matriculeReadiness'));

// ---------------------------------------------------------------------------

$faculty = Faculty::where(function ($q) {
    $q->whereNotNull('matric_code')->where('matric_code', '<>', '');
})->orWhere(function ($q) {
    $q->whereNotNull('shortcode')->where('shortcode', '<>', '');
})->first();

$batch = Batch::where('title', 'REGEXP', '[0-9]{2}')->first();
$program = $faculty ? Program::where('faculty_id', $faculty->id)->first() : null;

if (!$faculty || !$batch || !$program) {
    echo "\n  SKIP  need a faculty with a code, a batch with a year and a programme\n";
    echo "\n$passed passed, $failed failed\n";
    exit($failed > 0 ? 1 : 0);
}

echo "\n== Readiness says yes, and shows a shape rather than a number ==\n";

$readiness = Student::matriculeReadiness($faculty->id, $batch->id, $program->id);

check('a configured school is ready', $readiness['ready'] === true,
    json_encode($readiness));
check('nothing is reported as missing', $readiness['problems'] === []);
check('the format ends in placeholders, not a sequence',
    is_string($readiness['format']) && str_ends_with($readiness['format'], '###'),
    (string) $readiness['format']);
check('no matricule is returned under any key',
    !array_key_exists('student_id', $readiness) && !array_key_exists('matricule', $readiness),
    implode(',', array_keys($readiness)));

echo "\n== Readiness says what is unset, and where ==\n";

DB::beginTransaction();

try {
    DB::table('settings')->update(['academy_code' => null]);
    $blocked = Student::matriculeReadiness($faculty->id, $batch->id, $program->id);

    check('an unset Academy Code blocks generation', $blocked['ready'] === false);
    check('and is named, with where to set it',
        collect($blocked['problems'])->contains(function ($p) {
            return str_contains($p['what'], 'Academy Code') && str_contains($p['where'], 'Settings');
        }),
        json_encode($blocked['problems']));
    check('no format is offered while it is blocked', $blocked['format'] === null);
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    DB::table('faculties')->where('id', $faculty->id)->update(['matric_code' => null, 'shortcode' => null]);
    $blocked = Student::matriculeReadiness($faculty->id, $batch->id, $program->id);

    check('a faculty with no Matricule Code blocks generation', $blocked['ready'] === false);
    check('and the faculty is named',
        collect($blocked['problems'])->contains(function ($p) use ($faculty) {
            return str_contains($p['what'], (string) $faculty->title);
        }),
        json_encode($blocked['problems']));
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    DB::table('batches')->where('id', $batch->id)->update(['title' => 'Intake Alpha']);
    $blocked = Student::matriculeReadiness($faculty->id, $batch->id, $program->id);

    check('a batch with no year in its name blocks generation', $blocked['ready'] === false,
        json_encode($blocked));
    check('and the batch is named',
        collect($blocked['problems'])->contains(fn ($p) => str_contains($p['what'], 'Intake Alpha')),
        json_encode($blocked['problems']));
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    DB::table('settings')->update(['academy_code' => null]);
    DB::table('batches')->where('id', $batch->id)->update(['title' => 'Intake Alpha']);
    $blocked = Student::matriculeReadiness($faculty->id, $batch->id, $program->id);

    check('every missing piece is reported at once, not one at a time',
        count($blocked['problems']) >= 2, json_encode($blocked['problems']));
} finally {
    DB::rollBack();
}

echo "\n== The number is claimed by the insert ==\n";

DB::beginTransaction();

try {
    $student = newStudent($batch->id, $program->id);
    check('a new student arrives with no matricule', empty($student->student_id));

    Student::saveWithIssuedId($student, $faculty->id, $batch->id, $program->id);

    check('and leaves with one', !empty($student->student_id), (string) $student->student_id);
    check('which is on file under that id',
        Student::where('student_id', $student->student_id)->exists());
    check('and matches the shape readiness advertised',
        str_starts_with($student->student_id, rtrim($readiness['format'], '#')),
        $student->student_id . ' vs ' . $readiness['format']);
} finally {
    DB::rollBack();
}

echo "\n== Two admissions at once cannot share a matricule ==\n";

DB::beginTransaction();

try {
    // What the first admin's screen would have been told.
    $shown = Student::generateStudentId($faculty->id, $batch->id, $program->id);

    // The second admin saves first, taking that very number — this is the race
    // the old screens lost.
    $winner = newStudent($batch->id, $program->id);
    $winner->student_id = $shown;
    $winner->save();

    // Now the first admin submits. Nothing they hold is used; a number is
    // issued against what is on file at this instant.
    $loser = newStudent($batch->id, $program->id);
    Student::saveWithIssuedId($loser, $faculty->id, $batch->id, $program->id);

    check('the second admission is still created', $loser->exists);
    check('on a different matricule', $loser->student_id !== $winner->student_id,
        $loser->student_id . ' vs ' . $winner->student_id);
    check('and both records survive',
        Student::where('student_id', $winner->student_id)->exists()
        && Student::where('student_id', $loser->student_id)->exists());

    // Teeth: doing it the old way — holding the number the screen showed —
    // is refused by the database. If this ever stops throwing, the unique
    // index on students.student_id has gone and the race is live again.
    $refused = false;

    try {
        $stale = newStudent($batch->id, $program->id);
        $stale->student_id = $shown;
        $stale->save();
    } catch (\Illuminate\Database\QueryException $e) {
        $refused = ($e->errorInfo[1] ?? null) === 1062;
    }

    check('submitting a matricule shown earlier is refused outright', $refused);
} finally {
    DB::rollBack();
}

echo "\n== The retry survives a number being taken repeatedly ==\n";

DB::beginTransaction();

try {
    $taken = [];

    // Take the next three numbers out from under the generator.
    for ($i = 0; $i < 3; $i++) {
        $blocker = newStudent($batch->id, $program->id);
        $blocker->student_id = Student::generateStudentId($faculty->id, $batch->id, $program->id);
        $blocker->save();
        $taken[] = $blocker->student_id;
    }

    $student = newStudent($batch->id, $program->id);
    Student::saveWithIssuedId($student, $faculty->id, $batch->id, $program->id);

    check('a fourth admission still gets a matricule', !empty($student->student_id));
    check('and it is none of the three already taken',
        !in_array($student->student_id, $taken, true),
        $student->student_id . ' vs ' . implode(', ', $taken));
} finally {
    DB::rollBack();
}

echo "\n== The Academy Code has a home, and a save cannot erase it ==\n";

$settingsView = source('resources/views/admin/setting/index.blade.php');

check('the settings page offers an Academy Code field',
    preg_match('/<input[^>]*name="academy_code"/', $settingsView) === 1);
check('and it is not commented out',
    preg_match('/\{\{--(?:(?!--\}\})[\s\S])*name="academy_code"[\s\S]*?--\}\}/', $settingsView) === 0);

// The field was once commented out of this page while the controller went on
// assigning $request->academy_code, so every settings save wrote NULL over the
// school's code — and no matricule could be issued until somebody put it back
// by hand. This posts the settings form as the page would, without the field,
// and insists the code survives.
$before = DB::table('settings')->value('academy_code');

DB::beginTransaction();

try {
    $admin = App\User::where('is_admin', 1)->where('status', '1')->first();
    Illuminate\Support\Facades\Auth::guard('web')->login($admin);

    $session = app('session.store');
    $session->start();

    // The settings row as it stands, resubmitted exactly as the page would —
    // every required field present, and no academy_code, which is what the
    // commented-out field produced.
    $settings = (array) DB::table('settings')->first();
    $payload = collect($settings)
        ->except(['id', 'academy_code', 'created_at', 'updated_at', 'logo_path', 'favicon_path'])
        ->map(fn ($value) => $value === null ? '' : $value)
        ->all();

    $payload['_token'] = $session->token();
    $payload['id'] = $settings['id'];

    $request = Illuminate\Http\Request::create('/admin/setting/siteinfo', 'POST', $payload);
    $request->setLaravelSession($session);

    $response = app(Illuminate\Contracts\Http\Kernel::class)->handle($request);
    $after = DB::table('settings')->value('academy_code');

    // A validation redirect would never reach the save, and the check below
    // would pass for the wrong reason.
    check('the settings form was actually saved, not bounced by validation',
        !$session->has('errors') || count($session->get('errors')->getBags()['default']->all()) === 0,
        $session->has('errors') ? implode('; ', $session->get('errors')->getBags()['default']->all()) : '');
    check('saving settings without the field is accepted',
        in_array($response->getStatusCode(), [200, 302], true), (string) $response->getStatusCode());
    check('and the Academy Code survives it', $after === $before,
        var_export($after, true) . ' vs ' . var_export($before, true));

    $stillWorks = true;

    try {
        App\Models\Setting::matriculePrefix();
    } catch (\Throwable $e) {
        $stillWorks = false;
    }

    check('so matricules can still be issued afterwards', $stillWorks);
} finally {
    DB::rollBack();
}

echo "\n== Nothing was left behind ==\n";

check('no test student is on file',
    !Student::where('last_name', 'Test')->where('first_name', 'Matricule')->exists());
check('the Academy Code is unchanged',
    trim((string) DB::table('settings')->value('academy_code')) !== '',
    var_export(DB::table('settings')->value('academy_code'), true));
check('the faculty still has its code',
    Faculty::find($faculty->id)->matric_code === $faculty->matric_code);
check('the batch still has its title',
    Batch::find($batch->id)->title === $batch->title);

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
