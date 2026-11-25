<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School-wide blocks of the week that are not classes — the midday break and
 * Holy Mass, Friday's compulsory club activities — so every timetable (the
 * admin grid, the printed sheet, the student's own week) shows them where
 * they fall. days: the day numbers it applies to (1 = Saturday … 7 = Friday,
 * as class_routines.day); empty means every day.
 */
class CreateTimetableBreaksTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('timetable_breaks')) {
            Schema::create('timetable_breaks', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 191);
                $table->json('days')->nullable();
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('status')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('timetable_breaks');
    }
}
