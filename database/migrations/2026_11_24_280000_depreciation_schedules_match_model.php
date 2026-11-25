<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * depreciation_schedules was created with one set of columns and the model and
 * DepreciationService were written against another (status, *_before /
 * *_after, created_by), so posting depreciation failed on "Unknown column
 * 'status'". Add the model's columns; keep the original ones, made optional,
 * and the model keeps them in step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depreciation_schedules', function (Blueprint $t) {
            if (!Schema::hasColumn('depreciation_schedules', 'status')) {
                $t->string('status', 20)->default('pending')->after('journal_entry_id')->index();
            }
            foreach (['accumulated_depreciation_before', 'accumulated_depreciation_after', 'book_value_before', 'book_value_after'] as $c) {
                if (!Schema::hasColumn('depreciation_schedules', $c)) {
                    $t->decimal($c, 15, 2)->nullable()->after('depreciation_amount');
                }
            }
            foreach (['created_by', 'updated_by'] as $c) {
                if (!Schema::hasColumn('depreciation_schedules', $c)) {
                    $t->unsignedBigInteger($c)->nullable();
                }
            }
        });

        DB::statement('ALTER TABLE depreciation_schedules
            MODIFY period_number INT NULL,
            MODIFY opening_book_value DECIMAL(15,2) NOT NULL DEFAULT 0,
            MODIFY accumulated_depreciation DECIMAL(15,2) NOT NULL DEFAULT 0,
            MODIFY closing_book_value DECIMAL(15,2) NOT NULL DEFAULT 0');

        DB::table('depreciation_schedules')->where('is_posted', 1)->update(['status' => 'posted']);
    }

    public function down(): void
    {
        Schema::table('depreciation_schedules', function (Blueprint $t) {
            $t->dropColumn(['status', 'accumulated_depreciation_before', 'accumulated_depreciation_after',
                'book_value_before', 'book_value_after', 'created_by', 'updated_by']);
        });
    }
};
