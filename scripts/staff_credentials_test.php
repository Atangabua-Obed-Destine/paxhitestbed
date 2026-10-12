<?php

/**
 * Sending a new member of staff their login details.
 *
 * Creating the account and sending the credentials used to be two errands on
 * two screens, and the second was easy to forget — so staff were created and
 * then waited for a login nobody had sent. The create form now does both.
 *
 * The rule that matters most is the one that is least obvious: **the account
 * must survive a mail failure**. A school whose SMTP is down must still end up
 * with the staff member created, told plainly that the e-mail did not go, and
 * able to send it again later. Rolling the account back, or reporting it as
 * failed when it saved, would be far worse than a missing e-mail.
 *
 * No real mail is sent: the mailer is faked, except where a case is explicitly
 * about a mail server that refuses.
 *
 * Every write happens inside a transaction that is rolled back.
 *
 *   php scripts/staff_credentials_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendPassword;
use App\Models\Department;
use App\Models\Designation;
use App\Models\MailSetting;
use App\Services\StaffCredentialsMailer;
use App\User;
use Spatie\Permission\Models\Role;

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
$department = Department::first();
$designation = Designation::first();
$role = Role::where('name', '!=', 'Super Admin')->where('name', '!=', 'Admin')->first();

if (!$admin || !$department || !$designation || !$role) {
    echo "need an admin, a department, a designation and an assignable role to create staff\n";
    exit(0);
}

Auth::guard('web')->login($admin);

$seq = 0;

/** Post the create form the way the screen does. */
function createStaff(array $override = [])
{
    global $kernel, $department, $designation, $role, $seq;

    $seq++;
    $stamp = time() . $seq;

    $payload = array_merge([
        '_token' => csrf_token(),
        'first_name' => 'Credentials',
        'last_name' => 'Probe' . $seq,
        'email' => 'credentials.probe' . $stamp . '@example.test',
        'department' => $department->id,
        'designation' => $designation->id,
        'gender' => 1,
        'dob' => '1990-01-01',
        'phone' => '600000000',
        'basic_salary' => 100000,
        'contract_type' => 'permanent',
        'salary_type' => 'monthly',
        'roles' => [$role->id],
        'send_credentials' => '1',
    ], $override);

    $request = Request::create('/admin/staff/user', 'POST', $payload);
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
}

$mailSetting = MailSetting::where('status', '1')->first();

// ---------------------------------------------------------------------------
section('Creating a staff account sends the login details');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    Mail::fake();

    $before = User::count();
    createStaff(['email' => 'auto.send.probe@example.test']);
    $created = User::where('email', 'auto.send.probe@example.test')->first();

    check('the staff account is created', $created !== null && User::count() === $before + 1);

    if (!$created) {
        skip('nothing created, so there is nothing to have emailed');
    } else {
        Mail::assertSent(SendPassword::class, function ($mail) use ($created) {
            return $mail->hasTo($created->email);
        });
        check('the login details are emailed without a second trip to the staff list', true);

        // The point of the e-mail is the password. A message that went out
        // without one would still satisfy "a mail was sent".
        $sent = collect(Mail::sent(SendPassword::class))->first();
        $plain = Crypt::decryptString($created->password_text);

        check('the email carries the password that was actually issued',
            $sent && ($sent->data['password'] ?? null) === $plain
                && password_verify($plain, $created->password),
            'an email with the wrong password is worse than none');

        check('and the staff id and address they sign in with',
            $sent && ($sent->data['staff_id'] ?? null) === $created->staff_id
                && ($sent->data['email'] ?? null) === $created->email);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Unticking the box is respected');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    Mail::fake();

    // An unticked checkbox posts the hidden "0", which is what the browser
    // really sends — not an absent key.
    createStaff(['email' => 'no.send.probe@example.test', 'send_credentials' => '0']);

    check('the account is still created',
        User::where('email', 'no.send.probe@example.test')->exists());

    Mail::assertNothingSent();
    check('and nothing is emailed', true);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A mail failure does not cost the school the account');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // Not a fake: a real send against a host that cannot answer, which is what
    // a school with a wrong SMTP host actually experiences.
    if (!$mailSetting) {
        skip('no mail settings row to point at a dead host');
    } else {
        config()->set('mail.driver', 'smtp');
        config()->set('mail.host', 'smtp.this-host-cannot-exist-pax.invalid');
        config()->set('mail.port', 465);
        config()->set('mail.username', 'u@example.test');
        config()->set('mail.password', 'irrelevant');
        config()->set('mail.encryption', 'ssl');
        app()->forgetInstance('mailer');
        app()->forgetInstance('mail.manager');

        $before = User::count();
        $response = createStaff(['email' => 'mail.down.probe@example.test']);
        $created = User::where('email', 'mail.down.probe@example.test')->first();

        check('the staff account exists even though the mail server does not',
            $created !== null && User::count() === $before + 1,
            'losing the account because an email failed is the worst outcome of all');

        // Asserted against the index route itself. "Not the create page" also
        // matched the redirect-back that a failed save produces, so it passed
        // while creation was in fact broken.
        check('the administrator is sent on to the staff list',
            $response->isRedirect()
                && str_contains((string) $response->headers->get('Location'), 'admin/staff/user'),
            'bounced to: ' . $response->headers->get('Location'));

        check('the role was still attached',
            $created && $created->roles()->count() > 0,
            'the transaction must have committed, not rolled back');
    }
} finally {
    app()->forgetInstance('mailer');
    app()->forgetInstance('mail.manager');
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The mailer reports why, rather than throwing');
// ---------------------------------------------------------------------------
$mailer = app(StaffCredentialsMailer::class);

DB::beginTransaction();

try {
    Mail::fake();

    $user = User::whereNotNull('password_text')->where('password_text', '!=', '')->first();

    if (!$user) {
        skip('no staff account with a recoverable password');
    } else {
        $outcome = $mailer->send($user);
        check('a healthy account sends', $outcome['sent'] === true, $outcome['reason'] ?? '');

        // Each refusal must name its own cause: a single "could not send" for
        // every case would tell the administrator nothing about what to fix.
        $noEmail = clone $user;
        $noEmail->email = '';
        $outcome = $mailer->send($noEmail);
        check('no address is reported as a missing address',
            $outcome['sent'] === false && stripos($outcome['reason'], 'email address') !== false,
            $outcome['reason'] ?? 'no reason given');

        $noPassword = clone $user;
        $noPassword->password_text = null;
        $outcome = $mailer->send($noPassword);
        check('an account with no recoverable password says so',
            $outcome['sent'] === false && stripos($outcome['reason'], 'password') !== false,
            $outcome['reason'] ?? 'no reason given');

        // A changed APP_KEY makes stored passwords undecryptable. That must be
        // a reported reason, not an exception on a staff screen.
        $corrupt = clone $user;
        $corrupt->password_text = 'not-a-valid-encrypted-value';
        $outcome = $mailer->send($corrupt);
        check('an undecryptable password is handled, not thrown',
            $outcome['sent'] === false && $outcome['reason'] !== null,
            'reason: ' . var_export($outcome['reason'] ?? null, true));
    }
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    Mail::fake();

    $user = User::whereNotNull('password_text')->where('password_text', '!=', '')->first();

    if (!$user) {
        skip('no staff account to test unconfigured mail with');
    } else {
        MailSetting::where('status', '1')->update(['sender_email' => '']);

        $outcome = app(StaffCredentialsMailer::class)->send($user->fresh());

        check('unconfigured mail settings are reported, not silently skipped',
            $outcome['sent'] === false && stripos($outcome['reason'], 'mail settings') !== false,
            $outcome['reason'] ?? 'no reason given');

        Mail::assertNothingSent();
        check('and nothing is attempted', true);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The manual button on the staff list still works');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    Mail::fake();

    $user = User::whereNotNull('password_text')->where('password_text', '!=', '')
        ->where('id', '!=', $admin->id)->first();

    if (!$user) {
        skip('no staff account to resend for');
    } else {
        $request = Request::create('/admin/staff/user-send-password/' . $user->id, 'POST', ['_token' => csrf_token()]);
        $request->setLaravelSession(app('session.store'));
        Auth::guard('web')->login($admin);
        $response = $kernel->handle($request);

        check('the resend route answers', $response->isRedirect() || $response->getStatusCode() === 200,
            'status ' . $response->getStatusCode());

        Mail::assertSent(SendPassword::class);
        check('and it sends through the same path as the create screen', true);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The form offers the choice');
// ---------------------------------------------------------------------------
$request = Request::create('/admin/staff/user/create', 'GET');
$request->setLaravelSession(app('session.store'));
Auth::guard('web')->login($admin);
$page = $kernel->handle($request)->getContent();

check('the create form has the send-credentials checkbox',
    str_contains($page, 'name="send_credentials"'));

check('it is ticked by default',
    (bool) preg_match('/id="send_credentials"[^>]*checked|checked[^>]*id="send_credentials"/', $page),
    'the whole point is that it happens without being remembered');

check('and a hidden companion so unticking it means something',
    (bool) preg_match('/type="hidden"\s+name="send_credentials"\s+value="0"/', $page),
    'without it an unticked box posts nothing and the server cannot tell');

// ---------------------------------------------------------------------------
section('The staff ID it generates can actually be used');
// ---------------------------------------------------------------------------
// This is what broke creation: ids are a mix of widths (PAX0027, PAX00028,
// PAX000213), they were ordered as text, and the generator kept proposing one
// that was already taken — so every create died on the unique index.
$generated = User::generateStaffId();

check('the generated staff ID is not already in use',
    !User::where('staff_id', $generated)->exists(),
    $generated . ' already belongs to someone');

check('and it is past every number already issued',
    (function () use ($generated) {
        $prefix = \App\Models\Setting::matriculePrefix();
        $highest = User::where('staff_id', 'LIKE', $prefix . '%')
            ->get()
            ->map(fn ($u) => (int) substr($u->staff_id, strlen($prefix)))
            ->max();

        return (int) substr($generated, strlen($prefix)) > $highest;
    })(),
    'ordering ids as text is what made it reuse one');

DB::beginTransaction();

try {
    // Generated, taken, then generated again: the second must differ, or two
    // administrators creating staff in the same minute collide.
    $first = User::generateStaffId();
    createStaff(['email' => 'staffid.probe@example.test']);
    $second = User::generateStaffId();

    check('once taken, the next one generated is different',
        $first !== $second,
        'both were ' . $first);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('no probe accounts remain',
    User::where('email', 'like', '%probe%@example.test')->count() === 0,
    User::where('email', 'like', '%probe%@example.test')->count() . ' left');

check('the mail settings are as they were',
    optional(MailSetting::where('status', '1')->first())->sender_email === optional($mailSetting)->sender_email);

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
