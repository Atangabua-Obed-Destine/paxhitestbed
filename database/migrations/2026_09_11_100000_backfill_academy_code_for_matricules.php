<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keep issuing the matricules this school already issues.
 *
 * The prefix on every student matricule and staff id used to be the literal
 * 'PAX', written into three generators, so every school running this system
 * issued matricules under one school's name. It now comes from the Academy
 * Code on Settings → General, and is refused when that is not set.
 *
 * Which would change the prefix from under an installation that has been
 * running for years. So where the code is empty and the records on hand
 * already start with PAX, PAX is written into the setting: the next matricule
 * issued is exactly the one that would have been issued before, and the school
 * can change the code when it chooses.
 *
 * An installation with no records yet is left empty on purpose — it is a new
 * school, and it should set its own code rather than inherit this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')->first();

        if (!$setting) {
            return;
        }

        if (trim((string) ($setting->academy_code ?? '')) !== '') {
            return;
        }

        $usesPax = DB::table('students')->where('student_id', 'LIKE', 'PAX%')->exists()
            || DB::table('student_enrolls')->where('matricule', 'LIKE', 'PAX%')->exists()
            || DB::table('users')->where('staff_id', 'LIKE', 'PAX%')->exists();

        if ($usesPax) {
            DB::table('settings')->where('id', $setting->id)->update([
                'academy_code' => 'PAX',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Not reversed. Clearing the code would stop the school issuing
        // matricules at all, and the value describes the records it already has.
    }
};
