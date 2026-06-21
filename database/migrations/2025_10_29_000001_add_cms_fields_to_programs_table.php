<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCmsFieldsToProgramsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->text('excerpt')->nullable()->after('description');
            $table->string('featured_image')->nullable()->after('excerpt');
            $table->string('banner_image')->nullable()->after('featured_image');
            $table->string('duration')->nullable()->after('banner_image');
            $table->string('credit')->nullable()->after('duration');
            $table->text('requirements')->nullable()->after('credit');
            $table->text('career_prospects')->nullable()->after('requirements');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'excerpt',
                'featured_image',
                'banner_image',
                'duration',
                'credit',
                'requirements',
                'career_prospects'
            ]);
        });
    }
}
