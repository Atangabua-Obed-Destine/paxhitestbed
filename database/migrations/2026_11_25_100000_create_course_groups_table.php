<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course groups: courses that are one course taught to several programmes
 * under each programme's own code (AGE118H and MGT1101H, "Principles of
 * Management"). Each course keeps its own code, credits, marks and
 * transcript; the group is what joint classes, class sessions, exam
 * timetables and the shared mark sheet compare. A course is in at most one
 * group, and no programme may take two courses of one group.
 */
class CreateCourseGroupsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('course_groups')) {
            Schema::create('course_groups', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->text('note')->nullable();
                $table->boolean('status')->default(1);
                $table->bigInteger('created_by')->unsigned()->nullable();
                $table->bigInteger('updated_by')->unsigned()->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('subjects', 'course_group_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->integer('course_group_id')->unsigned()->nullable()->after('code')->index();
                $table->foreign('course_group_id')->references('id')->on('course_groups')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('subjects', 'course_group_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropForeign(['course_group_id']);
                $table->dropColumn('course_group_id');
            });
        }
        Schema::dropIfExists('course_groups');
    }
}
