<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentAccountTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_account_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('payment_account_id')->unsigned();
            $table->enum('transaction_type', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable()->comment('fees, income, expense, payroll, transfer, deposit, withdrawal');
            $table->bigInteger('reference_id')->unsigned()->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->decimal('balance_after', 15, 2);
            $table->string('attach')->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->timestamps();
            
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_account_transactions');
    }
}
