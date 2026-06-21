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
        Schema::create('platform_fee_exemptions', function (Blueprint $table) {
            $table->id();
            $table->enum('exemption_type', ['student', 'session'])->comment('student = specific student exempt, session = entire session exempt');
            $table->unsignedBigInteger('student_enroll_id')->nullable()->comment('If exemption_type=student');
            $table->unsignedInteger('session_id')->nullable()->comment('If exemption_type=session');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->boolean('status')->default(1);
            $table->timestamps();

            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_fee_exemptions');
    }
};
