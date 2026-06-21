<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class AddLockingToExamsTable extends Migration
{
    public function up()
    {
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'attendance_locked')) {
                $table->boolean('attendance_locked')->default(0)->after('attendance');
            }
            if (!Schema::hasColumn('exams', 'marks_locked')) {
                $table->boolean('marks_locked')->default(0)->after('achieve_marks');
            }
        });

        // Add permission
        try {
            if (!Permission::where('name', 'subject-marking-unlock')->exists()) {
                Permission::create([
                    'name' => 'subject-marking-unlock',
                    'group' => 'Course Final',
                    'title' => 'Unlock Exam/Attendance'
                ]);
            }
        } catch (\Exception $e) {
            // Permission might already exist or table issue, ignore
        }
    }

    public function down()
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['attendance_locked', 'marks_locked']);
        });
    }
}
