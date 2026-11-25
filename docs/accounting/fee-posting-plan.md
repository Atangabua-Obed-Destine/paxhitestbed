# Fee posting: what is wrong today, and the plan to fix it

## Status, 24 Nov 2026 (testbed)

| Step | Status |
|---|---|
| 1. Repost fees at the cash received | **Done on the testbed.** 53 fees reposted (6,193,000 off tuition income and cash); 21 more that `ledger:backfill` had re-posted at the paid amount were reversed again, and the backfill now posts the cash received. Fee postings now equal the cash collected exactly (22,143,667, plan instalments included). |
| 2. Hold overpayments on 419 | **Done.** `StudentCreditLedger`: hold (Dr fee income / Cr 419) when credit is raised, release (Dr 419 / Cr income) when it is used, refund (Dr 419 / Cr cash) when it is paid back. Only credit that cash backs is held: 6,233,000 of the 6,992,000 raised. All of it has since been used, so 419 stands at nil, and total income did not change; only the month it is recognised in did. Refunds used to post nothing at all; they now take the cash out of the books. |
| Decision: the 759,000 of unbacked credit already spent | **Honoured** (it stays as a discount on the fees it settled). No cash came in, so nothing is posted for it. The accountant can still recover it from the students; the credit audit lists them. |
| Decision: the 22 fees with no receipt (2,363,333) | Left for the accountant to confirm against the cash book; still listed by `php artisan fees:credit-audit`, section 4. |
| Fees on accrual (decided by the school, 24 Nov) | **Done.** `php artisan fees:accrual --enable`: 298 fees billed to 411 (25,230,000), 259 fee payments and 4 plan instalments reposted to settle 411, credit moved between 411 and 419. 411 = what students owe (3,684,333) and agrees with the fee records. |
| What the "overpayments" were | The school confirmed: students paid both instalments before the second was assigned; the excess was recorded on the first and transferred to the second. That is money paid in advance, which 419 holds until the instalment is billed. Quick-assigning an instalment now applies such credit automatically. |
| 3. Cash on the day it came in | **Done.** Each fee's approved receipts dated before its last payment are posted as their own entries, on their own days, into the account their method names (`fee_cash_parts`); the fee's own entry carries the rest on its pay date. Receipts on the same day as the fee's last payment stay in that entry, so an ordinary payment makes no extra entries. Receipts from before the first fiscal year stay with the fee. On the testbed: 54 fees, 81 entries, 6,713,000 moved to the month it arrived. The budget sheet, the daybook and the fee collection chart read the same split, so they still agree with the ledger month by month. `fees:credit-audit --correct-ledger` applies it. |

On production, the same commands apply, in this order, after a database backup:
`php artisan migrate --force`, then `php artisan accounts:syscohada` (read the preview; any account marked NAME DIFFERS is settled by hand first) and `php artisan accounts:syscohada --apply`, then `php artisan fees:credit-audit --correct-ledger --post-credit`, then `php artisan fees:accrual --enable`, then `php artisan fees:credit-audit` (sections 3, 6 and 7 must all agree).


A plan for decision. Nothing described here has been built or changed. The
figures come from the testbed (`php artisan fees:credit-audit`, run on 23 Nov
2026); production will have its own figures.

## How fees reach the books today

- A fee is recorded in the books only when money is received (cash basis).
  Assigning a fee posts nothing; the balance sheet does not show what
  students owe.
- Each fee carries **one** ledger entry for everything paid on it. Every new
  payment cancels that entry and posts the fee's total again, dated on the
  **latest** payment.
- Since 23 Nov the cash side follows the fee's payment method or payment
  account (cash box, bank, mobile money). Because there is still one entry per
  fee, a fee paid partly in cash and partly by bank posts entirely to the
  account of the **last** payment.

## What that gets wrong

| # | Problem | Testbed figure | Effect on the books |
|---|---|---|---|
| 1 | Overpayment credit moved from one fee to another was posted again as cash received | **6,079,667** over 54 fees, Apr–Sep 2026 | Tuition revenue (711, now 7061) and cash (571) both overstated by that amount |
| 2 | Credit raised twice for the same overpayment, already spent on other fees | **759,000** on 5 fees | Students hold credit no payment backs; needs a person to decide |
| 3 | Instalments move to the date of the last payment | every fee paid in more than one go | October cash shows in November, so monthly reports and the cash flow mislead |
| 4 | Overpayments are counted as revenue straight away | part of #1 | The school shows income it has not earned yet; no liability for money it holds |
| 5 | Mixed payment methods on one fee post to one account | any fee paid by two methods | The bank account in the books does not match the bank statement |
| 6 | Fees marked paid with no receipt on file | 22 fees, 2,363,333 | Not a posting error, but cannot be confirmed from the system |
| 7 | Credit applied to fees that were later deleted | 5 applications, 390,000 | Audit trail only; check each student |

The reports that read the fee screens already correct #1 (they use the net
figure); the **ledger** does not, which is why the income statement shows
about 6.1M more fee revenue than the fee screens.

## The plan, in three steps

### Step 1 — correct the double-counted credit (small, ready now)

Run `php artisan fees:credit-audit --correct-ledger`. It re-posts the 54 fees
at the cash they actually received. Every correction is a reversal plus a new
entry; nothing is deleted.

- **Effect:** 2026 tuition revenue and cash in the books both fall by
  6,079,667. The income statement then agrees with the fee screens.
- **Needs:** your accountant's approval, because it changes 2026 figures.
- Problems #2, #6 and #7 stay as reports for a person to work through; no
  tool should guess at them.

### Step 2 — hold overpayments as a liability (medium)

As edutrustshsm already does:

- the excess over what a fee is due is credited to **419 Clients créditeurs**
  (money held for the student), not to revenue;
- when the credit is used on another fee: **Dr 419 / Cr the fee's revenue
  account**, so the money becomes revenue once, when it is earned;
- a refund of credit: **Dr 419 / Cr cash**.

This stops #1 and #4 from happening again. It needs Step 1 first, and a
re-post of fees that currently hold credit.

### Step 3 — one ledger entry per payment (large)

Add a `fee_payments` record: one row per receipt with its amount, date,
payment method, payment account and receipt number. Each row posts its own
entry, on its own date, to its own cash account; reversing a payment reverses
that row only.

- Fixes #3 and #5 completely.
- History is rebuilt from what exists (receipts, payment-account
  transactions, student statement lines). Where a fee's history cannot be
  split — only a total and a last date survive — it becomes one row marked
  "carried from before", posted as it is today.
- The biggest change of the three: new table, changes to fee collection,
  reversal and receipts, and a full re-post of fee history, which re-dates
  revenue into the months it was actually received.

## Decisions needed

1. Approve Step 1 (the 6,079,667 correction) — with the accountant.
2. Decide what to do with the 759,000 of unbacked credit already spent (#2):
   honour it as a discount, or recover it from the students concerned.
3. Confirm the 22 fees without receipts (#6) against the cash book.
4. Approve Steps 2 and 3, in that order, or Step 2 only.

## Not in this plan

Recording fees as owed when they are assigned (Dr 411 / Cr revenue, accrual
accounting). That is a separate policy decision for the school and its
accountant; it would make the balance sheet show what students owe, and is
easiest to add after Step 3.
