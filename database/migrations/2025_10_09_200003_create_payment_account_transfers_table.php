<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentAccountTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_account_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('from_account_id')->unsigned();
            $table->bigInteger('to_account_id')->unsigned();
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->text('note')->nullable();
            $table->string('attach')->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->timestamps();
            
            $table->foreign('from_account_id')->references('id')->on('payment_accounts')->onDelete('restrict');
            $table->foreign('to_account_id')->references('id')->on('payment_accounts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_account_transfers');
    }
}
