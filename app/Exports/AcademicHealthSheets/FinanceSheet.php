<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Fees billed against fees collected.
 *
 * Included because "is the school ready to operate" is half an academic
 * question and half a financial one, and the academic report answered only the
 * first.
 */
class FinanceSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Finance';
    }

    public function array(): array
    {
        $f = $this->data['financial'];

        $rows = $this->masthead('Fee position and financial controls');

        $rows[] = ['COLLECTIONS'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Measure', 'Amount', 'What it means'];
        $this->headerRows[] = count($rows);

        $rows[] = ['Fees billed', $f['fees_raised'], 'Total raised against students.'];
        $rows[] = ['Fees received', $f['fees_paid'], 'Total paid.'];
        $rows[] = ['Outstanding', $f['outstanding'], 'Billed less received.'];
        $this->verdictRows[count($rows)] = $f['outstanding'] > 0 ? 'warn' : 'ok';

        $rows[] = ['Collection rate', $f['collection_percent'] . '%', ''];
        $rows[] = ['Students owing', $f['students_owing'], 'Students with any instalment unpaid.'];
        $this->verdictRows[count($rows)] = $f['students_owing'] > 0 ? 'warn' : 'ok';

        // More received than billed is a missing-instalment fault, not a
        // windfall, and it makes every arrears figure above unreliable.
        if ($f['fees_paid'] > $f['fees_raised']) {
            $rows[] = [''];
            $rows[] = ['WARNING: MORE RECEIVED THAN BILLED'];
            $this->sectionRows[] = count($rows);
            $rows[] = [
                number_format($f['fees_paid'] - $f['fees_raised']) . ' more has been received than was ever raised.',
                '',
                'Fee instalments are missing from the assignment. A student can be short on one '
                    . 'instalment while their total still looks settled, so the arrears above cannot be relied on '
                    . 'until this is corrected.',
            ];
            $this->verdictRows[count($rows)] = 'bad';
        }

        $rows[] = [''];
        $rows[] = ['CONTROLS'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Control', 'Status', 'What it means'];
        $this->headerRows[] = count($rows);

        $rows[] = [
            'Active budget sheet',
            $f['active_budget'] > 0 ? 'Yes' : 'None',
            'Without one, spending is not measured against a plan.',
        ];
        $this->verdictRows[count($rows)] = $f['active_budget'] > 0 ? 'ok' : 'warn';

        $rows[] = [
            'Unposted payroll',
            $f['unposted_payroll'],
            'Payroll not yet posted does not appear in the ledger.',
        ];
        $this->verdictRows[count($rows)] = $f['unposted_payroll'] > 0 ? 'warn' : 'ok';

        $rows[] = [''];
        $rows[] = ['FEE CONFIGURATION'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Measure', 'Count', ''];
        $this->headerRows[] = count($rows);

        $rows[] = ['Fee configurations', $this->data['all_fees']->count(), ''];
        $rows[] = ['Programmes without fees', $this->data['programs_without_fees']->count(), ''];
        $this->verdictRows[count($rows)] = $this->data['programs_without_fees']->count() > 0 ? 'bad' : 'ok';
        $rows[] = ['Students on a programme with no fees', $this->data['students_without_fee_program'], ''];

        return $rows;
    }
}
