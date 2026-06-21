<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Allow credit_applications.fee_id to be NULL so the audit row of
 * "credit X was applied to fee Y" survives when fee Y is later
 * deleted via the Fee Assignments History page. The FK is recreated
 * with ON DELETE SET NULL so deleting the fee automatically clears
 * the orphan reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('credit_applications')) {
            return;
        }

        $this->dropFkIfExists('credit_applications', 'credit_applications_fee_id_foreign');

        DB::statement('ALTER TABLE `credit_applications` MODIFY `fee_id` BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE `credit_applications`
            ADD CONSTRAINT `credit_applications_fee_id_foreign`
            FOREIGN KEY (`fee_id`) REFERENCES `fees`(`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('credit_applications')) {
            return;
        }

        $this->dropFkIfExists('credit_applications', 'credit_applications_fee_id_foreign');

        DB::table('credit_applications')->whereNull('fee_id')->update(['fee_id' => 0]);

        DB::statement('ALTER TABLE `credit_applications` MODIFY `fee_id` BIGINT UNSIGNED NOT NULL');

        DB::statement('ALTER TABLE `credit_applications`
            ADD CONSTRAINT `credit_applications_fee_id_foreign`
            FOREIGN KEY (`fee_id`) REFERENCES `fees`(`id`)');
    }

    private function dropFkIfExists(string $table, string $constraint): void
    {
        $db = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$db, $table, $constraint]
        );
        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
