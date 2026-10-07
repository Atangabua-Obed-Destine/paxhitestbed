<?php

/**
 * Who may hand out the Admin role.
 *
 * Admin carries nearly everything Super Admin does, so being able to create one
 * is effectively being able to create a peer. It is therefore offered only to a
 * Super Admin — and, because a dropdown is a convenience rather than a lock,
 * refused on the way in as well. Most of this suite is that second half: the
 * screens are easy to get right and easy to walk around.
 *
 * Super Admin itself is assignable by nobody through this screen.
 *
 * Every write happens inside a transaction that is rolled back.
 *
 *   php scripts/role_assignment_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
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
    echo "\n" . $title . "\n";
}

$superAdmin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
$plainAdmin = User::whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
    ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$superAdmin) {
    echo "no Super Admin in this database; nothing to test\n";
    exit(0);
}

$adminRole = Role::where('name', 'Admin')->first();
$superRole = Role::where('name', 'Super Admin')->first();
$harmless = Role::whereNotIn('name', ['Super Admin', 'Admin'])->first();

if (!$adminRole || !$harmless) {
    echo "the Admin role or an ordinary role is missing; nothing to test\n";
    exit(0);
}

/**
 * Someone who can edit staff but is not a Super Admin.
 *
 * It has to be someone who genuinely holds the staff-edit permission: pick any
 * user with a role and the permission middleware turns them away, so every
 * check about what they can and cannot assign would pass without the rule
 * under test ever running.
 */
$editor = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
    ->whereHas('roles')->get()
    ->first(fn (User $user) => $user->can('user-edit'));

// Whether a real Admin existed before this suite made one.
$hadRealAdmin = $plainAdmin !== null;

/** The roles the controller would offer, acting as this person. */
function offeredTo(?User $actor): array
{
    if ($actor) {
        Auth::guard('web')->login($actor);
    } else {
        Auth::guard('web')->logout();
    }

    $controller = new \App\Http\Controllers\Admin\UserController();
    $method = new ReflectionMethod($controller, 'assignableRoles');
    $method->setAccessible(true);

    return $method->invoke($controller)->pluck('name')->all();
}

/** What the controller would refuse, acting as this person. */
function refusedFor(?User $actor, array $roleIds): array
{
    if ($actor) {
        Auth::guard('web')->login($actor);
    }

    $controller = new \App\Http\Controllers\Admin\UserController();
    $method = new ReflectionMethod($controller, 'refusedRoles');
    $method->setAccessible(true);

    return $method->invoke($controller, $roleIds);
}

// ---------------------------------------------------------------------------
section('What each person is offered');
// ---------------------------------------------------------------------------
$forSuper = offeredTo($superAdmin);

check('a Super Admin is offered the Admin role', in_array('Admin', $forSuper, true));
check('but never Super Admin itself', !in_array('Super Admin', $forSuper, true));
check('and still gets the ordinary roles', in_array($harmless->name, $forSuper, true));

if (!$editor) {
    skip('no non-Super-Admin user to act as');
} else {
    $forEditor = offeredTo($editor);

    check('anyone else is not offered Admin', !in_array('Admin', $forEditor, true),
        'offered to ' . $editor->staff_id);
    check('nor Super Admin', !in_array('Super Admin', $forEditor, true));
    check('but is offered the ordinary roles', in_array($harmless->name, $forEditor, true));
}

// ---------------------------------------------------------------------------
section('A dropdown is not a lock');
// ---------------------------------------------------------------------------
check('a Super Admin posting the Admin role is allowed',
    refusedFor($superAdmin, [$adminRole->id]) === []);
check('a Super Admin posting Super Admin is still refused',
    $superRole ? refusedFor($superAdmin, [$superRole->id]) === ['Super Admin'] : true);

if (!$editor) {
    skip('no non-Super-Admin user to act as');
} else {
    check('anyone else posting the Admin role by hand is refused',
        refusedFor($editor, [$adminRole->id]) === ['Admin']);
    check('and told which role it was',
        in_array('Admin', refusedFor($editor, [$adminRole->id, $harmless->id]), true));
    check('while an ordinary role goes through',
        refusedFor($editor, [$harmless->id]) === []);
}

// ---------------------------------------------------------------------------
section('Through the real screen');
// ---------------------------------------------------------------------------
$target = User::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Admin']))
    ->whereHas('roles')->first();

if (!$target || !$editor) {
    skip('no ordinary staff member to try this on');
} else {
    app('session.store')->start();
    $token = csrf_token();

    /** Post the edit form with the given roles, as the given person. */
    $save = function (User $actor, array $roleIds) use ($kernel, $target, $token) {
        $payload = [
            '_token' => $token,
            '_method' => 'PUT',
            'staff_id' => $target->staff_id,
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'gender' => $target->gender ?: 1,
            'dob' => $target->date_of_birth ?: '1990-01-01',
            'joining_date' => $target->joining_date ?: '2020-01-01',
            'phone' => $target->phone ?: '000',
            'basic_salary' => $target->basic_salary ?: 1,
            'contract_type' => $target->contract_type ?: 1,
            'salary_type' => $target->salary_type ?: 1,
            'department' => $target->department_id,
            'designation' => $target->designation_id,
            'roles' => $roleIds,
        ];

        $request = Request::create('/admin/staff/user/' . $target->id, 'POST', $payload);
        $request->setLaravelSession(app('session.store'));
        Auth::guard('web')->login($actor);

        return $kernel->handle($request);
    };

    // Someone who is not a Super Admin tries to make this person an Admin.
    DB::beginTransaction();

    try {
        $save($editor, [$harmless->id, $adminRole->id]);
        $after = $target->fresh()->roles->pluck('name')->all();

        check('a non-Super-Admin cannot make someone an Admin through the form',
            !in_array('Admin', $after, true), implode(', ', $after));
    } finally {
        DB::rollBack();
    }

    // A Super Admin does the same thing.
    DB::beginTransaction();

    try {
        $save($superAdmin, [$harmless->id, $adminRole->id]);
        $after = $target->fresh()->roles->pluck('name')->all();

        check('a Super Admin can', in_array('Admin', $after, true), implode(', ', $after));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Editing an Admin does not quietly demote them');
// ---------------------------------------------------------------------------
// This database has no plain Admin, so one is made for the duration of the
// transaction. The hazard being tested — a plain sync stripping a role the
// editor was never shown — only exists when somebody actually holds it.
DB::beginTransaction();

try {
    if (!$plainAdmin) {
        $candidate = User::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Admin']))
            ->whereHas('roles')
            ->where('id', '!=', optional($editor)->id)
            ->first();

        if ($candidate) {
            $candidate->roles()->attach($adminRole->id);
            $plainAdmin = $candidate->fresh();
        }
    }

    if (!$plainAdmin || !$editor || $editor->id === $plainAdmin->id) {
        skip('no Admin to edit, or nobody else to edit them');
    } else {
        app('session.store')->start();
        $token = csrf_token();

        // Someone who is not a Super Admin saves an Admin's record. The form
        // never offered the Admin role, so a plain sync would strip it.
        $payload = [
            '_token' => $token,
            '_method' => 'PUT',
            'staff_id' => $plainAdmin->staff_id,
            'first_name' => $plainAdmin->first_name,
            'last_name' => $plainAdmin->last_name,
            'email' => $plainAdmin->email,
            'gender' => $plainAdmin->gender ?: 1,
            'dob' => $plainAdmin->date_of_birth ?: '1990-01-01',
            'joining_date' => $plainAdmin->joining_date ?: '2020-01-01',
            'phone' => '123456789',
            'basic_salary' => $plainAdmin->basic_salary ?: 1,
            'contract_type' => $plainAdmin->contract_type ?: 1,
            'salary_type' => $plainAdmin->salary_type ?: 1,
            'department' => $plainAdmin->department_id,
            'designation' => $plainAdmin->designation_id,
            'roles' => [$harmless->id],
        ];

        $request = Request::create('/admin/staff/user/' . $plainAdmin->id, 'POST', $payload);
        $request->setLaravelSession(app('session.store'));
        Auth::guard('web')->login($editor);
        $kernel->handle($request);

        $after = $plainAdmin->fresh()->roles->pluck('name')->all();

        check('they keep the Admin role', in_array('Admin', $after, true), implode(', ', $after));
        check('and the role that was chosen is added', in_array($harmless->name, $after, true),
            implode(', ', $after));
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('the Super Admin still has their role',
    $superAdmin->fresh()->hasRole('Super Admin'));

if ($hadRealAdmin) {
    check('the Admin still has theirs', $plainAdmin->fresh()->hasRole('Admin'));
} else {
    check('the Admin this suite created is gone with its transaction',
        $plainAdmin === null || !$plainAdmin->fresh()->hasRole('Admin'));
}

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
