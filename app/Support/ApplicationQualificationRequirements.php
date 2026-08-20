<?php

namespace App\Support;

class ApplicationQualificationRequirements
{
    /**
     * The default qualification cards, used when a degree type has none of its
     * own configured. Mirrors ApplicationDocumentRequirements::all() so the two
     * catalogs resolve through the same fallback contract.
     *
     * A card carries only its identity and prompt — which upload slots it shows
     * comes from the documents tagged with its key, so an administrator can add
     * or remove slots without touching this file.
     */
    public static function all(): array
    {
        return [
            'gce_al' => [
                'label' => __('GCE A-Level or equivalent'),
                'description' => __('Enter your A Level, Baccalaureate, or equivalent qualification and upload its certificate or result slip.'),
                'required' => true,
            ],
            'gce_ol' => [
                'label' => __('GCE O-Level or equivalent'),
                'description' => __('Enter your O Level, BEPC, or equivalent qualification and upload its certificate or result slip.'),
                'required' => true,
            ],
        ];
    }
}
