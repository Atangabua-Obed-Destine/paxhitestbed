<?php

namespace App\Exports;

use App\Services\Academic\CourseRoster;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The course class list as a workbook.
 *
 * Same array as the screen and the PDF, so the three cannot disagree.
 *
 * Counts are written as NUMBERS, never as pre-formatted strings: the whole
 * point of a workbook over a PDF is that the reader sorts, filters and sums it
 * themselves, and a column of text does none of that. The rate is a number too,
 * and blank rather than 0 where no session has been held -- a student with no
 * working days has no attendance rate, and writing 0 would put them at the
 * bottom of a sort beside people who genuinely never turn up.
 */
class CourseClassListExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(protected array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->classList(),
            $this->needsAttention(),
            $this->byClass(),
            $this->sessionBySession(),
            $this->register(),
        ];
    }

    /** The lines above every table, so a detached sheet still says what it is. */
    protected function preamble(string $what): array
    {
        $lines = [
            $this->report['institution'],
            $what,
        ];

        foreach ($this->report['filters'] as $label => $value) {
            if ($value !== '' && $value !== null) {
                $lines[] = $label . ': ' . $value;
            }
        }

        $lines[] = __('Sessions held') . ': ' . $this->report['sessions_held'];
        $lines[] = __('Generated') . ': ' . $this->report['generated_at']->format('d/m/Y H:i');

        if ($this->report['outside']['records'] > 0) {
            $lines[] = __('Excluded by the filters: :n attendance records on :s enrolments outside this class.', [
                'n' => $this->report['outside']['records'],
                's' => $this->report['outside']['students'],
            ]);
        }

        return $lines;
    }

    protected function classList(): ReportSheet
    {
        $rows = [];

        foreach ($this->report['students'] as $i => $s) {
            $rows[] = [
                $i + 1,
                $s['matricule'],
                $s['name'],
                $s['gender'],
                $s['program'],
                $s['semester'],
                $s['section'],
                $s['phone'],
                $s['email'],
                $s['present'],
                $s['absent'],
                $s['leave'],
                $s['holiday'],
                $s['unmarked'],
                $s['rate'],
                $s['last_seen'],
            ];
        }

        return new ReportSheet(
            __('Class list'),
            $this->preamble(__('Class list and attendance')),
            [
                '#', __('field_matricule'), __('field_name'), __('field_gender'),
                __('field_program'), __('field_semester'), __('field_section'),
                __('field_phone'), __('field_email'),
                __('Present'), __('Absent'), __('Leave'), __('Holiday'),
                __('Not marked'), __('Attendance %'), __('Last present'),
            ],
            $rows,
            [],
            [1, 7] // matricule and phone are labels, not quantities
        );
    }

    protected function needsAttention(): ReportSheet
    {
        $rows = [];

        foreach ($this->report['at_risk'] as $s) {
            $rows[] = [
                $s['matricule'],
                $s['name'],
                $s['program'],
                $s['phone'],
                $s['email'],
                $s['present'],
                $s['absent'],
                $s['rate'],
                $s['trailing'],
                implode('; ', $s['reasons']),
            ];
        }

        return new ReportSheet(
            __('Needs attention'),
            $this->preamble(__('Students to follow up')),
            [
                __('field_matricule'), __('field_name'), __('field_program'),
                __('field_phone'), __('field_email'),
                __('Present'), __('Absent'), __('Attendance %'),
                __('Absences running'), __('Why'),
            ],
            $rows,
            [],
            [0, 3]
        );
    }

    protected function byClass(): ReportSheet
    {
        $rows = [];

        foreach ($this->report['by_class'] as $c) {
            $rows[] = [
                $c['program'], $c['semester'], $c['section'], $c['students'],
                $c['present'], $c['absent'], $c['leave'], $c['unmarked'], $c['rate'],
            ];
        }

        return new ReportSheet(
            __('By class'),
            $this->preamble(__('Attendance by teaching group')),
            [
                __('field_program'), __('field_semester'), __('field_section'),
                __('field_students'), __('Present'), __('Absent'), __('Leave'),
                __('Not marked'), __('Attendance %'),
            ],
            $rows
        );
    }

    protected function sessionBySession(): ReportSheet
    {
        $rows = [];

        foreach ($this->report['by_date'] as $d) {
            $rows[] = [
                date('d/m/Y', strtotime($d['date'])),
                date('D', strtotime($d['date'])),
                $d['marked'],
                $d['present'],
                $d['absent'],
                $d['leave'],
                $d['holiday'],
                $d['rate'],
            ];
        }

        return new ReportSheet(
            __('Session by session'),
            $this->preamble(__('Turnout for each session')),
            [
                __('field_date'), __('Day'), __('Marked'), __('Present'),
                __('Absent'), __('Leave'), __('Holiday'), __('Attendance %'),
            ],
            $rows
        );
    }

    /**
     * The register itself: one column per session, one row per student.
     *
     * A blank cell means NOT MARKED, which is not the same as absent -- the
     * legend says so in the preamble, because a reader who assumes blank means
     * absent would invent absences for anyone who joined the course late.
     */
    protected function register(): ReportSheet
    {
        $letters = [
            'present' => __('P'),
            'absent' => __('A'),
            'leave' => __('L'),
            'holiday' => __('H'),
            'unmarked' => '',
        ];

        $headings = [__('field_matricule'), __('field_name')];

        foreach ($this->report['dates'] as $date) {
            $headings[] = date('d/m', strtotime($date));
        }

        $rows = [];

        foreach ($this->report['students'] as $s) {
            $row = [$s['matricule'], $s['name']];

            foreach ($s['strip'] as $mark) {
                $row[] = $letters[$mark] ?? '';
            }

            $rows[] = $row;
        }

        $preamble = $this->preamble(__('Register'));
        $preamble[] = __('P present · A absent · L leave · H holiday · blank means not marked, which is not the same as absent.');

        return new ReportSheet(__('Register'), $preamble, $headings, $rows, [], [0]);
    }
}
