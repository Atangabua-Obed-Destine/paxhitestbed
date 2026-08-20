<?php

namespace App\Services;

use App\Models\DegreeType;
use App\Models\Field;
use App\Support\ApplicationDocumentRequirements;
use App\Support\ApplicationQualificationRequirements;

/**
 * Resolves the EFFECTIVE application-form configuration for a degree type:
 *  - field/section toggles (per-degree override, else the global Field default)
 *  - the document checklist (per-degree, else the global catalog)
 *  - intro/requirements text and admission-fee settings (per-degree, else env)
 *
 * One place so the applicant portal and the admin preview agree.
 */
class DegreeTypeFormConfig
{
    /** @var array<int,array<string,bool>> cached field maps per degree type id */
    protected static array $fieldCache = [];

    /**
     * Is a form field/section enabled for this degree type?
     * Falls back to the global Field toggle when no per-degree override exists.
     */
    public static function fieldEnabled(?DegreeType $degreeType, string $slug): bool
    {
        if ($degreeType) {
            $map = static::fieldMap($degreeType);
            if (array_key_exists($slug, $map)) {
                return $map[$slug];
            }
        }

        // Global default
        return (int) optional(Field::field($slug))->status === 1;
    }

    /**
     * The resolved field override map for a degree type (slug => bool).
     */
    public static function fieldMap(DegreeType $degreeType): array
    {
        if (!isset(static::$fieldCache[$degreeType->id])) {
            static::$fieldCache[$degreeType->id] = $degreeType->fieldSettings()
                ->pluck('status', 'slug')
                ->map(fn ($s) => (bool) $s)
                ->toArray();
        }

        return static::$fieldCache[$degreeType->id];
    }

    /**
     * The document checklist for this degree type, as an associative array
     * keyed by doc_key with label/description/required/assign_to_column — the
     * same shape the apply form already consumes. Falls back to the global
     * catalog when a degree type has no rows configured.
     *
     * @return array<string,array>
     */
    public static function documents(?DegreeType $degreeType): array
    {
        if ($degreeType) {
            $rows = $degreeType->applicationDocuments()->where('status', 1)->orderBy('sort_order')->get();
            if ($rows->isNotEmpty()) {
                $docs = [];
                foreach ($rows as $row) {
                    $docs[$row->doc_key] = [
                        'label' => $row->label,
                        'description' => $row->description,
                        'required' => (bool) $row->required,
                    ];
                    if ($row->assign_to_column) {
                        $docs[$row->doc_key]['assign_to_column'] = $row->assign_to_column;
                    }
                    if ($row->qualification_group) {
                        $docs[$row->doc_key]['qualification_group'] = $row->qualification_group;
                    }
                }
                return $docs;
            }
        }

        return ApplicationDocumentRequirements::all();
    }

    /**
     * The qualification cards for this degree type, keyed by qual_key. Same
     * per-degree-then-global fallback as documents(), so the applicant portal
     * and the admin screens cannot disagree about which cards exist.
     *
     * @return array<string,array>
     */
    public static function qualifications(?DegreeType $degreeType): array
    {
        if ($degreeType) {
            $rows = $degreeType->applicationQualifications()
                ->where('status', 1)->orderBy('sort_order')->get();

            if ($rows->isNotEmpty()) {
                $cards = [];
                foreach ($rows as $row) {
                    $cards[$row->qual_key] = [
                        'label' => $row->label,
                        'description' => $row->description,
                        'required' => (bool) $row->required,
                    ];
                }
                return $cards;
            }
        }

        return ApplicationQualificationRequirements::all();
    }

    /**
     * Split the document checklist into where each item is actually collected.
     *
     * This is the single place that decides it, so no screen can drift and ask
     * for the same file twice — the bug this whole feature exists to remove.
     *
     * @param  array $identityKeys Keys collected on the Identification tab.
     * @return array{identity: array, qualification: array<string,array>, remaining: array}
     */
    public static function partitionDocuments(array $documents, array $qualifications, array $identityKeys): array
    {
        $identity = [];
        $qualification = [];
        $remaining = [];

        foreach ($documents as $key => $document) {
            $group = $document['qualification_group'] ?? null;

            if (in_array($key, $identityKeys, true)) {
                $identity[$key] = $document;
            } elseif ($group && isset($qualifications[$group])) {
                // Grouped under its card. A group naming a card that is not
                // configured falls through to the Documents step rather than
                // vanishing from the form entirely.
                $qualification[$group][$key] = $document;
            } else {
                $remaining[$key] = $document;
            }
        }

        return ['identity' => $identity, 'qualification' => $qualification, 'remaining' => $remaining];
    }

    /**
     * Resolved settings (intro/requirements/fee) for a degree type. Falls back
     * to the global env-based admission fee when no per-degree row exists.
     *
     * @return array{intro_html:?string,requirements_html:?string,fee_enabled:bool,fee_amount:float,fee_due_days:int,fee_instructions:?string}
     */
    public static function settings(?DegreeType $degreeType): array
    {
        $row = $degreeType ? $degreeType->applicationSetting : null;

        return [
            'intro_html' => $row->intro_html ?? null,
            'requirements_html' => $row->requirements_html ?? ($degreeType->requirements ?? null),
            'fee_enabled' => $row ? (bool) $row->fee_enabled
                : filter_var(env('ADMISSION_FEE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'fee_amount' => $row && !is_null($row->fee_amount)
                ? (float) $row->fee_amount
                : (float) env('ADMISSION_FEE_AMOUNT', 15000),
            'fee_due_days' => $row && !is_null($row->fee_due_days)
                ? (int) $row->fee_due_days
                : (int) env('ADMISSION_FEE_DUE_DAYS', 30),
            'fee_instructions' => $row->fee_instructions ?? env('ADMISSION_FEE_INSTRUCTIONS', null),
        ];
    }
}
