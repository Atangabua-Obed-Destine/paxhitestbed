<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $slugs = [
            'application_other_names',
            'application_birth_city',
            'application_birth_division',
            'application_birth_region',
            'application_birth_country',
            'application_catholic_baptised',
            'application_national_id_issue_date',
            'application_national_id_issue_place',
            'application_passport_issue_date',
            'application_passport_issue_country',
            'application_postal_address',
            'application_alternate_phone',
            'application_academic_year',
            'application_program_choice_second',
            'application_program_choice_third',
            'application_studied_in_english',
            'application_instruction_language_secondary',
            'application_registration_fee_bank',
            'application_registration_fee_reference',
            'application_declaration',
            'application_guardians',
            'application_academic_history',
            'application_language_proficiency',
            'application_document_checklist',
            'application_board_review',
        ];

        foreach ($slugs as $slug) {
            DB::table('fields')->updateOrInsert(
                ['slug' => $slug],
                ['status' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('fields')->whereIn('slug', [
            'application_other_names',
            'application_birth_city',
            'application_birth_division',
            'application_birth_region',
            'application_birth_country',
            'application_catholic_baptised',
            'application_national_id_issue_date',
            'application_national_id_issue_place',
            'application_passport_issue_date',
            'application_passport_issue_country',
            'application_postal_address',
            'application_alternate_phone',
            'application_academic_year',
            'application_program_choice_second',
            'application_program_choice_third',
            'application_studied_in_english',
            'application_instruction_language_secondary',
            'application_registration_fee_bank',
            'application_registration_fee_reference',
            'application_declaration',
            'application_guardians',
            'application_academic_history',
            'application_language_proficiency',
            'application_document_checklist',
            'application_board_review',
        ])->delete();
    }
};
