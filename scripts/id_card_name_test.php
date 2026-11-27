<?php
/**
 * The ID-card list name-correction endpoint: it updates first/last name,
 * validates an empty first name, and leaves other students untouched.
 * Rolled back.
 *   php scripts/id_card_name_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Student;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
$post = function ($who, $uri, array $data) use ($kernel) {
    app()->forgetScopedInstances();
    Auth::guard('web')->logout();
    app('session.store')->flush(); app('session.store')->start();
    Auth::guard('web')->login($who);
    $data['_token'] = app('session.store')->token();
    $r = Illuminate\Http\Request::create($uri, 'POST', $data);
    $r->setLaravelSession(app('session.store'));
    $res = $kernel->handle($r);
    return [$res->getStatusCode(), json_decode((string) $res->getContent(), true)];
};

DB::beginTransaction();
try {
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
    $student = Student::orderByDesc('id')->first();
    $other = Student::where('id', '<>', $student->id)->orderByDesc('id')->first();
    $otherBefore = trim($other->first_name . ' ' . $other->last_name);
    $uri = '/admin/admission/id-card-update-name/' . $student->id;

    [$st, $json] = $post($admin, $uri, ['first_name' => 'Jeanette Marie', 'last_name' => 'Nkeng-Tabi']);
    check('a valid update returns 200 + success', $st === 200 && ($json['success'] ?? false), $st);
    $student->refresh();
    check('first name saved', $student->first_name === 'Jeanette Marie', $student->first_name);
    check('last name saved', $student->last_name === 'Nkeng-Tabi', $student->last_name);
    check('full_name returned joined + trimmed', ($json['full_name'] ?? '') === 'Jeanette Marie Nkeng-Tabi', $json['full_name'] ?? '');

    // Whitespace is trimmed.
    [$st, $json] = $post($admin, $uri, ['first_name' => '  Paul  ', 'last_name' => '  ']);
    $student->refresh();
    check('first name trimmed', $student->first_name === 'Paul', '['.$student->first_name.']');
    check('blank last name becomes empty string, not null', $student->last_name === '', var_export($student->last_name, true));
    check('full_name has no trailing space when last blank', ($json['full_name'] ?? 'x') === 'Paul', $json['full_name'] ?? '');

    // Empty first name is rejected (422), name unchanged.
    [$st, $json] = $post($admin, $uri, ['first_name' => '   ', 'last_name' => 'X']);
    check('empty first name rejected 422', $st === 422 && !($json['success'] ?? true), $st);
    $student->refresh();
    check('rejected update changed nothing', $student->first_name === 'Paul', $student->first_name);

    // No collateral damage.
    $other->refresh();
    check('a different student is untouched', trim($other->first_name . ' ' . $other->last_name) === $otherBefore, $otherBefore);
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
