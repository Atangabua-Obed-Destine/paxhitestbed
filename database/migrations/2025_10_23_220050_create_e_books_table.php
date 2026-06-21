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
        Schema::create('e_books', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('local'); // 'local' or 'openlibrary'
            $table->string('openlibrary_id')->nullable(); // For books from OpenLibrary API
            $table->string('isbn')->nullable()->index();
            $table->string('isbn_13')->nullable();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('authors')->nullable(); // JSON array
            $table->string('publisher')->nullable();
            $table->string('publish_date')->nullable();
            $table->integer('number_of_pages')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('cover_image_large')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('e_book_categories')->nullOnDelete();
            $table->text('subjects')->nullable(); // JSON array
            $table->string('language')->default('en');
            $table->string('file_path')->nullable(); // For uploaded books
            $table->string('file_type')->nullable(); // pdf, epub, etc
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->text('preview_link')->nullable(); // OpenLibrary preview link
            $table->text('read_online_link')->nullable(); // OpenLibrary read link
            $table->boolean('is_downloadable')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('downloads_count')->default(0);
            $table->integer('favorites_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->integer('rating_count')->default(0);
            $table->boolean('featured')->default(false);
            $table->boolean('status')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['title', 'status']);
            $table->index('featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_books');
    }
};
