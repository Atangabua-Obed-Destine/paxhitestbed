<?php

namespace App\Console\Commands;

use App\Services\PermissionSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bring this database's permissions up to what the code defines.
 *
 * Runs every permission seeder — found by name, see PermissionSync — and
 * reports what it added. It runs by itself after every `php artisan migrate`
 * (see EventServiceProvider), so a deploy or a new school's install needs no
 * separate step. Running it by hand is always safe.
 *
 * It only ever ADDS. Every permission seeder creates what is missing and grants
 * it to roles that already hold a related permission; none removes a
 * permission or a grant. That is checked here, not assumed: the permissions and
 * every role's grants are compared before and after, and anything that went
 * missing fails the command loudly — because a sync that quietly took a
 * permission away from a role would lock somebody out of their work.
 */
class PermissionsSync extends Command
{
    protected $signature = 'permissions:sync
                            {--show-output : Also print each seeder\'s own output}';

    protected $description = 'Create any permission the code defines that this database lacks. Adds only; never removes.';

    public function handle(): int
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('role_has_permissions')) {
            $this->warn('The permission tables do not exist yet. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $before = $this->snapshot();
        $seeders = PermissionSync::seeders();
        $failed = [];

        foreach ($seeders as $class) {
            $arguments = ['--class' => $class, '--force' => true];

            try {
                $code = $this->option('show-output')
                    ? $this->call('db:seed', $arguments)
                    : $this->callSilently('db:seed', $arguments);
            } catch (\Throwable $e) {
                // One broken seeder must not stop the rest: the others still
                // carry permissions somebody needs today.
                $code = 1;
                $this->error('  ' . class_basename($class) . ': ' . $e->getMessage());
            }

            if ($code !== 0) {
                $failed[] = class_basename($class);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $after = $this->snapshot();

        $added = array_values(array_diff($after['permissions'], $before['permissions']));
        $removed = array_values(array_diff($before['permissions'], $after['permissions']));
        $newGrants = array_values(array_diff($after['grants'], $before['grants']));
        $lostGrants = array_values(array_diff($before['grants'], $after['grants']));

        $this->line(sprintf('Permissions: %d → %d  (%d seeders run)',
            count($before['permissions']), count($after['permissions']), count($seeders)));

        foreach ($added as $name) {
            $this->line('  + ' . $name);
        }

        $this->line(sprintf('Role grants: %d → %d', count($before['grants']), count($after['grants'])));

        foreach ($newGrants as $grant) {
            $this->line('  + ' . $grant);
        }

        if ($removed || $lostGrants) {
            $this->error('Something was REMOVED. A permission sync must only ever add — this is a bug in one of the seeders:');

            foreach ($removed as $name) {
                $this->error('  - permission ' . $name);
            }

            foreach ($lostGrants as $grant) {
                $this->error('  - grant ' . $grant);
            }

            return self::FAILURE;
        }

        if ($failed) {
            $this->error('These seeders failed: ' . implode(', ', $failed) . '. Run php artisan permissions:sync --show-output to see why.');

            return self::FAILURE;
        }

        $this->info(($added || $newGrants) ? 'Permissions are up to date.' : 'Nothing to add — permissions were already up to date.');

        return self::SUCCESS;
    }

    /**
     * Every permission name, and every grant as "Role → permission".
     *
     * @return array{permissions:array<int,string>, grants:array<int,string>}
     */
    private function snapshot(): array
    {
        return [
            'permissions' => DB::table('permissions')->orderBy('name')->pluck('name')->all(),
            'grants' => DB::table('role_has_permissions as rp')
                ->join('roles as r', 'r.id', '=', 'rp.role_id')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->orderBy('r.name')->orderBy('p.name')
                ->selectRaw("CONCAT(r.name, ' → ', p.name) as grant_label")
                ->pluck('grant_label')
                ->all(),
        ];
    }
}
