# OHADA Financial Statements Implementation Guide
## Balance Sheet & Income Statement

**Created:** October 10, 2025  
**System:** PaxHi School Management System  
**Accounting Standard:** OHADA (Organisation for the Harmonisation of Business Law in Africa)  
**Currency:** FCFA (Franc CFA)

---

## Table of Contents
1. [Overview](#overview)
2. [Balance Sheet (Bilan)](#balance-sheet)
3. [Income Statement (Compte de Résultat)](#income-statement)
4. [Features](#features)
5. [Routes](#routes)
6. [Controller Methods](#controller-methods)
7. [Views](#views)
8. [Usage Guide](#usage-guide)
9. [Export Functionality](#export-functionality)
10. [OHADA Compliance](#ohada-compliance)
11. [Troubleshooting](#troubleshooting)

---

## 1. Overview

This implementation provides professional, OHADA-compliant financial statements for the accounting module. Both reports follow international accounting standards with proper classification of accounts according to the OHADA chart of accounts.

### Key Features
- ✅ OHADA-compliant account classification
- ✅ Comparative period analysis
- ✅ Real-time balance calculations
- ✅ Professional report formatting
- ✅ Print functionality
- ✅ PDF export
- ✅ Excel export (ready for implementation)
- ✅ Date range filtering
- ✅ Automatic balance verification
- ✅ Bilingual support (English/French)

---

## 2. Balance Sheet (Bilan)

### Purpose
The Balance Sheet provides a snapshot of the organization's financial position at a specific point in time, showing what the organization owns (assets) and owes (liabilities), as well as the equity.

### Structure

#### ASSETS (ACTIF)
1. **Fixed Assets (Immobilisations)** - Class 2
   - Buildings, equipment, furniture, etc.
   - Long-term investments

2. **Inventory & Stocks** - Class 3
   - Raw materials
   - Finished goods
   - Work in progress

3. **Receivables (Créances)** - Class 4 (Debit accounts)
   - Student fees receivable
   - Staff advances
   - Other debtors

4. **Cash & Banks (Trésorerie)** - Class 5
   - Cash on hand
   - Bank accounts
   - Short-term investments

#### LIABILITIES & EQUITY (PASSIF)
1. **Equity & Capital (Capitaux Propres)** - Class 1
   - Capital
   - Retained earnings
   - Current year profit/loss

2. **Payables (Dettes)** - Class 4 (Credit accounts)
   - Suppliers payable
   - Staff salaries payable
   - Tax liabilities
   - Other creditors

### Calculation Logic
```php
// Assets calculation
Total Assets = Fixed Assets + Inventory + Receivables + Cash & Banks

// Liabilities calculation
Total Liabilities & Equity = Equity + Payables

// Balance verification
Total Assets MUST EQUAL Total Liabilities & Equity
```

### Access URL
```
/admin/general-ledger/balance-sheet
```

---

## 3. Income Statement (Compte de Résultat)

### Purpose
The Income Statement shows the organization's financial performance over a specific period, displaying all revenue and expenses to calculate the net profit or loss.

### Structure

#### REVENUE (PRODUITS) - Class 7
- Student fees (tuition, registration, etc.)
- Government subsidies
- Other income
- **Total Revenue**

#### EXPENSES (CHARGES) - Class 6
- Staff salaries
- Utilities
- Supplies
- Maintenance
- Transportation
- **Total Expenses**

#### OPERATING RESULT
```
Operating Result = Total Revenue - Total Expenses
```

#### OTHER RESULTS - Class 8
- Exceptional income/expenses
- Prior period adjustments
- **Total Other Results**

#### NET PROFIT/LOSS
```
Net Profit/Loss = Operating Result + Other Results
```

### Additional Metrics
- **Profit Margin**: `(Net Profit / Total Revenue) × 100`
- Visual indicators for profit (green) or loss (red)

### Access URL
```
/admin/general-ledger/income-statement
```

---

## 4. Features

### Balance Sheet Features
1. **Date Selection**
   - "As of Date" - View balance at specific date
   - "Comparative Date" (Optional) - Compare with another period

2. **Automatic Calculations**
   - Cumulative balances from opening to selected date
   - Separate asset and liability totals
   - Balance verification alert

3. **Visual Indicators**
   - Green alert: Balance sheet is balanced ✅
   - Red alert: Balance sheet has discrepancy ❌
   - Section headers with color coding

4. **Export Options**
   - Print (browser print)
   - PDF export
   - Excel export

### Income Statement Features
1. **Date Range Selection**
   - Start Date (optional - from beginning if not set)
   - End Date (required)

2. **Categorized Display**
   - Revenue by category
   - Expenses by category
   - Expandable account details

3. **Summary Cards**
   - Total Revenue (green)
   - Total Expenses (red)
   - Net Profit/Loss (blue/orange)
   - Profit Margin (cyan)

4. **Visual Indicators**
   - Green for profit ↑
   - Red for loss ↓
   - Percentage profit margin

---

## 5. Routes

### Route Definitions
**File:** `routes/web.php` (Line 375+)

```php
// General Ledger
Route::get('general-ledger', 'GeneralLedgerController@index')
    ->name('general-ledger.index');

// Balance Sheet
Route::get('general-ledger/balance-sheet', 'GeneralLedgerController@balanceSheet')
    ->name('general-ledger.balance-sheet');
Route::get('general-ledger/balance-sheet/export-pdf', 'GeneralLedgerController@exportBalanceSheetPDF')
    ->name('general-ledger.balance-sheet-pdf');
Route::get('general-ledger/balance-sheet/export-excel', 'GeneralLedgerController@exportBalanceSheetExcel')
    ->name('general-ledger.balance-sheet-excel');

// Income Statement
Route::get('general-ledger/income-statement', 'GeneralLedgerController@incomeStatement')
    ->name('general-ledger.income-statement');
Route::get('general-ledger/income-statement/export-pdf', 'GeneralLedgerController@exportIncomeStatementPDF')
    ->name('general-ledger.income-statement-pdf');
Route::get('general-ledger/income-statement/export-excel', 'GeneralLedgerController@exportIncomeStatementExcel')
    ->name('general-ledger.income-statement-excel');
```

### Route Parameters
- **Balance Sheet:**
  - `as_of_date`: Date for balance (YYYY-MM-DD)
  - `comparative_date`: Optional comparison date (YYYY-MM-DD)

- **Income Statement:**
  - `start_date`: Optional period start date (YYYY-MM-DD)
  - `end_date`: Period end date (YYYY-MM-DD)

---

## 6. Controller Methods

### File: `app/Http/Controllers/Admin/GeneralLedgerController.php`

#### Public Methods

##### 1. `balanceSheet(Request $request)`
Displays the balance sheet report.

**Parameters:**
- `as_of_date` (optional): Date for balance, defaults to today
- `comparative_date` (optional): Comparison date

**Returns:** `View` - Balance sheet with all account balances

**Logic:**
```php
// Get account balances by class
$fixedAssets = getClassBalances(2, $asOfDate);
$inventory = getClassBalances(3, $asOfDate);
$receivables = getClassBalances(4, $asOfDate, debitOnly: true);
$cashAndBanks = getClassBalances(5, $asOfDate);
$equity = getClassBalances(1, $asOfDate);
$payables = getClassBalances(4, $asOfDate, creditOnly: true);

// Calculate totals
$totalAssets = sum(all asset categories);
$totalLiabilities = sum(equity + payables);
```

##### 2. `incomeStatement(Request $request)`
Displays the income statement report.

**Parameters:**
- `start_date` (optional): Period start date
- `end_date` (optional): Period end date, defaults to today

**Returns:** `View` - Income statement with revenue and expenses

**Logic:**
```php
// Get period activity
$revenue = getClassBalances(7, $endDate, startDate: $startDate);
$expenses = getClassBalances(6, $endDate, startDate: $startDate);
$otherResults = getClassBalances(8, $endDate, startDate: $startDate);

// Calculate profit/loss
$netProfit = $totalRevenue + $totalOtherResults - $totalExpenses;
```

##### 3. Export Methods
- `exportBalanceSheetPDF(Request $request)`
- `exportBalanceSheetExcel(Request $request)`
- `exportIncomeStatementPDF(Request $request)`
- `exportIncomeStatementExcel(Request $request)`

#### Private Helper Methods

##### 1. `getClassBalances($classNumber, $asOfDate, $debitOnly, $creditOnly, $startDate)`
Retrieves all account balances for a specific class.

**Parameters:**
- `$classNumber`: OHADA class (1-9)
- `$asOfDate`: Balance date
- `$debitOnly`: Filter only debit accounts (for receivables)
- `$creditOnly`: Filter only credit accounts (for payables)
- `$startDate`: For period activity (income statement)

**Returns:** `Collection` - Accounts with calculated balances

##### 2. `getClassBreakdown($classNumber, $startDate, $endDate)`
Groups accounts by category for detailed breakdown.

**Returns:** `Collection` - Categories with accounts and totals

##### 3. `calculateTotal($collections)`
Sums balances from multiple account collections.

**Returns:** `float` - Total balance

##### 4. `calculateOpeningBalance($accountId, $beforeDate)`
Calculates cumulative balance before a date.

##### 5. `calculatePeriodDebit($accountId, $startDate, $endDate)`
Calculates total debits for a period.

##### 6. `calculatePeriodCredit($accountId, $startDate, $endDate)`
Calculates total credits for a period.

---

## 7. Views

### Balance Sheet View
**File:** `resources/views/admin/general-ledger/balance-sheet.blade.php`

**Sections:**
1. Header with title and action buttons
2. Date filters (as of date, comparative date)
3. Assets table
   - Fixed Assets section
   - Inventory section
   - Receivables section
   - Cash & Banks section
   - Total Assets row
4. Liabilities & Equity table
   - Equity section
   - Payables section
   - Total Liabilities row
5. Balance verification alert
6. Report footer

**Key Features:**
- Responsive table layout
- Print-friendly styling
- Color-coded sections
- Comparative columns (if date selected)
- Balance verification with visual indicator

### Income Statement View
**File:** `resources/views/admin/general-ledger/income-statement.blade.php`

**Sections:**
1. Header with title and action buttons
2. Date range filters
3. Revenue section
   - By category
   - Detailed accounts
   - Total revenue
4. Expenses section
   - By category
   - Detailed accounts
   - Total expenses
5. Operating result
6. Other results section
7. Net profit/loss
8. Summary cards (info boxes)
9. Report footer

**Key Features:**
- Category-based organization
- Expandable account details
- Visual profit/loss indicators
- Summary metrics cards
- Profit margin calculation
- Print-friendly layout

---

## 8. Usage Guide

### Accessing Reports

#### From General Ledger Dashboard
1. Navigate to **Accounting** > **General Ledger**
2. In the "Quick Access" section, click on:
   - **Balance Sheet** (Yellow card) - For Bilan
   - **Income Statement** (Green card) - For Compte de Résultat

#### Direct URLs
- Balance Sheet: `/admin/general-ledger/balance-sheet`
- Income Statement: `/admin/general-ledger/income-statement`

### Using the Balance Sheet

#### Step 1: Select Date
1. Choose "As of Date" - The date for which you want to see balances
2. (Optional) Choose "Comparative Date" - A previous date for comparison
3. Click "Apply Filter"

#### Step 2: Review Report
- **Assets Section:** Shows what the organization owns
- **Liabilities Section:** Shows what the organization owes
- **Verification:** Check if balance sheet is balanced (Assets = Liabilities)

#### Step 3: Export or Print
- **Print:** Click print button (or Ctrl+P)
- **PDF:** Click "Export PDF" to download
- **Excel:** Click "Export Excel" to download spreadsheet

### Using the Income Statement

#### Step 1: Select Period
1. (Optional) Choose "Start Date" - Leave blank for inception to date
2. Choose "End Date" - The period end date
3. Click "Apply Filter"

#### Step 2: Review Report
- **Revenue Section:** All income sources
- **Expenses Section:** All costs incurred
- **Operating Result:** Revenue minus Expenses
- **Net Profit/Loss:** Final result after other items

#### Step 3: Analyze Metrics
- Look at summary cards for quick overview
- Check profit margin percentage
- Compare categories to identify trends

#### Step 4: Export or Print
- Same options as Balance Sheet

---

## 9. Export Functionality

### Print Export
**How it works:**
- Uses browser's native print functionality
- CSS media queries hide non-essential elements
- Automatically formats for A4 paper
- Maintains table borders and styling

**Usage:**
```javascript
// Triggered by print button
onclick="window.print()"
```

**CSS Print Styles:**
```css
@media print {
    .no-print { display: none !important; }
    .card { box-shadow: none !important; border: none !important; }
    .info-box { display: none !important; }
}
```

### PDF Export
**Implementation:**
Uses Laravel's PDF library (barryvdh/laravel-dompdf)

**Controller Method:**
```php
public function exportBalanceSheetPDF(Request $request)
{
    $data = $this->getBalanceSheetData($asOfDate, $comparativeDate);
    $pdf = PDF::loadView('admin.general-ledger.balance-sheet-pdf', $data);
    $pdf->setPaper('A4', 'portrait');
    return $pdf->download('balance-sheet-' . $asOfDate . '.pdf');
}
```

**Note:** Currently returns JSON placeholder. Implementation requires:
1. Create PDF-specific view templates
2. Configure PDF library settings
3. Test output formatting

### Excel Export
**Implementation:**
Uses Maatwebsite\Excel package

**Controller Method:**
```php
public function exportBalanceSheetExcel(Request $request)
{
    // Implementation using Excel export class
    return Excel::download(new BalanceSheetExport($data), 'balance-sheet.xlsx');
}
```

**Note:** Currently returns JSON placeholder. Implementation requires:
1. Create Export classes (e.g., `BalanceSheetExport`)
2. Define column mappings
3. Format cells (numbers, dates, styling)

---

## 10. OHADA Compliance

### OHADA Chart of Accounts
The implementation follows the OHADA (Organisation pour l'Harmonisation en Afrique du Droit des Affaires) accounting system used in French-speaking African countries.

#### Class Structure
- **Class 1:** Equity & Capital (Capitaux)
- **Class 2:** Fixed Assets (Immobilisations)
- **Class 3:** Inventory & Stocks (Stocks)
- **Class 4:** Third Parties (Tiers) - Both receivables and payables
- **Class 5:** Financial Accounts (Trésorerie)
- **Class 6:** Expense Accounts (Charges)
- **Class 7:** Revenue Accounts (Produits)
- **Class 8:** Off-Balance Sheet & Special (Comptes Spéciaux)
- **Class 9:** Analytical Accounting (Comptabilité Analytique)

### Balance Sheet Mapping

#### Assets (Actif)
```
Class 2 → Fixed Assets (Actif Immobilisé)
Class 3 → Inventory (Stocks et Encours)
Class 4 (Debit) → Receivables (Créances)
Class 5 → Cash & Banks (Trésorerie-Actif)
```

#### Liabilities (Passif)
```
Class 1 → Equity (Capitaux Propres)
Class 4 (Credit) → Payables (Dettes)
```

### Income Statement Mapping
```
Class 7 → Revenue (Produits)
Class 6 → Expenses (Charges)
Class 8 → Other Results (Autres Charges et Produits)
```

### Accounting Equation
```
Assets = Liabilities + Equity
(Actif = Passif + Capitaux Propres)
```

### Terminology
| English | French (OHADA) |
|---------|---------------|
| Balance Sheet | Bilan |
| Income Statement | Compte de Résultat |
| Assets | Actif |
| Liabilities | Passif |
| Equity | Capitaux Propres |
| Fixed Assets | Immobilisations |
| Inventory | Stocks |
| Receivables | Créances |
| Payables | Dettes |
| Revenue | Produits |
| Expenses | Charges |
| Cash | Trésorerie |

---

## 11. Troubleshooting

### Issue: Balance Sheet Not Balanced

**Symptom:** Total Assets ≠ Total Liabilities & Equity

**Possible Causes:**
1. Unposted journal entries
2. Incorrect account classification
3. Data entry errors
4. Opening balances not set correctly

**Solutions:**
1. Verify all journal entries are posted:
   ```sql
   SELECT * FROM journal_entries WHERE is_posted = 0;
   ```

2. Check account classifications:
   ```sql
   SELECT * FROM chart_of_accounts 
   WHERE account_category != 'detail' 
   AND (opening_balance != 0 OR current_balance != 0);
   ```

3. Run balance recalculation script:
   ```bash
   php recalculate_balances.php
   ```

4. Verify opening balances match previous period closing:
   ```sql
   SELECT account_code, account_name, opening_balance, current_balance 
   FROM chart_of_accounts 
   WHERE is_active = 1 
   ORDER BY account_code;
   ```

### Issue: No Data Showing

**Symptom:** Reports show "No accounts found" or zero balances

**Possible Causes:**
1. No posted journal entries
2. Date filters excluding all data
3. Accounts not properly categorized as 'detail'

**Solutions:**
1. Check for posted entries:
   ```sql
   SELECT COUNT(*) FROM journal_entries WHERE is_posted = 1;
   ```

2. Verify date range includes transactions:
   ```sql
   SELECT MIN(entry_date), MAX(entry_date) 
   FROM journal_entries 
   WHERE is_posted = 1;
   ```

3. Check account categories:
   ```sql
   SELECT account_category, COUNT(*) 
   FROM chart_of_accounts 
   WHERE is_active = 1 
   GROUP BY account_category;
   ```

### Issue: Wrong Account in Wrong Section

**Symptom:** Receivable showing as payable, or vice versa

**Possible Causes:**
1. Incorrect `normal_balance` setting
2. Class 4 account not properly identified as debit/credit

**Solutions:**
1. Check account setup:
   ```sql
   SELECT account_code, account_name, normal_balance 
   FROM chart_of_accounts 
   WHERE account_code LIKE '4%';
   ```

2. Update if necessary:
   ```sql
   UPDATE chart_of_accounts 
   SET normal_balance = 'debit' 
   WHERE account_code LIKE '41%'; -- Receivables
   
   UPDATE chart_of_accounts 
   SET normal_balance = 'credit' 
   WHERE account_code LIKE '40%'; -- Payables
   ```

### Issue: Export Not Working

**Symptom:** PDF/Excel export returns error or placeholder message

**Current Status:** Export functions return JSON placeholder

**Implementation Required:**
1. For PDF export:
   - Create `balance-sheet-pdf.blade.php` template
   - Create `income-statement-pdf.blade.php` template
   - Configure PDF settings in `config/pdf.php`

2. For Excel export:
   - Create `App\Exports\BalanceSheetExport` class
   - Create `App\Exports\IncomeStatementExport` class
   - Install/configure Maatwebsite\Excel package

### Issue: Performance Slow on Large Data

**Symptom:** Reports take long time to load

**Solutions:**
1. Add database indexes:
   ```sql
   CREATE INDEX idx_journal_entries_posted_date 
   ON journal_entries(is_posted, entry_date);
   
   CREATE INDEX idx_journal_entry_lines_account 
   ON journal_entry_lines(account_id, journal_entry_id);
   ```

2. Cache frequent queries:
   ```php
   $balances = Cache::remember('balance-sheet-' . $asOfDate, 3600, function() {
       return $this->getBalanceSheetData($asOfDate);
   });
   ```

3. Optimize balance calculations by using aggregations:
   ```php
   JournalEntryLine::where('account_id', $accountId)
       ->whereHas('journalEntry', function($q) use ($date) {
           $q->where('is_posted', true)
             ->where('entry_date', '<=', $date);
       })
       ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
       ->first();
   ```

---

## Best Practices

### 1. Regular Reconciliation
- Run Balance Sheet at end of each month
- Verify balance (Assets = Liabilities + Equity)
- Compare with previous periods

### 2. Period Closing
- Generate Income Statement before closing period
- Document profit/loss
- Transfer net result to equity accounts

### 3. Comparative Analysis
- Use comparative dates on Balance Sheet
- Compare month-to-month or year-to-year
- Identify trends and anomalies

### 4. Backup Before Period Close
- Export both reports to PDF
- Save Excel versions for analysis
- Archive with period documents

### 5. Data Integrity
- Post all journal entries before running reports
- Verify account classifications
- Run balance recalculation if discrepancies found

---

## Future Enhancements

### Planned Features
1. **Cash Flow Statement**
   - Operating activities
   - Investing activities
   - Financing activities

2. **Financial Ratios**
   - Liquidity ratios
   - Profitability ratios
   - Efficiency ratios

3. **Graphical Analysis**
   - Charts and graphs
   - Trend analysis
   - Comparative visualizations

4. **Multi-Period Comparison**
   - Side-by-side comparison
   - Variance analysis
   - Percentage changes

5. **Budget vs Actual**
   - Budget tracking
   - Variance reporting
   - Performance metrics

6. **Scheduled Reports**
   - Automated generation
   - Email delivery
   - Dashboard integration

---

## Support and Maintenance

### Documentation Location
- **This File:** `FINANCIAL_STATEMENTS_DOCUMENTATION.md`
- **Related:** `JOURNAL_ENTRY_VALIDATION_README.md`
- **Related:** `JOURNAL_ENTRY_TRASH_DOCUMENTATION.md`

### Contact Information
For technical support or questions about the financial statements:
- System: PaxHi School Management
- Module: Accounting / General Ledger
- Date Implemented: October 10, 2025

### Version History
- **v1.0** (2025-10-10): Initial implementation
  - Balance Sheet with comparative analysis
  - Income Statement with categorization
  - Print functionality
  - Export placeholders (PDF/Excel)
  - OHADA compliance
  - Full bilingual support

---

**End of Documentation**
