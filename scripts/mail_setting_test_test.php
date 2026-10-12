<?php

/**
 * The "test these settings" button on the mail settings screen.
 *
 * Mail is the setting whose failure is silent: nothing on screen breaks, and
 * applicants simply never receive their letters. The button exists so the
 * school can ask the server directly, and its value is entirely in telling a
 * wrong password apart from a wrong host — so that is what is tested here.
 *
 * No real mail is sent. Every case points at a host that cannot answer, which
 * is also why each one must fail at the stage it is supposed to fail at: a
 * suite that only checked "it failed" would pass even if every diagnosis were
 * wrong.
 *
 * Nothing is written to the database or to .env.
 *
 *   php scripts/mail_setting_test_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\MailSetting;
use App\Services\MailSettingTester;

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

$tester = app(MailSettingTester::class);

/** Settings that are complete but point nowhere, so no mail can leave. */
function deadEnd(array $override = []): array
{
    return array_merge([
        'driver' => 'smtp',
        'host' => 'smtp.this-host-cannot-exist-pax.invalid',
        'port' => '465',
        'username' => 'someone@example.com',
        'password' => 'a-password-long-enough',
        'encryption' => 'ssl',
        'sender_email' => 'someone@example.com',
        'sender_name' => 'Test',
    ], $override);
}

$savedBefore = MailSetting::where('status', '1')->first();
$envBefore = is_file(base_path('.env')) ? md5_file(base_path('.env')) : null;

// ---------------------------------------------------------------------------
section('It reaches the right verdict, at the right stage');
// ---------------------------------------------------------------------------
$result = $tester->run(deadEnd(['host' => '', 'password' => '']), 'someone@example.com');

check('incomplete settings stop before any connection is attempted',
    $result['ok'] === false && $result['stage'] === MailSettingTester::STAGE_CONFIGURATION,
    'stage was ' . $result['stage']);

check('and the missing fields are named',
    str_contains($result['detail'], 'Host') && str_contains($result['detail'], 'Password'),
    $result['detail']);

check('nothing further is attempted once settings are incomplete',
    collect($result['steps'])->whereIn('key', [MailSettingTester::STAGE_CONNECTION, MailSettingTester::STAGE_DELIVERY])
        ->every(fn ($s) => $s['state'] === 'skipped'),
    'a test that dials a half-filled form wastes the admin\'s time on a failure it could predict');

$result = $tester->run(deadEnd(), 'someone@example.com');

check('an unreachable host fails at the connection stage',
    $result['ok'] === false && $result['stage'] === MailSettingTester::STAGE_CONNECTION,
    'stage was ' . $result['stage']);

check('and says so in words an administrator can act on',
    stripos($result['headline'], 'host name') !== false,
    $result['headline']);

check('the configuration step is still marked as passed',
    collect($result['steps'])->firstWhere('key', MailSettingTester::STAGE_CONFIGURATION)['state'] === 'passed',
    'the fields were complete; only the server was wrong');

check('delivery is not attempted after the connection fails',
    collect($result['steps'])->firstWhere('key', MailSettingTester::STAGE_DELIVERY)['state'] === 'skipped');

// A closed port on this machine answers immediately, so this is a different
// failure from an unknown host and must be reported differently.
$result = $tester->run(deadEnd(['host' => '127.0.0.1', 'port' => '2', 'encryption' => 'tls']), 'someone@example.com');

check('a refused port is diagnosed as a port or firewall problem, not a bad host name',
    $result['ok'] === false
        && stripos($result['headline'], 'host name') === false
        && (stripos($result['headline'], 'port') !== false || stripos($result['headline'], 'answer') !== false),
    $result['headline']);

// ---------------------------------------------------------------------------
section('The wording maps to the real failures');
// ---------------------------------------------------------------------------
// Checked against the sentences SMTP servers actually return, because the whole
// point of the feature is turning those into something a registrar can act on.
$explain = new ReflectionMethod(MailSettingTester::class, 'explain');
$explain->setAccessible(true);
$say = fn (string $raw) => $explain->invoke($tester, $raw);

$expectations = [
    'Failed to authenticate on SMTP server with username "x" using LOGIN' => 'username or password',
    '535 5.7.8 Authentication credentials invalid' => 'username or password',
    'php_network_getaddresses: getaddrinfo failed' => 'host name',
    'stream_socket_client(): Unable to connect (Connection refused)' => 'port',
    'SSL routines::wrong version number' => 'Encryption',
    '553 5.7.1 Sender address rejected: not owned by user' => 'send from this address',
];

foreach ($expectations as $raw => $expected) {
    check('"' . substr($raw, 0, 44) . '…" is explained as a ' . $expected . ' problem',
        stripos($say($raw), $expected) !== false,
        'got: ' . $say($raw));
}

check('an error it does not recognise still gets a plain headline',
    trim($say('620 something nobody has seen before')) !== '',
    'an empty headline would leave the panel blank');

// ---------------------------------------------------------------------------
section('Credentials do not leak into the page');
// ---------------------------------------------------------------------------
$clean = new ReflectionMethod(MailSettingTester::class, 'clean');
$clean->setAccessible(true);

$secret = 'Sup3rSecretPassw0rd';
$scrubbed = $clean->invoke($tester, 'Server said: AUTH LOGIN ' . $secret . ' rejected', ['password' => $secret]);

check('the password is removed from the server\'s reply',
    !str_contains($scrubbed, $secret),
    'this text is shown on a page an administrator may screenshot — ' . $scrubbed);

check('and base64 of it too, which is how SMTP actually sends it',
    !str_contains(
        $clean->invoke($tester, 'sent ' . base64_encode($secret), ['password' => $secret]),
        base64_encode($secret)
    ));

// The bug this caught: a two-character password matched ordinary letters all
// through the message, so the diagnostic redacted itself into nonsense.
$short = $clean->invoke($tester, 'Connection could not be established with host "smtp.example.com"', ['password' => 'p']);

check('a very short password does not shred the message it appears in',
    str_contains($short, 'smtp.example.com'),
    'got: ' . $short);

// ---------------------------------------------------------------------------
section('It does not wait forever');
// ---------------------------------------------------------------------------
// A black-holed address: the packets go nowhere and nothing refuses them, which
// is exactly the case that hangs a browser if no timeout is set.
$start = microtime(true);
$tester->run(deadEnd(['host' => '10.255.255.1', 'port' => '465']), 'someone@example.com');
$elapsed = microtime(true) - $start;

check('an address that never answers gives up inside the timeout',
    $elapsed < MailSettingTester::TIMEOUT + 8,
    sprintf('took %.1fs; a button that spins forever reads as a broken page', $elapsed));

// ---------------------------------------------------------------------------
section('The endpoint behind the button');
// ---------------------------------------------------------------------------
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no Super Admin to act as');
} else {
    Auth::guard('web')->login($admin);

    $post = function (array $fields) use ($kernel) {
        $request = Request::create('/admin/setting/mail-setting/test', 'POST',
            array_merge(['_token' => csrf_token()], $fields));
        $request->setLaravelSession(app('session.store'));
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');

        return $kernel->handle($request);
    };

    $response = $post(array_merge(deadEnd(), ['test_email' => 'someone@example.com']));
    $body = json_decode($response->getContent(), true);

    check('the route answers with JSON',
        $response->getStatusCode() === 200 && is_array($body),
        'status ' . $response->getStatusCode());

    check('and reports the connection failure it should',
        ($body['ok'] ?? null) === false && ($body['stage'] ?? null) === MailSettingTester::STAGE_CONNECTION,
        json_encode($body['stage'] ?? null));

    $response = $post(array_merge(deadEnd(), ['test_email' => 'not-an-email']));

    check('a bad recipient address is rejected as validation, not run as a test',
        $response->getStatusCode() === 422,
        'status ' . $response->getStatusCode() . ' — dialling the server to discover a typo wastes ten seconds');

    // The password field posts whatever is on screen; a blank must not be
    // mistaken for "the password is empty" and fail a working configuration.
    $withoutPassword = deadEnd();
    unset($withoutPassword['password']);
    $response = $post(array_merge($withoutPassword, ['password' => '', 'test_email' => 'someone@example.com']));
    $body = json_decode($response->getContent(), true);

    if (!$savedBefore || trim((string) $savedBefore->password) === '') {
        skip('no saved password to fall back to');
    } else {
        check('a blank password falls back to the saved one rather than failing on it',
            ($body['stage'] ?? null) !== MailSettingTester::STAGE_CONFIGURATION,
            'it reported missing settings instead: ' . ($body['detail'] ?? ''));
    }
}

// ---------------------------------------------------------------------------
section('The page offers the button');
// ---------------------------------------------------------------------------
if (!$admin) {
    skip('no Super Admin to render as');
} else {
    $request = Request::create('/admin/setting/mail-setting', 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('web')->login($admin);
    $page = $kernel->handle($request)->getContent();

    check('the test button is on the page', str_contains($page, 'mail-test-btn'));
    check('with somewhere to put the answer', str_contains($page, 'mail-test-result'));
    check('and a field for the recipient', str_contains($page, 'test_email'));
}

// ---------------------------------------------------------------------------
section('Nothing was changed by testing');
// ---------------------------------------------------------------------------
$savedAfter = MailSetting::where('status', '1')->first();

check('the saved mail settings are untouched',
    optional($savedBefore)->getAttributes() == optional($savedAfter)->getAttributes(),
    'a test that saves what it tried would overwrite a working configuration with a broken one');

check('.env is untouched',
    $envBefore === (is_file(base_path('.env')) ? md5_file(base_path('.env')) : null));

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
