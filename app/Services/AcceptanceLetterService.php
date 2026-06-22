<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;
use App\Models\MailSetting;
use App\Mail\AcceptanceLetter as AcceptanceLetterMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Renders a degree-type's acceptance-letter template for a student (placeholder
 * substitution), produces the PDF, and emails it. Reused by the conversion hook,
 * the admin download/resend actions and the config preview.
 */
class AcceptanceLetterService
{
    /**
     * The degree type whose template applies to this student (via programme).
     */
    public function degreeType(Student $student)
    {
        $student->loadMissing('program.degreeType');
        return optional($student->program)->degreeType;
    }

    /**
     * Whether an enabled, non-empty template exists for this student.
     */
    public function isEnabled(Student $student): bool
    {
        $dt = $this->degreeType($student);
        $setting = $dt ? $dt->applicationSetting : null;
        return $setting && $setting->acceptance_letter_enabled && filled($setting->acceptance_letter_html);
    }

    /**
     * Resolve the template HTML with the student's values, or null when no
     * enabled template is configured.
     */
    public function resolveHtml(Student $student): ?string
    {
        $dt = $this->degreeType($student);
        $setting = $dt ? $dt->applicationSetting : null;

        if (!$setting || !$setting->acceptance_letter_enabled || !filled($setting->acceptance_letter_html)) {
            return null;
        }

        return strtr($setting->acceptance_letter_html, $this->tokens($student, $dt));
    }

    /**
     * Placeholder => value map (square-bracket tokens, matching the rest of the app).
     */
    public function tokens(Student $student, $degreeType = null): array
    {
        $student->loadMissing(['program.faculty', 'program.degreeType', 'studentEnrolls.session']);
        $degreeType = $degreeType ?: $this->degreeType($student);

        $enroll = $student->studentEnrolls->first();
        $setting = Setting::where('status', '1')->first();

        $name = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
        $admissionDate = $student->admission_date ? date('F j, Y', strtotime($student->admission_date)) : '';

        return [
            '[name]'           => $name,
            '[first_name]'     => $student->first_name ?? '',
            '[last_name]'      => $student->last_name ?? '',
            '[student_id]'     => $student->student_id ?? '',
            '[matricule]'      => optional($enroll)->matricule ?? $student->student_id ?? '',
            '[program]'        => optional($student->program)->title ?? '',
            '[degree_type]'    => optional($degreeType)->title ?? '',
            '[faculty]'        => optional(optional($student->program)->faculty)->title ?? '',
            '[intake]'         => optional(optional($enroll)->session)->title ?? '',
            '[admission_date]' => $admissionDate,
            '[date]'           => date('F j, Y'),
            '[institution]'    => optional($setting)->title ?? config('app.name'),
            '[address]'        => optional($setting)->address ?? '',
            '[email]'          => $student->email ?? '',
            '[phone]'          => $student->phone ?? '',
        ];
    }

    /**
     * Build the acceptance-letter PDF for a student, or null if no template.
     *
     * @return \Barryvdh\DomPDF\PDF|null
     */
    public function pdf(Student $student)
    {
        $body = $this->resolveHtml($student);
        if ($body === null) {
            return null;
        }

        $pdf = Pdf::loadView('admin.acceptance-letter.pdf', [
            'body' => $body,
            'student' => $student,
            'setting' => Setting::where('status', '1')->first(),
        ]);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        return $pdf;
    }

    /**
     * Render the template against sample data for the admin preview (no student).
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function previewPdf($degreeType)
    {
        $setting = Setting::where('status', '1')->first();
        $html = $degreeType->applicationSetting->acceptance_letter_html ?? '<p>'.__('No acceptance letter has been configured yet.').'</p>';

        $sample = [
            '[name]' => 'Jane A. Doe', '[first_name]' => 'Jane', '[last_name]' => 'Doe',
            '[student_id]' => 'PAX25SAMPLE001', '[matricule]' => 'PAX25SAMPLE001',
            '[program]' => optional($degreeType->programs()->first())->title ?? 'Sample Programme',
            '[degree_type]' => $degreeType->title,
            '[faculty]' => optional(optional($degreeType->programs()->first())->faculty)->title ?? 'Sample Faculty',
            '[intake]' => date('Y') . '/' . (date('Y') + 1),
            '[admission_date]' => date('F j, Y'), '[date]' => date('F j, Y'),
            '[institution]' => optional($setting)->title ?? config('app.name'),
            '[address]' => optional($setting)->address ?? '',
            '[email]' => 'jane.doe@example.com', '[phone]' => '+237 6XX XXX XXX',
        ];

        $pdf = Pdf::loadView('admin.acceptance-letter.pdf', [
            'body' => strtr($html, $sample),
            'student' => null,
            'setting' => $setting,
        ]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Render + email the acceptance letter (PDF attached) to the student.
     * Never throws — logs and returns false on failure so callers (e.g. the
     * application→student conversion) are not interrupted.
     */
    public function sendTo(Student $student): bool
    {
        try {
            $pdf = $this->pdf($student);
            if ($pdf === null) {
                return false; // no enabled template for this degree type
            }

            if (!filled($student->email)) {
                return false;
            }

            $mail = MailSetting::where('status', '1')->first();
            if (!$mail || !$mail->sender_email || !$mail->sender_name) {
                Log::warning('Acceptance letter not sent: mail is not configured.');
                return false;
            }

            $setting = Setting::where('status', '1')->first();
            $institution = optional($setting)->title ?? config('app.name');

            $data = [
                'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'institution' => $institution,
                'from' => $mail->sender_email,
                'sender' => $mail->sender_name,
                'subject' => __('Offer of Admission') . ' — ' . $institution,
                'pdf' => $pdf->output(),
            ];

            Mail::to($student->email, $data['name'])->send(new AcceptanceLetterMail($data));

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send acceptance letter: ' . $e->getMessage());
            return false;
        }
    }
}
