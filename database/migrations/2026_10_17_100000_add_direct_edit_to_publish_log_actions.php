<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let the publish log record a direct mark correction.
 *
 * action was enum('unpublish','republish'), so the 'direct_edit' row the
 * correction writes was stored as an empty string on a connection with
 * strict mode off - the mark changed and the history showed nothing - and
 * would have thrown after commit on a strict connection, which is worse:
 * the mark changed and nothing recorded it at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subject_marking_publish_logs
            MODIFY action ENUM('unpublish', 'republish', 'direct_edit') NOT NULL");

        // Rows written before this migration, blanked by the old enum.
        DB::table('subject_marking_publish_logs')
            ->where('action', '')
            ->update(['action' => 'direct_edit']);
    }

    public function down(): void
    {
        $stranded = DB::table('subject_marking_publish_logs')->where('action', 'direct_edit')->count();

        if ($stranded > 0) {
            // Narrowing the enum would silently blank these. They are an
            // academic audit trail, so the rollback stops instead.
            throw new RuntimeException(
                "Refusing to narrow the enum: {$stranded} direct_edit row(s) would be blanked."
            );
        }

        DB::statement("ALTER TABLE subject_marking_publish_logs
            MODIFY action ENUM('unpublish', 'republish') NOT NULL");
    }
};
