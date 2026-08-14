<?php

namespace App\Services;

use App\Models\DegreeType;
use App\Models\Program;
use App\Models\Setting;
use App\Models\ApplicationSetting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generates a blank, printable application form PDF for a given degree type.
 * Respects the per-degree-type field/section configurations so only enabled
 * fields appear on the printed form.
 */
class BlankApplicationFormService
{
    /**
     * Generate the blank application form PDF for the given degree type.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function pdf(DegreeType $degreeType)
    {
        $setting = Setting::where('status', '1')->first();
        $appSetting = ApplicationSetting::where('slug', 'admission')->where('status', '1')->first();
        $formSettings = DegreeTypeFormConfig::settings($degreeType);

        // Programmes available for this degree type
        $programs = Program::where('status', '1')
            ->where('degree_type_id', $degreeType->id)
            ->orderBy('title', 'asc')
            ->get();

        // Document requirements for this degree type
        $documents = DegreeTypeFormConfig::documents($degreeType);

        // Field enabled helper closure
        $fieldEnabled = function (string $slug) use ($degreeType): bool {
            return DegreeTypeFormConfig::fieldEnabled($degreeType, $slug);
        };

        $data = [
            'degreeType'     => $degreeType,
            'setting'        => $setting,
            'appSetting'     => $appSetting,
            'formSettings'   => $formSettings,
            'programs'       => $programs,
            'documents'      => $documents,
            'fieldEnabled'   => $fieldEnabled,
        ];

        $pdf = Pdf::loadView('admin.degree-type.blank-form-pdf', $data);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        return $pdf;
    }
}
