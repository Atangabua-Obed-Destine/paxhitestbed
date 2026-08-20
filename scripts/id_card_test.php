<?php
/**
 * The ID card artwork is configurable, and configuring it changes nothing else.
 *
 * Renders every card surface three times — with nothing configured, with a
 * student background set, and with a staff background set — and compares the
 * card CSS across the three. The point of the suite is not that the upload
 * works; it is that the layout does not move when it does.
 *
 * Each render is a separate process: compiled Blade views in this project
 * declare a global panel() helper, so two renders in one process fatal.
 *
 * Usage: php scripts/id_card_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IdCardSetting;
use App\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

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

const SURFACES = ['student-print', 'student-download', 'student-index', 'staff-print', 'staff-download'];
const STUDENT_SURFACES = ['student-print', 'student-download', 'student-index'];
const STAFF_SURFACES = ['staff-print', 'staff-download'];

/** Render one surface in its own process and return its parsed output. */
function render(string $surface): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/id_card_render.php')
        . ' ' . escapeshellarg($surface) . ' --full 2>&1';
    $out = shell_exec($cmd);

    $rule = '';
    $children = [];
    foreach (explode("\n", (string) $out) as $line) {
        $line = rtrim($line, "\r");
        if (strpos($line, 'rule:') === 0) {
            $rule = trim(substr($line, 5));
        } elseif (strpos(ltrim($line), 'child:') === 0) {
            $children[] = trim(substr(ltrim($line), 6));
        }
    }

    return ['rule' => $rule, 'children' => $children, 'raw' => (string) $out];
}

/** Render every surface. */
function renderAll(): array
{
    $out = [];
    foreach (SURFACES as $s) {
        $out[$s] = render($s);
    }
    return $out;
}

/** The url(...) inside a card rule. */
function backgroundUrl(string $rule): string
{
    return preg_match("~url\('([^']+)'\)~", $rule, $m) ? $m[1] : '';
}

/** Everything in the card rule except the background image. */
function ruleWithoutBackground(string $rule): string
{
    return preg_replace("~url\('[^']+'\)~", "url(...)", $rule);
}

// ---------------------------------------------------------------------------
// Preserve the two settings rows and the uploads folder; restore at the end.
// ---------------------------------------------------------------------------

$student = IdCardSetting::where('slug', 'student-card')->first();
$staff = IdCardSetting::where('slug', 'staff-id-card')->first();

if (!$student || !$staff) {
    fwrite(STDERR, "missing id_card_settings rows (student-card / staff-id-card)\n");
    exit(2);
}

$originalStudent = $student->background;
$originalStaff = $staff->background;

$uploadDir = public_path('uploads/card-setting');
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$studentFile = 'test_student_bg_' . time() . '.jpg';
$staffFile = 'test_staff_bg_' . time() . '.jpg';
copy(public_path('uploads/templates/paxid.jpg'), $uploadDir . '/' . $studentFile);
copy(public_path('uploads/templates/staffid.jpg'), $uploadDir . '/' . $staffFile);

$restore = function () use ($student, $staff, $originalStudent, $originalStaff, $uploadDir, $studentFile, $staffFile) {
    $student->background = $originalStudent;
    $student->save();
    $staff->background = $originalStaff;
    $staff->save();
    @unlink($uploadDir . '/' . $studentFile);
    @unlink($uploadDir . '/' . $staffFile);
};

register_shutdown_function($restore);

echo "\n";
echo str_repeat('=', 60) . "\n";
echo "ID card background configuration\n";
echo str_repeat('=', 60) . "\n";

// ---------------------------------------------------------------------------
echo "\nNothing configured — the built-in artwork is used\n";
// ---------------------------------------------------------------------------

$student->background = null;
$student->save();
$staff->background = null;
$staff->save();

$baseline = renderAll();

foreach (SURFACES as $s) {
    check("$s renders", $baseline[$s]['rule'] !== '', trim($baseline[$s]['raw']));
}

foreach (STUDENT_SURFACES as $s) {
    check(
        "$s falls back to paxid.jpg",
        str_ends_with(backgroundUrl($baseline[$s]['rule']), '/uploads/templates/paxid.jpg'),
        backgroundUrl($baseline[$s]['rule'])
    );
}
foreach (STAFF_SURFACES as $s) {
    check(
        "$s falls back to staffid.jpg",
        str_ends_with(backgroundUrl($baseline[$s]['rule']), '/uploads/templates/staffid.jpg'),
        backgroundUrl($baseline[$s]['rule'])
    );
}

// ---------------------------------------------------------------------------
echo "\nStudent artwork configured\n";
// ---------------------------------------------------------------------------

$student->background = $studentFile;
$student->save();

$withStudent = renderAll();

foreach (STUDENT_SURFACES as $s) {
    check(
        "$s uses the uploaded artwork",
        str_ends_with(backgroundUrl($withStudent[$s]['rule']), '/uploads/card-setting/' . $studentFile),
        backgroundUrl($withStudent[$s]['rule'])
    );
}

// The two cards share one table, so this is the regression worth guarding.
foreach (STAFF_SURFACES as $s) {
    check(
        "$s is untouched by the student upload",
        $withStudent[$s]['rule'] === $baseline[$s]['rule'],
        backgroundUrl($withStudent[$s]['rule'])
    );
}

// ---------------------------------------------------------------------------
echo "\nStaff artwork configured\n";
// ---------------------------------------------------------------------------

$staff->background = $staffFile;
$staff->save();

$withStaff = renderAll();

foreach (STAFF_SURFACES as $s) {
    check(
        "$s uses the uploaded artwork",
        str_ends_with(backgroundUrl($withStaff[$s]['rule']), '/uploads/card-setting/' . $staffFile),
        backgroundUrl($withStaff[$s]['rule'])
    );
}
foreach (STUDENT_SURFACES as $s) {
    check(
        "$s still uses its own artwork",
        str_ends_with(backgroundUrl($withStaff[$s]['rule']), '/uploads/card-setting/' . $studentFile),
        backgroundUrl($withStaff[$s]['rule'])
    );
}

// ---------------------------------------------------------------------------
echo "\nLayout is untouched — only the image changes\n";
// ---------------------------------------------------------------------------

foreach (SURFACES as $s) {
    check(
        "$s card box unchanged (size, position, fonts)",
        ruleWithoutBackground($baseline[$s]['rule']) === ruleWithoutBackground($withStaff[$s]['rule']),
        "before: " . ruleWithoutBackground($baseline[$s]['rule']) . "\n          after:  " . ruleWithoutBackground($withStaff[$s]['rule'])
    );

    check(
        "$s overlay rules unchanged (" . count($baseline[$s]['children']) . " rules)",
        $baseline[$s]['children'] === $withStaff[$s]['children'],
        'photo / matricule / validity positions differ'
    );

    // The rules that make background images survive printing must stay.
    check(
        "$s keeps background-size: cover",
        strpos($withStaff[$s]['rule'], 'background-size: cover') !== false
    );
}

// ---------------------------------------------------------------------------
echo "\nSetting screens\n";
// ---------------------------------------------------------------------------

$admin = User::whereHas('roles', function ($q) {
    $q->where('name', 'Super Admin');
})->first() ?: User::first();
Auth::guard('web')->login($admin);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$fetch = function (string $uri) use ($kernel) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);
    return [$response->getStatusCode(), $response->getContent()];
};

[$code, $body] = $fetch('/admin/admission/id-card-setting');
check('student setting screen loads', $code === 200, "HTTP $code");
check('student setting screen offers a Background upload', strpos($body, 'name="background"') !== false);
check('student setting screen previews the current artwork', strpos($body, 'uploads/card-setting/' . $studentFile) !== false);

check(
    'staff permissions exist',
    Permission::whereIn('name', ['staff-id-card-setting-view', 'staff-id-card-setting-edit'])->count() === 2
);

[$code, $body] = $fetch('/admin/staff/staff-id-card-setting');
check('staff setting screen loads', $code === 200, "HTTP $code");
check('staff setting screen offers a Background upload', strpos($body, 'name="background"') !== false);
check('staff setting screen previews the current artwork', strpos($body, 'uploads/card-setting/' . $staffFile) !== false);

// ---------------------------------------------------------------------------
echo "\nUploading through the staff controller\n";
// ---------------------------------------------------------------------------

// Exercise the real controller rather than writing the column directly, so the
// validation, the file move and the slug scoping are all covered.
$tmp = tempnam(sys_get_temp_dir(), 'card') . '.jpg';
copy(public_path('uploads/templates/staffid.jpg'), $tmp);

$upload = new Illuminate\Http\UploadedFile($tmp, 'new_staff_card.jpg', 'image/jpeg', null, true);
$controller = $app->make(App\Http\Controllers\Admin\StaffIdCardSettingController::class);
$storeRequest = Illuminate\Http\Request::create('/admin/staff/staff-id-card-setting', 'POST', [], [], ['background' => $upload]);
$storeRequest->setLaravelSession(app('session.store'));

$studentBefore = IdCardSetting::where('slug', 'student-card')->value('background');
$controller->store($storeRequest);

$staffAfter = IdCardSetting::where('slug', 'staff-id-card')->value('background');
$studentAfter = IdCardSetting::where('slug', 'student-card')->value('background');

check('upload is stored against the staff row', $staffAfter !== $staffFile && !empty($staffAfter), (string) $staffAfter);
check(
    'uploaded file lands in uploads/card-setting',
    $staffAfter && file_exists(public_path('uploads/card-setting/' . $staffAfter)),
    (string) $staffAfter
);
check('the student row is not touched by a staff upload', $studentAfter === $studentBefore, "$studentBefore -> $studentAfter");

// Saving the form with no file chosen must keep the artwork, not wipe it —
// the screen tells the admin so, and this is what makes that true.
$emptyRequest = Illuminate\Http\Request::create('/admin/staff/staff-id-card-setting', 'POST');
$emptyRequest->setLaravelSession(app('session.store'));
$controller->store($emptyRequest);

check(
    'saving with no file chosen keeps the current artwork',
    IdCardSetting::where('slug', 'staff-id-card')->value('background') === $staffAfter,
    'background was cleared by an empty save'
);

// Clean up whatever the controller just wrote.
if ($staffAfter && $staffAfter !== $originalStaff) {
    @unlink(public_path('uploads/card-setting/' . $staffAfter));
}
@unlink($tmp);

echo "\n" . str_repeat('-', 60) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
