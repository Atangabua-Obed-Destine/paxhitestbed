<?php

namespace App\Services;

/**
 * Which seeders define permissions, and the order they run in.
 *
 * Found by name, never listed by hand. The hand-kept list this replaces, in
 * SyncAllPermissionsSeeder, had already fallen five seeders behind — Daybook,
 * Academic Health, Tax Remittance, Letterhead and EdutrustPay — which is how a
 * screen could be visible on the testbed and missing on production. A seeder
 * named *PermissionSeeder or *PermissionsSeeder is picked up the day it is
 * written; there is no list for anyone to forget to update.
 *
 * Only that naming is matched, deliberately. Seeders such as AdminSeeder,
 * SettingSeeder and FeesCategorySeeder DELETE their table before refilling it.
 * They must never run on a live school, and none of them is named like a
 * permission seeder. scripts/permissions_test.php fails if a seeder picked up
 * here ever contains a delete, so the naming rule cannot quietly stop being
 * enough.
 */
class PermissionSync
{
    /**
     * Always first. Every other seeder copies its role grants from permissions
     * this one creates, so they have to exist before anything looks for them.
     */
    public const FIRST = 'PermissionSeeder';

    /**
     * Always last, in this order. MissingPermissionsSeeder gives Super Admin
     * every permission that exists, so it has to run after everything that
     * creates one — including a permission added tomorrow. FixPermissionNamesSeeder
     * then settles display names and groups.
     */
    public const LAST = ['MissingPermissionsSeeder', 'FixPermissionNamesSeeder'];

    /** The old aggregator. Running it here would run everything twice. */
    public const EXCLUDED = ['SyncAllPermissionsSeeder'];

    /**
     * Every permission seeder, fully qualified, in the order it must run.
     *
     * The ones in between run alphabetically. That is enough today because the
     * only seeders that copy grants from another seeder's permissions copy from
     * the base seeder or from one earlier in the alphabet — Daybook from Budget,
     * Tax Remittance and EdutrustPay from Accounting. The test checks each
     * source exists by the time its seeder runs.
     *
     * @return array<int,string>
     */
    public static function seeders(?string $directory = null): array
    {
        $directory ??= database_path('seeders');

        $found = [];

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (preg_match('/Permissions?Seeder$/', $name) && !in_array($name, self::EXCLUDED, true)) {
                $found[] = $name;
            }
        }

        sort($found, SORT_STRING);

        $middle = array_values(array_diff($found, array_merge([self::FIRST], self::LAST)));

        $ordered = array_merge(
            in_array(self::FIRST, $found, true) ? [self::FIRST] : [],
            $middle,
            array_values(array_filter(self::LAST, fn ($name) => in_array($name, $found, true)))
        );

        return array_map(fn ($name) => 'Database\\Seeders\\' . $name, $ordered);
    }
}
