<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EdutrustPay connection settings.
 *
 * WHY THE DATABASE AND NOT .env
 *
 * The obvious design is a settings screen that writes .env. It is the wrong one:
 * it needs the web user to have write access to a file holding every secret this
 * application owns, it races with anyone editing that file by hand, and the
 * change does nothing while the config is cached. A bursar clicking Save and
 * seeing no effect is worse than no screen at all.
 *
 * So the values live here, and .env remains the fallback for installations
 * configured before this screen existed. Nothing that already worked stops.
 *
 * The secret is ENCRYPTED, not hashed. Verifying an HMAC needs the real secret,
 * so it has to be recoverable — a weaker property than hashing and worth
 * stating: anyone holding both this database and APP_KEY can recover it, which
 * is why APP_KEY must not sit in the same backup as the database.
 *
 * A single row: this installation is one institution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edutrustpay_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('enabled')->default(false);
            $table->string('endpoint')->nullable();
            $table->string('institution_ref')->nullable();
            $table->string('key_id')->nullable();

            // Laravel `encrypted` cast. TEXT because the envelope is long.
            $table->text('secret_ciphertext')->nullable();

            /*
             * The result of the last connection test, so the screen can say
             * "these credentials worked at 14:02 yesterday" rather than only
             * "they are filled in". A key rotated on the platform and never
             * updated here is the most likely real failure, and it is invisible
             * until month end when the reports start bouncing.
             */
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_message', 500)->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edutrustpay_settings');
    }
};
