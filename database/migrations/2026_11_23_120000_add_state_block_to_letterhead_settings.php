<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bilingual "République du Cameroun / Republic of Cameroon" block that
 * Cameroonian official documents carry above the school's own heading, kept
 * as settings rather than typed into the letterhead: the ministry and the
 * delegation change, the motto and its layout do not.
 *
 * Off by default, so no document changes until a school switches it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letterhead_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('letterhead_settings', 'state_block')) {
                $table->boolean('state_block')->default(false)->after('status');
                $table->string('ministry_fr')->nullable()->after('state_block');
                $table->string('ministry_en')->nullable()->after('ministry_fr');
                $table->string('delegation_fr')->nullable()->after('ministry_en');
                $table->string('delegation_en')->nullable()->after('delegation_fr');
            }
        });
    }

    public function down(): void
    {
        Schema::table('letterhead_settings', function (Blueprint $table) {
            foreach (['delegation_en', 'delegation_fr', 'ministry_en', 'ministry_fr', 'state_block'] as $column) {
                if (Schema::hasColumn('letterhead_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
