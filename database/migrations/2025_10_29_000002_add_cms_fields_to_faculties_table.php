<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCmsFieldsToFacultiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('faculties', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->text('excerpt')->nullable()->after('description');
            $table->string('featured_image')->nullable()->after('excerpt');
            $table->string('banner_image')->nullable()->after('featured_image');
            $table->string('dean_name')->nullable()->after('banner_image');
            $table->string('dean_photo')->nullable()->after('dean_name');
            $table->string('email')->nullable()->after('dean_photo');
            $table->string('phone')->nullable()->after('email');
            $table->string('website')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('faculties', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'excerpt',
                'featured_image',
                'banner_image',
                'dean_name',
                'dean_photo',
                'email',
                'phone',
                'website'
            ]);
        });
    }
}
