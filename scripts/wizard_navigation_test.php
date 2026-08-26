<?php
/**
 * The wizard must remember where the applicant was, and what they have done.
 *
 * currentIndex and highestIndexReached were both hardcoded to 0 on every page
 * load. Two things followed, and an applicant hit them together:
 *
 *   - reloading the page threw them back to step 1, however far in they were;
 *   - every sidebar step then refused to open, saying "Please complete the
 *     preceding steps before skipping ahead", even on a finished form, because
 *     the only record of their progress was a JavaScript variable the reload
 *     had just reset.
 *
 * The ceiling now comes from the server, computed from SAVED data, so it is
 * correct on a fresh browser or another device. The exact position is kept in
 * localStorage, which is the right home for a per-viewer convenience.
 *
 * Usage: php scripts/wizard_navigation_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Web\ApplicationController;
use App\Models\Application;
use App\Services\ApplicationCompleteness;
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

$unlock = new ReflectionMethod(ApplicationController::class, 'wizardUnlockedThrough');
$unlock->setAccessible(true);
$controller = app(ApplicationController::class);

$ceiling = function (Application $a) use ($unlock, $controller): int {
    return $unlock->invoke($controller, $a);
};

echo "\n== The ceiling follows the saved data ==\n";

$application = Application::whereNotNull('applicant_id')->whereNotNull('degree_type_id')
    ->orderByDesc('id')->first();
if (!$application) {
    fwrite(STDERR, "no application to test with\n");
    exit(2);
}

$missing = ApplicationCompleteness::missing($application);
check(
    'a complete application unlocks the whole wizard',
    $missing !== [] || $ceiling($application) === 8,
    'ceiling ' . $ceiling($application) . ', missing ' . count($missing)
);

check('the ceiling is always a real step', $ceiling($application) >= 1 && $ceiling($application) <= 8);

// An incomplete form must still stop at the step that needs attention, or the
// guard would be gone rather than fixed. Blank a step-1 field to prove it.
DB::beginTransaction();
try {
    $original = $application->phone;
    $application->phone = null;
    $application->save();
    $application->refresh();

    $blanked = ApplicationCompleteness::missing($application);
    $steps   = array_column($blanked, 'step');

    check(
        'blanking a step 1 field is seen as missing',
        in_array(1, $steps, true),
        'missing at steps: ' . implode(',', array_unique($steps))
    );
    check(
        'the ceiling drops to the step that needs attention',
        $ceiling($application) === 1,
        'ceiling ' . $ceiling($application)
    );
} finally {
    DB::rollBack();
}

$application->refresh();
check('the blanked field was rolled back', $application->phone !== null);

echo "\n== The page carries the ceiling to the browser ==\n";

Auth::guard('applicant')->loginUsingId($application->applicant_id);

$kernel  = app(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/application/' . $application->id . '/edit', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

check('the wizard renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
$html = $response->getContent();

check(
    'the server ceiling reaches the page',
    strpos($html, 'serverUnlockedIndex') !== false
);
check(
    'the ceiling is rendered as a number, not left as Blade',
    (bool) preg_match('/serverUnlockedIndex\s*=\s*Math\.max\(0,\s*Math\.min\(\s*(\d+)\s*-\s*1/', $html, $m),
    'no numeric literal found'
);
if (!empty($m[1])) {
    check(
        'it matches what the controller computed (' . $ceiling($application) . ')',
        (int) $m[1] === $ceiling($application),
        'page says ' . $m[1]
    );
}

check(
    'the position is restored rather than reset to zero',
    strpos($html, 'var currentIndex = storedIndex === null ? 0') !== false
);
check(
    'the ceiling seeds highestIndexReached',
    strpos($html, 'var highestIndexReached = serverUnlockedIndex;') !== false
);
check(
    'neither counter is hardcoded to 0 any more',
    strpos($html, 'var currentIndex = 0;') === false
        && strpos($html, 'var highestIndexReached = 0;') === false
);

echo "\n== Storage is used safely ==\n";

check('the key is scoped to this application', strpos($html, "'paxApplicationStep:" . $application->id . "'") !== false);
check('moving step records the position', strpos($html, 'rememberIndex(currentIndex);') !== false);

// localStorage throws outright in some embedded webviews and with site data
// blocked, which would take the whole wizard down with it.
check(
    'reads are guarded against blocked storage',
    (bool) preg_match('/function readStoredIndex\(\)\s*\{.*?try\s*\{.*?catch/s', $html)
);
check(
    'writes are guarded against blocked storage',
    (bool) preg_match('/function rememberIndex\([^)]*\)\s*\{\s*try\s*\{.*?catch/s', $html)
);
check(
    'a restored position is clamped to the ceiling',
    strpos($html, 'Math.min(storedIndex, serverUnlockedIndex)') !== false
);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
