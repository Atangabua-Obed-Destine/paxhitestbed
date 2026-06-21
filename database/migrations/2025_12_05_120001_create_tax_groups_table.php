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
        Schema::create('tax_groups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->nullable()->comment('Short code like PIT, SS, etc.');
            $table->text('description')->nullable();
            $table->boolean('is_progressive')->default(true)->comment('True = progressive tax, False = flat rate on matching bracket');
            $table->boolean('status')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // Add tax_group_id to tax_settings
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_group_id')->nullable()->after('id');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->dropForeign(['tax_group_id']);
            $table->dropColumn('tax_group_id');
        });

        Schema::dropIfExists('tax_groups');
    }
};
