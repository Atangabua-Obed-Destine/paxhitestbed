<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admission approvals, recorded on the system instead of on paper.
 *
 * The table is append-only: a decision is never edited or deleted, so the
 * history of an admission can be read back exactly as it happened.
 *
 * The backfill matters as much as the table. Applications that were already
 * approved before this existed carry no approval rows, and the student record
 * is about to be gated on the final approval — so without this they would all
 * become unconvertible on the day this deploys. Each one is given the final
 * approval it plainly already had, dated from its own decision_at and labelled
 * as predating the flow, so nobody mistakes it for a signature that was given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('step', 50);
            $table->string('decision', 20);
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('signed_name')->nullable();
            $table->string('signed_position')->nullable();
            $table->string('returned_to_step', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'step']);
            $table->index(['application_id', 'id']);

            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
            $table->foreign('decided_by')->references('id')->on('users')->onDelete('set null');
        });

        $this->backfillAlreadyApproved();
    }

    /**
     * Applications already at decision_approved keep their standing.
     */
    protected function backfillAlreadyApproved(): void
    {
        $step = \App\Models\Application::finalApprovalStep();
        $now = now();

        DB::table('applications')
            ->where('stage', 'decision_approved')
            ->orderBy('id')
            ->chunkById(200, function ($applications) use ($step, $now) {
                $rows = [];

                foreach ($applications as $application) {
                    $rows[] = [
                        'application_id' => $application->id,
                        'step' => $step,
                        'decision' => 'approved',
                        'decided_by' => null,
                        'signed_name' => 'Approved before the approval flow existed',
                        'signed_position' => null,
                        'returned_to_step' => null,
                        'note' => 'Recorded automatically when admission approvals moved onto the system. '
                            . 'This application was already approved; no signature was captured at the time.',
                        'decided_at' => $application->decision_at ?: $application->updated_at ?: $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('application_approvals')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_approvals');
    }
};
