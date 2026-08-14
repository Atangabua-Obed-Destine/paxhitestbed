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
        $feeData = $this->getFeeBreakdownData($student);
        $tokens['[fee_breakdown]'] = $feeData['html'];
        $tokens['[fee_breakdown_total]'] = $feeData['total'];
        $tokens['[fee_breakdown_total_words]'] = $feeData['words'];

        return strtr($this->normalizeLetterHtml($setting->acceptance_letter_html), $tokens);
    }

    /**
     * Build the Year-1 first-installment fee breakdown table from the programme's
     * published fee configuration. Returns '' when no fees are configured.
     */
    public function feeBreakdownHtml(Student $student): string
    {
        return $this->getFeeBreakdownData($student)['html'];
    }

    /**
     * Get the fee breakdown HTML, numeric total, and total in words.
     */
    public function getFeeBreakdownData(Student $student): array
    {
        $emptyResult = ['html' => '', 'total' => '0', 'words' => 'Zero'];

        $student->loadMissing('studentEnrolls.semester');
        $enroll = $student->studentEnrolls->first();
        if (!$enroll || !$student->program_id) {
            return $emptyResult;
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
            return $emptyResult;
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
            return $emptyResult;
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

        $words = 'Zero';
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
            $words = ucwords($formatter->format($total));
        }

        return [
            'html' => $html,
            'total' => $money($total),
            'words' => $words
        ];
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
        $dob = $student->dob ? date('F j, Y', strtotime($student->dob)) : '';

        // Retrieve place of birth from the original application (linked by registration_no)
        $application = \App\Models\Application::where('registration_no', $student->registration_no)->first();
        $placeOfBirthParts = [];
        if ($application) {
            if ($application->birth_city) $placeOfBirthParts[] = $application->birth_city;
            if ($application->birth_division) $placeOfBirthParts[] = $application->birth_division;
            if ($application->birth_region) $placeOfBirthParts[] = $application->birth_region;
            if ($application->birth_country) $placeOfBirthParts[] = $application->birth_country;
        }
        $placeOfBirth = implode(', ', $placeOfBirthParts);

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
            '[dob]'            => $dob,
            '[place_of_birth]' => $placeOfBirth,
            '[payment_deadlines]' => $this->getPaymentDeadlinesHtml($student, $setting),
        ];
    }

    /**
     * Build the payment deadlines HTML list based on the student's program and first-year semesters.
     */
    public function getPaymentDeadlinesHtml(Student $student, ?Setting $setting = null): string
    {
        $student->loadMissing('studentEnrolls.semester');
        $enroll = $student->studentEnrolls->first();
        if (!$enroll || !$student->program_id) {
            return '';
        }

        // Get the admission year or current year as base
        $baseYear = $student->admission_date ? date('Y', strtotime($student->admission_date)) : date('Y');

        // Find all non-resit semesters for Year 1
        $semesters = Semester::where('is_resit', 0)
            ->where('year', '1') // Based on your db schema, year is often stored as "1" or integer 1
            ->whereHas('programs', fn ($q) => $q->where('program_id', $student->program_id))
            ->get();

        if ($semesters->isEmpty()) {
            return '';
        }

        // Fetch all installment fees for these semesters
        $installmentFees = ProgramSemesterFee::with(['feesCategory', 'semester'])
            ->where('program_id', $student->program_id)
            ->whereIn('semester_id', $semesters->pluck('id'))
            ->where('status', 1)
            ->get()
            ->filter(function ($fee) {
                $categoryTitle = strtolower($fee->feesCategory->title ?? '');
                return optional($fee->feesCategory)->is_first_installment ||
                       optional($fee->feesCategory)->is_second_installment ||
                       str_contains($categoryTitle, 'installment') ||
                       str_contains($categoryTitle, 'instalment');
            })
            ->sortBy(function ($fee) {
                // Sort roughly by semester type then due month
                return [optional($fee->semester)->semester_type, $fee->due_month];
            });

        if ($installmentFees->isEmpty()) {
            return '';
        }

        $currency = $setting->currency_symbol ?? 'FCFA';
        $dp = $setting->decimal_place ?? 0;
        
        $html = '<ul style="list-style-type: none; padding-left: 0; margin-bottom: 0;">';

        // Set up NumberFormatter for spelled-out amounts
        $formatter = null;
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
        }

        foreach ($installmentFees as $fee) {
            $categoryName = $fee->feesCategory->title ?? 'Installment';
            $categoryName = str_replace(['First', 'Second', 'Third', 'Instalment'], ['1st', '2nd', '3rd', 'Installment'], $categoryName);

            $amountNum = number_format((float) $fee->amount, $dp, '.', ',');
            $amountWords = $formatter ? ucwords($formatter->format($fee->amount)) : '';

            $dueDateStr = 'TBD';
            if ($fee->due_month && $fee->due_day) {
                // If due month is early in the year (Jan-July), bump the year to account for Spring semester
                $year = $fee->due_month <= 7 ? $baseYear + 1 : $baseYear;
                $dueDate = \Carbon\Carbon::createFromDate($year, $fee->due_month, $fee->due_day);
                $dueDateStr = $dueDate->format('l, F jS, Y');
            } elseif (optional($fee->feesCategory)->is_first_installment) {
                $dueDateStr = 'Upon Enrollment';
            }

            // e.g. - 1st Installment: Tuesday September 30th 2025 being (150,000) One Hundred and Fifty Thousand FCFA
            $html .= '<li>- ' . e($categoryName) . ': ' . e($dueDateStr) . ' being (' . $amountNum . ') ' . e($amountWords) . ' ' . e($currency) . '</li>';
        }
        $html .= '</ul>';

        return $html;
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
            '[dob]' => 'January 1, 2000', '[place_of_birth]' => 'Buea, South West, Cameroon',
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

        $sample['[fee_breakdown_total]'] = '138,500';
        $sample['[fee_breakdown_total_words]'] = 'One Hundred Thirty-Eight Thousand Five Hundred';
        
        $sample['[payment_deadlines]'] = '<ul style="list-style-type: none; padding-left: 0; margin-bottom: 0;">'
            . '<li>- 1st Installment: Tuesday, September 30th, ' . date('Y') . ' being (120,500) One Hundred Twenty Thousand Five Hundred ' . $currency . '</li>'
            . '<li>- 2nd Installment: Saturday, January 31st, ' . (date('Y') + 1) . ' being (18,000) Eighteen Thousand ' . $currency . '</li>'
            . '</ul>';

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
