<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the school will still take resit requests.
 *
 * Resits are requested per semester: a student who failed a First Semester
 * course asks for a resit of that semester's exams. Once the school has drawn
 * up the resit timetable it cannot keep accepting new requests for that sitting,
 * but students go on asking because nothing on their portal says otherwise —
 * and every late request is a conversation someone has to have.
 *
 * So the window is closed per academic session and semester type, which is the
 * grain a resit sitting actually has. Closing one stops new requests; declining
 * stays open, because a student still has to be able to settle the course by
 * carrying it over, and blocking that would leave them stuck with no move at
 * all.
 *
 * No row means open. Nothing changes until a school closes a window on purpose.
 *
 * ---
 *
 * This migration repairs as well as creates, because its first version could
 * not finish. It declared session_id with foreignId(), which is bigint, while
 * sessions.id in this application is int unsigned — every other table here
 * declares session_id as unsigned integer to match. MySQL refuses a foreign key
 * whose two sides differ in type (errno 150), so the sequence went:
 *
 *   1. CREATE TABLE               succeeded
 *   2. ALTER ... ADD UNIQUE       succeeded
 *   3. ALTER ... ADD FOREIGN KEY  failed on the type mismatch
 *
 * MySQL does not roll back DDL, so the table survived while the migration was
 * never recorded as run — and every later `migrate` tried step 1 again and died
 * with "table already exists". Dropping the table is not an option: by the time
 * this was found, schools had already closed real windows with it.
 *
 * So each step below is applied only where it is missing. Running this against
 * a fresh database, a half-built one, or a finished one all end the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('resit_request_windows')) {
            Schema::create('resit_request_windows', function (Blueprint $table) {
                $table->id();
                // Unsigned integer, not foreignId()'s bigint: it has to match
                // sessions.id exactly or the foreign key below is refused.
                $table->unsignedInteger('session_id');
                $table->unsignedTinyInteger('semester_type'); // 1 = First, 2 = Second
                $table->boolean('is_open')->default(true);
                $table->string('note')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(['session_id', 'semester_type'], 'resit_request_windows_session_semester_unique');
                $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            });

            return;
        }

        // --- Repairing a table left behind by the failed first version. ---

        if ($this->columnType('resit_request_windows', 'session_id') !== 'int unsigned') {
            // Narrowing bigint to int: session ids are small and the column is
            // only ever written with one, so no value can be lost here.
            DB::statement('ALTER TABLE `resit_request_windows` MODIFY `session_id` INT UNSIGNED NOT NULL');
        }

        if (!$this->hasIndex('resit_request_windows', 'resit_request_windows_session_semester_unique')) {
            DB::statement(
                'ALTER TABLE `resit_request_windows`
                 ADD UNIQUE `resit_request_windows_session_semester_unique` (`session_id`, `semester_type`)'
            );
        }

        if (!$this->hasForeignKey('resit_request_windows', 'session_id')) {
            DB::statement(
                'ALTER TABLE `resit_request_windows`
                 ADD CONSTRAINT `resit_request_windows_session_id_foreign`
                 FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resit_request_windows');
    }

    /** The column's type as MySQL reports it, e.g. "int unsigned". */
    private function columnType(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (!$row) {
            return null;
        }

        return str_contains(strtolower($row->COLUMN_TYPE), 'unsigned')
            ? strtolower($row->DATA_TYPE) . ' unsigned'
            : strtolower($row->DATA_TYPE);
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        ) !== null;
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$table, $column]
        ) !== null;
    }
};
