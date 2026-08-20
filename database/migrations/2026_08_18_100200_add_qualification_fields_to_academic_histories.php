<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns an academic-history row into a qualification card.
 *
 * qualification_key binds a row to the card an administrator configured; rows
 * left null are the extra qualifications an applicant adds themselves.
 *
 * Applicants know the year they sat an examination, not the day, so start_year
 * and end_year replace the date_from / date_to pickers. The date columns are
 * deliberately kept and backfilled from — not dropped — so this migration is
 * reversible and any consumer still reading them keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_academic_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('application_academic_histories', 'qualification_key')) {
                $table->string('qualification_key')->nullable()->after('application_id')->index();
            }
            if (!Schema::hasColumn('application_academic_histories', 'awarding_body')) {
                $table->string('awarding_body')->nullable()->after('institution_name');
            }
            if (!Schema::hasColumn('application_academic_histories', 'institution_same_as_awarding_body')) {
                $table->boolean('institution_same_as_awarding_body')->default(0)->after('awarding_body');
            }
            if (!Schema::hasColumn('application_academic_histories', 'start_year')) {
                $table->smallInteger('start_year')->unsigned()->nullable()->after('date_to');
            }
            if (!Schema::hasColumn('application_academic_histories', 'end_year')) {
                $table->smallInteger('end_year')->unsigned()->nullable()->after('start_year');
            }
        });

        // Carry the existing dates over so no applicant loses the years they
        // already entered when the form switches to year selects.
        DB::table('application_academic_histories')
            ->whereNull('start_year')->whereNotNull('date_from')
            ->update(['start_year' => DB::raw('YEAR(date_from)')]);

        DB::table('application_academic_histories')
            ->whereNull('end_year')->whereNotNull('date_to')
            ->update(['end_year' => DB::raw('YEAR(date_to)')]);
    }

    public function down(): void
    {
        Schema::table('application_academic_histories', function (Blueprint $table) {
            foreach (['qualification_key', 'awarding_body', 'institution_same_as_awarding_body', 'start_year', 'end_year'] as $column) {
                if (Schema::hasColumn('application_academic_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
