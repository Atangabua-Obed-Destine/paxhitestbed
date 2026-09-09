<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EdutrustPay outbox.
 *
 * Reports are written here first and delivered separately, because this is a
 * bursary server: the power goes, the hotspot is on for a few minutes a day,
 * and the month still has to be reported. Building the report and sending it in
 * one step would mean a month is simply lost whenever the network happens to be
 * down at the moment it closed.
 *
 * The signed payload is stored verbatim so a retry sends the SAME BYTES. The
 * signature covers those bytes, so rebuilding the report on retry would produce
 * a different document — and if the figures had moved in between, a silent
 * restatement nobody asked for.
 *
 * Nothing here is ever deleted. A delivered report stays as the local record of
 * what was sent and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edutrustpay_outbox', function (Blueprint $table) {
            $table->id();

            $table->string('kind', 20)->default('report');   // report | heartbeat
            $table->string('period', 7)->nullable();
            $table->unsignedInteger('sequence')->default(1);

            // Exactly what will be sent, and what the signature covers.
            $table->longText('payload');
            $table->string('payload_hash', 64);

            $table->string('status', 20)->default('pending'); // pending | delivered | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_response')->nullable();

            $table->timestamps();

            // One row per (period, sequence): re-running the build for a month
            // must not queue it twice.
            $table->unique(['kind', 'period', 'sequence'], 'etp_outbox_unique');
            $table->index(['status', 'next_attempt_at'], 'etp_outbox_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edutrustpay_outbox');
    }
};
