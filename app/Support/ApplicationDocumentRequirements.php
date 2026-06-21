<?php

namespace App\Support;

class ApplicationDocumentRequirements
{
    /**
     * Return the document checklist configuration shared between applicant and admin views.
     */
    public static function all(): array
    {
        return [
            'birth_certificate' => [
                'label' => __('Certified Birth Certificate'),
                'description' => __('Scan of the applicant\'s birth certificate as issued by the civil registry.'),
                'required' => true,
            ],
            'baptism_certificate' => [
                'label' => __('Baptism Certificate (if applicable)'),
                'description' => __('Upload only if you indicated you are baptised.'),
                'required' => false,
            ],
            'national_id_card' => [
                'label' => __('National ID Card or Passport'),
                'description' => __('Upload a single PDF containing both sides of the ID or the information page of the passport.'),
                'required' => true,
            ],
            'gce_ol_transcript' => [
                'label' => __('GCE Ordinary Level / Probatoire Transcript'),
                'description' => __('Official transcript or statement of results for your Ordinary Level or Probatoire examinations.'),
                'required' => true,
                'assign_to_column' => 'school_transcript',
            ],
            'gce_ol_certificate' => [
                'label' => __('GCE Ordinary Level / Probatoire Certificate'),
                'description' => __('Certificate awarded for your Ordinary Level or Probatoire examinations.'),
                'required' => true,
                'assign_to_column' => 'school_certificate',
            ],
            'gce_al_transcript' => [
                'label' => __('GCE Advanced Level / Baccalaureate Transcript'),
                'description' => __('Official transcript or statement of results for your Advanced Level or Baccalaureate examinations.'),
                'required' => true,
                'assign_to_column' => 'collage_transcript',
            ],
            'gce_al_certificate' => [
                'label' => __('GCE Advanced Level / Baccalaureate Certificate'),
                'description' => __('Certificate awarded for your Advanced Level or Baccalaureate examinations.'),
                'required' => true,
                'assign_to_column' => 'collage_certificate',
            ],
            'medical_certificate' => [
                'label' => __('Medical Fitness Certificate'),
                'description' => __('Recent medical report confirming fitness for studies.'),
                'required' => false,
            ],
        ];
    }
}
