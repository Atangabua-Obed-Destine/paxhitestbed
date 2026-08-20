<?php

namespace App\Support;

use App\Models\Application;
use App\Models\DegreeType;
use App\Services\DegreeTypeFormConfig;

/**
 * Assembles the qualification cards for one application.
 *
 * Every screen that shows academic qualifications — the applicant wizard, the
 * admin create/edit forms, the review page, the PDFs — builds its cards here,
 * so none of them can drift into asking for a document another one already
 * collected. That drift is the bug this feature exists to remove.
 */
class ApplicationQualificationCards
{
    /**
     * @return array{
     *   cards: array<int,array>,
     *   extras: array<int,array>,
     *   identityDocuments: array,
     *   remainingDocuments: array,
     *   qualificationDocuments: array
     * }
     */
    public static function build(?Application $application, ?DegreeType $degreeType, array $identityKeys, ?array $oldHistory = null): array
    {
        $documents = DegreeTypeFormConfig::documents($degreeType);
        $qualifications = DegreeTypeFormConfig::qualifications($degreeType);
        $partition = DegreeTypeFormConfig::partitionDocuments($documents, $qualifications, $identityKeys);

        // Old input wins on a validation redirect so the applicant does not
        // lose what they typed; otherwise read the saved rows.
        $history = $oldHistory !== null
            ? $oldHistory
            : ($application ? $application->academicHistories->map(fn ($h) => $h->toArray())->all() : []);

        $byKey = [];
        $extras = [];
        foreach ($history as $row) {
            $key = $row['qualification_key'] ?? null;
            if ($key && !isset($byKey[$key])) {
                $byKey[$key] = $row;
            } else {
                $extras[] = $row;
            }
        }

        $cards = [];
        foreach ($qualifications as $key => $qualification) {
            $cards[] = [
                'key' => $key,
                'label' => $qualification['label'],
                'description' => $qualification['description'] ?? null,
                'required' => (bool) ($qualification['required'] ?? true),
                'documents' => $partition['qualification'][$key] ?? [],
                'history' => $byKey[$key] ?? [],
            ];
        }

        return [
            'cards' => $cards,
            'extras' => array_values($extras),
            'identityDocuments' => $partition['identity'],
            'remainingDocuments' => $partition['remaining'],
            'qualificationDocuments' => $partition['qualification'],
        ];
    }

    /**
     * Is there anything worth saving in a submitted qualification row?
     *
     * Shared by the applicant and admin write paths so they agree. Testing
     * institution_name alone was too strict: a card filled in with the
     * qualification and awarding body but no school yet would be dropped
     * silently on save.
     */
    public static function rowHasContent(array $history): bool
    {
        foreach (['institution_name', 'awarding_body', 'certificate_obtained', 'start_year', 'end_year', 'country', 'city'] as $field) {
            if (filled($history[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every document key collected inside a card, flattened. Used to keep the
     * Documents step from rendering them a second time.
     */
    public static function documentKeys(array $qualificationDocuments): array
    {
        $keys = [];
        foreach ($qualificationDocuments as $group) {
            foreach (array_keys($group) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
