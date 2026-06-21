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
        Schema::create('e_book_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('e_book_id')->constrained('e_books')->cascadeOnDelete();
            $table->integer('rating'); // 1-5 stars
            $table->text('review')->nullable();
            $table->integer('helpful_count')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
            
            $table->unique(['user_id', 'e_book_id']);
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_book_reviews');
    }
};
