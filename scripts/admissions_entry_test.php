<?php
/**
 * The admissions portal must have a front door.
 *
 * A lecturer reviewing the flow pointed out that clicking "Apply Online" on the
 * public site landed a brand-new applicant on "Welcome Back — please enter your
 * details to sign in". That was not a design choice: "Apply Now" pointed at a
 * route behind auth:applicant, so Laravel bounced every guest to the login form.
 * The applicant was seeing a redirect, not a page anyone had designed for them.
 *
 * What has to hold now:
 *   - a guest route exists that is the real entry point;
 *   - the public "Apply Now" and navbar buttons point at it;
 *   - a guest hitting a protected applicant URL lands there, not on login;
 *   - the start page leads with starting, and offers signing in second;
 *   - it reports the live intake, fee and document checklist;
 *   - it degrades honestly when applications are closed;
 *   - a signed-in applicant is carried straight to their dashboard;
 *   - the login page no longer greets a stranger as a returning user.
 *
 * Usage: php scripts/admissions_entry_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Applicant;
use App\Models\ApplicationSetting;
use App\Models\Session as AcademicSession;
use App\Support\ApplicationDocumentRequirements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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

/** Render a route through the kernel as an unauthenticated visitor. */
function get(string $uri)
{
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
}

echo "\n== The route exists and is public ==\n";

$routes = Route::getRoutes();
$start = $routes->getByName('application.start');
check('application.start is registered', $start !== null);

if ($start) {
    $middleware = $start->gatherMiddleware();
    check(
        'it is a guest route, not behind auth:applicant',
        in_array('guest:applicant', $middleware, true) && !in_array('auth:applicant', $middleware, true),
        implode(', ', $middleware)
    );
    check('it answers at application/start', $start->uri() === 'application/start', $start->uri());
}

echo "\n== The public site points at it ==\n";

$publicViews = [
    'resources/views/web/admissions.blade.php'     => 'the "Ready to Apply?" CTA',
    'resources/views/web/layouts/master.blade.php' => 'the site navbar and footer',
];

foreach ($publicViews as $path => $what) {
    $src = file_get_contents(base_path($path));
    check(
        "$what no longer links to the authenticated application.index",
        strpos($src, "route('application.index')") === false,
        $path
    );
    check(
        "$what links to application.start",
        strpos($src, "route('application.start')") !== false,
        $path
    );
}

echo "\n== A guest deep-linking a protected URL lands on the start page ==\n";

$response = get('/application/dashboard');
$target = $response->headers->get('Location');
check(
    'application/dashboard redirects a guest to the start page',
    $response->isRedirect() && strpos((string) $target, 'application/start') !== false,
    'status ' . $response->getStatusCode() . ', location ' . var_export($target, true)
);

echo "\n== The start page itself ==\n";

$response = get('/application/start');
check('it renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());

$html = $response->getContent();

// The exact wording the lecturer objected to must not be the first thing a
// prospective applicant meets.
check(
    'it does not greet a stranger with "Welcome Back"',
    stripos($html, 'Welcome Back') === false
);
check(
    'it does not open with "enter your details to sign in"',
    stripos($html, 'enter your details to sign in') === false
);

check('it says what the page is for', stripos($html, 'Apply to') !== false);

$startPos    = stripos($html, 'Start my application');
$continuePos = stripos($html, 'Sign in to continue');

$applicationSetting = ApplicationSetting::status();
$openSessions       = AcademicSession::where('applications_open', 1)->get();
$isOpen             = $applicationSetting && $openSessions->isNotEmpty();

if ($isOpen) {
    check('it offers a way to start a new application', $startPos !== false);
    check('it offers a way to continue an existing one', $continuePos !== false);

    // Order on the page is the whole point of the fix: the new applicant is the
    // majority audience and must be served first, not in a footnote.
    check(
        'starting comes before signing in',
        $startPos !== false && $continuePos !== false && $startPos < $continuePos,
        "start at $startPos, continue at $continuePos"
    );

    check(
        'it names the open intake',
        stripos($html, (string) $openSessions->first()->title) !== false,
        'expected session "' . $openSessions->first()->title . '"'
    );

    if (!empty($applicationSetting->fee_amount)) {
        check(
            'it states the application fee from settings',
            strpos($html, number_format((float) $applicationSetting->fee_amount, 0)) !== false,
            'expected ' . number_format((float) $applicationSetting->fee_amount, 0)
        );
    }

    $required = collect(ApplicationDocumentRequirements::all())->filter(fn ($d) => !empty($d['required']));
    $missing  = $required->filter(fn ($d) => stripos($html, $d['label']) === false);
    check(
        'it lists every required document (' . $required->count() . ')',
        $missing->isEmpty(),
        'missing: ' . $missing->pluck('label')->implode('; ')
    );

    check(
        'it says progress is saved, so the form is not one long sitting',
        stripos($html, 'saved as you go') !== false
    );
} else {
    echo "  ..  applications are closed in this database; checking the closed state\n";
    check('it says applications are closed', stripos($html, 'currently closed') !== false);
    check('it offers no way into a form that would refuse them', $startPos === false);
    check('it still lets an existing applicant check their application', stripos($html, 'Sign in to check') !== false);
}

check('it offers a way back to the main site', stripos($html, 'Back to website') !== false);

check(
    'the hero pulls no image from an external host',
    stripos($html, 'unsplash.com') === false
);

echo "\n== The login page is reframed ==\n";

$response = get('/application/login');
$login    = $response->getContent();

check('login still renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
check('it no longer says "Welcome Back"', stripos($login, 'Welcome Back') === false);
check('it says what signing in is for', stripos($login, 'Continue your application') !== false);
check(
    'a first-time applicant is offered their own route, above the form',
    stripos($login, 'Applying for the first time') !== false
        && stripos($login, 'Applying for the first time') < stripos($login, 'name="password"')
);

echo "\n== A signed-in applicant is not shown the marketing page ==\n";

$applicant = Applicant::orderByDesc('id')->first();
if (!$applicant) {
    echo "  ..  no applicant on file; skipping\n";
} else {
    Auth::guard('applicant')->login($applicant);

    $controller = app(App\Http\Controllers\Web\ApplicationController::class);
    $redirect   = $controller->startPage();

    check(
        'startPage() sends an authenticated applicant to their dashboard',
        $redirect instanceof Illuminate\Http\RedirectResponse
            && strpos($redirect->getTargetUrl(), 'application/dashboard') !== false,
        method_exists($redirect, 'getTargetUrl') ? $redirect->getTargetUrl() : get_class($redirect)
    );

    Auth::guard('applicant')->logout();
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
