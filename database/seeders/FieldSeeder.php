<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class FieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('fields')->delete();

        $fields = [
            ['slug' => 'user_father_name', 'status' => '1'],
            ['slug' => 'user_mother_name', 'status' => '1'],
            ['slug' => 'user_joining_date', 'status' => '1'],
            ['slug' => 'user_ending_date', 'status' => '1'],
            ['slug' => 'user_emergency_phone', 'status' => '1'],
            ['slug' => 'user_religion', 'status' => '0'],
            ['slug' => 'user_caste', 'status' => '0'],
            ['slug' => 'user_mother_tongue', 'status' => '0'],
            ['slug' => 'user_nationality', 'status' => '0'],
            ['slug' => 'user_marital_status', 'status' => '1'],
            ['slug' => 'user_blood_group', 'status' => '1'],
            ['slug' => 'user_national_id', 'status' => '1'],
            ['slug' => 'user_passport_no', 'status' => '1'],
            ['slug' => 'user_address', 'status' => '1'],
            ['slug' => 'user_education', 'status' => '1'],
            ['slug' => 'user_epf_no', 'status' => '1'],
            ['slug' => 'user_bank_account', 'status' => '1'],
            ['slug' => 'user_signature', 'status' => '1'],
            ['slug' => 'user_resume', 'status' => '1'],
            ['slug' => 'user_joining_letter', 'status' => '1'],
            ['slug' => 'user_documents', 'status' => '1'],



            ['slug' => 'student_other_names', 'status' => '1'],
            ['slug' => 'student_father_name', 'status' => '1'],
            ['slug' => 'student_mother_name', 'status' => '1'],
            ['slug' => 'student_father_occupation', 'status' => '1'],
            ['slug' => 'student_mother_occupation', 'status' => '1'],
            ['slug' => 'student_alternate_phone', 'status' => '1'],
            ['slug' => 'student_emergency_phone', 'status' => '1'],
            ['slug' => 'student_postal_address', 'status' => '1'],
            ['slug' => 'student_birth_city', 'status' => '1'],
            ['slug' => 'student_birth_division', 'status' => '1'],
            ['slug' => 'student_birth_region', 'status' => '1'],
            ['slug' => 'student_birth_country', 'status' => '1'],
            ['slug' => 'student_religion', 'status' => '1'],
            ['slug' => 'student_catholic_baptised', 'status' => '1'],
            ['slug' => 'student_caste', 'status' => '0'],
            ['slug' => 'student_mother_tongue', 'status' => '1'],
            ['slug' => 'student_nationality', 'status' => '0'],
            ['slug' => 'student_marital_status', 'status' => '1'],
            ['slug' => 'student_blood_group', 'status' => '1'],
            ['slug' => 'student_national_id', 'status' => '1'],
            ['slug' => 'student_national_id_issue_date', 'status' => '1'],
            ['slug' => 'student_national_id_issue_place', 'status' => '1'],
            ['slug' => 'student_passport_no', 'status' => '1'],
            ['slug' => 'student_passport_issue_date', 'status' => '1'],
            ['slug' => 'student_passport_issue_country', 'status' => '1'],
            ['slug' => 'student_address', 'status' => '1'],
            ['slug' => 'student_school_info', 'status' => '1'],
            ['slug' => 'student_collage_info', 'status' => '1'],
            ['slug' => 'student_relatives', 'status' => '1'],
            ['slug' => 'student_school_transcript', 'status' => '1'],
            ['slug' => 'student_school_certificate', 'status' => '1'],
            ['slug' => 'student_collage_transcript', 'status' => '1'],
            ['slug' => 'student_collage_certificate', 'status' => '1'],
            ['slug' => 'student_photo', 'status' => '1'],
            ['slug' => 'student_signature', 'status' => '1'],
            ['slug' => 'student_documents', 'status' => '1'],



            ['slug' => 'application_program_choice_second', 'status' => '1'],
            ['slug' => 'application_program_choice_third', 'status' => '1'],
            ['slug' => 'application_academic_year', 'status' => '1'],
            ['slug' => 'application_registration_fee_bank', 'status' => '1'],
            ['slug' => 'application_registration_fee_reference', 'status' => '1'],
            ['slug' => 'application_studied_in_english', 'status' => '1'],
            ['slug' => 'application_instruction_language_secondary', 'status' => '1'],
            ['slug' => 'application_guardians', 'status' => '1'],
            ['slug' => 'application_academic_history', 'status' => '1'],
            ['slug' => 'application_language_proficiency', 'status' => '1'],
            ['slug' => 'application_document_checklist', 'status' => '1'],
            ['slug' => 'application_declaration', 'status' => '1'],
            ['slug' => 'application_other_names', 'status' => '1'],
            ['slug' => 'application_father_name', 'status' => '1'],
            ['slug' => 'application_mother_name', 'status' => '1'],
            ['slug' => 'application_father_occupation', 'status' => '1'],
            ['slug' => 'application_mother_occupation', 'status' => '1'],
            ['slug' => 'application_alternate_phone', 'status' => '1'],
            ['slug' => 'application_emergency_phone', 'status' => '0'],
            ['slug' => 'application_postal_address', 'status' => '1'],
            ['slug' => 'application_birth_city', 'status' => '1'],
            ['slug' => 'application_birth_division', 'status' => '1'],
            ['slug' => 'application_birth_region', 'status' => '1'],
            ['slug' => 'application_birth_country', 'status' => '1'],
            ['slug' => 'application_religion', 'status' => '1'],
            ['slug' => 'application_catholic_baptised', 'status' => '1'],
            ['slug' => 'application_caste', 'status' => '0'],
            ['slug' => 'application_mother_tongue', 'status' => '1'],
            ['slug' => 'application_nationality', 'status' => '0'],
            ['slug' => 'application_marital_status', 'status' => '1'],
            ['slug' => 'application_blood_group', 'status' => '1'],
            ['slug' => 'application_national_id', 'status' => '1'],
            ['slug' => 'application_national_id_issue_date', 'status' => '1'],
            ['slug' => 'application_national_id_issue_place', 'status' => '1'],
            ['slug' => 'application_passport_no', 'status' => '1'],
            ['slug' => 'application_passport_issue_date', 'status' => '1'],
            ['slug' => 'application_passport_issue_country', 'status' => '1'],
            ['slug' => 'application_address', 'status' => '1'],
            ['slug' => 'application_school_info', 'status' => '1'],
            ['slug' => 'application_collage_info', 'status' => '1'],
            ['slug' => 'application_school_transcript', 'status' => '1'],
            ['slug' => 'application_school_certificate', 'status' => '1'],
            ['slug' => 'application_collage_transcript', 'status' => '1'],
            ['slug' => 'application_collage_certificate', 'status' => '1'],
            ['slug' => 'application_photo', 'status' => '1'],
            ['slug' => 'application_signature', 'status' => '1'],



            ['slug' => 'panel_class_routine', 'status' => '1'],
            ['slug' => 'panel_exam_routine', 'status' => '1'],
            ['slug' => 'panel_attendance', 'status' => '1'],
            ['slug' => 'panel_leave', 'status' => '1'],
            ['slug' => 'panel_fees_report', 'status' => '1'],
            ['slug' => 'panel_online_exam', 'status' => '1'],
            ['slug' => 'panel_library', 'status' => '1'],
            ['slug' => 'panel_notice', 'status' => '1'],
            ['slug' => 'panel_assignment', 'status' => '1'],
            ['slug' => 'panel_syllabus', 'status' => '1'],
            ['slug' => 'panel_download', 'status' => '1'],
            ['slug' => 'panel_tutorial', 'status' => '1'],
            ['slug' => 'panel_transcript', 'status' => '1'],
            ['slug' => 'panel_profile', 'status' => '1'],
        ];

        DB::table('fields')->insert($fields);
    }
}
