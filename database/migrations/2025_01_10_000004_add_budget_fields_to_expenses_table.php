<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBudgetFieldsToExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->bigInteger('budget_id')->unsigned()->nullable()->after('category_id');
            $table->bigInteger('budget_allocation_id')->unsigned()->nullable()->after('budget_id');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved')->after('status');
            $table->bigInteger('approved_by')->unsigned()->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // Foreign keys
            $table->foreign('budget_id')
                  ->references('id')->on('budgets')
                  ->onDelete('set null');
            $table->foreign('budget_allocation_id')
                  ->references('id')->on('budget_allocations')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['budget_id']);
            $table->dropForeign(['budget_allocation_id']);
            $table->dropColumn(['budget_id', 'budget_allocation_id', 'approval_status', 'approved_by', 'approved_at']);
        });
    }
}
