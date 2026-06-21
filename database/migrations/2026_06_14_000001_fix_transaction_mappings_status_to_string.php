<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * `transaction_mappings.status` was declared boolean, but the whole codebase
 * (TransactionAutoMapService, PayrollAccountingService, the model's active() scope)
 * uses string statuses 'active' / 'reversed'. As a boolean column both strings cast
 * to 0, so a reversed mapping was indistinguishable from an active one. Convert the
 * column to a string and normalize existing rows to 'active'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_mappings', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->change();
        });

        // Existing rows were stored as 0 (string cast); they represent active postings.
        DB::table('transaction_mappings')->update(['status' => 'active']);
    }

    public function down(): void
    {
        // Best-effort revert: map strings back to the old boolean semantics.
        DB::table('transaction_mappings')->where('status', '!=', 'active')->update(['status' => 0]);
        DB::table('transaction_mappings')->where('status', 'active')->update(['status' => 1]);

        Schema::table('transaction_mappings', function (Blueprint $table) {
            $table->boolean('status')->default(1)->change();
        });
    }
};
