<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two fallback provider slots, and a record of which one answered.
 *
 * A single provider's free tier returned HTTP 429 after roughly two calls in
 * a minute, and a client retrying itself cannot help when that provider is
 * the thing refusing. Two more slots let the assistant keep answering.
 *
 * Only the choice of provider and model lives here. The API keys stay in
 * .env: a secret in a settings table is readable by anyone with database
 * access, gets copied into backups, and tends to end up rendered on screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_settings', 'provider_2')) {
                $table->string('provider_2')->nullable()->after('model');
                $table->string('model_2')->nullable()->after('provider_2');
            }

            if (!Schema::hasColumn('chat_settings', 'provider_3')) {
                $table->string('provider_3')->nullable()->after('model_2');
                $table->string('model_3')->nullable()->after('provider_3');
            }
        });

        // Which provider actually answered. Without it, an installation whose
        // primary has quietly stopped working looks healthy: the answers keep
        // coming and nothing says they are coming from the second choice.
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'provider')) {
                $table->string('provider')->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_settings', function (Blueprint $table) {
            foreach (['provider_2', 'model_2', 'provider_3', 'model_3'] as $column) {
                if (Schema::hasColumn('chat_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
