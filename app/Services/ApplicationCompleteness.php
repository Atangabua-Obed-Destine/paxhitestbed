<?php

namespace App\Services;

use App\Models\Application;

/**
 * Is an application complete enough to be submitted?
 *
 * There is one definition of "complete" in this system and it lives in
 * Web\ApplicationController::update() — the validation rules the Submit button
 * enforces. This class answers the same question against *saved* data instead
 * of a posted form, which is what the two callers that have no request need:
 *
 *   - the payment gate: an applicant may not pay until the form is finished, so
 *     that approving the fee can submit the application without ever having to
 *     ask whether it was ready;
 *   - the automatic submission that follows fee approval.
 *
 * Keep this in step with the rules in update(). Where that method writes
 * 'required' for a field, this must require it too; where it writes 'nullable',
 * this must not. scripts/application_completeness_test.php walks those rules and
 * fails if the two drift apart.
 */
class ApplicationCompleteness
{
    /**
     * Every unmet requirement, in the order the wizard presents them.
     *
     * @return array<int, array{step: int, label: string}>
     */
    public static function missing(Application $application): array
    {
        $degreeType = $application->degreeType;
        $missing = [];

        $add = function (int $step, string $label) use (&$missing) {
            $missing[] = ['step' => $step, 'label' => $label];
        };

        $enabled = function (string $slug) use ($degreeType): bool {
            return DegreeTypeFormConfig::fieldEnabled($degreeType, $slug);
        };

        $blank = function (string $column) use ($application): bool {
            $value = $application->{$column};
            return $value === null || (is_string($value) && trim($value) === '');
        };

        /* ---- Step 1: personal details ---------------------------------- */

        // Required by update() whatever the degree type.
        $always = [
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'gender' => __('Gender'),
            'dob' => __('Date of birth'),
            'nationality' => __('Nationality'),
            'country' => __('Country of residence'),
            'present_province' => __('Region / province of residence'),
            'present_district' => __('Division / district of residence'),
            'phone' => __('Phone number'),
            'email' => __('Email address'),
            // Not listed: the passport photograph and the identity document are
            // optional, so neither may hold up payment or submission.
        ];
        foreach ($always as $column => $label) {
            if ($blank($column)) {
                $add(1, $label);
            }
        }

        $conditional = [
            'application_birth_city' => ['birth_city', __('Town / city of birth')],
            'application_birth_division' => ['birth_division', __('Division of birth')],
            'application_birth_region' => ['birth_region', __('Region of birth')],
            'application_birth_country' => ['birth_country', __('Country of birth')],
        ];
        foreach ($conditional as $slug => $pair) {
            if ($enabled($slug) && $blank($pair[0])) {
                $add(1, $pair[1]);
            }
        }

        // update() requires the secondary language of instruction only when the
        // applicant has answered that they did *not* study in English.
        if ($enabled('application_instruction_language_secondary')
            && $enabled('application_studied_in_english')
            && $application->studied_in_english !== null
            && (int) $application->studied_in_english === 0
            && $blank('instruction_language_secondary')) {
            $add(1, __('Language of instruction at secondary school'));
        }

        /* ---- Step 2: family -------------------------------------------- */

        if ($enabled('application_guardians')) {
            $guardians = $application->guardians()->get();
            if ($guardians->isEmpty()) {
                $add(2, __('At least one parent or guardian'));
            } else {
                foreach ($guardians as $index => $guardian) {
                    $fields = [
                        'full_name' => __('full name'),
                        'type' => __('relationship type'),
                        'phone_primary' => __('phone number'),
                    ];
                    foreach ($fields as $column => $label) {
                        if (trim((string) $guardian->{$column}) === '') {
                            $add(2, __('Guardian :position — :field', [
                                'position' => $index + 1,
                                'field' => $label,
                            ]));
                        }
                    }
                }
            }
        }

        /* ---- Step 3: academic qualifications ---------------------------- */

        if ($enabled('application_academic_history')) {
            $history = $application->academicHistories()->get();
            if ($history->isEmpty()) {
                $add(3, __('At least one academic qualification'));
            } else {
                foreach ($history as $index => $row) {
                    if (trim((string) $row->institution_name) === '') {
                        $label = trim((string) $row->certificate_obtained) !== ''
                            ? $row->certificate_obtained
                            : __('Qualification :position', ['position' => $index + 1]);
                        $add(3, __(':qualification — school attended', ['qualification' => $label]));
                    }
                }
            }
        }

        /* ---- Step 4: programme ------------------------------------------ */

        if (!$application->program_id) {
            $add(4, __('Programme of study'));
        }
        if ($enabled('application_academic_year') && $blank('academic_year')) {
            $add(4, __('Academic year'));
        }

        /* ---- Step 6: documents ------------------------------------------ */

        if ($enabled('application_document_checklist')) {
            $held = $application->documents()
                ->pluck('file_path', 'document_type')
                ->filter(function ($path) {
                    return trim((string) $path) !== '';
                })
                ->toArray();

            foreach (DegreeTypeFormConfig::documents($degreeType) as $key => $document) {
                if (!empty($document['required']) && !isset($held[$key])) {
                    $add(6, $document['name'] ?? $key);
                }
            }
        }

        /* ---- Step 7: declaration ---------------------------------------- */

        if ($enabled('application_declaration')) {
            if ($blank('declaration_name')) {
                $add(7, __('Name typed on the declaration'));
            }
            if ($blank('declaration_signed_date')) {
                $add(7, __('Date of the declaration'));
            }
            if (!self::hasAgreedToTerms($application)) {
                $add(7, __('Agreement to the declaration and terms'));
            }
        }

        return $missing;
    }

    /** Nothing outstanding — the application would pass Submit as it stands. */
    public static function isComplete(Application $application): bool
    {
        return self::missing($application) === [];
    }

    /** The unmet requirements as plain labels, for a message or an API reply. */
    public static function missingLabels(Application $application): array
    {
        return array_values(array_map(function ($item) {
            return $item['label'];
        }, self::missing($application)));
    }

    /**
     * Has the applicant ticked the declaration?
     *
     * Consent lives in portal_meta rather than a column of its own. It used to be
     * written only at the moment of submission; drafts persist it now, because
     * under fee-triggered submission the applicant has to have agreed *before*
     * paying — there is no later moment at which they press a button.
     */
    public static function hasAgreedToTerms(Application $application): bool
    {
        $meta = $application->portal_meta;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        return is_array($meta) && !empty($meta['agreed_to_terms']);
    }
}
