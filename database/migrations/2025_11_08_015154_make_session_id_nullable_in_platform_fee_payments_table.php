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
        // The unique_student_session_payment index is being used by a foreign key
        // We need to drop the FK first, then the index, then recreate
        DB::statement('ALTER TABLE platform_fee_payments DROP FOREIGN KEY platform_fee_payments_student_enroll_id_foreign');
        DB::statement('ALTER TABLE platform_fee_payments DROP INDEX unique_student_session_payment');
        DB::statement('ALTER TABLE platform_fee_payments ADD UNIQUE unique_student_payment (student_enroll_id)');
        DB::statement('ALTER TABLE platform_fee_payments ADD CONSTRAINT platform_fee_payments_student_enroll_id_foreign FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes
        DB::statement('ALTER TABLE platform_fee_payments DROP FOREIGN KEY platform_fee_payments_student_enroll_id_foreign');
        DB::statement('ALTER TABLE platform_fee_payments DROP INDEX unique_student_payment');
        DB::statement('ALTER TABLE platform_fee_payments ADD UNIQUE unique_student_session_payment (student_enroll_id, session_id)');
        DB::statement('ALTER TABLE platform_fee_payments ADD CONSTRAINT platform_fee_payments_student_enroll_id_foreign FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE');
    }
};
