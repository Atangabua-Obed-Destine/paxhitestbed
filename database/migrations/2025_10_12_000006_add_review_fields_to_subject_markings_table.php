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
        Schema::table('subject_markings', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('state_changed_by');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            $table->text('review_notes')->nullable()->after('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_markings', function (Blueprint $table) {
            $table->dropColumn([
                'reviewed_at',
                'reviewed_by',
                'review_notes',
            ]);
        });
    }
};
