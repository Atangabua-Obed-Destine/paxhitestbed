<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class AgingReportService
{
    /**
     * Age brackets in days
     */
    protected $ageBrackets = [
        ['min' => 0, 'max' => 30, 'label' => '0-30 Days'],
        ['min' => 31, 'max' => 60, 'label' => '31-60 Days'],
        ['min' => 61, 'max' => 90, 'label' => '61-90 Days'],
        ['min' => 91, 'max' => 120, 'label' => '91-120 Days'],
        ['min' => 121, 'max' => null, 'label' => 'Over 120 Days'],
    ];

    /**
     * Get accounts receivable aging report
     *
     * @param Carbon|string|null $asOfDate
     * @param array $filters
     * @return array
     */
    public function getReceivablesAging($asOfDate = null, array $filters = [])
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();
        
        // Get receivable accounts (OHADA Class 41 - Customers)
        $receivableAccounts = ChartOfAccount::where('account_code', 'like', '41%')
            ->where('is_active', true)
            ->pluck('id');

        return $this->generateAgingReport($receivableAccounts, $asOfDate, $filters, 'receivable');
    }

    /**
     * Get accounts payable aging report
     *
     * @param Carbon|string|null $asOfDate
     * @param array $filters
     * @return array
     */
    public function getPayablesAging($asOfDate = null, array $filters = [])
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();
        
        // Get payable accounts (OHADA Class 40 - Suppliers)
        $payableAccounts = ChartOfAccount::where('account_code', 'like', '40%')
            ->where('is_active', true)
            ->pluck('id');

        return $this->generateAgingReport($payableAccounts, $asOfDate, $filters, 'payable');
    }

    /**
     * Generate aging report for given accounts
     *
     * @param \Illuminate\Support\Collection $accountIds
     * @param Carbon $asOfDate
     * @param array $filters
     * @param string $type 'receivable' or 'payable'
     * @return array
     */
    protected function generateAgingReport($accountIds, Carbon $asOfDate, array $filters, string $type)
    {
        $report = [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'type' => $type,
            'brackets' => $this->ageBrackets,
            'accounts' => [],
            'totals' => $this->initializeBracketTotals(),
            'grand_total' => 0,
        ];

        // Get all accounts with their balances
        $accounts = ChartOfAccount::whereIn('id', $accountIds)->get();

        foreach ($accounts as $account) {
            $accountData = $this->getAccountAging($account, $asOfDate, $type);
            
            if ($accountData['total'] != 0) {
                $report['accounts'][] = $accountData;
                
                // Update totals
                foreach ($this->ageBrackets as $index => $bracket) {
                    $key = $this->getBracketKey($index);
                    $report['totals'][$key] += $accountData['brackets'][$key];
                }
                $report['grand_total'] += $accountData['total'];
            }
        }

        // Calculate percentages
        if ($report['grand_total'] != 0) {
            foreach ($this->ageBrackets as $index => $bracket) {
                $key = $this->getBracketKey($index);
                $report['totals'][$key . '_percent'] = round(
                    ($report['totals'][$key] / $report['grand_total']) * 100, 
                    2
                );
            }
        }

        return $report;
    }

    /**
     * Get aging data for a specific account
     *
     * @param ChartOfAccount $account
     * @param Carbon $asOfDate
     * @param string $type
     * @return array
     */
    protected function getAccountAging(ChartOfAccount $account, Carbon $asOfDate, string $type)
    {
        $brackets = $this->initializeBracketTotals();
        
        // Get all journal entry lines for this account up to the as-of date
        $entries = JournalEntryLine::where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                  ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->with('journalEntry')
            ->get();

        // Group by journal entry and calculate aging
        $openItems = [];
        
        foreach ($entries as $line) {
            $entryDate = Carbon::parse($line->journalEntry->entry_date);
            $daysOld = $entryDate->diffInDays($asOfDate);
            
            // For receivables: debit increases, credit decreases
            // For payables: credit increases, debit decreases
            $amount = $type === 'receivable' 
                ? ($line->debit - $line->credit)
                : ($line->credit - $line->debit);

            if ($amount == 0) {
                continue;
            }

            // Find the appropriate bracket
            $bracketIndex = $this->findBracketIndex($daysOld);
            $bracketKey = $this->getBracketKey($bracketIndex);
            
            $brackets[$bracketKey] += $amount;
        }

        $total = array_sum($brackets);

        return [
            'account_id' => $account->id,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'brackets' => $brackets,
            'total' => $total,
        ];
    }

    /**
     * Initialize bracket totals array
     *
     * @return array
     */
    protected function initializeBracketTotals()
    {
        $totals = [];
        foreach ($this->ageBrackets as $index => $bracket) {
            $totals[$this->getBracketKey($index)] = 0;
        }
        return $totals;
    }

    /**
     * Get bracket key from index
     *
     * @param int $index
     * @return string
     */
    protected function getBracketKey(int $index)
    {
        return 'bracket_' . $index;
    }

    /**
     * Find the bracket index for a given number of days
     *
     * @param int $days
     * @return int
     */
    protected function findBracketIndex(int $days)
    {
        foreach ($this->ageBrackets as $index => $bracket) {
            if ($bracket['max'] === null) {
                return $index; // Last bracket (over X days)
            }
            if ($days >= $bracket['min'] && $days <= $bracket['max']) {
                return $index;
            }
        }
        return count($this->ageBrackets) - 1; // Default to last bracket
    }

    /**
     * Get student fee aging report
     *
     * @param Carbon|string|null $asOfDate
     * @param array $filters
     * @return array
     */
    public function getStudentFeeAging($asOfDate = null, array $filters = [])
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();

        $report = [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'type' => 'student_fees',
            'brackets' => $this->ageBrackets,
            'students' => [],
            'totals' => $this->initializeBracketTotals(),
            'grand_total' => 0,
            'student_count' => 0,
        ];

        // Get students with outstanding fees using the fees table
        $query = DB::table('fees')
            ->join('student_enrolls', 'fees.student_enroll_id', '=', 'student_enrolls.id')
            ->join('students', 'student_enrolls.student_id', '=', 'students.id')
            ->leftJoin('programs', 'students.program_id', '=', 'programs.id')
            ->whereDate('fees.due_date', '<=', $asOfDate)
            ->where('fees.status', '!=', 1) // 0 = Unpaid, 1 = Paid, 2 = Cancel
            ->select(
                'students.id as student_id',
                'student_enrolls.matricule',
                DB::raw("CONCAT(students.first_name, ' ', students.last_name) as student_name"),
                'programs.title as program_name',
                'fees.id as assignment_id',
                'fees.fee_amount as amount',
                'fees.paid_amount',
                'fees.due_date',
                DB::raw('(fees.fee_amount - COALESCE(fees.paid_amount, 0)) as outstanding')
            );

        if (!empty($filters['program_id'])) {
            $query->where('students.program_id', $filters['program_id']);
        }

        if (!empty($filters['batch_id'])) {
            $query->where('student_enrolls.batch_id', $filters['batch_id']);
        }

        $assignments = $query->get();

        // Group by student
        $studentData = [];
        
        foreach ($assignments as $assignment) {
            if ($assignment->outstanding <= 0) {
                continue;
            }

            $dueDate = Carbon::parse($assignment->due_date);
            $daysOld = $dueDate->diffInDays($asOfDate);
            $bracketIndex = $this->findBracketIndex($daysOld);
            $bracketKey = $this->getBracketKey($bracketIndex);

            if (!isset($studentData[$assignment->student_id])) {
                $studentData[$assignment->student_id] = [
                    'student_id' => $assignment->student_id,
                    'matricule' => $assignment->matricule,
                    'student_name' => $assignment->student_name,
                    'program_name' => $assignment->program_name,
                    'brackets' => $this->initializeBracketTotals(),
                    'total' => 0,
                ];
            }

            $studentData[$assignment->student_id]['brackets'][$bracketKey] += $assignment->outstanding;
            $studentData[$assignment->student_id]['total'] += $assignment->outstanding;
            
            $report['totals'][$bracketKey] += $assignment->outstanding;
            $report['grand_total'] += $assignment->outstanding;
        }

        $report['students'] = array_values($studentData);
        $report['student_count'] = count($studentData);

        // Calculate percentages
        if ($report['grand_total'] != 0) {
            foreach ($this->ageBrackets as $index => $bracket) {
                $key = $this->getBracketKey($index);
                $report['totals'][$key . '_percent'] = round(
                    ($report['totals'][$key] / $report['grand_total']) * 100, 
                    2
                );
            }
        }

        return $report;
    }

    /**
     * Get aging summary with charts data
     *
     * @param string $type 'receivables', 'payables', or 'student_fees'
     * @param Carbon|string|null $asOfDate
     * @return array
     */
    public function getAgingSummaryWithCharts($type, $asOfDate = null)
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();

        switch ($type) {
            case 'receivables':
                $data = $this->getReceivablesAging($asOfDate);
                break;
            case 'payables':
                $data = $this->getPayablesAging($asOfDate);
                break;
            case 'student_fees':
                $data = $this->getStudentFeeAging($asOfDate);
                break;
            default:
                throw new Exception(__('invalid_aging_report_type'));
        }

        // Prepare chart data
        $chartLabels = [];
        $chartData = [];
        $chartColors = [
            '#28a745', // Green - Current
            '#ffc107', // Yellow - 31-60
            '#fd7e14', // Orange - 61-90
            '#dc3545', // Red - 91-120
            '#6c757d', // Gray - Over 120
        ];

        foreach ($this->ageBrackets as $index => $bracket) {
            $chartLabels[] = $bracket['label'];
            $chartData[] = $data['totals'][$this->getBracketKey($index)] ?? 0;
        }

        $data['chart'] = [
            'labels' => $chartLabels,
            'data' => $chartData,
            'colors' => $chartColors,
        ];

        return $data;
    }

    /**
     * Set custom age brackets
     *
     * @param array $brackets
     * @return $this
     */
    public function setAgeBrackets(array $brackets)
    {
        $this->ageBrackets = $brackets;
        return $this;
    }

    /**
     * Get default age brackets
     *
     * @return array
     */
    public function getDefaultAgeBrackets()
    {
        return [
            ['min' => 0, 'max' => 30, 'label' => '0-30 Days'],
            ['min' => 31, 'max' => 60, 'label' => '31-60 Days'],
            ['min' => 61, 'max' => 90, 'label' => '61-90 Days'],
            ['min' => 91, 'max' => 120, 'label' => '91-120 Days'],
            ['min' => 121, 'max' => null, 'label' => 'Over 120 Days'],
        ];
    }
}
