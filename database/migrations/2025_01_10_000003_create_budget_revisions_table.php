<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBudgetRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('budget_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('budget_id')->unsigned();
            $table->integer('revision_number')->default(1);
            $table->decimal('previous_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->decimal('change_amount', 15, 2); // new - previous
            $table->enum('change_type', ['increase', 'decrease', 'reallocation']);
            $table->text('reason');
            $table->text('justification')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->bigInteger('requested_by')->unsigned();
            $table->bigInteger('approved_by')->unsigned()->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('budget_id')
                  ->references('id')->on('budgets')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('budget_revisions');
    }
}
