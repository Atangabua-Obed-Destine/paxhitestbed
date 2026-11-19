<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the student card shows its barcode: framed at the left, as it always
 * has (on the built-in artwork that frame also covers a decorative barcode
 * printed into the background), or without a border, moved right to sit
 * beside the QR code. Every existing card keeps the framed one until someone
 * chooses otherwise.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('id_card_settings', 'barcode_position')) {
            return;
        }

        Schema::table('id_card_settings', function (Blueprint $table) {
            $table->string('barcode_position', 20)->default('left')->after('barcode');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('id_card_settings', 'barcode_position')) {
            Schema::table('id_card_settings', function (Blueprint $table) {
                $table->dropColumn('barcode_position');
            });
        }
    }
};
