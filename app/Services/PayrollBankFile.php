<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffBankAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The file a bank takes to pay salaries: one row per payroll, the columns,
 * separator and reference as set on the Payroll Settings page.
 *
 * Each row pays into the account the payroll was paid to, or, before it is
 * paid, the staff member's default active account. A payroll with neither is
 * left out and reported, never written with a blank account number.
 */
class PayrollBankFile
{
    /**
     * @param  Collection<int, Payroll>  $payrolls
     * @return array{rows: array<int, array<string, string>>, missing: array<int, string>, total: float}
     */
    public function build(Collection $payrolls): array
    {
        $settings = PayrollSetting::current();
        $currency = (string) (\App\Models\Setting::first()->currency ?? '');
        $decimals = (int) $settings->bank_file_decimals;

        $rows = [];
        $missing = [];
        $total = 0.0;

        foreach ($payrolls as $payroll) {
            $payroll->loadMissing('user', 'bankAccount');
            $user = $payroll->user;
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            $account = $payroll->bankAccount && (int) $payroll->bankAccount->user_id === (int) $payroll->user_id
                ? $payroll->bankAccount
                : StaffBankAccount::where('user_id', $payroll->user_id)->where('status', 1)
                    ->orderByDesc('is_default')->orderBy('id')->first();

            if (!$account || trim((string) $account->account_number) === '') {
                $missing[] = $name . ' (' . ($user->staff_id ?? $payroll->user_id) . ')';
                continue;
            }

            $month = Carbon::parse($payroll->salary_month);
            $reference = strtr((string) $settings->bank_file_reference, [
                '{MONTH}' => strtoupper($month->format('M')),
                '{MONTH_NUMBER}' => $month->format('m'),
                '{YEAR}' => $month->format('Y'),
                '{STAFF_ID}' => (string) ($user->staff_id ?? ''),
                '{NAME}' => $name,
            ]);

            $values = [
                'staff_id' => (string) ($user->staff_id ?? ''),
                'name' => $name,
                'account_name' => (string) ($account->account_name ?: $name),
                'bank_name' => (string) $account->bank_name,
                'branch' => (string) ($account->branch ?? ''),
                'account_number' => (string) $account->account_number,
                'bank_code' => (string) ($account->ifsc_code ?? ''),
                'amount' => number_format((float) $payroll->net_salary, $decimals, '.', ''),
                'currency' => $currency,
                'reference' => $reference,
                'month' => $month->format('Y-m'),
            ];

            $row = [];
            foreach ($settings->bankColumns() as $column) {
                $row[$column] = $values[$column];
            }

            $rows[] = $row;
            $total += round((float) $payroll->net_salary, $decimals);
        }

        return ['rows' => $rows, 'missing' => $missing, 'total' => round($total, $decimals)];
    }

    /** The rows as a delimited text file, with a header if the settings ask for one. */
    public function csv(array $rows): string
    {
        $settings = PayrollSetting::current();
        $delimiter = $settings->bank_file_delimiter === '\t' || $settings->bank_file_delimiter === 'tab'
            ? "\t"
            : ((string) $settings->bank_file_delimiter !== '' ? substr((string) $settings->bank_file_delimiter, 0, 1) : ',');

        $out = fopen('php://temp', 'r+');

        if ($settings->bank_file_header) {
            fputcsv($out, array_map(fn ($c) => PayrollSetting::BANK_COLUMNS[$c], $settings->bankColumns()), $delimiter);
        }

        foreach ($rows as $row) {
            // A leading = + - @ makes a spreadsheet treat a cell as a formula.
            fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@]/', $v) ? "'" . $v : $v, array_values($row)), $delimiter);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
