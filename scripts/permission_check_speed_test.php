<?php
/**
 * The fast permission check in App\User::hasPermissionTo() must give exactly
 * the package's answer, for every permission, for users of every role - and
 * must stay correct when roles or permissions change during the request.
 * Read-only: changes are made inside a transaction that is rolled back.
 *
 * Usage: php scripts/permission_check_speed_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$pass = 0;
$fail = 0;
function check(bool $ok, string $what)
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    if (!$ok) {
        echo "  FAIL  $what\n";
    }
}

$spatie = function (App\User $u, string $name): bool {
    $m = new ReflectionMethod($u, 'spatieHasPermissionTo');
    $m->setAccessible(true);
    return $m->invoke($u, $name);
};

$names = Permission::where('guard_name', 'web')->pluck('name')->all();
echo count($names), " permissions\n";

// 1. Same answer as the package, for up to 3 users of every role and a user with no role
$users = collect();
foreach (Role::all() as $role) {
    $users = $users->merge(App\User::whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))->limit(3)->get());
}
$users = $users->merge(App\User::whereDoesntHave('roles')->limit(2)->get())->unique('id');
$mismatch = 0;
foreach ($users as $u) {
    $fresh = App\User::find($u->id);
    foreach ($names as $n) {
        if ($fresh->hasPermissionTo($n) !== $spatie(App\User::find($u->id), $n)) {
            $mismatch++;
            if ($mismatch < 5) {
                echo "    user {$u->id} '$n' differs\n";
            }
        }
    }
}
check($mismatch === 0, "identical answers for {$users->count()} users x " . count($names) . " permissions ($mismatch differ)");

// 2. Through the gate (@can / $user->can), same answers
$u = $users->first(fn ($u) => !$u->hasRole('Super Admin') && $u->is_admin != 1) ?? $users->first();
$u = App\User::find($u->id);
$gateDiff = 0;
foreach ($names as $n) {
    if ($u->can($n) !== ($spatie(App\User::find($u->id), $n) || App\User::find($u->id)->is_admin == 1)) {
        $gateDiff++;
    }
}
check($gateDiff === 0, "gate answers match for user {$u->id} ($gateDiff differ)");

// 3. An unknown permission name still throws, as before
try {
    App\User::find($u->id)->hasPermissionTo('no-such-permission-' . uniqid());
    check(false, 'unknown permission throws PermissionDoesNotExist');
} catch (Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
    check(true, 'unknown permission throws PermissionDoesNotExist');
}
check(App\User::find($u->id)->can('no-such-permission-' . uniqid()) === ($u->is_admin == 1), 'unknown permission through the gate is refused (not an error)');

// 4. Changes during the request are seen at once
DB::beginTransaction();
try {
    $user = App\User::find($u->id);
    $missing = collect($names)->first(fn ($n) => !$user->hasPermissionTo($n));
    if ($missing) {
        $user->givePermissionTo($missing);
        check($user->hasPermissionTo($missing) === true, "a permission given during the request counts at once ($missing)");
        $user->revokePermissionTo($missing);
        check($user->hasPermissionTo($missing) === false, 'and stops counting once revoked');

        $role = Role::create(['name' => 'zz-speed-test-' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($missing);
        $user->assignRole($role);
        check($user->hasPermissionTo($missing) === true, 'a role assigned during the request counts at once');
        $user->removeRole($role);
        check($user->hasPermissionTo($missing) === false, 'and stops counting once removed');
    } else {
        check(true, 'user already holds every permission; change tests skipped');
    }
} finally {
    DB::rollBack();
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

// 5. Speed: 320 distinct checks, as the sidebar makes
$user = App\User::find($u->id);
$sample = array_slice($names, 0, 320);
$user->can($sample[0]);
$t = microtime(true);
foreach ($sample as $n) {
    $user->can($n);
}
$ms = (microtime(true) - $t) * 1000;
echo sprintf("  320 checks: %.1f ms\n", $ms);
check($ms < 150, 'a page\'s worth of permission checks takes under 150 ms');

echo "passed $pass, failed $fail\n";
exit($fail ? 1 : 0);
