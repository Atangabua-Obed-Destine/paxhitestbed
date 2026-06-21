<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBudgetAllocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('budget_id')->unsigned();
            $table->integer('expense_category_id')->unsigned(); // Links to existing expense_categories
            $table->integer('department_id')->unsigned()->nullable(); // Optional: for departmental budgets
            $table->string('title'); // e.g., "Q1 Salaries", "Lab Equipment"
            $table->decimal('allocated_amount', 15, 2); // Budget allocated to this line item
            $table->decimal('spent_amount', 15, 2)->default(0); // Actual expenses
            $table->decimal('committed_amount', 15, 2)->default(0); // Pending/approved but not yet spent
            $table->decimal('remaining_amount', 15, 2)->default(0); // allocated - spent - committed
            $table->enum('period', ['yearly', 'q1', 'q2', 'q3', 'q4', 'semester1', 'semester2'])->default('yearly');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('budget_id')
                  ->references('id')->on('budgets')
                  ->onDelete('cascade');
            $table->foreign('expense_category_id')
                  ->references('id')->on('expense_categories')
                  ->onDelete('restrict');
            $table->foreign('department_id')
                  ->references('id')->on('departments')
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
        Schema::dropIfExists('budget_allocations');
    }
}
