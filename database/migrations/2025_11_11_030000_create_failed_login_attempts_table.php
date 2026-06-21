<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->index();
            $table->string('ip_address', 45)->index();
            $table->string('user_agent')->nullable();
            $table->string('user_type')->nullable(); // admin, student, applicant
            $table->integer('attempts')->default(1);
            $table->timestamp('last_attempt_at');
            $table->timestamp('blocked_until')->nullable();
            $table->timestamps();
            
            $table->index(['email', 'ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('failed_login_attempts');
    }
};
