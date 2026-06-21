<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ResitRequest;
use App\Services\Resit\ResitFeeService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('resit_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('resit_requests', 'fee_id')) {
                $table->unsignedBigInteger('fee_id')->nullable()->after('payment_status');
                $table->foreign('fee_id')->references('id')->on('fees')->onDelete('set null');
            }
        });

        if (class_exists(ResitRequest::class)) {
            $service = app(ResitFeeService::class);

            ResitRequest::whereNull('fee_id')->chunkById(100, function ($requests) use ($service) {
                foreach ($requests as $request) {
                    $service->ensureFee($request);
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
            if (Schema::hasColumn('resit_requests', 'fee_id')) {
                $table->dropForeign(['fee_id']);
                $table->dropColumn('fee_id');
            }
        });
    }
};
