<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data backfill: for every application that already has an admission_fee_id,
 * copy that link onto fees.applicant_id. Then propagate to payment_receipts
 * (matched by fee_id). Finally null out payment_receipts.student_id rows that
 * had been mis-populated with an application id (they don't reference any real
 * students row).
 */
return new class extends Migration {
    public function up(): void
    {
        // fees.applicant_id ← applications.id
        DB::statement("
            UPDATE fees f
            INNER JOIN applications a ON a.admission_fee_id = f.id
            SET f.applicant_id = a.id
            WHERE f.applicant_id IS NULL
        ");

        // payment_receipts.applicant_id ← fees.applicant_id (only for admission fees)
        DB::statement("
            UPDATE payment_receipts pr
            INNER JOIN fees f ON f.id = pr.fee_id
            SET pr.applicant_id = f.applicant_id
            WHERE f.applicant_id IS NOT NULL
              AND pr.applicant_id IS NULL
        ");

        // Any payment_receipts.student_id that now has an applicant_id set almost
        // certainly held a bogus value (application id shoved into student_id).
        // Null it out so joins to students behave.
        DB::statement("
            UPDATE payment_receipts
            SET student_id = NULL
            WHERE applicant_id IS NOT NULL
              AND student_id IS NOT NULL
              AND student_id NOT IN (SELECT id FROM students)
        ");
    }

    public function down(): void
    {
        // Non-destructive backfill; nothing to undo.
    }
};
