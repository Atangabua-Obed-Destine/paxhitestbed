{{--
    Everything the transcript page is made of, shared by the three views
    that show one: download, print, and the bulk print of many.

    They were near-identical copies of ~870 lines before this - the two that
    existed differed by nine, all of them in the closing script block - so a
    fix applied to one silently missed the other. A bulk view would have made
    that three.
--}}
@php
    // Resolve enrollment
    if (!isset($currentEnroll)) {
        $currentEnroll = \App\Models\StudentEnroll::where('student_id', $row->id)
            ->with('program')
            ->orderBy('id', 'desc')
            ->first();
    }
    if (!isset($selectedProgramId)) {
        $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : $row->program_id;
    }

    // Pre-compute CGPA
    $total_credits = 0;
    $total_cgpa = 0;
    $total_credits_earned = 0;
    $total_courses = 0;
    $starting_year = '';
    $ending_year = '';
    $unique_courses = [];

    foreach ($row->studentEnrolls as $item) {
        if ($item->program_id != $selectedProgramId || $item->matricule != $currentEnroll->matricule) continue;

        if (!$starting_year && $item->session) $starting_year = $item->session->title;
        if ($item->session) $ending_year = $item->session->title;

        if (isset($item->subjectMarks)) {
            foreach ($item->subjectMarks as $mark) {
                if ($mark->is_visible_to_student) {
                    $marks_per = round($mark->total_marks);
                    $credit = $mark->subject->credit_hour;

                    foreach ($grades as $grade) {
                        if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                            $total_cgpa += ($grade->point * $credit);
                            $total_credits += $credit;
                            if ($grade->point > 0) $total_credits_earned += $credit;
                            if (!isset($unique_courses[$mark->subject_id])) {
                                $unique_courses[$mark->subject_id] = true;
                                $total_courses++;
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    $safe_credits = $total_credits > 0 ? $total_credits : 1;
    $com_gpa = $total_cgpa / $safe_credits;

    // Academic standing
    if ($com_gpa >= 3.6) $standing = 'First Class (Distinction)';
    elseif ($com_gpa >= 3.0) $standing = 'Second Class Upper';
    elseif ($com_gpa >= 2.5) $standing = 'Second Class Lower';
    elseif ($com_gpa >= 2.0) $standing = 'Third Class';
    elseif ($com_gpa >= 1.0) $standing = 'Pass';
    elseif ($total_courses > 0) $standing = 'Fail';
    else $standing = 'N/A';

    // Semester items
    $semester_items = [];
    $semester_keys = [];
    foreach ($row->studentEnrolls as $enroll) {
        if (isset($enroll->session) && isset($enroll->semester) && isset($enroll->section) && $enroll->program_id == $selectedProgramId && $enroll->matricule == $currentEnroll->matricule) {
            $key = $enroll->session->title . '|' . $enroll->semester->title;
            if (!in_array($key, $semester_keys)) {
                $semester_items[] = [$enroll->session->title, $enroll->semester->title, $enroll->section->title];
                $semester_keys[] = $key;
            }
        }
    }
@endphp

<div class="tp-page printable">
    {{-- Letterhead, configured under Academic → Letterhead. Was a hardcoded
         filename, so changing it meant editing this view. --}}
    {{-- forPdf must stay false: this page is rendered by the browser, not by
         dompdf. With it true the letterhead's image src becomes a local
         filesystem path (C:\xampp\...), which dompdf can read and a browser
         cannot — so the logo silently failed to load. --}}
    @include('partials.letterhead', ['forPdf' => false])

    @php
        // The site title from Settings, so the watermark follows the
        // institution rather than being written into the markup. Sized down for
        // longer names, which otherwise fill the page corner to corner.
        $watermark = trim((string) institution_name());
        $watermarkSize = mb_strlen($watermark) > 34 ? 34 : (mb_strlen($watermark) > 22 ? 44 : 56);
    @endphp

    @if($watermark !== '')
    <div class="tp-watermark" aria-hidden="true">
        <span style="font-size: {{ $watermarkSize }}px;">{{ $watermark }}</span>
    </div>
    @endif

    <div class="tp-content">

        {{-- Document Title --}}
        <div class="tp-doc-title">
            <h2>Academic Transcript</h2>
            <div class="tp-doc-subtitle">Official Record of Academic Achievement</div>
        </div>

        {{-- Student Information --}}
        <div class="tp-info-section">
            <div class="tp-info-grid">
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_matricule') }}</div>
                    <div class="tp-info-cell tp-info-value"><span class="tp-matricule">{{ $currentEnroll->matricule ?? $row->student_id }}</span></div>
                    <div class="tp-info-cell tp-info-label">{{ __('field_program') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ $currentEnroll->program->title ?? $row->program->title ?? 'N/A' }}</div>
                </div>
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_name') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ strtoupper($row->first_name . ' ' . $row->last_name) }}</div>
                    <div class="tp-info-cell tp-info-label">{{ __('field_batch') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ $row->batch->title ?? 'N/A' }}</div>
                </div>
                @php
                    // The national exam code CNOENC issued this student, shown
                    // only once it has been recorded (Admission → HND Exam
                    // Codes). A student who changed programme keeps the code of
                    // the programme this transcript is for. Two years of HND
                    // means two codes, so each is shown against its level.
                    $examCodes = \App\Models\StudentExamCode::where('student_id', $row->id)
                        ->where(function ($query) use ($selectedProgramId) {
                            $query->where('program_id', $selectedProgramId)->orWhereNull('program_id');
                        })
                        ->orderBy('level')->get();
                @endphp
                @if($examCodes->isNotEmpty())
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('HND Exam Code') }}</div>
                    <div class="tp-info-cell tp-info-value">
                        @if($examCodes->count() === 1)
                            <span class="tp-matricule">{{ $examCodes->first()->code }}</span>
                        @else
                            {{ $examCodes->map(fn ($c) => __('Level :level', ['level' => $c->level]) . ': ' . $c->code)->implode('   ') }}
                        @endif
                    </div>
                    {{-- Empty, not absent: the cells hold the column widths so
                         this row lines up with the ones above it. --}}
                    <div class="tp-info-cell"></div>
                    <div class="tp-info-cell"></div>
                </div>
                @endif
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_gender') }}</div>
                    <div class="tp-info-cell tp-info-value">
                        @if($row->gender == 1) {{ __('gender_male') }}
                        @elseif($row->gender == 2) {{ __('gender_female') }}
                        @elseif($row->gender == 3) {{ __('gender_other') }}
                        @endif
                    </div>
                    <div class="tp-info-cell tp-info-label">{{ __('field_starting_year') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ $starting_year }}</div>
                </div>
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">{{ __('field_dob') }}</div>
                    <div class="tp-info-cell tp-info-value">{{ date($setting->date_format ?? 'd-m-Y', strtotime($row->dob)) }}</div>
                    {{-- Ending year removed: it repeated the starting year on
                         every record here, and a transcript is issued against a
                         date rather than a closed period. The date it was
                         issued takes the slot so the row is not left half
                         empty. --}}
                    <div class="tp-info-cell tp-info-label">Date Issued</div>
                    <div class="tp-info-cell tp-info-value">{{ date('F d, Y') }}</div>
                </div>
                @if($row->nationality)
                <div class="tp-info-row">
                    <div class="tp-info-cell tp-info-label">Nationality</div>
                    <div class="tp-info-cell tp-info-value">{{ $row->nationality }}</div>
                    {{-- Empty, not absent: the cells hold the column widths so
                         this row lines up with the ones above it. --}}
                    <div class="tp-info-cell"></div>
                    <div class="tp-info-cell"></div>
                </div>
                @endif
            </div>
        </div>

        {{-- Grading Scale: above the results, so the reader knows what a grade
             means before meeting one. The totals follow the table instead. --}}
        <div class="tp-grade-scale tp-grade-scale-top">
            <div class="tp-grade-scale-title">Grading Scale</div>
            <div class="tp-grade-scale-grid">
                @foreach($grades as $grade)
                <div class="tp-grade-scale-item">
                    <span class="tp-gs-grade">{{ $grade->title }}</span>
                    <span class="tp-gs-point">({{ number_format($grade->point, 1) }})</span>
                    <span class="tp-gs-range">{{ number_format($grade->min_mark, 0) }}-{{ number_format($grade->max_mark, 0) }}%</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Academic Records Table --}}
        <table class="tp-records-table">
            <thead>
                <tr>
                    <th class="tp-col-code tp-col-left">Code</th>
                    <th class="tp-col-title tp-col-left">Course Title</th>
                    <th class="tp-col-type">Type</th>
                    <th class="tp-col-cv">Credit</th>
                    <th class="tp-col-att">Attempted</th>
                    <th class="tp-col-ern">Earned</th>
                    <th class="tp-col-gpt">Grade Pt</th>
                    <th class="tp-col-grd">Grade</th>
                    <th class="tp-col-qpt">Quality Pts</th>
                </tr>
            </thead>

            @foreach($semester_items as $semIdx => $semester_item)
            <tbody>
                {{-- Semester Header --}}
                <tr class="tp-sem-header">
                    <td colspan="9">{{ $semester_item[1] }} &mdash; {{ $semester_item[0] }} (Section: {{ $semester_item[2] }})</td>
                </tr>
                {{-- Column Headers --}}
                <tr class="tp-col-headers">
                    <th class="tp-col-code tp-col-left">Code</th>
                    <th class="tp-col-title tp-col-left">Course Title</th>
                    <th class="tp-col-type">Type</th>
                    <th class="tp-col-cv">Credit</th>
                    <th class="tp-col-att">Attempted</th>
                    <th class="tp-col-ern">Earned</th>
                    <th class="tp-col-gpt">Grade Pt</th>
                    <th class="tp-col-grd">Grade</th>
                    <th class="tp-col-qpt">Quality Pts</th>
                </tr>

                @php
                    $sem_credits = 0;
                    $sem_qp = 0;
                    $sem_earned = 0;
                @endphp

                @foreach($row->studentEnrolls as $item)
                @if(isset($item->semester) && isset($item->session) && $semester_item[1] == $item->semester->title && $semester_item[0] == $item->session->title && $item->program_id == $selectedProgramId && $item->matricule == $currentEnroll->matricule)
                @foreach($item->subjects as $subject)
                @php
                    $creditsAttempted = (float) $subject->credit_hour;
                    $sem_credits += $creditsAttempted;
                    $subject_grade = null;
                    $subjectGradePoint = null;
                    $subjectQualityPoints = null;
                    $creditsEarned = 0;

                    $typeLabel = $subject->subject_type == 1 ? 'C' : ($subject->subject_type == 2 ? 'UR' : 'E');

                    if (isset($item->subjectMarks)) {
                        foreach ($item->subjectMarks as $mark) {
                            if ($mark->subject_id == $subject->id) {
                                if ($mark->is_visible_to_student) {
                                    $marks_per = round($mark->total_marks);
                                    foreach ($grades as $grade) {
                                        if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                            $subjectGradePoint = (float) $grade->point;
                                            $subjectQualityPoints = $subjectGradePoint * $creditsAttempted;
                                            $sem_qp += $subjectQualityPoints;
                                            if ($subjectGradePoint > 0) {
                                                $sem_earned += $creditsAttempted;
                                                $creditsEarned = $creditsAttempted;
                                            }
                                            $subject_grade = $grade->title;
                                            break;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                @endphp
                <tr>
                    <td class="tp-td-code">{{ $subject->code }}</td>
                    <td class="tp-td-title">{{ $subject->title }}</td>
                    <td class="tp-td-type">{{ $typeLabel }}</td>
                    <td>{{ number_format($creditsAttempted, 1) }}</td>
                    <td>{{ number_format($creditsAttempted, 1) }}</td>
                    <td>{{ !is_null($subjectGradePoint) ? number_format($creditsEarned, 1) : '—' }}</td>
                    <td>{{ !is_null($subjectGradePoint) ? number_format($subjectGradePoint, 2) : '—' }}</td>
                    <td class="tp-td-grade">{{ $subject_grade ?? '—' }}</td>
                    <td>{{ !is_null($subjectQualityPoints) ? number_format($subjectQualityPoints, 2) : '—' }}</td>
                </tr>
                @endforeach
                @endif
                @endforeach

                @php
                    $semGpa = $sem_credits > 0 ? $sem_qp / $sem_credits : 0;
                @endphp

                {{-- Semester subtotal --}}
                <tr class="tp-sem-subtotal">
                    <td class="tp-td-left" colspan="3"><strong>Semester Totals</strong></td>
                    <td><strong>{{ number_format($sem_credits, 1) }}</strong></td>
                    <td><strong>{{ number_format($sem_credits, 1) }}</strong></td>
                    <td><strong>{{ number_format($sem_earned, 1) }}</strong></td>
                    <td colspan="2"></td>
                    <td><strong>{{ number_format($sem_qp, 2) }}</strong></td>
                </tr>
                <tr class="tp-sem-gpa">
                    <td class="tp-td-left" colspan="4">Semester GPA: <span class="tp-sem-gpa-val">{{ number_format($semGpa, 2) }}</span></td>
                    <td colspan="5" style="text-align:right; font-size:8.5px; color:#555;">Credits Earned: {{ number_format($sem_earned, 1) }} / {{ number_format($sem_credits, 1) }}</td>
                </tr>
            </tbody>
            @endforeach
        </table>

        {{-- Summary: the totals read after the results they are drawn from.
             Academic standing is left off — it is awarded on graduation, and a
             transcript issued mid-programme should not appear to confer one. --}}
        <div class="tp-summary-bar">
            <div class="tp-summary-item">
                <span class="tp-summary-label">Cumulative GPA</span>
                <span class="tp-summary-value tp-gpa-big">{{ number_format((float)$com_gpa, 2, '.', '') }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Total Credits</span>
                <span class="tp-summary-value">{{ number_format((float)$total_credits, 1) }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Credits Earned</span>
                <span class="tp-summary-value">{{ number_format((float)$total_credits_earned, 1) }}</span>
            </div>
            <div class="tp-summary-item">
                <span class="tp-summary-label">Courses</span>
                <span class="tp-summary-value">{{ $total_courses }}</span>
            </div>
        </div>

        {{-- Key: what every column and abbreviation on this transcript means, so
             it can be read by someone outside the school. --}}
        <div class="tp-key">
            <div class="tp-grade-scale-title">Key to this Transcript</div>
            <div class="tp-key-grid">
                <div class="tp-key-item"><span class="tp-key-term">Code</span> The course code as it appears in the programme.</div>
                {{-- The three course types get a line each: bundled into one
                     "Type" entry, a reader looking up C or UR did not find them.
                     The wording is the school's own, from the subject form. --}}
                <div class="tp-key-item"><span class="tp-key-term">Type</span> What kind of course it is: C, UR or E.</div>
                <div class="tp-key-item"><span class="tp-key-term">C</span> Compulsory — required for this programme.</div>
                <div class="tp-key-item"><span class="tp-key-term">UR</span> University Requirement — required of every student.</div>
                <div class="tp-key-item"><span class="tp-key-term">E</span> Elective — chosen by the student.</div>
                <div class="tp-key-item"><span class="tp-key-term">Credit</span> The credit value of the course.</div>
                <div class="tp-key-item"><span class="tp-key-term">Attempted</span> Credits the student sat for.</div>
                <div class="tp-key-item"><span class="tp-key-term">Earned</span> Credits passed. A failed course earns none.</div>
                <div class="tp-key-item"><span class="tp-key-term">Grade Pt</span> The point value of the grade, from the Grading Scale above.</div>
                <div class="tp-key-item"><span class="tp-key-term">Grade</span> The letter grade awarded.</div>
                <div class="tp-key-item"><span class="tp-key-term">Quality Pts</span> Grade Pt &times; Credit — what the course contributes to the GPA.</div>
                <div class="tp-key-item"><span class="tp-key-term">Semester GPA</span> Quality Points for the semester &divide; credits attempted in it.</div>
                <div class="tp-key-item"><span class="tp-key-term">Cumulative GPA</span> The same, across every semester on this transcript.</div>
                <div class="tp-key-item"><span class="tp-key-term">&mdash;</span> No mark has been published for that course yet.</div>
            </div>
        </div>

        {{-- Signatures --}}
        <div class="tp-footer-section">
            <div class="tp-signatures">
                <div class="tp-sig-block">
                    <div class="tp-sig-line"></div>
                    <div class="tp-sig-label">{!! $marksheet->footer_left !!}</div>
                </div>
                <div class="tp-sig-block">
                    <div class="tp-sig-line"></div>
                    <div class="tp-sig-label">{!! $marksheet->footer_center !!}</div>
                </div>
                <div class="tp-sig-block">
                    <div class="tp-sig-line"></div>
                    <div class="tp-sig-label">{!! $marksheet->footer_right !!}</div>
                </div>
            </div>
        </div>

        {{-- Verification --}}
        @if(!empty($transcriptRecord))
        <div class="tp-verify">
            <div class="tp-verify-qr">
                {{-- SVG, because the PNG back end needs the imagick extension
                     and this has to work on any server. --}}
                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                        ->size(220)->margin(0)
                        ->errorCorrection('M')
                        ->generate($transcriptRecord->verification_url) !!}
            </div>
            <div class="tp-verify-text">
                <strong>{{ __('Verify this transcript') }}</strong>
                {{ __('Scan the code, or visit') }}
                {{ $transcriptRecord->verification_url }}<br>
                {{ __('Reference') }}:
                <span class="tp-verify-code">{{ $transcriptRecord->verification_code }}</span>
                &middot; {{ __('Issued') }}
                {{ $transcriptRecord->issued_at?->format('d/m/Y') }}
            </div>
        </div>
        @endif

        {{-- End marker --}}
        <div class="tp-end-marker">End of Transcript</div>

    </div>{{-- end .tp-content --}}
</div>{{-- end .tp-page --}}
