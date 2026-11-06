<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When students must have registered their courses by.
 *
 * Set per academic session and semester type, the same grain as resit request
 * windows: a First Semester deadline reaches every student sitting a first
 * semester that year, whichever year of study they are in, because class and
 * exam lists for a semester are drawn up at one time for all of them.
 *
 * Once the moment passes, a student can neither register nor drop on the
 * portal. The school's own Assign Subjects screen is untouched, so a late
 * admission or a genuine case is still fixed by the department.
 *
 * No row means no deadline, so nothing changes until the school sets one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_registration_deadlines', function (Blueprint $table) {
            $table->id();
            // Unsigned integer, not foreignId()'s bigint: it has to match
            // sessions.id exactly or the foreign key is refused (errno 150).
            $table->unsignedInteger('session_id');
            $table->unsignedTinyInteger('semester_type'); // 1 = First, 2 = Second
            $table->dateTime('closes_at');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('set_by')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'semester_type'], 'course_registration_deadlines_session_semester_unique');
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_registration_deadlines');
    }
};
