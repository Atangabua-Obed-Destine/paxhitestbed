<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_departments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('faculty_id')->unsigned();
            $table->string('title')->unique();
            $table->string('shortcode')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->bigInteger('head_of_department_id')->unsigned()->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default('1');
            $table->timestamps();

            // Foreign keys
            $table->foreign('faculty_id')
                  ->references('id')->on('faculties')
                  ->onDelete('cascade');
            
            $table->foreign('head_of_department_id')
                  ->references('id')->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_departments');
    }
};
