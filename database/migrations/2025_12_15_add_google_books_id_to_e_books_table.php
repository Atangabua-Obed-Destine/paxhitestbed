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
            // Add google_books_id column for Google Books API integration
            if (!Schema::hasColumn('e_books', 'google_books_id')) {
                $table->string('google_books_id')->nullable();
            }
            
            // Add info_link column for Google Books info page
            if (!Schema::hasColumn('e_books', 'info_link')) {
                $table->string('info_link', 512)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('e_books', function (Blueprint $table) {
            if (Schema::hasColumn('e_books', 'google_books_id')) {
                $table->dropColumn('google_books_id');
            }
            if (Schema::hasColumn('e_books', 'info_link')) {
                $table->dropColumn('info_link');
            }
        });
    }
};
