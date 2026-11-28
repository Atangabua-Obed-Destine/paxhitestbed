<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment loans: projectors, laptops, speakers and the like, signed out at a
 * desk by scanning the borrower's ID card and the item's sticker, and checked
 * back in the same way.
 *
 *   equipment_desks      the offices that lend (ICT office, Stores, ...)
 *   equipment_items      one row per physical item, with its sticker code
 *   equipment_loans      one row per time an item goes out
 *   loan_desk_settings   one row: due time, reminders, library loan days, and
 *                        whether an overdue item at one desk blocks the other
 *
 * A borrower is a [type, id] pair like the mailbox: 'staff' = users.id,
 * 'student' = students.id. An item can be out on at most one open loan; the
 * desk enforces that under a row lock rather than a unique index, because a
 * closed loan keeps its item id forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_desks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('equipment_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('category', 80)->nullable();
            $table->json('accessories')->nullable();
            $table->boolean('students_may_borrow')->default(false);
            $table->unsignedBigInteger('desk_id')->nullable()->index();
            $table->unsignedBigInteger('fixed_asset_id')->nullable()->index();
            $table->text('notes')->nullable();
            // active | maintenance | lost | retired. Whether it is out is the loans' business.
            $table->string('state', 20)->default('active')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_item_id');
            $table->string('borrower_type', 10);
            $table->unsignedBigInteger('borrower_id');
            // self | class | lecturer
            $table->string('purpose', 10)->default('self');
            $table->string('purpose_detail', 190)->nullable();
            $table->unsignedBigInteger('lecturer_id')->nullable();
            $table->unsignedBigInteger('desk_id')->nullable();
            $table->dateTime('lent_at');
            $table->dateTime('due_at');
            $table->unsignedBigInteger('lent_by');
            $table->json('accessories_out')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('returned_by_type', 10)->nullable();
            $table->unsignedBigInteger('returned_by_id')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            // fine | damaged | missing
            $table->string('return_condition', 10)->nullable();
            $table->json('accessories_back')->nullable();
            $table->text('return_note')->nullable();
            // out | returned | lost
            $table->string('status', 10)->default('out');
            $table->boolean('follow_up')->default(false);
            $table->dateTime('follow_up_cleared_at')->nullable();
            $table->unsignedBigInteger('follow_up_cleared_by')->nullable();
            $table->text('follow_up_note')->nullable();
            $table->unsignedBigInteger('mailbox_thread_id')->nullable();
            $table->unsignedSmallInteger('reminders_sent')->default(0);
            $table->dateTime('last_reminded_at')->nullable();
            $table->timestamps();

            $table->index(['equipment_item_id', 'status']);
            $table->index(['borrower_type', 'borrower_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('loan_desk_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_due_time', 5)->default('17:00');
            $table->unsignedSmallInteger('first_reminder_minutes')->default(30);
            $table->string('daily_reminder_time', 5)->default('08:00');
            $table->unsignedSmallInteger('library_loan_days')->default(14);
            // When on, an overdue item at either desk stops borrowing at both.
            $table->boolean('block_across_desks')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('equipment_desks')->insert([
            ['name' => 'ICT office', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Stores', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('loan_desk_settings')->insert(['created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_desk_settings');
        Schema::dropIfExists('equipment_loans');
        Schema::dropIfExists('equipment_items');
        Schema::dropIfExists('equipment_desks');
    }
};
