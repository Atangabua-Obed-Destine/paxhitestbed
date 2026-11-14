<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the student card prints its validity: in white at the top right, as
 * it always has, or in black as its own row under Date Issued. Every existing
 * card keeps the top right until someone chooses otherwise.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('id_card_settings', 'validity_position')) {
            return;
        }

        Schema::table('id_card_settings', function (Blueprint $table) {
            $table->string('validity_position', 20)->default('top')->after('validity');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('id_card_settings', 'validity_position')) {
            Schema::table('id_card_settings', function (Blueprint $table) {
                $table->dropColumn('validity_position');
            });
        }
    }
};
