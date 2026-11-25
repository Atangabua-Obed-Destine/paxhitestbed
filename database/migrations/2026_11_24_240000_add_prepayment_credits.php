<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money a student pays beyond every instalment they owe, kept as credit, is a
 * "prepayment": cash that arrived but was never added to any fee's paid
 * amount. As an "overpayment" credit it was cash no ledger entry ever posted,
 * and once applied elsewhere it was subtracted from the paying fee as if it had
 * been part of its paid amount, making that fee look underpaid.
 *
 * It gets its own source type, and the credit records how and when the cash
 * came in, so StudentCreditLedger can post it (Dr cash / Cr 419) on the day.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE student_credits MODIFY source_type ENUM('overpayment','refund_reversal','admin_adjustment','transfer','prepayment') NOT NULL DEFAULT 'overpayment'");

        Schema::table('student_credits', function (Blueprint $table) {
            if (!Schema::hasColumn('student_credits', 'received_on')) {
                $table->date('received_on')->nullable()->after('source_type');
                $table->unsignedTinyInteger('received_method')->nullable()->after('received_on');
                $table->unsignedBigInteger('received_payment_account_id')->nullable()->after('received_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_credits', function (Blueprint $table) {
            if (Schema::hasColumn('student_credits', 'received_on')) {
                $table->dropColumn(['received_on', 'received_method', 'received_payment_account_id']);
            }
        });
    }
};
