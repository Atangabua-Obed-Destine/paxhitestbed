<?php

use App\Models\AttendanceSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make the class hub's fee gate a decision rather than a fact.
 *
 * The hub sat behind the platform fee and the first instalment, so an unpaid
 * student could not open the live room at all. That stays the default - this
 * only gives the school somewhere to say otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('attendance_settings')->where('key', 'class_hub_requires_fees')->exists()) {
            return;
        }

        DB::table('attendance_settings')->insert([
            'key' => 'class_hub_requires_fees',
            'value' => '1',
            'type' => 'boolean',
            'category' => 'class_session',
            'description' => 'Require the platform fee and first instalment before a student '
                . 'can open the Class Hub. Attendance is never affected: the lecturer takes '
                . 'it from the student ID card whatever the balance.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('attendance_settings')->where('key', 'class_hub_requires_fees')->delete();
    }
};
