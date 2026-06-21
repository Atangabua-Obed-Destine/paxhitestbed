<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBudgetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title'); // e.g., "2025 Annual Budget"
            $table->string('budget_code')->unique(); // e.g., "BDG-2025-001"
            $table->enum('type', ['annual', 'departmental', 'project'])->default('annual');
            $table->integer('department_id')->unsigned()->nullable(); // null for annual budgets
            $table->string('fiscal_year', 10); // e.g., "2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_amount', 15, 2); // Total budget allocated
            $table->decimal('allocated_amount', 15, 2)->default(0); // Amount allocated to categories/departments
            $table->decimal('spent_amount', 15, 2)->default(0); // Actual expenses against this budget
            $table->decimal('remaining_amount', 15, 2)->default(0); // Calculated: total - spent
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'active', 'closed', 'cancelled'])->default('draft');
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('approved_by')->unsigned()->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Foreign keys
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
        Schema::dropIfExists('budgets');
    }
}
