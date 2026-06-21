<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'other_names')) {
                $table->string('other_names')->nullable();
            }
            if (!Schema::hasColumn('applications', 'birth_city')) {
                $table->string('birth_city')->nullable();
            }
            if (!Schema::hasColumn('applications', 'birth_division')) {
                $table->string('birth_division')->nullable();
            }
            if (!Schema::hasColumn('applications', 'birth_region')) {
                $table->string('birth_region')->nullable();
            }
            if (!Schema::hasColumn('applications', 'birth_country')) {
                $table->string('birth_country')->nullable();
            }
            if (!Schema::hasColumn('applications', 'is_catholic_baptised')) {
                $table->boolean('is_catholic_baptised')->default(false);
            }
            if (!Schema::hasColumn('applications', 'national_id_issue_date')) {
                $table->date('national_id_issue_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'national_id_issue_place')) {
                $table->string('national_id_issue_place')->nullable();
            }
            if (!Schema::hasColumn('applications', 'passport_issue_date')) {
                $table->date('passport_issue_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'passport_issue_country')) {
                $table->string('passport_issue_country')->nullable();
            }
            if (!Schema::hasColumn('applications', 'postal_address_line1')) {
                $table->string('postal_address_line1')->nullable();
            }
            if (!Schema::hasColumn('applications', 'postal_address_line2')) {
                $table->string('postal_address_line2')->nullable();
            }
            if (!Schema::hasColumn('applications', 'alternate_phone')) {
                $table->string('alternate_phone')->nullable();
            }
            if (!Schema::hasColumn('applications', 'academic_year')) {
                $table->string('academic_year')->nullable();
            }
            if (!Schema::hasColumn('applications', 'first_program_choice_id')) {
                $table->unsignedInteger('first_program_choice_id')->nullable();
            }
            if (!Schema::hasColumn('applications', 'second_program_choice_id')) {
                $table->unsignedInteger('second_program_choice_id')->nullable();
            }
            if (!Schema::hasColumn('applications', 'third_program_choice_id')) {
                $table->unsignedInteger('third_program_choice_id')->nullable();
            }
            if (!Schema::hasColumn('applications', 'studied_in_english')) {
                $table->boolean('studied_in_english')->nullable();
            }
            if (!Schema::hasColumn('applications', 'instruction_language_secondary')) {
                $table->string('instruction_language_secondary')->nullable();
            }
            if (!Schema::hasColumn('applications', 'registration_fee_bank')) {
                $table->string('registration_fee_bank')->nullable();
            }
            if (!Schema::hasColumn('applications', 'registration_fee_reference')) {
                $table->string('registration_fee_reference')->nullable();
            }
            if (!Schema::hasColumn('applications', 'declaration_name')) {
                $table->string('declaration_name')->nullable();
            }
            if (!Schema::hasColumn('applications', 'declaration_signed_date')) {
                $table->date('declaration_signed_date')->nullable();
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'first_program_choice_id')) {
                return;
            }

            $table->foreign('first_program_choice_id')->references('id')->on('programs')->onDelete('set null');
            $table->foreign('second_program_choice_id')->references('id')->on('programs')->onDelete('set null');
            $table->foreign('third_program_choice_id')->references('id')->on('programs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['first_program_choice_id']);
            $table->dropForeign(['second_program_choice_id']);
            $table->dropForeign(['third_program_choice_id']);

            $table->dropColumn([
                'other_names',
                'birth_city',
                'birth_division',
                'birth_region',
                'birth_country',
                'is_catholic_baptised',
                'national_id_issue_date',
                'national_id_issue_place',
                'passport_issue_date',
                'passport_issue_country',
                'postal_address_line1',
                'postal_address_line2',
                'alternate_phone',
                'academic_year',
                'first_program_choice_id',
                'second_program_choice_id',
                'third_program_choice_id',
                'studied_in_english',
                'instruction_language_secondary',
                'registration_fee_bank',
                'registration_fee_reference',
                'declaration_name',
                'declaration_signed_date',
            ]);
        });
    }
};
