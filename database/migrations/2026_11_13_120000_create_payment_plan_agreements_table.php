<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A payment plan agreement: what the bursar prepares, the student signs and
 * the Vice Chancellor approves.
 *
 * One agreement can cover several fees — typically a student's first and
 * second instalment together. Underneath, each fee keeps its own plan, so the
 * way money reaches a fee, the ledger and reversals is exactly as before. The
 * agreement is what ties them together: one reference, one document, one
 * approval.
 *
 * A plan used to be created already "active" and recorded as approved by the
 * person who created it, so approval meant nothing. A plan now starts
 * "pending" and takes no money until the agreement is approved; a rejected one
 * is "rejected" and lets its fee go.
 *
 * Every change to an agreement — created, revised, sent back because one of
 * its fees changed, approved, rejected — is kept as an event with a snapshot
 * of the schedule at that moment, so whoever approves can see exactly what
 * changed since the last approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_plan_agreements')) {
            Schema::create('payment_plan_agreements', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 30)->unique();
                $table->unsignedBigInteger('student_id');
                // pending | approved | rejected | cancelled
                $table->string('status', 20)->default('pending');
                // Rises with every revision, so a printed copy can be matched
                // to the version it was printed from.
                $table->unsignedInteger('version')->default(1);
                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                // The version that was approved: what the changes are measured against.
                $table->unsignedInteger('approved_version')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                // Why an approved agreement went back for approval.
                $table->text('review_reason')->nullable();
                $table->timestamps();

                $table->index(['student_id', 'status']);
                $table->index('status');
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('payment_plan_agreement_events')) {
            Schema::create('payment_plan_agreement_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agreement_id');
                $table->unsignedInteger('version');
                // created | revised | returned | approved | rejected | cancelled
                $table->string('event', 20);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('reason')->nullable();
                // What changed, as sentences a reader can follow.
                $table->json('changes')->nullable();
                // The schedule as it stood after this event.
                $table->json('snapshot')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['agreement_id', 'id']);
                $table->foreign('agreement_id')->references('id')->on('payment_plan_agreements')->onDelete('cascade');
            });
        }

        if (!Schema::hasColumn('payment_plans', 'agreement_id')) {
            Schema::table('payment_plans', function (Blueprint $table) {
                $table->unsignedBigInteger('agreement_id')->nullable()->after('id');
                $table->index('agreement_id');
            });
        }

        DB::statement("ALTER TABLE payment_plans MODIFY status ENUM('pending','active','completed','cancelled','defaulted','rejected') NOT NULL DEFAULT 'pending'");

        // Every plan that already exists becomes its own agreement, approved
        // as it was: it is already in force, already printed or paid against.
        $next = (int) DB::table('payment_plan_agreements')->count();

        foreach (DB::table('payment_plans')->whereNull('agreement_id')->orderBy('id')->get() as $plan) {
            $next++;
            $approved = in_array($plan->status, ['active', 'completed', 'defaulted'], true);
            $created = $plan->created_at ?? now();

            $id = DB::table('payment_plan_agreements')->insertGetId([
                'reference' => 'PPA-' . date('Y', strtotime((string) $created)) . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT),
                'student_id' => $plan->student_id,
                'status' => $approved ? 'approved' : 'cancelled',
                'version' => 1,
                'notes' => $plan->notes,
                'created_by' => $plan->created_by,
                'approved_by' => $plan->approved_by,
                'approved_at' => $plan->approved_at,
                'approved_version' => $approved ? 1 : null,
                'created_at' => $created,
                'updated_at' => now(),
            ]);

            DB::table('payment_plans')->where('id', $plan->id)->update(['agreement_id' => $id]);

            DB::table('payment_plan_agreement_events')->insert([
                'agreement_id' => $id,
                'version' => 1,
                'event' => $approved ? 'approved' : 'cancelled',
                'user_id' => $plan->approved_by,
                'reason' => 'Plan created before agreements existed; carried over as it stood.',
                'created_at' => $plan->approved_at ?? $created,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_plans', 'agreement_id')) {
            Schema::table('payment_plans', function (Blueprint $table) {
                $table->dropIndex(['agreement_id']);
                $table->dropColumn('agreement_id');
            });
        }

        DB::statement("UPDATE payment_plans SET status = 'cancelled' WHERE status IN ('pending','rejected')");
        DB::statement("ALTER TABLE payment_plans MODIFY status ENUM('active','completed','cancelled','defaulted') NOT NULL DEFAULT 'active'");

        Schema::dropIfExists('payment_plan_agreement_events');
        Schema::dropIfExists('payment_plan_agreements');
    }
};
