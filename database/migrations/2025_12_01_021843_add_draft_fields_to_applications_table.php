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
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('draft_progress')->default(0)->after('progress')
                ->comment('Estimated completion progress of draft (0-100%)');
            $table->timestamp('draft_last_saved_at')->nullable()->after('draft_progress')
                ->comment('Last time the draft was saved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['draft_progress', 'draft_last_saved_at']);
        });
    }
};
