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
        Schema::table('class_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('class_sessions', 'logbook_delegated')) {
                $table->boolean('logbook_delegated')->default(false)->after('class_rep_enroll_id');
            }
            if (!Schema::hasColumn('class_sessions', 'logbook_filled_by')) {
                $table->enum('logbook_filled_by', ['lecturer', 'class_rep'])->nullable()->after('logbook_delegated');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('class_sessions', 'logbook_delegated')) {
                $table->dropColumn('logbook_delegated');
            }
            if (Schema::hasColumn('class_sessions', 'logbook_filled_by')) {
                $table->dropColumn('logbook_filled_by');
            }
        });
    }
};
