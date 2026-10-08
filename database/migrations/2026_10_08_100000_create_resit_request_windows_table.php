<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the school will still take resit requests.
 *
 * Resits are requested per semester: a student who failed a First Semester
 * course asks for a resit of that semester's exams. Once the school has drawn
 * up the resit timetable it cannot keep accepting new requests for that sitting,
 * but students go on asking because nothing on their portal says otherwise —
 * and every late request is a conversation someone has to have.
 *
 * So the window is closed per academic session and semester type, which is the
 * grain a resit sitting actually has. Closing one stops new requests; declining
 * stays open, because a student still has to be able to settle the course by
 * carrying it over, and blocking that would leave them stuck with no move at
 * all.
 *
 * No row means open. Nothing changes until a school closes a window on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resit_request_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester_type'); // 1 = First, 2 = Second
            $table->boolean('is_open')->default(true);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'semester_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resit_request_windows');
    }
};
