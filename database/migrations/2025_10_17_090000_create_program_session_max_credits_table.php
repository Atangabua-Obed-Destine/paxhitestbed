<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProgramSessionMaxCreditsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('program_session_max_credits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('faculty_id')->unsigned();
            $table->integer('program_id')->unsigned();
            $table->integer('session_id')->unsigned();
            $table->integer('max_credit_hours')->unsigned()->nullable();
            $table->timestamps();

            $table->unique(['faculty_id', 'program_id', 'session_id'], 'program_session_max_credits_unique');

            $table->foreign('faculty_id')->references('id')->on('faculties')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('program_session_max_credits');
    }
}
