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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('status');
            $table->text('block_reason')->nullable()->after('blocked_at');
            $table->unsignedBigInteger('blocked_by')->nullable()->after('block_reason');
            $table->integer('failed_login_attempts')->default(0)->after('blocked_by');
            
            $table->foreign('blocked_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('status');
            $table->text('block_reason')->nullable()->after('blocked_at');
            $table->unsignedBigInteger('blocked_by')->nullable()->after('block_reason');
            $table->integer('failed_login_attempts')->default(0)->after('blocked_by');
            
            $table->foreign('blocked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['blocked_by']);
            $table->dropColumn(['blocked_at', 'block_reason', 'blocked_by', 'failed_login_attempts']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['blocked_by']);
            $table->dropColumn(['blocked_at', 'block_reason', 'blocked_by', 'failed_login_attempts']);
        });
    }
};
