<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            // Auto-created receipts (MoMo, gateway callbacks) have no uploaded file.
            $table->string('receipt_file')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->string('receipt_file')->nullable(false)->change();
        });
    }
};
