<?php
/**
 * Admission → Applicants: finding, correcting, getting back in, switching off.
 *
 * What this guards:
 *   - an admin-set password is the one the portal actually accepts (the portal
 *     signs in against `applicants`, not the leftover copy on `applications`);
 *   - a disabled account cannot sign in, and one already signed in is thrown
 *     out on its next click;
 *   - changing a password leaves an audit entry, and never a password in it;
 *   - the reset link the admin sends is the same one the portal sends;
 *   - none of it is reachable without its permission.
 *
 * Every write runs inside a transaction that is rolled back. Mail is faked.
 *
 * Usage: php scripts/applicant_admin_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Mail\ApplicantForgotPassword;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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

/**
 * A request through the whole stack, with a real CSRF token. Returns the
 * response and the validation errors it flashed, read at once and cleared —
 * flashed errors otherwise leak into the next request and a later check reads
 * an earlier failure.
 */
function http(string $method, string $uri, array $data = []): array
{
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $session = app('session.store');

    $session->forget('errors');
    $session->regenerateToken();

    if ($method !== 'GET') {
        $data['_token'] = $session->token();
    }

    $request = Illuminate\Http\Request::create($uri, $method, $data);
    $request->setLaravelSession($session);

    try {
        $response = $kernel->handle($request);
    } catch (Spatie\Permission\Exceptions\UnauthorizedException $e) {
        $response = new Illuminate\Http\Response('', 403);
    }

    $bag = $session->get('errors');
    $errors = $bag ? $bag->getBag('default')->getMessages() : [];
    $session->forget('errors');

    return [$response, $errors];
}

$admin = App\User::where('is_admin', 1)->orderBy('id')->firstOrFail();
Auth::guard('web')->login($admin);

Mail::fake();

$permissionNames = ['applicant-view', 'applicant-edit', 'applicant-password-change'];

echo "\n== The permissions exist, and follow the application permissions ==\n";

foreach ($permissionNames as $name) {
    check("{$name} exists", DB::table('permissions')->where('name', $name)->exists());
}

$holders = fn (string $permission) => DB::table('role_has_permissions as rp')
    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
    ->where('p.name', $permission)->pluck('rp.role_id')->sort()->values()->all();

$missing = array_diff($holders('application-edit'), $holders('applicant-password-change'));
check('every role that can edit applications can change applicant passwords', $missing === [],
    'roles without it: ' . implode(', ', $missing));

DB::beginTransaction();

try {
    $email = 'applicant-test-' . uniqid() . '@example.test';

    $applicant = Applicant::create([
        'first_name' => 'Test',
        'last_name' => 'Applicant',
        'email' => $email,
        'phone' => '670000000',
        'password' => Hash::make('OldPassword1'),
    ]);

    echo "\n== The screen ==\n";

    [$response] = http('GET', '/admin/admission/applicant?q=' . urlencode($email));
    $html = $response->getContent();

    check('the Applicants screen opens', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
    check('a search by email finds the account', str_contains($html, $email));
    check('the Admission menu links to it', str_contains($html, route('admin.applicant.index')));

    [$response] = http('GET', '/admin/admission/applicant?status=no_application&q=' . urlencode($email));
    check('an account with no application shows under "No application yet"', str_contains($response->getContent(), $email));

    // Bootstrap 5: a v4 attribute does not error, the button just does nothing.
    check('no Bootstrap 4 data-toggle on the page', !str_contains($html, 'data-toggle='));

    echo "\n== Editing account details ==\n";

    $taken = Applicant::where('id', '<>', $applicant->id)->value('email');

    [, $errors] = http('PUT', "/admin/admission/applicant/{$applicant->id}", ['email' => $taken, 'first_name' => 'Test']);
    check("an email another account already uses is refused", isset($errors['email']));
    check('and the account keeps its own', $applicant->fresh()->email === $email);

    $newEmail = 'renamed-' . uniqid() . '@example.test';
    http('PUT', "/admin/admission/applicant/{$applicant->id}", ['email' => $newEmail, 'first_name' => 'Renamed', 'last_name' => 'Person', 'phone' => '680000000']);

    $fresh = $applicant->fresh();
    check('name, email and phone are saved', $fresh->email === $newEmail && $fresh->first_name === 'Renamed' && $fresh->phone === '680000000');

    // The details form must not be a way to switch an account on or off.
    http('PUT', "/admin/admission/applicant/{$applicant->id}", ['email' => $newEmail, 'disabled_at' => now()->toDateTimeString()]);
    check('the details form cannot disable an account', $applicant->fresh()->disabled_at === null);

    // Applications carry a copy of the email; the ones that matched move with it.
    $withApps = Applicant::has('applications')->first();

    if ($withApps) {
        $old = $withApps->email;
        $matching = Application::where('applicant_id', $withApps->id)->where('email', $old)->count();
        $moved = 'moved-' . uniqid() . '@example.test';

        http('PUT', "/admin/admission/applicant/{$withApps->id}", ['email' => $moved, 'first_name' => $withApps->first_name]);

        check("the applications that used the old address now use the new one ({$matching})",
            Application::where('applicant_id', $withApps->id)->where('email', $moved)->count() === $matching);
        check('none are left on the old address', Application::where('applicant_id', $withApps->id)->where('email', $old)->count() === 0);
    }

    echo "\n== Changing the password ==\n";

    $token = $applicant->fresh()->getRememberToken();

    [, $errors] = http('POST', "/admin/admission/applicant/{$applicant->id}/password", ['password' => 'NewPassword2', 'password_confirmation' => 'Different3']);
    check('a mismatched confirmation is refused', isset($errors['password']));

    [, $errors] = http('POST', "/admin/admission/applicant/{$applicant->id}/password", ['password' => 'short', 'password_confirmation' => 'short']);
    check('a password under 8 characters is refused', isset($errors['password']));

    http('POST', "/admin/admission/applicant/{$applicant->id}/password", ['password' => 'NewPassword2', 'password_confirmation' => 'NewPassword2']);

    $credentials = fn (string $password) => ['email' => $newEmail, 'password' => $password];

    // The portal signs in through this guard, against the applicants table —
    // so this is the check that matters, not the hash itself.
    check('the portal accepts the new password', Auth::guard('applicant')->validate($credentials('NewPassword2')));
    check('and no longer accepts the old one', !Auth::guard('applicant')->validate($credentials('OldPassword1')));
    check('"remember me" on other devices is revoked', $applicant->fresh()->getRememberToken() !== $token);

    $log = DB::table('audit_logs')->where('auditable_type', Applicant::class)
        ->where('auditable_id', $applicant->id)->where('event', 'password_changed')->first();

    check('the change is in the audit trail', $log !== null);
    check('recorded against the admin who made it', $log && (int) $log->user_id === (int) $admin->id);

    $hash = $applicant->fresh()->password;
    check('and no audit entry for this account holds its password',
        !DB::table('audit_logs')->where('auditable_type', Applicant::class)->where('auditable_id', $applicant->id)
            ->where(fn ($q) => $q->where('old_values', 'like', '%' . $hash . '%')->orWhere('new_values', 'like', '%' . $hash . '%'))
            ->exists());

    echo "\n== Disabling and enabling ==\n";

    http('POST', "/admin/admission/applicant/{$applicant->id}/toggle", ['disabled_reason' => 'Duplicate account']);
    $fresh = $applicant->fresh();

    check('the account is disabled', $fresh->disabled_at !== null);
    check('with the reason and who did it', $fresh->disabled_reason === 'Duplicate account' && (int) $fresh->disabled_by === (int) $admin->id);

    [$response, $errors] = http('POST', '/application/login', $credentials('NewPassword2'));
    check('the portal refuses the right password for a disabled account', !Auth::guard('applicant')->check());
    check('and says why', str_contains(implode(' ', $errors['email'] ?? []), 'disabled'), json_encode($errors));

    // Someone already signed in when the account is disabled.
    Auth::guard('applicant')->login($fresh);
    [$response] = http('GET', '/application/dashboard');

    check('an applicant already signed in is sent to the login page', $response->isRedirect() && str_contains($response->headers->get('location'), 'application/login'),
        $response->getStatusCode() . ' ' . $response->headers->get('location'));
    check('and is signed out', !Auth::guard('applicant')->check());
    check('the administrator stays signed in', Auth::guard('web')->check());

    $resets = DB::table('password_resets')->where('email', $newEmail)->count();
    http('POST', "/admin/admission/applicant/{$applicant->id}/send-reset-link");
    check('no reset link is sent for a disabled account', DB::table('password_resets')->where('email', $newEmail)->count() === $resets);

    http('POST', "/admin/admission/applicant/{$applicant->id}/toggle");
    $fresh = $applicant->fresh();
    check('enabling clears the disabled state', $fresh->disabled_at === null && $fresh->disabled_reason === null);

    http('POST', '/application/login', $credentials('NewPassword2'));
    check('and the applicant can sign in again', Auth::guard('applicant')->check());
    Auth::guard('applicant')->logout();

    echo "\n== Emailing a reset link ==\n";

    $mailReady = (bool) MailSetting::where('status', '1')->whereNotNull('sender_email')->whereNotNull('sender_name')->first();

    http('POST', "/admin/admission/applicant/{$applicant->id}/send-reset-link");

    if ($mailReady) {
        check('the admin button emails the reset link', Mail::hasSent(ApplicantForgotPassword::class));
        check('and records a token for it', DB::table('password_resets')->where('email', $newEmail)->exists());

        DB::table('password_resets')->where('email', $newEmail)->delete();
        Auth::guard('web')->logout();
        http('POST', '/application/password/email', ['email' => $newEmail]);
        Auth::guard('web')->login($admin);

        check('the portal\'s own forgot-password form still sends one', DB::table('password_resets')->where('email', $newEmail)->exists());
    } else {
        echo "  SKIP  mail is not configured here, so no link can be sent\n";
        check('with no mail set up, nothing is recorded', !DB::table('password_resets')->where('email', $newEmail)->exists());
    }

    echo "\n== Signing in as an applicant ==\n";

    $impersonators = DB::table('role_has_permissions as rp')->join('roles as r', 'r.id', '=', 'rp.role_id')
        ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
        ->where('p.name', 'applicant-impersonate')->pluck('r.name')->all();

    check('only Super Admin may sign in as an applicant', $impersonators === ['Super Admin'], implode(', ', $impersonators));

    // Following a link must not be able to start it.
    [$response] = http('GET', "/admin/admission/applicant/{$applicant->id}/impersonate");
    check('it cannot be started by a plain link', in_array($response->getStatusCode(), [404, 405], true), 'status ' . $response->getStatusCode());
    check('and that did not sign anyone in', !Auth::guard('applicant')->check());

    http('POST', "/admin/admission/applicant/{$applicant->id}/impersonate");

    check('the administrator is signed in as the applicant', (int) Auth::guard('applicant')->id() === (int) $applicant->id);
    check('and is still signed in as themselves', (int) Auth::guard('web')->id() === (int) $admin->id);

    [$response] = http('GET', '/application/dashboard');
    check('the portal says the session is an impersonation', str_contains($response->getContent(), 'leave-impersonation'));

    check('it is recorded in the audit trail', DB::table('audit_logs')->where('auditable_type', Applicant::class)
        ->where('auditable_id', $applicant->id)->where('event', 'impersonated')->exists());

    [$response] = http('GET', '/application/leave-impersonation');
    check('leaving signs the applicant out', !Auth::guard('applicant')->check());
    check('and goes back to the Applicants screen', $response->isRedirect() && str_contains((string) $response->headers->get('location'), 'admission/applicant'));
    check('the administrator is untouched by leaving', (int) Auth::guard('web')->id() === (int) $admin->id);
    check('leaving is also in the audit trail', DB::table('audit_logs')->where('auditable_type', Applicant::class)
        ->where('auditable_id', $applicant->id)->where('event', 'impersonation_ended')->exists());

    // A disabled account cannot be entered.
    http('POST', "/admin/admission/applicant/{$applicant->id}/toggle", ['disabled_reason' => 'Checking impersonation']);
    http('POST', "/admin/admission/applicant/{$applicant->id}/impersonate");
    check('a disabled account cannot be signed in to', !Auth::guard('applicant')->check());
    http('POST', "/admin/admission/applicant/{$applicant->id}/toggle");

    echo "\n== Only the right people ==\n";

    $plain = App\User::where('is_admin', 0)->where('status', 1)->first();

    if (!$plain) {
        echo "  SKIP  no non-admin user to test with\n";
    } else {
        $plain->roles()->detach();
        $plain->permissions()->detach();
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Auth::guard('web')->login($plain);

        [$response] = http('GET', '/admin/admission/applicant');
        check('without applicant-view the screen is refused', $response->getStatusCode() === 403, 'status ' . $response->getStatusCode());

        [$response] = http('POST', "/admin/admission/applicant/{$applicant->id}/password", ['password' => 'Hijacked99', 'password_confirmation' => 'Hijacked99']);
        check('without the permission a password change is refused', $response->getStatusCode() === 403, 'status ' . $response->getStatusCode());
        check('and the password is unchanged', Auth::guard('applicant')->validate($credentials('NewPassword2')));

        [$response] = http('POST', "/admin/admission/applicant/{$applicant->id}/toggle");
        check('without the permission the account cannot be disabled', $response->getStatusCode() === 403 && $applicant->fresh()->disabled_at === null);

        [$response] = http('POST', "/admin/admission/applicant/{$applicant->id}/impersonate");
        check('without the permission nobody can sign in as an applicant',
            $response->getStatusCode() === 403 && !Auth::guard('applicant')->check(), 'status ' . $response->getStatusCode());

        Auth::guard('web')->login($admin);
    }
} finally {
    DB::rollBack();
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
