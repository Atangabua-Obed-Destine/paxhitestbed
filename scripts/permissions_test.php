<?php
/**
 * Every permission the code uses exists — on this server, on production, and at
 * the next school.
 *
 * What went wrong before: permissions live in seeders, deploying never runs a
 * seeder, and the one list of "all permission seeders" was kept by hand and had
 * fallen five behind. So screens existed on the testbed and were invisible on
 * production, a fresh install got about 573 of 762 permissions, and one
 * permission — payment-gateway-manage — was checked by the code and created by
 * nothing at all.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/permissions_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PermissionSync;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

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

$root = dirname(__DIR__);
$seeders = PermissionSync::seeders();
$names = array_map('class_basename', $seeders);

echo "\n== Every permission seeder is found, without a list ==\n";

$onDisk = [];
foreach (glob($root . '/database/seeders/*.php') as $file) {
    $name = basename($file, '.php');
    if (preg_match('/Permissions?Seeder$/', $name) && $name !== 'SyncAllPermissionsSeeder') {
        $onDisk[] = $name;
    }
}

$missed = array_diff($onDisk, $names);
check('every *PermissionSeeder on disk is picked up (' . count($onDisk) . ')', $missed === [], 'missed: ' . implode(', ', $missed));
check('each one is a real class', collect($seeders)->every(fn ($c) => class_exists($c)));

foreach (['DaybookPermissionSeeder', 'AcademicHealthPermissionSeeder', 'TaxRemittancePermissionSeeder',
          'LetterheadPermissionSeeder', 'EdutrustPayPermissionSeeder', 'PaymentGatewayPermissionSeeder'] as $recent) {
    check("{$recent} is included — the old hand-kept list left it out", in_array($recent, $names, true));
}

check('the base PermissionSeeder runs first', ($names[0] ?? '') === 'PermissionSeeder', $names[0] ?? 'none');
check('MissingPermissionsSeeder then FixPermissionNamesSeeder run last',
    array_slice($names, -2) === ['MissingPermissionsSeeder', 'FixPermissionNamesSeeder'], implode(', ', array_slice($names, -2)));
check('the old aggregator is not run from inside itself', !in_array('SyncAllPermissionsSeeder', $names, true));

// The naming rule is the only thing keeping a table-wiping seeder out of a
// live deploy. These are the ones that would do the damage.
foreach (['AdminSeeder', 'SettingSeeder', 'FeesCategorySeeder', 'SessionSeeder', 'TaxSettingSeeder'] as $wiper) {
    check("{$wiper}, which empties its table first, is never picked up", !in_array($wiper, $names, true));
}

echo "\n== Nothing that runs on every deploy can remove anything ==\n";

$destructive = '/->delete\(|truncate|->detach\(|revokePermissionTo|removeRole|syncRoles|->forceDelete|DB::table\([^)]*\)->(delete|update)|syncPermissions\(/';
$offenders = [];

foreach ($seeders as $class) {
    $source = file_get_contents($root . '/database/seeders/' . class_basename($class) . '.php');

    // The one allowed case: giving Super Admin every permission that exists,
    // which only ever adds to it.
    $source = str_replace('$role->syncPermissions(Permission::all())', '', $source);

    if (preg_match($destructive, $source, $m)) {
        $offenders[] = class_basename($class) . ' (' . $m[0] . ')';
    }
}

check('no permission seeder deletes, detaches, revokes or re-syncs', $offenders === [], implode(', ', $offenders));

echo "\n== Grants are copied from permissions that already exist ==\n";

// A seeder that grants "to whoever holds X" needs X created before it runs.
$definedBefore = [];
$late = [];

foreach ($seeders as $class) {
    $source = file_get_contents($root . '/database/seeders/' . class_basename($class) . '.php');

    if (preg_match_all("/'mirrors' => '([a-z0-9.-]+)'/", $source, $m)) {
        foreach ($m[1] as $source_permission) {
            if (!isset($definedBefore[$source_permission])) {
                $late[] = class_basename($class) . ' copies from ' . $source_permission;
            }
        }
    }

    if (preg_match_all("/'([a-z0-9]+(?:[-.][a-z0-9]+)+)'/", $source, $m)) {
        foreach ($m[1] as $defined) {
            $definedBefore[$defined] = true;
        }
    }
}

check('every grant source is created by an earlier seeder', $late === [], implode('; ', $late));

echo "\n== The sync restores what is missing, and removes nothing ==\n";

$snapshot = fn () => [
    'permissions' => DB::table('permissions')->pluck('name')->all(),
    'grants' => DB::table('role_has_permissions as rp')->join('roles as r', 'r.id', '=', 'rp.role_id')
        ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
        ->selectRaw("CONCAT(r.name, ':', p.name) g")->pluck('g')->all(),
];

$original = $snapshot();

DB::beginTransaction();

try {
    // Take away what production was missing: a seeder-only permission and the
    // one nothing used to create.
    DB::table('permissions')->whereIn('name', ['daybook-view', 'payment-gateway-manage', 'tax-remittance-view'])->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $code = Artisan::call('permissions:sync');
    $output = Artisan::output();
    $after = $snapshot();

    check('permissions:sync succeeds', $code === 0, trim($output));

    foreach (['daybook-view', 'payment-gateway-manage', 'tax-remittance-view'] as $name) {
        check("{$name} is back", in_array($name, $after['permissions'], true));
    }

    $lostPermissions = array_diff($original['permissions'], $after['permissions']);
    check('no permission that existed before is missing after', $lostPermissions === [], implode(', ', $lostPermissions));

    // Grants for the three removed permissions are re-created by their seeders
    // (Daybook and Tax Remittance copy from related permissions), so every
    // grant that existed before must exist again.
    $lostGrants = array_diff($original['grants'], $after['grants']);
    check('no role lost a grant', $lostGrants === [], count($lostGrants) . ': ' . implode(', ', array_slice($lostGrants, 0, 5)));

    $holders = DB::table('role_has_permissions as rp')->join('roles as r', 'r.id', '=', 'rp.role_id')
        ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
        ->where('p.name', 'payment-gateway-manage')->pluck('r.name')->all();

    check('payment-gateway-manage is granted to Super Admin only', $holders === ['Super Admin'], implode(', ', $holders));

    $second = Artisan::call('permissions:sync');
    check('running it again changes nothing', $second === 0 && str_contains(Artisan::output(), 'Nothing to add'));
} finally {
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

echo "\n== It runs by itself after migrate ==\n";

/** Would a migrate that finished like this have restored the permission? */
function afterMigrate(string $command, int $exitCode, bool $pretend = false): bool
{
    DB::beginTransaction();

    try {
        DB::table('permissions')->where('name', 'payment-gateway-manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $input = new ArrayInput(
            $pretend ? ['--pretend' => true] : [],
            new InputDefinition([new InputOption('pretend', null, InputOption::VALUE_NONE)])
        );

        event(new CommandFinished($command, $input, new BufferedOutput(), $exitCode));

        return DB::table('permissions')->where('name', 'payment-gateway-manage')->exists();
    } finally {
        DB::rollBack();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

check('after a successful migrate, permissions are synced', afterMigrate('migrate', 0));
check('after migrate:fresh too', afterMigrate('migrate:fresh', 0));
check('not after a migrate that failed', !afterMigrate('migrate', 1));
check('not after migrate --pretend', !afterMigrate('migrate', 0, true));
check('not after an unrelated command', !afterMigrate('migrate:rollback', 0));

config(['permission.sync_on_migrate' => false]);
check('not when PERMISSIONS_SYNC_ON_MIGRATE is false', !afterMigrate('migrate', 0));
config(['permission.sync_on_migrate' => true]);

echo "\n== Every permission the code checks exists after a sync ==\n";

$referenced = [];

$files = array_merge(
    glob($root . '/routes/*.php'),
    iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app')), '/\.php$/'), false),
    iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views')), '/\.blade\.php$/'), false)
);

foreach ($files as $file) {
    $path = is_string($file) ? $file : $file->getPathname();
    $source = file_get_contents($path);

    // Route and controller middleware: 'permission:a|b,c'.
    if (preg_match_all("/permission:([a-z0-9|,.-]+)/", $source, $m)) {
        foreach ($m[1] as $list) {
            foreach (preg_split('/[|,]/', $list) as $name) {
                $referenced[$name][] = $path;
            }
        }
    }

    // A controller whose whole permission is its $access, used as-is.
    if (preg_match("/\\\$this->access\s*=\s*'([a-z0-9.-]+)'/", $source, $access)
        && preg_match("/'permission:'\s*\.\s*\\\$this->access\s*\)/", $source)) {
        $referenced[$access[1]][] = $path;
    }

    // Views: @can('x') and @canany(['x', 'y']).
    if (preg_match_all("/@can(?:any)?\(\s*(\[[^\]]*\]|'[^']*')/", $source, $m)) {
        foreach ($m[1] as $argument) {
            preg_match_all("/'([^']+)'/", $argument, $inner);
            foreach ($inner[1] as $name) {
                $referenced[$name][] = $path;
            }
        }
    }
}

// A name ending in "-" is a prefix that code completes at runtime, not a
// permission; the literal names it builds are checked where they are written.
$referenced = array_filter($referenced, fn ($paths, $name) => $name !== '' && !str_ends_with($name, '-'), ARRAY_FILTER_USE_BOTH);

DB::beginTransaction();

try {
    Artisan::call('permissions:sync');
    $existing = DB::table('permissions')->pluck('name')->flip();
} finally {
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

$undefined = [];
foreach ($referenced as $name => $paths) {
    if (!isset($existing[$name])) {
        $undefined[] = $name . '  (' . str_replace($root . DIRECTORY_SEPARATOR, '', str_replace('/', DIRECTORY_SEPARATOR, $paths[0])) . ')';
    }
}

check('checked ' . count($referenced) . ' permission names used in routes, controllers and views', count($referenced) > 100);
check('every one of them exists once synced', $undefined === [], implode("\n          ", $undefined));

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
