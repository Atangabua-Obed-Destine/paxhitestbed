<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let the school hold students back from a year that is not ready for them.
 *
 * A student whose marks are published is offered progression into the next
 * academic year whether or not that year has any courses in it. For a school
 * still setting up, that means being invited to move into an empty semester —
 * and the portal reading as though the programme were finished.
 *
 * Modelled on sessions.applications_open, which already closes admissions for an
 * intake: the same idea, applied to the year a student would be entering.
 *
 * The default is deliberately split:
 *
 *   - sessions that already exist stay OPEN, so running this migration changes
 *     nothing for anybody until a year is closed on purpose;
 *   - the column defaults to CLOSED, so a year created from now on starts
 *     closed — a year that has just been created is precisely one whose courses
 *     do not exist yet, which is the situation this exists for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->boolean('progression_open')->default(0)->after('applications_open');
            $table->string('progression_note')->nullable()->after('progression_open');
        });

        // Everything that exists today keeps behaving as it did.
        DB::table('sessions')->update(['progression_open' => 1]);
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['progression_open', 'progression_note']);
        });
    }
};
