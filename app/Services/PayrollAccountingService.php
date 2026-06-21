<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\TransactionMapping;
use App\Models\DefaultAccountMapping;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\TaxSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollAccountingService
{
    /**
     * Create journal entries when payroll is paid
     * 
     * This creates a comprehensive journal entry with:
     * - Salary Expense (Debit) - Class 6 account
     * - Tax Payable (Credit) - Class 4 account for taxes withheld
     * - Cash/Bank (Credit) - Class 5 account for net payment
     * 
     * @param Payroll $payroll
     * @param int $userId - The user performing the action
     * @return JournalEntry|null
     */
    public function createPayrollJournalEntry(Payroll $payroll, int $userId): ?JournalEntry
    {
        try {
            // Get active fiscal year
            $fiscalYear = FiscalYear::where('is_active', true)->first();
            
            if (!$fiscalYear) {
                Log::warning('PayrollAccountingService: No active fiscal year found for payroll #' . $payroll->id);
                return null;
            }

            // Get the accounting period
            $accountingPeriod = AccountingPeriod::where('is_closed', false)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('start_date', '<=', $payroll->pay_date)
                ->where('end_date', '>=', $payroll->pay_date)
                ->first();

            // Get default payroll mapping for main salary expense
            $defaultMapping = DefaultAccountMapping::where('mapping_type', 'payroll')
                ->whereNull('category_id')
                ->where('status', 'active')
                ->first();

            // If no mapping configured, try to auto-detect accounts
            $salaryExpenseAccount = null;
            $paymentAccount = null;
            
            if ($defaultMapping) {
                $salaryExpenseAccount = ChartOfAccount::find($defaultMapping->debit_account_id);
                $paymentAccount = ChartOfAccount::find($defaultMapping->credit_account_id);
            }
            
            // Fallback to auto-detection if mapping not configured or accounts not found
            if (!$salaryExpenseAccount) {
                $salaryExpenseAccount = $this->findPayrollExpenseAccount();
            }
            if (!$paymentAccount) {
                $paymentAccount = $this->findPaymentAccount();
            }

            if (!$salaryExpenseAccount || !$paymentAccount) {
                Log::warning('PayrollAccountingService: Could not find required accounts for payroll #' . $payroll->id . 
                    '. Salary Account: ' . ($salaryExpenseAccount ? 'found' : 'missing') . 
                    ', Payment Account: ' . ($paymentAccount ? 'found' : 'missing') .
                    '. Please configure payroll mappings in Accounting > Mappings > Settings.');
                return null;
            }

            // Load relationships
            $payroll->load('user', 'details');

            // Get staff name
            $staffName = 'Staff';
            if ($payroll->user) {
                $staffName = trim($payroll->user->first_name . ' ' . $payroll->user->last_name);
                if (empty($staffName)) {
                    $staffName = $payroll->user->name ?? 'Staff #' . $payroll->user_id;
                }
            }

            $salaryMonth = date('F Y', strtotime($payroll->salary_month));
            $description = "Salary Payment - {$staffName} ({$salaryMonth})";

            DB::beginTransaction();

            // Calculate total debit (gross salary + employer contributions)
            $employerTax = $payroll->employer_tax ?? 0;
            $totalDebitAmount = $payroll->gross_salary + $employerTax;

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $payroll->pay_date,
                'fiscal_year_id' => $fiscalYear->id,
                'accounting_period_id' => $accountingPeriod->id ?? null,
                'journal_type' => 'payroll',
                'description' => $description,
                'reference_type' => 'payroll',
                'reference_id' => $payroll->id,
                'total_debit' => $totalDebitAmount,
                'total_credit' => $totalDebitAmount,
                'is_posted' => false,
                'is_system_generated' => true,
                'created_by' => $userId,
            ]);

            $lineNumber = 1;

            // DEBIT: Salary Expense Account (gross salary)
            // This includes basic salary + allowances + bonus
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $lineNumber++,
                'account_id' => $salaryExpenseAccount->id,
                'description' => "Gross Salary - {$staffName}",
                'debit' => $payroll->gross_salary,
                'credit' => 0,
            ]);

            // DEBIT: Employer Social Charges (employer_tax)
            // This is the employer's contribution to shared taxes like CNPS
            $employerTax = $payroll->employer_tax ?? 0;
            if ($employerTax > 0) {
                // Try to find employer charges expense account
                $employerChargesAccount = $this->findEmployerChargesAccount();
                
                if ($employerChargesAccount) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'line_number' => $lineNumber++,
                        'account_id' => $employerChargesAccount->id,
                        'description' => "Employer Social Charges - {$staffName}",
                        'debit' => $employerTax,
                        'credit' => 0,
                    ]);
                } else {
                    // Use the same salary expense account if no specific account found
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'line_number' => $lineNumber++,
                        'account_id' => $salaryExpenseAccount->id,
                        'description' => "Employer Social Charges - {$staffName}",
                        'debit' => $employerTax,
                        'credit' => 0,
                    ]);
                }
            }

            // CREDIT: Tax Payable Account (employee tax withheld)
            if ($payroll->tax > 0) {
                // First try to get tax mapping from configuration
                $taxMapping = DefaultAccountMapping::where('mapping_type', 'payroll_tax')
                    ->where('status', 'active')
                    ->first();
                
                $taxPayableAccount = null;
                if ($taxMapping) {
                    $taxPayableAccount = ChartOfAccount::find($taxMapping->credit_account_id);
                }
                
                // Fallback to auto-detection
                if (!$taxPayableAccount) {
                    $taxPayableAccount = $this->findTaxPayableAccount();
                }
                
                if ($taxPayableAccount) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'line_number' => $lineNumber++,
                        'account_id' => $taxPayableAccount->id,
                        'description' => "Tax Withheld (Employee) - {$staffName}",
                        'debit' => 0,
                        'credit' => $payroll->tax,
                    ]);
                } else {
                    // If no tax account found, include tax in the main credit account
                    Log::info('PayrollAccountingService: No tax payable account found, including tax in main credit');
                }
            }

            // CREDIT: Social Charges Payable (employer contributions)
            // This is the liability for employer's share of CNPS, etc.
            if ($employerTax > 0) {
                // Try to find social charges payable account
                $socialChargesPayableAccount = $this->findSocialChargesPayableAccount();
                
                if ($socialChargesPayableAccount) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'line_number' => $lineNumber++,
                        'account_id' => $socialChargesPayableAccount->id,
                        'description' => "Social Charges Payable (Employer) - {$staffName}",
                        'debit' => 0,
                        'credit' => $employerTax,
                    ]);
                } else {
                    // Use the same tax payable account if no specific account found
                    $fallbackAccount = $taxPayableAccount ?? $this->findTaxPayableAccount();
                    if ($fallbackAccount) {
                        JournalEntryLine::create([
                            'journal_entry_id' => $journalEntry->id,
                            'line_number' => $lineNumber++,
                            'account_id' => $fallbackAccount->id,
                            'description' => "Social Charges Payable (Employer) - {$staffName}",
                            'debit' => 0,
                            'credit' => $employerTax,
                        ]);
                    }
                }
            }

            // Handle deductions (if stored in payroll_details with status = 0)
            $deductions = $payroll->details->where('status', 0);
            $totalDeductions = $deductions->sum('amount');
            
            // If there are specific deduction accounts, we could map them here
            // For now, deductions are already reflected in the gross_salary calculation

            // CREDIT: Cash/Bank Account (net salary - the actual payment)
            $netPaymentAmount = $payroll->net_salary;
            
            // Check if tax was credited separately
            $taxAccountConfigured = false;
            if ($payroll->tax > 0) {
                $taxMapping = DefaultAccountMapping::where('mapping_type', 'payroll_tax')
                    ->where('status', 'active')
                    ->first();
                
                if ($taxMapping || $this->findTaxPayableAccount()) {
                    $taxAccountConfigured = true;
                }
            }
            
            // If tax was not credited separately, use gross salary
            if ($payroll->tax > 0 && !$taxAccountConfigured) {
                $netPaymentAmount = $payroll->gross_salary;
            }
            
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $lineNumber++,
                'account_id' => $paymentAccount->id,
                'description' => "Net Salary Payment - {$staffName}",
                'debit' => 0,
                'credit' => $netPaymentAmount,
            ]);

            // Recalculate and verify totals
            $totalDebit = $journalEntry->lines()->sum('debit');
            $totalCredit = $journalEntry->lines()->sum('credit');

            // Ensure balance
            if (abs($totalDebit - $totalCredit) > 0.01) {
                // Adjust the last credit line to balance
                $lastCreditLine = $journalEntry->lines()->where('credit', '>', 0)->orderBy('line_number', 'desc')->first();
                if ($lastCreditLine) {
                    $difference = $totalDebit - $totalCredit;
                    $lastCreditLine->credit += $difference;
                    $lastCreditLine->save();
                }
                
                // Recalculate
                $totalDebit = $journalEntry->lines()->sum('debit');
                $totalCredit = $journalEntry->lines()->sum('credit');
            }

            // Update journal entry totals
            $journalEntry->total_debit = $totalDebit;
            $journalEntry->total_credit = $totalCredit;
            $journalEntry->save();

            // Create or update transaction mapping
            TransactionMapping::updateOrCreate(
                [
                    'transaction_type' => 'payroll',
                    'transaction_id' => $payroll->id,
                ],
                [
                    'debit_account_id' => $salaryExpenseAccount->id,
                    'credit_account_id' => $paymentAccount->id,
                    'amount' => $payroll->net_salary,
                    'transaction_date' => $payroll->pay_date,
                    'description' => $description,
                    'journal_entry_id' => $journalEntry->id,
                    'mapped_at' => now(),
                    'mapped_by' => $userId,
                    'status' => 'active',
                ]
            );

            // Post the journal entry
            $journalEntry->post($userId);

            DB::commit();

            Log::info('PayrollAccountingService: Created and posted journal entry #' . $journalEntry->entry_number . ' for payroll #' . $payroll->id);

            return $journalEntry;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayrollAccountingService: Error creating journal entry for payroll #' . $payroll->id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reverse journal entry when payroll is unpaid
     * 
     * @param Payroll $payroll
     * @param int $userId
     * @return JournalEntry|null
     */
    public function reversePayrollJournalEntry(Payroll $payroll, int $userId): ?JournalEntry
    {
        try {
            // Find the original transaction mapping
            $mapping = TransactionMapping::where('transaction_type', 'payroll')
                ->where('transaction_id', $payroll->id)
                ->first();

            if (!$mapping || !$mapping->journal_entry_id) {
                Log::info('PayrollAccountingService: No journal entry found to reverse for payroll #' . $payroll->id);
                return null;
            }

            $originalEntry = JournalEntry::find($mapping->journal_entry_id);
            
            if (!$originalEntry) {
                Log::warning('PayrollAccountingService: Original journal entry not found for payroll #' . $payroll->id);
                return null;
            }

            DB::beginTransaction();

            // Load relationships
            $payroll->load('user');

            // Get staff name
            $staffName = 'Staff';
            if ($payroll->user) {
                $staffName = trim($payroll->user->first_name . ' ' . $payroll->user->last_name);
                if (empty($staffName)) {
                    $staffName = $payroll->user->name ?? 'Staff #' . $payroll->user_id;
                }
            }

            $salaryMonth = date('F Y', strtotime($payroll->salary_month));
            $description = "Reversal: Salary Payment - {$staffName} ({$salaryMonth})";

            // Create reversal journal entry
            $reversalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => now()->format('Y-m-d'),
                'fiscal_year_id' => $originalEntry->fiscal_year_id,
                'accounting_period_id' => $originalEntry->accounting_period_id,
                'journal_type' => 'payroll',
                'description' => $description,
                'reference_type' => 'payroll_reversal',
                'reference_id' => $payroll->id,
                'total_debit' => $originalEntry->total_credit,
                'total_credit' => $originalEntry->total_debit,
                'is_posted' => false,
                'is_system_generated' => true,
                'created_by' => $userId,
            ]);

            // Create reversed lines (swap debits and credits)
            $lineNumber = 1;
            foreach ($originalEntry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $line->account_id,
                    'description' => 'Reversal: ' . $line->description,
                    'debit' => $line->credit, // Swap
                    'credit' => $line->debit, // Swap
                ]);
            }

            // Update transaction mapping
            $mapping->status = 'reversed';
            $mapping->save();

            // Post the reversal entry
            $reversalEntry->post($userId);

            DB::commit();

            Log::info('PayrollAccountingService: Created and posted reversal journal entry #' . $reversalEntry->entry_number . ' for payroll #' . $payroll->id);

            return $reversalEntry;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayrollAccountingService: Error reversing journal entry for payroll #' . $payroll->id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find employer social charges expense account (Class 6)
     * Common OHADA accounts: 664 - Charges sociales
     * 
     * @return ChartOfAccount|null
     */
    private function findEmployerChargesAccount(): ?ChartOfAccount
    {
        // Try common employer charges/social contributions expense accounts (Class 6)
        $chargesCodes = ['664', '6641', '6642', '6643', '6644', '663'];
        
        foreach ($chargesCodes as $code) {
            $account = ChartOfAccount::where('account_code', 'LIKE', $code . '%')
                ->where('is_active', true)
                ->where('account_category', 'detail')
                ->first();
            
            if ($account) {
                return $account;
            }
        }

        // Try to find by name
        $account = ChartOfAccount::where(function($q) {
                $q->where('account_name', 'LIKE', '%charges sociales%')
                  ->orWhere('account_name', 'LIKE', '%social charges%')
                  ->orWhere('account_name', 'LIKE', '%cotisation%')
                  ->orWhere('account_name', 'LIKE', '%CNPS%')
                  ->orWhere('account_name', 'LIKE', '%patronal%')
                  ->orWhere('account_name', 'LIKE', '%employer%');
            })
            ->where('is_active', true)
            ->where('account_category', 'detail')
            ->where('account_code', 'LIKE', '6%') // Class 6 - Expenses
            ->first();

        return $account;
    }

    /**
     * Find social charges payable account (Class 4 - Liabilities)
     * Common OHADA accounts: 431, 4311, 4312 for CNPS and other social charges
     * 
     * @return ChartOfAccount|null
     */
    private function findSocialChargesPayableAccount(): ?ChartOfAccount
    {
        // Try common social charges payable accounts (Class 4)
        $socialCodes = ['431', '4311', '4312', '4313', '432', '433', '43'];
        
        foreach ($socialCodes as $code) {
            $account = ChartOfAccount::where('account_code', 'LIKE', $code . '%')
                ->where('is_active', true)
                ->where('account_category', 'detail')
                ->first();
            
            if ($account) {
                return $account;
            }
        }

        // Try to find by name
        $account = ChartOfAccount::where(function($q) {
                $q->where('account_name', 'LIKE', '%CNPS%')
                  ->orWhere('account_name', 'LIKE', '%securite sociale%')
                  ->orWhere('account_name', 'LIKE', '%sécurité sociale%')
                  ->orWhere('account_name', 'LIKE', '%social security%')
                  ->orWhere('account_name', 'LIKE', '%organismes sociaux%')
                  ->orWhere('account_name', 'LIKE', '%cotisation%');
            })
            ->where('is_active', true)
            ->where('account_category', 'detail')
            ->where('account_code', 'LIKE', '4%') // Class 4 - Liabilities
            ->first();

        return $account;
    }

    /**
     * Find a tax payable account (typically Class 4 - Liabilities)
     * Common OHADA accounts: 4424, 4425, 4426 for various taxes
     * 
     * @return ChartOfAccount|null
     */
    private function findTaxPayableAccount(): ?ChartOfAccount
    {
        // Try to find account by common tax payable codes
        $taxCodes = ['4424', '4425', '4426', '4421', '442', '44'];
        
        foreach ($taxCodes as $code) {
            $account = ChartOfAccount::where('account_code', 'LIKE', $code . '%')
                ->where('is_active', true)
                ->where('account_category', 'detail')
                ->first();
            
            if ($account) {
                return $account;
            }
        }

        // Try to find by name containing 'tax' or 'impot'
        $account = ChartOfAccount::where(function($q) {
                $q->where('account_name', 'LIKE', '%tax%')
                  ->orWhere('account_name', 'LIKE', '%impot%')
                  ->orWhere('account_name', 'LIKE', '%impôt%')
                  ->orWhere('account_name', 'LIKE', '%IRPP%')
                  ->orWhere('account_name', 'LIKE', '%retenue%');
            })
            ->where('is_active', true)
            ->where('account_category', 'detail')
            ->where('account_code', 'LIKE', '4%') // Class 4 - Liabilities
            ->first();

        return $account;
    }

    /**
     * Find or create payroll expense account
     * 
     * @return ChartOfAccount|null
     */
    public function findPayrollExpenseAccount(): ?ChartOfAccount
    {
        // Try common payroll/salary expense accounts (Class 6)
        $salaryCodes = ['661', '6611', '662', '66'];
        
        foreach ($salaryCodes as $code) {
            $account = ChartOfAccount::where('account_code', 'LIKE', $code . '%')
                ->where('is_active', true)
                ->where('account_category', 'detail')
                ->first();
            
            if ($account) {
                return $account;
            }
        }

        // Try to find by name
        $account = ChartOfAccount::where(function($q) {
                $q->where('account_name', 'LIKE', '%salaire%')
                  ->orWhere('account_name', 'LIKE', '%salary%')
                  ->orWhere('account_name', 'LIKE', '%personnel%')
                  ->orWhere('account_name', 'LIKE', '%remuneration%')
                  ->orWhere('account_name', 'LIKE', '%rémunération%');
            })
            ->where('is_active', true)
            ->where('account_category', 'detail')
            ->where('account_code', 'LIKE', '6%') // Class 6 - Expenses
            ->first();

        return $account;
    }

    /**
     * Find or create bank/cash account for payments
     * 
     * @return ChartOfAccount|null
     */
    public function findPaymentAccount(): ?ChartOfAccount
    {
        // Try common bank/cash accounts (Class 5)
        $bankCodes = ['521', '5211', '52', '571', '57'];
        
        foreach ($bankCodes as $code) {
            $account = ChartOfAccount::where('account_code', 'LIKE', $code . '%')
                ->where('is_active', true)
                ->where('account_category', 'detail')
                ->first();
            
            if ($account) {
                return $account;
            }
        }

        // Try to find by name
        $account = ChartOfAccount::where(function($q) {
                $q->where('account_name', 'LIKE', '%banque%')
                  ->orWhere('account_name', 'LIKE', '%bank%')
                  ->orWhere('account_name', 'LIKE', '%caisse%')
                  ->orWhere('account_name', 'LIKE', '%cash%');
            })
            ->where('is_active', true)
            ->where('account_category', 'detail')
            ->where('account_code', 'LIKE', '5%') // Class 5 - Treasury
            ->first();

        return $account;
    }

    /**
     * Check if payroll accounting integration is properly configured
     * 
     * @return array
     */
    public function checkConfiguration(): array
    {
        $issues = [];
        $configured = true;

        // Check for active fiscal year
        $fiscalYear = FiscalYear::where('is_active', true)->first();
        if (!$fiscalYear) {
            $issues[] = 'No active fiscal year configured';
            $configured = false;
        }

        // Check for payroll default mapping
        $defaultMapping = DefaultAccountMapping::where('mapping_type', 'payroll')
            ->where('status', 'active')
            ->first();
        
        if (!$defaultMapping) {
            $issues[] = 'No default payroll account mapping configured';
            $configured = false;
        } else {
            // Verify the accounts exist and are active
            $debitAccount = ChartOfAccount::find($defaultMapping->debit_account_id);
            $creditAccount = ChartOfAccount::find($defaultMapping->credit_account_id);
            
            if (!$debitAccount || !$debitAccount->is_active) {
                $issues[] = 'Payroll debit account is missing or inactive';
                $configured = false;
            }
            
            if (!$creditAccount || !$creditAccount->is_active) {
                $issues[] = 'Payroll credit account is missing or inactive';
                $configured = false;
            }
        }

        // Check for tax payable account
        $taxAccount = $this->findTaxPayableAccount();
        if (!$taxAccount) {
            $issues[] = 'No tax payable account found (optional but recommended)';
        }

        // Check for employer charges expense account (664)
        $employerChargesAccount = $this->findEmployerChargesAccount();
        if (!$employerChargesAccount) {
            $issues[] = 'No employer charges expense account found (account 664 - optional but recommended for shared taxes)';
        }

        // Check for social charges payable account (431)
        $socialChargesPayableAccount = $this->findSocialChargesPayableAccount();
        if (!$socialChargesPayableAccount) {
            $issues[] = 'No social charges payable account found (account 431 - optional but recommended for shared taxes)';
        }

        return [
            'configured' => $configured,
            'issues' => $issues,
            'fiscal_year' => $fiscalYear,
            'default_mapping' => $defaultMapping,
            'tax_account' => $taxAccount,
            'employer_charges_account' => $employerChargesAccount,
            'social_charges_payable_account' => $socialChargesPayableAccount,
        ];
    }
}
