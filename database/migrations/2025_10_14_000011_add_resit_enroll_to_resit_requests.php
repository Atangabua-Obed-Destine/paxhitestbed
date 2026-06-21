<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ResitRequest;
use App\Services\Resit\ResitEnrollmentService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('resit_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('resit_requests', 'resit_enroll_id')) {
                $table->unsignedBigInteger('resit_enroll_id')->nullable()->after('resit_semester_id');
                $table->foreign('resit_enroll_id')->references('id')->on('student_enrolls')->onDelete('set null');
            }
        });

        if (class_exists(ResitRequest::class)) {
            $service = app(ResitEnrollmentService::class);

            ResitRequest::where('workflow_state', ResitRequest::STATE_SCHEDULED)
                ->whereNotNull('resit_session_id')
                ->whereNotNull('resit_semester_id')
                ->chunkById(100, function ($requests) use ($service) {
                    foreach ($requests as $request) {
                        $service->ensureEnrollment($request);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resit_requests', function (Blueprint $table) {
            if (Schema::hasColumn('resit_requests', 'resit_enroll_id')) {
                $table->dropForeign(['resit_enroll_id']);
                $table->dropColumn('resit_enroll_id');
            }
        });
    }
};
