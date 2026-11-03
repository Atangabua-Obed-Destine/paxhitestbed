<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The fees report as a workbook.
 *
 * The same figures as the PDF, built from the same array, laid out one subject
 * per tab so each can be sorted and filtered: the summary, then the money by
 * fee type, by programme, by faculty and by term, then who owes, then how it
 * was collected, then the full register.
 *
 * Money is written as NUMBERS, not as formatted strings. A workbook whose
 * amounts are text cannot be summed, which is the one thing people open a
 * workbook to do — the PDF is where the thousands separators belong.
 */
class FeeReportExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(protected array $report)
    {
    }

    public function sheets(): array
    {
        return [
            $this->summary(),
            $this->table(__('Fee types'), __('BILLED AND COLLECTED BY FEE TYPE'), __('Fee type'), $this->report['by_category']),
            $this->programmes(),
            $this->table(__('Faculties'), __('BILLED AND COLLECTED BY FACULTY'), __('Faculty'), $this->report['by_faculty']),
            $this->terms(),
            $this->owing(),
            $this->collection(),
            $this->register(),
        ];
    }

    /** The lines above every table, saying what the report is and what it covers. */
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

    /**
     * The money columns every breakdown carries, in one place.
     *
     * @param bool $students  false on a sheet whose rows ARE students, where
     *                        the column would read 1 the whole way down
     */
    protected function moneyHeadings(bool $students = true): array
    {
        return array_values(array_filter([
            __('Fees'), $students ? __('Students') : null, __('Billed'), __('Collected'),
            __('Outstanding'), __('Overpayment held'), __('Collected (%)'),
        ], fn ($heading) => $heading !== null));
    }

    /** @param array<string, mixed> $line a row from FeeReport's breakdowns */
    protected function moneyCells(array $line, bool $students = true): array
    {
        return array_values(array_filter([
            $line['fees'], $students ? $line['students'] : null, $line['billed'], $line['collected'],
            $line['outstanding'], $line['overpaid'], $line['rate'],
        ], fn ($cell) => $cell !== null));
    }

    protected function summary(): ReportSheet
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

        $section(__('THE MONEY'));
        $rows[] = [__('Billed'), $t['billed']];
        $rows[] = [__('Collected'), $t['collected']];
        $rows[] = [__('Outstanding'), $t['outstanding']];
        $rows[] = [__('Overpayment held'), $t['overpaid']];
        $rows[] = [__('Collected (%)'), $t['rate']];

        $section(__('WHAT IT COVERS'));
        $rows[] = [__('Fees'), $t['fees']];
        $rows[] = [__('Students'), $t['students']];

        $outside = $this->report['outside'];
        if ($outside['fees'] > 0) {
            $rows[] = [__('Fees matched but not counted (no student record)'), $outside['fees']];
            $rows[] = [__('Billed on those fees'), $outside['billed']];
        }

        // Only when there is something wrong: a standing "nothing to report"
        // section teaches the reader to skip the one place they must not.
        $anomalies = $this->report['anomalies'];
        if ($anomalies['fees'] > 0) {
            $section(__('NEEDS CHECKING'));
            $rows[] = [__('These fees carry more overpayment credit moved to another fee than was ever paid on them, which understates Collected above. The figures are wrong in the records, not in this report.')];
            $rows[] = [__('Understates collected by'), $anomalies['amount']];
            $rows[] = [
                __('Receipt'), __('Matricule'), __('Student'), __('Fee type'),
                __('Billed'), __('Recorded as paid'), __('Credit moved to another fee'), __('Understates collected by'),
            ];
            foreach ($anomalies['rows'] as $line) {
                $rows[] = [
                    $line['receipt'], $line['matricule'], $line['name'], $line['category'],
                    $line['due'], $line['paid_raw'], $line['moved_out'], $line['shortfall'],
                ];
            }
        }

        $section(__('FEES BY STATUS'));
        $rows[] = array_merge([__('Status')], $this->moneyHeadings());
        foreach ($this->report['by_status'] as $line) {
            $rows[] = array_merge([$line['title']], $this->moneyCells($line));
        }

        $section(__('HOW THIS REPORT COUNTS'));
        foreach ($this->report['notes'] as $note) {
            $rows[] = [$note];
        }

        return new ReportSheet(__('Summary'), $this->preamble(__('FEES REPORT')), [], $rows, $emphasis);
    }

    /** A breakdown with one naming column in front of the money columns. */
    protected function table(string $tab, string $heading, string $label, array $lines): ReportSheet
    {
        $rows = array_map(
            fn ($line) => array_merge([$line['title']], $this->moneyCells($line)),
            $lines
        );

        return new ReportSheet(
            $tab,
            $this->preamble($heading),
            array_merge([$label], $this->moneyHeadings()),
            $rows
        );
    }

    protected function programmes(): ReportSheet
    {
        $rows = array_map(
            fn ($line) => array_merge([$line['faculty'], $line['title']], $this->moneyCells($line)),
            $this->report['by_programme']
        );

        return new ReportSheet(
            __('Programmes'),
            $this->preamble(__('BILLED AND COLLECTED BY PROGRAMME')),
            array_merge([__('Faculty'), __('Programme')], $this->moneyHeadings()),
            $rows
        );
    }

    protected function terms(): ReportSheet
    {
        $rows = array_map(
            fn ($line) => array_merge([$line['session'], $line['semester']], $this->moneyCells($line)),
            $this->report['by_term']
        );

        return new ReportSheet(
            __('Terms'),
            $this->preamble(__('BILLED AND COLLECTED BY SESSION AND SEMESTER')),
            array_merge([__('Session'), __('Semester')], $this->moneyHeadings()),
            $rows
        );
    }

    /**
     * One line per student, worst debt first — the sheet a Bursar chases from.
     */
    protected function owing(): ReportSheet
    {
        $headings = array_merge(
            [__('Matricule'), __('Student ID'), __('Student'), __('Faculty'), __('Programme'), __('Session'), __('Semester')],
            $this->moneyHeadings(false),
            [__('Phone'), __('Email')]
        );

        $rows = array_map(fn ($line) => array_merge(
            [$line['matricule'], $line['student_id'], $line['name'], $line['faculty'], $line['programme'], $line['session'], $line['semester']],
            $this->moneyCells($line, false),
            [$line['phone'], $line['email']]
        ), $this->report['students']);

        return new ReportSheet(
            __('Who owes'),
            $this->preamble(__('EVERY STUDENT IN SCOPE, MOST OWED FIRST')),
            $headings,
            $rows
        );
    }

    /** How the money was taken, and when it arrived. */
    protected function collection(): ReportSheet
    {
        $rows = [];
        $emphasis = [];

        $section = function (string $title) use (&$rows, &$emphasis) {
            if ($rows) {
                $rows[] = [''];
            }
            $emphasis[] = count($rows);
            $rows[] = [$title];
        };

        $section(__('COLLECTED BY PAYMENT METHOD'));
        $rows[] = [__('Method'), __('Fees'), __('Collected')];
        foreach ($this->report['by_method'] as $line) {
            $rows[] = [$line['title'], $line['fees'], $line['collected']];
        }

        $section(__('COLLECTED BY MONTH'));
        $rows[] = [__('Month'), __('Fees'), __('Collected')];
        foreach ($this->report['by_month'] as $line) {
            $rows[] = [$line['month'], $line['fees'], $line['collected']];
        }

        return new ReportSheet(__('Collection'), $this->preamble(__('HOW THE MONEY WAS COLLECTED')), [], $rows, $emphasis);
    }

    /** Every fee the filters matched. */
    protected function register(): ReportSheet
    {
        $headings = [
            __('#'), __('Receipt'), __('Matricule'), __('Student ID'), __('Student'),
            __('Faculty'), __('Programme'), __('Session'), __('Semester'),
            __('Fee type'), __('Resit course'),
            __('Fee'), __('Discount'), __('Fine'), __('Billed'),
            __('Paid'), __('Outstanding'), __('Overpayment held'), __('Credit moved to another fee'),
            __('Status'), __('Payment plan'), __('Method'), __('Payment date'), __('Note'),
        ];

        $rows = array_map(fn ($line) => [
            $line['no'], $line['receipt'], $line['matricule'], $line['student_id'], $line['name'],
            $line['faculty'], $line['programme'], $line['session'], $line['semester'],
            $line['category'], $line['resit'],
            $line['gross'], $line['discount'], $line['fine'], $line['due'],
            $line['paid'], $line['balance'], $line['held'], $line['moved_out'],
            $line['status'], $line['plan'], $line['method'],
            $line['pay_date'] ? $line['pay_date']->format('Y-m-d') : '',
            $line['note'],
        ], $this->report['register']);

        return new ReportSheet(
            __('Register'),
            $this->preamble(__('REGISTER OF FEES')),
            $headings,
            $rows
        );
    }
}
