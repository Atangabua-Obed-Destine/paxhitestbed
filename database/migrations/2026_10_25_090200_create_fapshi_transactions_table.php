<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every attempt to pay the platform fee through Fapshi, successful or not.
 *
 * Why a table of its own rather than columns on platform_fee_payments: that
 * table means "a payment an admin may need to look at", and a student who
 * opened a payment link and then closed the tab is not one. Writing a row there
 * at the moment a link is minted would fill the verification queue with
 * non-payments. So a payment row is created only once Fapshi confirms, and
 * everything before that lives here.
 *
 * fee_amount and charged_amount are both recorded AT INITIATE. The student is
 * charged the fee plus the gateway's commission, and both of those are settings
 * an admin can change at any moment; a payment already in flight has to be
 * judged against the figures it was created with, not whatever the settings say
 * when the confirmation arrives.
 *
 * status carries two values that are not Fapshi's:
 *
 *   mismatched  the provider says SUCCESSFUL but the payment does not match what
 *               we asked for — too little, or an externalId belonging to another
 *               transaction. Money arrived and must not be lost, but access must
 *               not be granted either, so it is kept and flagged.
 *   refundable  a confirmed payment from a student who was already settled or
 *               exempt. Crediting it twice is wrong and keeping it quietly is
 *               worse, so the bursar gets told.
 *
 * raw_request / raw_response keep what was actually sent and received. When a
 * student says they paid and the system says otherwise, that is the only record
 * that settles it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fapshi_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 20)->comment('sandbox | live');

            // Nullable because it is assigned by Fapshi's reply: the row is
            // written first, so a request that dies mid-flight still leaves a
            // trace instead of vanishing.
            $table->string('trans_id', 100)->nullable()->unique();
            $table->string('external_id', 100)->unique()->comment('Ours: PFEE-{id}');

            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('student_enroll_id');
            $table->unsignedInteger('session_id')->nullable()->index();

            $table->decimal('fee_amount', 10, 2)->comment('The platform fee itself, as configured when this started');
            $table->decimal('charged_amount', 10, 2)->comment('What the student was asked for: fee + gateway commission');
            $table->decimal('amount_received', 10, 2)->nullable()->comment("What Fapshi says was paid");
            $table->decimal('revenue', 10, 2)->nullable()->comment('What reaches the school, after commission');
            $table->string('currency', 10)->default('XAF');

            $table->string('status', 20)->default('created')->index()
                ->comment('created|pending|successful|failed|expired|mismatched|refundable');

            $table->string('financial_trans_id', 100)->nullable()->comment("The operator's own reference");
            $table->text('payment_link')->nullable();
            $table->unsignedBigInteger('platform_fee_payment_id')->nullable()->index();
            $table->string('failure_reason', 255)->nullable();

            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');

            // Deliberately no foreign key to platform_fee_payments: that row may
            // be removed by an admin correcting a mistake, and losing the record
            // that money was taken would be the worse outcome of the two.

            // The lookup initiate() makes on every tap: has this student got a
            // live link for this session already?
            $table->index(['student_id', 'session_id', 'status'], 'fapshi_student_session_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fapshi_transactions');
    }
};
