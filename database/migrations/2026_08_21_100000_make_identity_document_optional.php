<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The identity document becomes optional on existing degree types.
 *
 * ApplicationDocumentRequirements now defaults `national_id_card` to optional,
 * but that default only applies to a degree type that has never been configured:
 * once a row exists in degree_type_documents it wins, so the change would have
 * had no effect on any live intake.
 *
 * Deliberately narrow. Only this one key is touched, only where it is currently
 * required, and every other document keeps whatever the institution chose. An
 * intake that genuinely needs an ID can mark it required again under
 * Academic → Degree Types → Form Configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('degree_type_documents')) {
            return;
        }

        DB::table('degree_type_documents')
            ->where('doc_key', 'national_id_card')
            ->where('required', 1)
            ->update(['required' => 0]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('degree_type_documents')) {
            return;
        }

        DB::table('degree_type_documents')
            ->where('doc_key', 'national_id_card')
            ->update(['required' => 1]);
    }
};
