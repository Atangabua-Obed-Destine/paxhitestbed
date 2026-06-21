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
        Schema::table('e_books', function (Blueprint $table) {
            // Add archive_id column for Internet Archive integration
            if (!Schema::hasColumn('e_books', 'archive_id')) {
                $table->string('archive_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('e_books', function (Blueprint $table) {
            if (Schema::hasColumn('e_books', 'archive_id')) {
                $table->dropColumn('archive_id');
            }
        });
    }
};
