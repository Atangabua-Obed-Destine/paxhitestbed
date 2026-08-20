<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One configurable letterhead for the whole institution.
 *
 * Replaces a filename hardcoded into the marksheet views and the several
 * different headers other documents assemble for themselves. Single-row, the
 * same shape as the other *Setting tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('letterhead_settings')) {
            return;
        }

        Schema::create('letterhead_settings', function (Blueprint $table) {
            $table->bigIncrements('id');

            // html          render the configured markup
            // reserve_space print nothing, but leave room for pre-printed paper
            // none          no letterhead and no gap
            $table->enum('mode', ['html', 'reserve_space', 'none'])->default('html');

            $table->longText('html')->nullable();

            // Only meaningful in reserve_space mode.
            $table->unsignedSmallInteger('reserve_height_mm')->default(35);

            // Lets the letterhead be switched off without losing the content.
            $table->boolean('status')->default(true);

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Seed with the letterhead already in use, so nothing looks broken on
        // the first render. The image is referenced by absolute URL — the
        // service rewrites it per destination, but starting from a resolvable
        // path means it works even before anyone opens the screen.
        $existing = 'uploads/letterhead/paxletterhead.jpg';

        DB::table('letterhead_settings')->insert([
            'mode' => 'html',
            'html' => is_file(public_path($existing))
                ? '<p style="text-align:center;margin:0"><img src="' . asset($existing) . '" alt="Letterhead"></p>'
                : null,
            'reserve_height_mm' => 35,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('letterhead_settings');
    }
};
