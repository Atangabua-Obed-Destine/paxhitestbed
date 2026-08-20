<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable public knowledge base.
 *
 * The guest surface has no authenticated subject, so it has no personal-data
 * tools at all. These entries are what make it useful: policies, deadlines,
 * "how do I apply", office hours — anything staff want answered publicly that
 * is not already derivable from Program / DegreeType / Session records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_knowledge_entries', function (Blueprint $table) {
            $table->id();

            $table->string('question');
            $table->text('answer');
            $table->string('tags')->nullable()->comment('Comma-separated, aids retrieval');

            // Which audiences may receive this entry. Public entries are also
            // available to signed-in actors; the reverse is never true.
            $table->boolean('for_web')->default(true);
            $table->boolean('for_application')->default(true);
            $table->boolean('for_student')->default(true);
            $table->boolean('for_admin')->default(true);

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_knowledge_entries');
    }
};
