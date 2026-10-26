<?php

/**
 * The student portal's phone shell.
 *
 * Three things have to stay true of this change, and none of them is visible
 * from the markup alone:
 *
 *   1. Desktop is untouched. The sidebar is the laptop portal's only
 *      navigation, and every rule added here lives inside a max-width: 991px
 *      block. Both are asserted, because a sheet dialog leaking onto a laptop
 *      is the likeliest way this escapes its blast radius.
 *
 *   2. The two navigations reach the same places. StudentNav deliberately does
 *      not rewrite the sidebar, so drift is caught here rather than prevented
 *      by shared code: the sidebar's rendered destinations and StudentNav's
 *      list are compared as sets.
 *
 *   3. The safe-area insets actually resolve. viewport-fit=cover is what makes
 *      env(safe-area-inset-*) anything other than 0px on iOS, and the install
 *      card and chat launcher have been asking for it, and silently getting
 *      zero, since before this work.
 *
 * Read-only: the suite renders. The panel states it flips are restored, and
 * the closing section proves it with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\StudentNav;
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

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$session = $app['session']->driver();

$enroll = DB::table('student_enrolls')->orderBy('id', 'desc')->first();
$student = App\Models\Student::find($enroll->student_id);

View::share('errors', new Illuminate\Support\ViewErrorBag());

/** Render one portal URL through the real kernel, as a signed-in student. */
$visit = function (string $url) use ($kernel, $session, $student, $enroll) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession($session);
    $session->put('student_enrollment_id', $enroll->id);
    Auth::guard('student')->setUser($student);

    StudentNav::forget();

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $response->getContent()];
};

/**
 * A stylesheet with its own prose removed.
 *
 * Twice now an assertion has passed by matching a comment that describes the
 * thing instead of the thing: "viewport-fit=cover" is named in a CSS comment
 * that ships inside the page, and "@media (max-width: 991px)" is named in this
 * file's own header. Comments go before anything is searched for.
 */
$withoutComments = function (string $text): string {
    $text = preg_replace('~\{\{--.*?--\}\}~s', '', $text);   // Blade
    return preg_replace('~/\*.*?\*/~s', '', $text);            // CSS
};

/** The bar's markup out of a rendered page. */
$barOf = function (string $html) {
    return preg_match('~<nav class="app-nav".*?</nav>~s', $html, $m) ? $m[0] : '';
};

$openingPanels = DB::table('fields')->where('slug', 'like', 'panel_%')
    ->orderBy('slug')->pluck('status', 'slug')->all();

$opening = [
    'students' => DB::table('students')->count(),
    'enrolls' => DB::table('student_enrolls')->count(),
    'fields' => DB::table('fields')->count(),
];

echo "=== 1. the bar is there, and it is the whole bar ===" . PHP_EOL;

[$status, $home] = $visit('/student');

check('the portal opens', 200, $status);

$bar = $barOf($home);

ok('the bar renders', $bar !== '');
ok('the sheet renders', str_contains($home, 'id="appMore"'));
ok('the shell stylesheet is on the page', str_contains($home, '--app-nav-h'));

// The stylesheet is pushed to a stack; the student layout's is 'styles', not
// the admin layout's 'css'. A push to a stack that does not exist is dropped
// in silence, which is exactly what happened first time.
ok('it pushed to a stack that exists', str_contains($home, '--app-nav-total'));

$tabs = StudentNav::tabs();

check('four tabs plus More', 4, count($tabs));
check('and they are the daily four',
    ['dashboard', 'class-routine', 'exam-results', 'fees'], array_column($tabs, 'key'));
ok('More is in the bar', str_contains($bar, 'appMore'));

echo PHP_EOL . "=== 2. the active tab follows the student ===" . PHP_EOL;

/*
 * A tab reaches further than its own URL. These are the ones that are easy to
 * get wrong: the dashboard answers on two URLs, and transcript and payment
 * plan belong to tabs they do not link to.
 */
$expected = [
    '/student' => 'Home',
    '/student/dashboard' => 'Home',
    '/student/class-routine' => 'Classes',
    '/student/attendance' => 'Classes',
    '/student/course-registration' => 'Classes',
    '/student/exam-results' => 'Results',
    '/student/transcript' => 'Results',
    '/student/resit' => 'Results',
    '/student/fees' => 'Fees',
    '/student/payment-plan' => 'Fees',
    '/student/manual-payment' => 'Fees',
    '/student/notice' => 'More',
    '/student/profile' => 'More',
];

foreach ($expected as $url => $want) {
    [$code, $html] = $visit($url);

    if ($code !== 200) {
        check($url . ' renders', 200, $code);
        continue;
    }

    $bar = $barOf($html);

    preg_match_all('~<(?:a|button)[^>]*class="app-tab([^"]*)"[^>]*>.*?<span>([^<]*)</span>\s*</(?:a|button)>~s',
        $bar, $found, PREG_SET_ORDER);

    $on = [];
    foreach ($found as $f) {
        if (str_contains($f[1], 'is-on')) { $on[] = trim($f[2]); }
    }

    check(sprintf('%-30s lights %s', $url, $want), [$want], $on);
}

echo PHP_EOL . "=== 3. both navigations reach the same places ===" . PHP_EOL;

/*
 * StudentNav does not rewrite the sidebar, so nothing structural stops the two
 * from drifting. This is what catches it.
 */
$sidebar = view('student.layouts.inc.sidebar')->render();

preg_match_all('~href="(https?://[^"]*/student/[^"]*)"~', $sidebar, $links);

$sidebarPaths = array_values(array_unique(array_map(
    fn ($u) => rtrim(parse_url($u, PHP_URL_PATH), '/'), $links[1])));

$navPaths = array_map(
    fn ($i) => rtrim(parse_url($i['url'], PHP_URL_PATH), '/'), StudentNav::items());

// The sidebar carries submenu leaves (class-hub/history, e-library/browse …)
// that the mobile navigation reaches through their parent, so the comparison
// is on top-level destinations.
$topLevel = fn (array $paths) => array_values(array_unique(array_filter(array_map(
    function ($p) {
        $parts = explode('/', trim($p, '/'));
        return isset($parts[1]) ? '/' . $parts[0] . '/' . $parts[1] : null;
    }, $paths))));

$fromSidebar = $topLevel($sidebarPaths);
$fromNav = $topLevel($navPaths);

sort($fromSidebar);
sort($fromNav);

$missing = array_values(array_diff($fromSidebar, $fromNav));
$extra = array_values(array_diff($fromNav, $fromSidebar));

check('nothing the sidebar offers is missing from the app nav', [], $missing);
check('and the app nav invents nothing the sidebar lacks', [], $extra);

echo PHP_EOL . "=== 4. a switched-off panel is off everywhere ===" . PHP_EOL;

DB::table('fields')->where('slug', 'panel_fees_report')->update(['status' => 0]);
StudentNav::forget();

[, $noFees] = $visit('/student');
$bar = $barOf($noFees);

ok('the Fees tab is gone', !str_contains($bar, '/student/fees"'));
check('the bar lays out what is left', 3, count(StudentNav::tabs()));
ok('and the payment screens are gone from the sheet',
    !str_contains($noFees, 'app-link" href') || !str_contains($noFees, '/student/multi-payment'));

DB::table('fields')->where('slug', 'panel_fees_report')->update(['status' => 1]);
StudentNav::forget();

[, $backAgain] = $visit('/student');

ok('and it comes back when switched on', str_contains($barOf($backAgain), '/student/fees'));

echo PHP_EOL . "=== 5. the safe-area fix is real ===" . PHP_EOL;

// Against the meta tag itself. Matching the page text found the phrase in a
// CSS comment and passed with the tag missing.
preg_match('~<meta name="viewport"[^>]*>~', $home, $meta);

ok('there is a viewport meta', isset($meta[0]));
ok('and it sets viewport-fit=cover', str_contains($meta[0] ?? '', 'viewport-fit=cover'));

$shell = $withoutComments(file_get_contents($base . '/resources/views/student/layouts/inc/app_shell.blade.php'));

foreach ([
    '.app-nav' => 'padding-bottom: env(safe-area-inset-bottom',
    'the sheet' => 'padding-bottom: env(safe-area-inset-bottom',
] as $what => $needle) {
    ok($what . ' pads by the inset', str_contains($shell, $needle));
}

// Both of these already asked for the inset and were getting zero.
ok('the chat launcher clears the bar',
    (bool) preg_match('~#pax-chat\s*\{[^}]*calc\(var\(--app-nav-h\)~s', $shell));
ok('the install card clears the bar',
    (bool) preg_match('~\.pwa-install--floating\s*\{[^}]*calc\(var\(--app-nav-h\)~s', $shell));

// One number, read by everything that has to clear the bar.
check('there is one bar height', 1, substr_count($shell, '--app-nav-h:'));

echo PHP_EOL . "=== 6. thumbs can reach it ===" . PHP_EOL;

foreach ([['.app-tab', 44], ['.app-link', 48]] as [$selector, $floor]) {
    preg_match('~' . preg_quote($selector, '~') . '\s*\{([^}]*)\}~', $shell, $rule);
    preg_match('~min-height:\s*(\d+)px~', $rule[1] ?? '', $height);

    ok(sprintf('%-10s is at least %dpx', $selector, $floor),
        isset($height[1]) && (int) $height[1] >= $floor);
}

ok('taps are not delayed', str_contains($shell, 'touch-action: manipulation'));
ok('and do not flash grey', str_contains($shell, '-webkit-tap-highlight-color: transparent'));
ok('the press is felt', str_contains($shell, '.app-tab:active'));

// Active is never colour alone.
ok('active carries a weight change', (bool) preg_match('~\.app-tab\.is-on\s*\{[^}]*font-weight~s', $shell));
ok('and a filled pill', (bool) preg_match('~\.app-tab\.is-on\s[^{]*\{[^}]*background~s', $shell));

echo PHP_EOL . "=== 7. desktop never sees any of it ===" . PHP_EOL;

/*
 * Everything the shell adds must be inside the mobile block. A dialog turned
 * into a sheet on a laptop is the likeliest way this change escapes.
 */
$mobileAt = strpos($shell, '@media (max-width: 991px)');
$desktopAt = strpos($shell, '@media (min-width: 992px)');

ok('there is a mobile block', $mobileAt !== false);
ok('and a desktop block after it', $desktopAt !== false && $desktopAt > $mobileAt);

$mobileBlock = substr($shell, $mobileAt, $desktopAt - $mobileAt);

foreach (['.modal:not(.app-sheet) .modal-dialog', '.app-nav {', '.app-link {', '#pax-chat'] as $rule) {
    ok('"' . $rule . '" is inside the mobile block', str_contains($mobileBlock, $rule));
}

ok('the bar is hidden above 992px',
    (bool) preg_match('~@media \(min-width: 992px\)\s*\{[^}]*\.app-nav[^}]*display:\s*none~s', $shell));

echo PHP_EOL . "=== 8. the sheet is a sheet, and leaves the others alone ===" . PHP_EOL;

ok('the More sheet opts out of the generic dialog styling',
    str_contains($shell, '.modal:not(.app-sheet)'));
ok('it uses Bootstrap, so Escape and focus still work',
    str_contains($home, 'data-bs-toggle="modal"') && str_contains($home, 'data-bs-target="#appMore"'));
ok('it has a grab handle', str_contains($home, 'app-grab'));

// None of the twelve dialogs was edited to get this.
$touched = 0;
foreach (glob($base . '/resources/views/student/*/*.blade.php') as $file) {
    if (str_contains(file_get_contents($file), 'app-sheet')) { $touched++; }
}

check('no page dialog was given sheet markup', 0, $touched);

echo PHP_EOL . "=== 9. every page still renders ===" . PHP_EOL;

foreach (['/student', '/student/class-routine', '/student/exam-results', '/student/fees',
          '/student/attendance', '/student/transcript', '/student/notice',
          '/student/profile', '/student/assignment'] as $url) {
    [$code, $html] = $visit($url);

    check(sprintf('%-30s renders', $url), 200, $code);
    ok(sprintf('%-30s has the bar', $url), $barOf($html) !== '');
    ok(sprintf('%-30s resolves its labels', $url), !str_contains($html, 'nav_group_'));
}

echo PHP_EOL . "=== nothing was left behind ===" . PHP_EOL;

$closingPanels = DB::table('fields')->where('slug', 'like', 'panel_%')
    ->orderBy('slug')->pluck('status', 'slug')->all();

check('every panel is as it was', $openingPanels, $closingPanels);

foreach ($opening as $table => $count) {
    check(sprintf('%-9s is still %d', $table, $count), $count,
        DB::table($table === 'enrolls' ? 'student_enrolls' : $table)->count());
}

printf(PHP_EOL . "%d passed, %d failed" . PHP_EOL . PHP_EOL, $pass, $fail);

exit($fail === 0 ? 0 : 1);
