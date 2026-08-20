<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an allocation belong to a budget line instead of an expense category.
 *
 * expense_category_id was NOT NULL, so an income line could not be stored at
 * all — the insert failed rather than the validation. Departmental budgets
 * still populate it; institutional sheet lines leave it null and use
 * budget_line_id.
 *
 * Raw SQL rather than a Blueprint change so this does not require doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('budget_allocations')) {
            return;
        }

        DB::statement('ALTER TABLE `budget_allocations` MODIFY `expense_category_id` INT UNSIGNED NULL');

        // title is set from the line name, but an allocation created by an
        // older code path may not supply one.
        DB::statement('ALTER TABLE `budget_allocations` MODIFY `title` VARCHAR(191) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('budget_allocations')) {
            return;
        }

        // Only reversible while no institutional rows exist, since those have
        // no expense category to restore.
        if (DB::table('budget_allocations')->whereNull('expense_category_id')->exists()) {
            throw new \RuntimeException(
                'Cannot revert: allocations exist that belong to a budget line rather than an expense category.'
            );
        }

        DB::statement('ALTER TABLE `budget_allocations` MODIFY `expense_category_id` INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `budget_allocations` MODIFY `title` VARCHAR(191) NOT NULL');
    }
};
