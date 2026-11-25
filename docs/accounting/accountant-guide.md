# The accountant's guide: routine, reports and controls

How the school's books work in this system, what to do each day, month and
year, and every report the system produces: what it answers, where its figures
come from, and what it must agree with.

The books follow SYSCOHADA. Amounts are in FCFA.

---

## 1. How the books are kept

Everything ends up as **posted journal entries** in the ledger. Most are
written for you when a record is saved; a few are entered by hand.

| What happens | Where it is recorded | What the ledger gets |
|---|---|---|
| A fee is assigned to a student | Fees → assign | what the student owes: Dr **411** / Cr fee income (7xx), on the assign date |
| A fee payment is taken | Fees → collect payment | Dr cash/bank (5xx) / Cr **411**, for the cash actually received |
| A student pays more than the fee asks (with "allow overpayment" ticked) | the same payment | the excess is held for the student: Dr 411 / Cr **419** |
| A student pays beyond every instalment they owe (e.g. both instalments before the second is assigned) | the same payment, "keep the rest as credit" | Dr cash / Cr **419** (paid in advance) |
| Credit is used on a fee (automatically when the next instalment is assigned, or from the student's credit page) | | Dr 419 / Cr 411 |
| A credit is refunded | Student credit → refund | Dr 419 / Cr cash |
| Another income (hall rental, donation) | Income | Dr cash / Cr the category's income account |
| A cash expense | Expense | Dr the category's expense account / Cr cash |
| A supplier invoice | Supplier Bills (entered, then approved) | Dr expense / Cr **401** Fournisseurs |
| Paying a supplier | Supplier Payments | Dr 401 / Cr bank or cash |
| Payroll paid | Payroll | salaries, social charges, tax withheld, net pay |
| Money moved between cash boxes and bank | Payment Accounts → transfer | Dr one 5xx / Cr another |
| Accruals, corrections, opening balances, depreciation | Journal Entries (by hand) | as entered |
| Bank charges or interest on the statement | Bank Reconciliation | Dr expense / Cr bank, or Dr bank / Cr income |

Each category (fee, income, expense) carries a **mapping**: the ledger
account it posts to and the budget line it counts on (Accounting →
Transaction mappings). An unmapped category is recorded but not posted; Ledger
health lists it.

**Fees are on accrual.** Assigning a fee posts what the student owes to 411
and the fee's income; a payment settles 411. So 411 is, at every date, what
students owe, and the income statement shows the fees of the period whether
or not they are paid yet. An applicant's admission fee is the exception: an
applicant owes nothing until they come, so it becomes income when paid.
Changing, cancelling or deleting a fee corrects its bill (the old one is
reversed). The credit audit (section 7) and Ledger health check that 411
equals what the fee records say is owed.

### Rules the system enforces

- **Every entry balances**, and posts only to detail accounts.
- **A closed month or year accepts nothing.** A record dated in a closed
  month is posted on the day it is recorded instead, never into the closed
  month.
- **Nothing posted is deleted.** A mistake is corrected by a reversing entry:
  in the original's month while that month is open, otherwise today.
- **Every action that posts money goes through a review**: the dialog shows
  the exact ledger entry and its effects, and confirming needs the tick box.
  The server refuses the action without it.
- **Entering and approving are separate.** The Accountant enters supplier
  bills, pays them, prepares bank reconciliations; Admin approves bills,
  completes reconciliations, closes months, reverses entries.

---

## 2. The routine

### Every day
1. Take fee payments (Fees) and print the receipt.
2. Record other income and cash expenses (Income / Expense).
3. Enter supplier invoices as they arrive (Supplier Bills → Save and review
   for approval).
4. Glance at **Ledger health**: it should read green. Anything red is money on
   one side of the books and not the other.

### Every week
1. Pay suppliers that are due (Supplier Payments → Review the payment).
   **Payables Aging** shows what is due and overdue.
2. Move cash to the bank (Payment Accounts → transfer).
3. Check **Fee collection** on the budget analysis page and chase the largest
   debtors.

### Every month
1. **Bank reconciliation** for each bank, cash and mobile money account
   (Accounting → Bank Reconciliation): enter the statement's closing balance,
   tick the movements that appear on it, post any charges or interest from the
   same screen, and complete it when the difference is nil.
2. **Accruals**: costs incurred but not yet invoiced (Dr expense / Cr 408) and
   staff costs owed (Cr 428), prepaid expenses (Dr 476), income received in
   advance (Cr 477). Reverse them on the first day of the next month (Journal
   entry → Reverse on…).
3. Check that **Payables Aging ties to account 401** (difference 0) and that the
   **credit audit** shows 419 in agreement.
4. Check the **budget sheet against the ledger** (Budget sheet → open the budget; the reconciliation is at the foot of the sheet):
   every difference must be named.
5. Read **Financial health** and the **budget analysis**.
6. Print the **trial balance, income statement and balance sheet**.
7. **Close the month** (Accounting → Fiscal years → the year → Close). Months
   close in order; the review lists anything not posted, draft bills and
   accounts without a reconciliation.

### At the year end
1. All twelve months closed, all bank accounts reconciled.
2. Depreciation posted (Fixed Assets → Depreciation).
3. **Year-End Closing**: the checklist, then the closing entries (results to
   retained earnings), approved by Admin.
4. Export the year's statements.

---

## 3. Every report

**Basis** says where the figures come from. *Ledger* reports read posted
journal entries and must agree with the trial balance. *Records* reports read
the fee, income, expense and payroll records directly; they answer operational
questions and are reconciled to the ledger by the checks named.

### Financial statements

| Report | Where | Answers | Basis | Must agree with |
|---|---|---|---|---|
| **Trial balance** | Accounting → Reports → Trial balance (Excel) | Does every debit have its credit; what is the balance of each account | Ledger | Debits = credits; each account = its general ledger |
| **General ledger** / account ledger / ledger by class | Accounting → Reports → General ledger | Every movement on an account, with running balance | Ledger | Trial balance |
| **Income statement** (Compte de résultat) | General ledger → Income statement (PDF, Excel) | Income, expenses and the result for a period | Ledger (the default view) | Classes 6 and 7 of the trial balance; the result on the balance sheet |
| Income statement, *by module* (switch off "accounting view") | same screen | Income and costs by module, fees counted when cash arrived | Records | Not the books: it leaves out supplier bills, manual entries, bank charges and student credit. A banner says so. |
| **Balance sheet** (Bilan), with comparison date | General ledger → Balance sheet (PDF, Excel) | What the school owns and owes at a date | Ledger | Assets = liabilities + equity + the year's result |
| **Cash flow statement**, comparative cash flow | Accounting Reports → Cash Flow Statement | Where cash came from and went: operating, investing, financing | Ledger | The change in class 5 over the period |

### Receivables, payables and cash

| Report | Where | Answers | Basis | Must agree with |
|---|---|---|---|---|
| **Payables Aging** (bills) | Income/Expense → Payables Aging | What is owed to each supplier, by how overdue | Supplier bills | **Account 401**: the screen shows the difference, which must be 0 |
| **Supplier statement** | Supplier Bills → Supplier Statement | A supplier's bills and payments with running balance | Supplier bills | The supplier's own statement |
| Payables / Receivables Aging (ledger) | Accounting Reports | Balances on 40x / 41x by age | Ledger | Trial balance |
| **Student Fee Aging** | Accounting Reports → Student Fee Aging | Fees still owed, by how overdue | Records (fees) | Fees report |
| **Fees report** (PDF, Excel) | Fees → report | Each student's fees, paid, owed; totals collected | Records, net of credit moved between fees | Fee postings (credit audit, section 3) |
| **Credit audit** | Fees → Credit audit (also `php artisan fees:credit-audit`) | Credit raised twice, fees paid with no receipt, whether fee postings equal cash, whether **419** agrees | Records and ledger | Section 3 must read "match"; section 6 "agree" |
| **Bank reconciliation** | Accounting → Bank Reconciliation | Does the statement agree with the books | Ledger | Difference nil to complete |
| Payment accounts: cash flow, summary, statement | Payment Accounts → reports | Movements per cash box | Payment account transactions | "Cash and bank in the ledger" comparison |
| **Daybook** (cash book), monthly summary (PDF, Excel) | Daybook | Every receipt and payment, day by day, against budget lines | Records, plus supplier bills and bank charges from the ledger | Budget sheet (same totals by line) |

### Budget

| Report | Where | Answers | Basis | Must agree with |
|---|---|---|---|---|
| **Budget sheet** (PDF, Excel) | Budget → Budget sheet | Budgeted against actual by line, for the budget year | Records, plus supplier bills and bank charges from the ledger | **Ledger**, section by section; any difference is named (student credit timing, unfiled categories) |
| Budget vs actual | Accounting Reports → Budget vs Actual | The same, as a report | Budget allocations | Budget sheet |
| Budget performance, variance, department, cash flow | Budget → reports | Over/under-spending by line and department | Budget allocations | Budget sheet |

### Payroll and tax

| Report | Where | Answers |
|---|---|---|
| Payroll report, payslips | Staff → Payroll report | Gross, deductions, employer charges, net, per run |
| Staff tax report (PDF, Excel) | Staff → Tax report | Income tax and CNPS withheld, for the remittance |
| Tax remittances | Payroll → remittances | What was paid to the State and CNPS, and what is still owed |

### Assets

| Report | Where | Answers |
|---|---|---|
| Asset register, asset report | Accounting → Fixed Assets | What the school owns, cost, accumulated depreciation |
| Depreciation report | Fixed Assets → Depreciation Report | The depreciation schedule and what has been posted |

### Control screens

| Screen | Where | Answers |
|---|---|---|
| **Ledger health** | Accounting → Ledger health | Records not posted, entries on the wrong date or amount, mappings missing, entries outside their year or month, unbalanced or duplicated entries |
| Cash and bank in the ledger | Accounting → Transaction mappings | Each cash box against its ledger account |
| Fiscal years | Accounting → Fiscal years | Which months are open or closed |

---

## 4. Analysis

| Screen | What it shows |
|---|---|
| **Financial health** (Accounting Reports → Financial health) | From the books, as at any date: cash and bank; **months of cash cover**; surplus and margin for the year; **payroll as a share of income**; **share of fees due that has been collected**; supplier bills overdue; money held for students on 419; budget spent against the share of the year gone. Each with a verdict (Healthy / Watch / Act now) and what it means. Then twelve months of income, expenses, surplus and month-end cash, and this year to date against the same dates last year. |
| **Budget analysis** (Daybook → Analysis, and its PDF pack) | Cash position month by month; money in and out; surplus or deficit by month; spending against the year; where money comes from and goes; what changed between months; the largest transactions; money on no budget line; **fee collection by student**: fully paid, part paid, nothing paid, by programme, by fee category, month by month, and who owes the most, with arrears from earlier years. Each chart says how to read it and what to do. |
| Budget reports | Variance and performance by line and department. |
| Dashboard | Fees collected, income and expenses at a glance. |

---

## 5. Who can do what

| Action | Accountant | Admin |
|---|---|---|
| Take fee payments, record income and expenses | yes | yes |
| Enter supplier bills; pay suppliers | yes | yes |
| Approve or void supplier bills | | yes |
| Manual journal entries | enter | enter and post |
| Reverse a posted manual entry | | yes |
| Prepare a bank reconciliation (tick, post charges) | yes | yes |
| Complete a bank reconciliation | | yes |
| Close or reopen a month; close the year | | yes |

Permissions are under Settings → Roles; the names start with
`supplier-bill-`, `journal-entry-`, `bank-reconciliation-` and
`accounting-period-`.

---

## 6. How fee money is accounted for

- A fee is **billed when assigned** (Dr 411 / Cr income) and **settled when
  paid** (Dr cash / Cr 411). The payment posts the cash it received: what was
  paid on it, less credit brought in from another fee (no cash came with
  that), less instalments a payment plan posted itself.
- Money paid **ahead** for an instalment not yet assigned is held on 419 and
  settles that instalment as soon as it is assigned.
- An **overpayment** stays on the fee it was paid with, and the excess is
  **held on 419** until it settles another fee (then it becomes that fee's
  income) or is refunded (then the cash goes out). Only credit that cash backs
  is held: credit raised twice for one overpayment holds nothing.
- The **budget sheet and daybook** count fee cash in the month it arrived; the
  ledger counts overpaid money as income in the month it is used. The
  reconciliation names this difference ("Student credit … timing").
- `php artisan fees:credit-audit` reports all of this, and
  `--correct-ledger` / `--post-credit` bring the ledger into line (after a
  backup).

---

## 7. Known limits, and what to decide

1. **Fees moved to accrual on 24 Nov 2026** (`php artisan fees:accrual
   --enable`). Income on the testbed rose by 3,684,333: fees billed and not
   yet paid, now income and owed on 411.
2. **Fee cash is dated by its receipts** (since 24 Nov 2026): a fee paid in
   October and December shows each payment in its own month, in the ledger,
   the daybook and the budget sheet alike. A payment recorded with no receipt
   is dated on the fee's last payment date.
3. **Bank receipts land in the cash account** until the default cash and bank
   accounts are set (Accounting → Transaction mappings → Cash and bank in the
   ledger). Set them, and the bank reconciliation will have the bank movements
   to match.
4. **No payment accounts (cash boxes) are set up** on this install, so cash
   box transfers and per-cashier balances are not in use.
5. **Opening balances**: the cash and bank accounts go below zero in the
   books in December 2025 and January 2026 (Financial health shows it), which
   usually means opening balances were never entered. Enter them as an
   opening journal entry against capital or retained earnings.
6. **Budget filing**: only 1% of the 2026/2027 budget shows as spent with 23%
   of its year gone; check that expenses are being filed under categories
   mapped to that budget's lines.
7. **Four tuition income records (20,254,000)** are in categories with no
   budget line, so the budget sheet cannot place them (the reconciliation
   lists them).
8. **The chart is on the standard SYSCOHADA numbering** (since 25 November
   2026, `php artisan accounts:syscohada`). The school's first chart used
   several numbers for something else; only the numbers and names changed, so
   every balance and posted entry stayed where it was. The ones an accountant
   meets most:

   | Was | Now | Account |
   |---|---|---|
   | 711 / 712 / 713 | **7061 / 7062 / 7063** | Tuition, registration, exam fees (services sold, under 706; 71 means subsidies) |
   | 73 | **718** | Grants and subsidies received |
   | 443 | **447** | Tax withheld from salaries, owed to the State (443 is VAT charged) |
   | 412 / 421 | **421 / 422** | Staff advances / net salaries owed |
   | 40 | **401** | Suppliers |
   | 53 | **552** | Mobile Money |
   | 12 | **13** | The year's result (12 is now retained earnings, "Report à nouveau") |
   | 601 / 602 / 605 / 606 / 607 | **6055 / 6056 / 6051 / 6053 / 6054** | Office supplies, teaching material, water and electricity, fuel, cleaning |
   | 608 | **602** | Agricultural project purchases |
   | 626 / 627 / 628 | **628 / 631 / 6388** | Telecom and internet, bank charges, other external charges |
   | 631 / 633 / 634 / 635 / 638 | **633 / 618 / 6383 / 627 / 626** | Training, travel, receptions, advertising, documentation |
   | 28 / 681 | **284 / 68131** | Accumulated depreciation, depreciation charge (equipment) |

   411 (fees receivable), 419 (credit held for students), 431 (CNPS), 521
   (bank), 571 (cash), 661 (salaries) and 664 (social charges) did not change.
   The command refuses any account whose name no longer looks like the one it
   expects, so an account renamed by hand is left for the accountant.
