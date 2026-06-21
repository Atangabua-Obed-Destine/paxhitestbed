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
            if (!Schema::hasColumn('e_books', 'gutenberg_id')) {
                $table->unsignedBigInteger('gutenberg_id')->nullable();
                $table->index('gutenberg_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('e_books', function (Blueprint $table) {
            $table->dropIndex(['gutenberg_id']);
            $table->dropColumn('gutenberg_id');
        });
    }
};
