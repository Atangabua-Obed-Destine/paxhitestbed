<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;
use App\Models\Semester;
use App\Models\MailSetting;
use App\Models\ProgramSemesterFee;
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

        // Normalise the user's template first (strip Word junk/fixed widths), THEN substitute,
        // so our generated [fee_breakdown] table is injected clean and untouched.
        $tokens = $this->tokens($student, $dt);
        $tokens['[fee_breakdown]'] = $this->feeBreakdownHtml($student);

        return strtr($this->normalizeLetterHtml($setting->acceptance_letter_html), $tokens);
    }

    /**
     * Build the Year-1 first-installment fee breakdown table from the programme's
     * published fee configuration. Returns '' when no fees are configured.
     */
    public function feeBreakdownHtml(Student $student): string
    {
        $student->loadMissing('studentEnrolls.semester');
        $enroll = $student->studentEnrolls->first();
        if (!$enroll || !$student->program_id) {
            return '';
        }

        // The student's first (Year-1) regular semester for this programme.
        $year = optional($enroll->semester)->year;
        $firstSemester = Semester::where('semester_type', 1)
            ->where('is_resit', 0)
            ->when($year, fn ($q) => $q->where('year', $year))
            ->whereHas('programs', fn ($q) => $q->where('program_id', $student->program_id))
            ->orderBy('id')
            ->first() ?? $enroll->semester;

        if (!$firstSemester) {
            return '';
        }

        $setting = Setting::where('status', '1')->first();
        $currency = $setting->currency_symbol ?? 'FCFA';
        $dp = $setting->decimal_place ?? 0;
        $money = fn ($n) => number_format((float) $n, $dp, '.', ',');

        // First-installment fee (with its itemised breakdown) for programme + first semester.
        $firstInstallment = ProgramSemesterFee::with(['breakdowns', 'feesCategory'])
            ->where('program_id', $student->program_id)
            ->where('semester_id', $firstSemester->id)
            ->where('status', 1)
            ->whereHas('feesCategory', fn ($q) => $q->where('is_first_installment', 1))
            ->first();

        $rows = [];
        $total = 0;

        if ($firstInstallment && $firstInstallment->breakdowns->count()) {
            foreach ($firstInstallment->breakdowns as $b) {
                $rows[] = [$b->title, $b->amount];
            }
            $total = $firstInstallment->amount;
        } else {
            // Fallback: list the configured fee categories for that semester.
            $fees = ProgramSemesterFee::with('feesCategory')
                ->where('program_id', $student->program_id)
                ->where('semester_id', $firstSemester->id)
                ->where('status', 1)
                ->get();
            foreach ($fees as $f) {
                $rows[] = [optional($f->feesCategory)->title ?? '—', $f->amount];
                $total += (float) $f->amount;
            }
        }

        if (empty($rows)) {
            return '';
        }

        $html = '<table class="fee-breakdown"><thead><tr>'
              . '<th>' . e(__('Fee Breakdown')) . '</th>'
              . '<th class="amt">' . e(__('Amount') . ' (' . $currency . ')') . '</th>'
              . '</tr></thead><tbody>';
        foreach ($rows as [$label, $amount]) {
            $html .= '<tr><td>' . e($label) . '</td><td class="amt">' . $money($amount) . '</td></tr>';
        }
        $html .= '</tbody><tfoot><tr>'
               . '<th>' . e(__('Total')) . '</th>'
               . '<th class="amt">' . $money($total) . '</th>'
               . '</tr></tfoot></table>';

        return $html;
    }

    /**
     * Strip Word-specific noise and fixed widths/heights that otherwise shrink the
     * letter into a narrow column on the A4 page. Colours, fonts, sizes, bold/italic,
     * alignment and lists are preserved — only sizing/junk that breaks page width is
     * removed so the content flows to the full A4 text area.
     */
    public function normalizeLetterHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // Drop Word's empty <o:p> tags and mso-* style properties.
        $html = preg_replace('/<\/?o:p[^>]*>/i', '', $html);
        $html = preg_replace('/mso-[^:;"\']+:[^;"\']*;?/i', '', $html);

        // Remove fixed width/height/min-/max- in inline styles (keep line-height & font-size).
        $html = preg_replace('/(?<![a-z-])(?:min-|max-)?width\s*:\s*[^;"\']*;?/i', '', $html);
        $html = preg_replace('/(?<!font-)(?<!line-)(?<![a-z])(?:min-|max-)?height\s*:\s*[^;"\']*;?/i', '', $html);

        // Remove text-indent (Word lists use a negative indent that makes the bullet
        // overlap the text in DomPDF).
        $html = preg_replace('/text-indent\s*:\s*[^;"\']*;?/i', '', $html);

        // Remove width/height HTML attributes (Word adds these to tables/cells/images),
        // covering quoted ("451") and unquoted (=451) forms.
        $html = preg_replace('/\s(?:width|height)\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s(?:width|height)\s*=\s*'[^']*'/i", '', $html);
        $html = preg_replace('/\s(?:width|height)\s*=\s*[0-9.]+%?/i', '', $html);

        return $html;
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

        // Sample fee-breakdown table so the admin sees how [fee_breakdown] renders.
        $currency = $setting->currency_symbol ?? 'FCFA';
        $sample['[fee_breakdown]'] = '<table class="fee-breakdown"><thead><tr>'
            . '<th>' . e(__('Fee Breakdown')) . '</th><th class="amt">' . e(__('Amount') . ' (' . $currency . ')') . '</th>'
            . '</tr></thead><tbody>'
            . '<tr><td>First Installment Tuition Fees</td><td class="amt">120,500</td></tr>'
            . '<tr><td>Health &amp; Accident Insurance</td><td class="amt">15,000</td></tr>'
            . '<tr><td>Student Identity Card</td><td class="amt">3,000</td></tr>'
            . '</tbody><tfoot><tr><th>' . e(__('Total')) . '</th><th class="amt">138,500</th></tr></tfoot></table>';

        // Normalise the template first, then substitute (keeps the sample table intact).
        $pdf = Pdf::loadView('admin.acceptance-letter.pdf', [
            'body' => strtr($this->normalizeLetterHtml($html), $sample),
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
