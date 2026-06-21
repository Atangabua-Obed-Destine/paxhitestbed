<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Platform Access Fee');
            $table->text('welcome_message')->nullable();
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->boolean('is_enabled')->default(false)->comment('Global enable/disable');
            $table->text('payment_instructions')->nullable();
            $table->string('currency', 10)->default('FRW');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_fee_settings');
    }
};
