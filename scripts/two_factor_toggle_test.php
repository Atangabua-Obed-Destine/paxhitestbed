<?php

/**
 * The two-factor switches on Security -> Users.
 *
 * Three separate questions, and they had three different answers:
 *
 *   1. Does the switch save?            It always did.
 *   2. Does saving it change anything?  Only in one of four policy states, and
 *                                       the screen never said which it was in.
 *   3. Who may use it?                  Anyone who could sign in. A lecturer
 *                                       could switch off a Super Admin's 2FA.
 *
 * The third is why this suite leans hardest on access: a security screen that
 * is not itself secured is worse than no screen.
 *
 * Every write happens inside a transaction that is rolled back, and no real
 * mail is sent.
 *
 *   php scripts/two_factor_toggle_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\SecuritySetting;
use App\Models\Student;
use App\User;

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

app('session.store')->start();

$admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
$lecturer = User::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Admin']))->first();

if (!$admin) {
    echo "no Super Admin to act as\n";
    exit(0);
}

$settingsBefore = SecuritySetting::pluck('value', 'key')->all();

function asUser($account): void
{
    Auth::guard('web')->logout();
    Auth::guard('web')->login($account);
}

function hit($method, $url, array $data = [])
{
    global $kernel;

    $request = Request::create($url, $method, array_merge(['_token' => csrf_token()], $data));
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');

    try {
        return $kernel->handle($request)->getStatusCode();
    } catch (\Throwable $e) {
        return 500;
    }
}

// ---------------------------------------------------------------------------
section('Only the right people can reach these screens');
// ---------------------------------------------------------------------------
if (!$lecturer) {
    skip('no non-administrative staff account to test refusal with');
} else {
    DB::beginTransaction();

    try {
        asUser($lecturer);

        $before = (bool) User::find($admin->id)->two_factor_enabled;

        check('a lecturer cannot open the security user list',
            hit('GET', '/admin/security/users') === 403,
            'it was open to anyone who could sign in');

        check('nor the security settings',
            hit('GET', '/admin/security/settings') === 403);

        check('nor clear the security log',
            hit('POST', '/admin/security/logs/clear') === 403,
            'the log is the record of what happened; whoever can erase it can erase their own trail');

        $status = hit('POST', '/admin/security/users/toggle-2fa', [
            'user_id' => $admin->id, 'user_type' => 'user', 'enabled' => 0,
        ]);

        check('and cannot switch off a Super Admin\'s two-factor authentication',
            $status === 403,
            'got ' . $status . ' — this worked before, and is the reason for the whole section');

        check('the Super Admin\'s setting is untouched by the attempt',
            (bool) User::find($admin->id)->two_factor_enabled === $before,
            'the refusal has to actually refuse, not merely redirect afterwards');

        // What the refusal LOOKS like, not only its status. errors/403.blade.php
        // was a copy of the 404 page, so a permission refusal read as "Error
        // 404, maybe this page not exist" - sending whoever saw it hunting for
        // a broken route instead of a missing permission.
        $refusalRequest = Request::create('/admin/security/users', 'GET');
        $refusalRequest->setLaravelSession(app('session.store'));
        $refusalPage = $kernel->handle($refusalRequest)->getContent();

        check('the refusal page does not claim the page is missing',
            !str_contains($refusalPage, 'Error 404'),
            'a 403 dressed as a 404 hands a wrong diagnosis to whoever reads it');

        check('it says the problem is permission',
            stripos($refusalPage, 'permission') !== false,
            'so that granting the role is the obvious next step');
    } finally {
        DB::rollBack();
    }
}

DB::beginTransaction();

try {
    asUser($admin);

    check('a Super Admin can still open the user list',
        hit('GET', '/admin/security/users') === 200,
        'locking the owner out of their own security screen would be worse than leaving it open');

    check('and the security settings', hit('GET', '/admin/security/settings') === 200);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The switch saves what it says it saves');
// ---------------------------------------------------------------------------
$subject = User::where('id', '!=', $admin->id)->first();

if (!$subject) {
    skip('no second staff account to toggle');
} else {
    DB::beginTransaction();

    try {
        asUser($admin);

        $toggle = fn ($enabled) => hit('POST', '/admin/security/users/toggle-2fa', [
            'user_id' => $subject->id, 'user_type' => 'user', 'enabled' => $enabled,
        ]);

        $toggle(1);
        $after = User::find($subject->id);

        check('turning it on records it', (bool) $after->two_factor_enabled === true);
        check('and stamps when it was turned on',
            $after->two_factor_enabled_at !== null,
            'without the date nobody can say when the account was protected');

        $toggle(0);
        $after = User::find($subject->id);

        check('turning it off records that too', (bool) $after->two_factor_enabled === false);
        check('and clears the date',
            $after->two_factor_enabled_at === null,
            'a date left behind reads as still protected');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('What the switch actually decides at sign-in');
// ---------------------------------------------------------------------------
// The real gate, called directly: LoginController::authenticated() is what
// sends someone to the code screen or straight in. Reading the setting back
// would only prove the setting was read.
function challenged($account): bool
{
    $controller = app(App\Http\Controllers\Auth\LoginController::class);
    $method = new ReflectionMethod(App\Http\Controllers\Auth\LoginController::class, 'authenticated');
    $method->setAccessible(true);

    $request = Request::create('/', 'POST');
    $request->setLaravelSession(app('session.store'));

    $response = $method->invoke($controller, $request, $account);

    return $response !== null
        && method_exists($response, 'getTargetUrl')
        && str_contains($response->getTargetUrl(), '2fa');
}

function policy(string $key, string $value): void
{
    SecuritySetting::where('key', $key)->update(['value' => $value]);
    Cache::flush();
}

if (!$subject) {
    skip('no account to sign in as');
} else {
    DB::beginTransaction();

    try {
        Mail::fake();

        $flag = function (int $on) use ($subject) {
            DB::table('users')->where('id', $subject->id)->update(['two_factor_enabled' => $on]);

            return User::find($subject->id);
        };

        policy('enable_2fa_admin', '1');
        policy('2fa_mandatory_admin', '0');

        check('with 2FA on and not mandatory, the switch decides',
            challenged($flag(1)) === true && challenged($flag(0)) === false,
            'this is the only state in which the switch means anything');

        policy('2fa_mandatory_admin', '1');

        check('with 2FA mandatory, everyone is challenged whatever the switch says',
            challenged($flag(1)) === true && challenged($flag(0)) === true,
            'so the switch is not a way to exempt anybody');

        policy('enable_2fa_admin', '0');
        policy('2fa_mandatory_admin', '0');

        check('with 2FA off globally, nobody is challenged whatever the switch says',
            challenged($flag(1)) === false && challenged($flag(0)) === false,
            'so the switch is not a way to protect anybody either');
    } finally {
        DB::rollBack();
        Cache::flush();
    }
}

// ---------------------------------------------------------------------------
section('The screen admits when the switch is not deciding');
// ---------------------------------------------------------------------------
function usersPage(string $query = ''): string
{
    global $kernel;

    $request = Request::create('/admin/security/users' . $query, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request)->getContent();
}

DB::beginTransaction();

try {
    asUser($admin);

    policy('enable_2fa_admin', '1');
    policy('2fa_mandatory_admin', '1');
    $page = usersPage();

    check('mandatory 2FA is declared on the page',
        stripos($page, 'not deciding anything') !== false && stripos($page, 'mandatory') !== false,
        'the switch still saves and still says "Enabled" — the page has to say it changes nothing');

    policy('enable_2fa_admin', '0');
    policy('2fa_mandatory_admin', '0');
    $page = usersPage();

    check('2FA being off globally is declared too',
        stripos($page, 'not deciding anything') !== false
            && stripos($page, 'switched off') !== false,
        'turning a switch on that protects nobody is the more dangerous of the two');

    policy('enable_2fa_admin', '1');
    policy('2fa_mandatory_admin', '0');
    $page = usersPage();

    check('and when the switch does decide, the page says nothing',
        stripos($page, 'not deciding anything') === false,
        'a notice that never goes away is a notice nobody reads');
} finally {
    DB::rollBack();
    Cache::flush();
}

// ---------------------------------------------------------------------------
section('The control is a control');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    asUser($admin);
    $page = usersPage();

    check('the switch uses this application\'s Bootstrap 5 classes',
        str_contains($page, 'form-check form-switch') && str_contains($page, 'form-check-input toggle-2fa'),
        'custom-control/custom-switch are Bootstrap 4 names that nothing here defines');

    check('and no Bootstrap 4 switch markup is left',
        !str_contains($page, 'custom-control custom-switch'),
        'it rendered as a bare checkbox with loose text beside it');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
Cache::flush();
$settingsAfter = SecuritySetting::pluck('value', 'key')->all();

check('every security setting is back as it was',
    $settingsBefore == $settingsAfter,
    'this suite changes the policy to test it and must put it back');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
