<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an explicit rejection trail to student_credits so a rejected
     * refund request is distinguishable from a request that was never made.
     * Without these columns, the previous reject() implementation cleared
     * refund_requested_* and dropped the reason into refund_note, which
     * made the rejection invisible in audit and lost on the next request.
     */
    public function up(): void
    {
        Schema::table('student_credits', function (Blueprint $table) {
            $table->datetime('refund_rejected_at')->nullable()->after('refund_note');
            $table->unsignedBigInteger('refund_rejected_by')->nullable()->after('refund_rejected_at');
            $table->text('refund_rejected_reason')->nullable()->after('refund_rejected_by');

            $table->foreign('refund_rejected_by')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('student_credits', function (Blueprint $table) {
            $table->dropForeign(['refund_rejected_by']);
            $table->dropColumn(['refund_rejected_at', 'refund_rejected_by', 'refund_rejected_reason']);
        });
    }
};
