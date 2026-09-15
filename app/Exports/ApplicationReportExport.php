<?php

namespace App\Exports;

use App\Services\ApplicationDemandReport;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The admissions board report as a workbook.
 *
 * The same figures as the PDF, built from the same array, laid out one subject
 * per tab so each can be sorted and filtered: the summary, programmes,
 * faculties, where applicants would go if a programme is not opened, first
 * choice against second, and the full register.
 */
class ApplicationReportExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(protected array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summary(),
            $this->programmes(),
            $this->faculties(),
            $this->ifNotOpened(),
            $this->choiceFlow(),
            $this->register(),
        ];
    }

    protected function preamble(string $heading): array
    {
        $lines = [
            $this->report['institution'],
            $heading,
            __('Generated') . ' ' . $this->report['generated_at']->format('d M Y H:i'),
        ];

        foreach ($this->report['filters'] as $label => $value) {
            $lines[] = $label . ': ' . $value;
        }

        return $lines;
    }

    protected function label(?string $signal): string
    {
        return $signal ? ($this->report['signal_labels'][$signal] ?? '') : '';
    }

    protected function summary(): ApplicationReportSheet
    {
        $t = $this->report['totals'];
        $rows = [];
        $emphasis = [];

        $section = function (string $title) use (&$rows, &$emphasis) {
            if ($rows) {
                $rows[] = [''];
            }
            $emphasis[] = count($rows);
            $rows[] = [$title];
        };

        $section(__('AT A GLANCE'));
        $rows[] = [__('Applications'), $t['applications']];
        $rows[] = [__('Approved'), $t['approved']];
        $rows[] = [__('Awaiting approval'), $t['in_progress']];
        $rows[] = [__('Refused'), $t['refused']];
        if ($this->report['drafts_counted']) {
            $rows[] = [__('Unfinished drafts (not counted as applications)'), $t['drafts']];
        }
        $rows[] = [__('Admission fee paid'), $t['fee_paid']];
        $rows[] = [__('Female'), $t['female']];
        $rows[] = [__('Male'), $t['male']];
        $rows[] = [__('Other or not recorded'), $t['gender_other']];
        $rows[] = [__('Gave a 2nd choice'), $t['with_second']];
        $rows[] = [__('Gave a 3rd choice'), $t['with_third']];
        $rows[] = [__('Programmes reported on'), $this->report['programme_counts']['offered']];
        $rows[] = [__('Programmes with first-choice applicants'), $this->report['programme_counts']['with_first']];

        $section(__('PROGRAMMES BY SIGNAL (minimum class: :min first-choice applicants)', ['min' => $this->report['min_class']]));
        $rows[] = [__('Signal'), __('Programmes'), __('Which'), __('What it means')];
        foreach (ApplicationDemandReport::SIGNALS as $signal) {
            $rows[] = [
                $this->label($signal),
                count($this->report['signals'][$signal]),
                collect($this->report['signals'][$signal])
                    ->map(fn ($row) => $row['title'] . ($signal === ApplicationDemandReport::NONE ? '' : ' (' . $row['first'] . ')'))
                    ->implode('; '),
                $this->report['signal_explanations'][$signal],
            ];
        }

        $section(__('APPLICATIONS BY MONTH'));
        foreach ($this->report['months'] as $month) {
            $rows[] = [$month['month'], $month['count']];
        }

        $section(__('HOW THIS REPORT COUNTS'));
        $rows[] = [__('Applications are those matching the filters above, exactly as the applications list shows them.')];
        $rows[] = [__('A programme\'s demand is its first choices — the applicants who would enrol. Second and third choices show where applicants would go if their first choice is not opened.')];
        $rows[] = [__('"Reachable with 2nd choices" counts every second-choice applicant as though they would come: the most a programme could reach, not what it will.')];
        $rows[] = [__('Weighted demand scores 3 per first choice, 2 per second and 1 per third, as a tie-breaker.')];

        return new ApplicationReportSheet(__('Summary'), $this->preamble(__('ADMISSIONS DEMAND REPORT')), [], $rows, $emphasis);
    }

    protected function programmes(): ApplicationReportSheet
    {
        $headings = [
            __('Rank'), __('Code'), __('Programme'), __('Faculty'),
            __('1st choice'), __('2nd choice'), __('3rd choice'), __('Any choice'), __('Weighted demand'),
            __('Share of 1st choices (%)'),
            __('Approved (1st choice)'), __('Awaiting approval (1st choice)'), __('Refused (1st choice)'),
            __('Fee paid (1st choice)'), __('Female (1st choice)'), __('Male (1st choice)'),
        ];

        if ($this->report['drafts_counted']) {
            $headings[] = __('Unfinished drafts naming it first');
        }

        array_push($headings, __('Most chosen 2nd choice'), __('Applicants choosing it'), __('Signal'));

        $rows = [];

        foreach ($this->report['programmes'] as $i => $row) {
            $line = [
                $i + 1, $row['code'], $row['title'], $row['faculty'],
                $row['first'], $row['second'], $row['third'], $row['mentions'], $row['weighted'],
                $row['share'],
                $row['approved'], $row['in_progress'], $row['refused'],
                $row['fee_paid'], $row['female'], $row['male'],
            ];

            if ($this->report['drafts_counted']) {
                $line[] = $row['drafts'];
            }

            array_push(
                $line,
                $row['top_second']['title'] ?? '',
                $row['top_second']['count'] ?? '',
                $this->label($row['signal'])
            );

            $rows[] = $line;
        }

        return new ApplicationReportSheet(__('Programmes'), $this->preamble(__('DEMAND FOR EACH PROGRAMME')), $headings, $rows);
    }

    protected function faculties(): ApplicationReportSheet
    {
        $headings = [
            __('Faculty'), __('Code'), __('Programmes'), __('Programmes with 1st-choice applicants'),
            __('Applicants (1st choice)'), __('Share of 1st choices (%)'), __('Applicants (any choice)'),
            __('2nd choices'), __('3rd choices'),
            __('Approved'), __('Awaiting approval'), __('Refused'), __('Fee paid'),
        ];

        foreach (ApplicationDemandReport::SIGNALS as $signal) {
            $headings[] = __('Programmes: :signal', ['signal' => $this->label($signal)]);
        }

        $rows = [];

        foreach ($this->report['faculties'] as $faculty) {
            $line = [
                $faculty['title'], $faculty['code'], $faculty['programmes'], $faculty['programmes_with_first'],
                $faculty['first'], $faculty['share'], $faculty['any'],
                $faculty['second'], $faculty['third'],
                $faculty['approved'], $faculty['in_progress'], $faculty['refused'], $faculty['fee_paid'],
            ];

            foreach (ApplicationDemandReport::SIGNALS as $signal) {
                $line[] = $faculty['signals'][$signal];
            }

            $rows[] = $line;
        }

        return new ApplicationReportSheet(__('Faculties'), $this->preamble(__('APPLICANTS BY FACULTY')), $headings, $rows);
    }

    protected function ifNotOpened(): ApplicationReportSheet
    {
        $headings = [
            __('Programme'), __('Programme signal'), __('Its 1st-choice applicants'),
            __('Reg. no'), __('Applicant'),
            __('2nd choice'), __('2nd choice signal'),
            __('3rd choice'), __('3rd choice signal'),
            __('Has a fallback with enough applicants'),
        ];

        $rows = [];

        foreach ($this->report['redirects'] as $redirect) {
            $p = $redirect['programme'];

            foreach ($redirect['applicants'] as $applicant) {
                $rows[] = [
                    $p['title'], $this->label($p['signal']), $p['first'],
                    $applicant['registration_no'], $applicant['name'],
                    $applicant['second'] ?? __('None given'), $this->label($applicant['second_signal']),
                    $applicant['third'] ?? __('None given'), $this->label($applicant['third_signal']),
                    $applicant['has_viable_fallback'] ? __('Yes') : __('No'),
                ];
            }
        }

        return new ApplicationReportSheet(
            __('If not opened'),
            $this->preamble(__('IF A PROGRAMME IS NOT OPENED — WHERE ITS APPLICANTS WOULD GO')),
            $headings,
            $rows
        );
    }

    protected function choiceFlow(): ApplicationReportSheet
    {
        $matrix = $this->report['matrix'];

        $headings = array_merge(
            [__('1st choice'), __('Applicants')],
            array_map(fn ($column) => $column['title'], $matrix['columns']),
            [__('No 2nd choice')]
        );

        $rows = [];

        foreach ($matrix['rows'] as $row) {
            $line = [$row['title'], $row['first']];

            foreach ($matrix['columns'] as $column) {
                $line[] = $matrix['cells'][$row['id']][$column['id']] ?? 0;
            }

            $line[] = $matrix['cells'][$row['id']]['none'] ?? 0;
            $rows[] = $line;
        }

        return new ApplicationReportSheet(
            __('1st vs 2nd choice'),
            $this->preamble(__('FIRST CHOICE (ROWS) AGAINST SECOND CHOICE (COLUMNS)')),
            $headings,
            $rows
        );
    }

    protected function register(): ApplicationReportSheet
    {
        $headings = [
            __('Reg. no'), __('Applicant'), __('Gender'), __('Degree type'), __('Intake'),
            __('1st choice'), __('2nd choice'), __('3rd choice'),
            __('Stage'), __('Approval'), __('Admission fee'), __('Applied'),
            __('Phone'), __('Email'),
        ];

        $rows = array_map(fn ($entry) => [
            $entry['registration_no'], $entry['name'], $entry['gender'], $entry['degree_type'], $entry['intake'],
            $entry['first'], $entry['second'], $entry['third'],
            $entry['stage'], $entry['approval'], $entry['fee'], $entry['applied'],
            $entry['phone'], $entry['email'],
        ], $this->report['register']);

        return new ApplicationReportSheet(__('Register'), $this->preamble(__('REGISTER OF APPLICATIONS')), $headings, $rows);
    }
}
