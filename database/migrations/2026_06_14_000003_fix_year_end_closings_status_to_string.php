<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The year_end_closings.status enum only allowed pending/in_progress/completed/reversed,
 * but the model's workflow also uses 'draft' and 'pending_approval'. Saving those failed.
 * Convert to a string so the full lifecycle (draft → in_progress → pending_approval →
 * completed / reversed) persists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('year_end_closings', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('year_end_closings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'completed', 'reversed'])->default('pending')->change();
        });
    }
};
