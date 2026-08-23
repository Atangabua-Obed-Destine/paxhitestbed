# EdutrustPay — the complete proposal

**Prepared for:** the Finance Council, Archdiocese of Bamenda
**Pilot:** Catholic schools and hospitals under the Archdiocese
**Scope:** School management · Institutional business management · Public fee payment · Consolidated oversight

---

## A note on how to read this document

Everything marked **Today** already exists and runs — it can be demonstrated on a
laptop this week. Everything marked **To build** is the new work this proposal
asks for. The distinction is kept deliberately visible throughout, because a
proposal to a finance council that blurs what exists with what is promised is not
worth reading.

---

# Part One — What this actually is

## The problem, in the Archdiocese's own words

We did not begin with a questionnaire. We studied `Daybook Sec`, the workbook the
bursars actually keep, and it is not an informal spreadsheet. It is a genuine
OHADA cash-analysis ledger built in Excel with real discipline:

| Measured | |
|---|---|
| Sheets in the workbook | **27** (twelve months × two, plus Cumulative, Budget) |
| Analysis columns per month | **155** |
| Income lines | **33** |
| Expense lines | **100** |
| Trading activities tracked | **9** (canteen, uniforms, books, phone booth, PTA…) |
| Financial year | **July → June** |

It already carries proper OHADA account codes — `7020000` Tuition 1st Cycle,
`7021000` Registration, `7022000` State Subvention — and its expense columns
follow the statutory classes in order: purchases, transport, external services,
rates and taxes, personnel, other, financial. Its monthly summary already reports
**Budget · Monthly · Brought Forward · Cumulative · Balance**.

**The bursars do not need to be taught accounting.** They need the typing to
stop, and the Archdiocese needs to see the result without waiting for a file to
be emailed.

### And the workbook names its own losses

Four lines sit in the expense structure, budgeted for every year as a matter of
course:

| Column | Line | What it concedes |
|---|---|---|
| `DG` | **Cash Shortages** | cash is handled by many hands and some does not arrive |
| `DE` | **Bad Debts** | fees charged and never recovered |
| `EH` | **Provision For Uncollected Fees** | more expected never to arrive |
| `EI` | **Provision For Contingencies** | and whatever else the year brings |

We are not making a claim about the Archdiocese. Its own budget makes it.

### What else the structure reveals

- **Every figure is entered by hand, twice** — once in the cash book, once in the
  analysis column, across 155 columns, for twelve months.
- **The workbook lives on one machine.** The Archdiocese cannot see a school's
  position until someone remembers to send the file.
- **Consolidation is manual** — several workbooks opened and added up, so the
  diocesan picture is always late and never quite the same twice.
- **Parents must travel with cash.** Queues at the bursary, cash in a drawer, a
  receipt book — which is precisely where *Cash Shortages* comes from.
- **Relatives abroad cannot pay at all**, though they fund a large share of
  school fees in this country.

---

# Part Two — How the package comes

Three products, one platform. An institution takes what it needs and grows into
the rest.

```
                    ┌──────────────────────────────────────────┐
                    │      BODY MANAGEMENT CONSOLE             │
                    │      (Archdiocese of Bamenda)            │
                    │                                          │
                    │  consolidated position · reconciliation  │
                    │  arrears · collection rates · budgets    │
                    └───────────────┬──────────────────────────┘
                                    │  reporting contract (OHADA groups)
            ┌───────────────────────┼───────────────────────┐
            │                       │                       │
   ┌────────┴─────────┐   ┌─────────┴────────┐   ┌──────────┴─────────┐
   │ SCHOOL SYSTEM    │   │ SCHOOL SYSTEM    │   │ BUSINESS SYSTEM    │
   │ Universities     │   │ Secondary /      │   │ Hospitals ·        │
   │ & higher inst.   │   │ Primary          │   │ pharmacies · shops │
   └────────┬─────────┘   └─────────┬────────┘   └────────────────────┘
            │                       │
            └───────────┬───────────┘
                        │  balances up · payments down
            ┌───────────┴────────────┐
            │   EDUTRUSTPAY PORTAL   │
            │   public fee payment   │
            └────────────────────────┘
```

| # | Product | For | State |
|---|---|---|---|
| 1 | **Integrated School Management System** | Universities, secondary and primary schools | **Today** |
| 2 | **Institutional Business Management** | Hospitals, pharmacies, shops, restaurants, guest houses | **Today** |
| 3 | **EdutrustPay** — public portal + Body Console | Parents, and the body that owns the institutions | **To build** |

---

# Part Three — The Integrated School Management System

Two editions sharing one design: a **higher-education edition** (faculties,
programmes, credits, semesters, senate deliberation) and a **secondary/primary
edition** (forms, streams, terms, class teachers, PTA). Both run the same
accounting spine, so a diocese never has to reconcile two philosophies.

## 3.1 The Bursary — the heart of it

This is where the Daybook lives, and where the Archdiocese's money is actually
managed. Everything in this section exists **today**.

### The ledger

- **OHADA chart of accounts**, seeded and hierarchical — classes 1 to 9, with
  class 6 charges and class 7 revenue as the statutory framework requires.
- **Double-entry journal**, with every posting balanced. Trial balance proves it:
  debits equal credits, to the franc.
- **Fiscal years and accounting periods**, so a period can be closed and a
  correction dated into the right one rather than wherever it happens to land.
- **Automatic posting.** A fee received, an expense recorded or a payroll run
  posts itself to the ledger through a mapping table — the bursar never writes a
  journal entry by hand for routine business.
- **Reversals that unwind everywhere.** A payment recorded in error is reversed
  by an opposing entry — never a deletion — and the fee, the payment account, the
  ledger and the student's own status all move back together, with the reason and
  the person recorded.

### The Income & Expenditure Sheet — the Daybook, digitised

The paper sheet the Bursar signs, generated from real transactions:

- Budget · Actual · Variance for every line, with headings totalling their
  children.
- **Prior-year actuals beside this year's budget**, because a budget is built
  from what really happened.
- **Start next year from last year** — carry forward last year's actual figures
  with an uplift percentage, rather than retyping 66 lines.
- **Capital shown separately** and correctly capitalised to fixed assets, not
  charged to expenses, so the closing balance is not mistaken for money in the
  bank. The sheet states the cash position on its own line.
- **Approval lifecycle** — draft → submitted → approved → active → closed. The
  figures lock while they are being reviewed, so they cannot shift underneath the
  person approving them.
- **Exports as PDF on the school's letterhead**, with signature blocks for
  preparer and approver, and as **Excel with live figures** — real numbers, not
  formatted text, so they can be modelled.

### Reconciliation — the discipline that makes it trustworthy

The sheet is grouped by activity; the ledger is grouped by nature of expense.
They are two views of the same money and **must add to the same figure**. The
system checks this continuously and states the result on the sheet itself:

| Section | Checked against | Status |
|---|---|---|
| Income | OHADA class 7 | agreed |
| Expenditure | OHADA class 6 | agreed |
| Capital | OHADA class 2 (capitalised, not expensed) | agreed |

If any category reaches the ledger but no budget line, the panel names it. A
difference is **reported loudly, not discovered at year end**.

Alongside it: **bank reconciliation**, **payables and receivables ageing**,
**cash flow statement**, **budget vs actual**, and **recurring entries** for the
standing monthly items.

### Fees and collection

- Fee structures by programme, class, term and session, with discounts, waivers
  and fines.
- **Payment plans and instalments** aligned to terms.
- **Student fee ageing** — who owes what, and for how long.
- **Walk-in payments** recorded at the counter with a printed receipt.
- **Payment accounts** — cash boxes and bank accounts, each with its own running
  balance, transfers between them, and cash denomination counts at close.
- Fees assigned only when they are owed: an admission fee is raised when an
  applicant completes their form and reaches payment, **not** when they merely
  open it — so the fees report is not filled with charges nobody agreed to.

### Payroll and statutory obligations

- Payroll runs with allowances and deductions, posted to the ledger.
- **Tax settings and tax groups**, staff tax exemptions, and a **staff tax
  distribution report** — CNPS and proportional tax on income are already lines in
  the Daybook.
- Staff bank accounts and payslips.

### Fixed assets

- **Asset register** with categories and locations.
- **Depreciation** computed and posted — the Daybook's *Buildings*, *Cars* and
  *Intangible Fixed Assets* lines become a maintained register rather than an
  annual estimate.
- Asset reports for insurance and for the diocesan inventory.

---

## 3.2 Documents that cannot be forged

This matters more in Cameroon than most places, and it is already built.

### Every receipt carries a verification code

A receipt's code is an **HMAC-SHA256 signature** over the receipt's own stable
attributes — its id, the fee, the applicant, the payment reference, the amount
and the date — signed with a key held only on the school's server:

```
verification code = HMAC-SHA256( id | fee | applicant | reference | amount | date , server key )
```

Anyone can verify a receipt at a public address by entering or scanning its code.
**A forged receipt cannot produce a valid code**, because producing one requires
the server's key. Change a single digit of the amount and the code no longer
matches.

### The same applies to student documents

- **Student verification by QR code** — scan a student ID card and the school's
  own site confirms whether that student is genuine and currently enrolled.
- **Form A3 verification** at a public address, for the documents third parties
  ask to confirm.
- Certificates and transcripts issued from records, not typed.

### Documents the system produces

| Document | Notes |
|---|---|
| **Student ID cards** | Print and download, single or bulk as a ZIP; background artwork configurable per institution; QR verification embedded |
| **Staff ID cards** | Same, with its own artwork |
| **Transcripts** | Generated from marks, with GPA/credits |
| **Report cards / marksheets** | Per term or semester, with grade scales and teacher comments |
| **Admit cards** | For examinations |
| **Form A2 / A3** | Registration and confirmation forms |
| **Acceptance letters** | Configurable, with tokens filled from the application |
| **Fee receipts** | Verifiable, as above |
| **Certificates** | Issued against records |

**One letterhead, everywhere.** The institution's letterhead is configured once
and every document renders it exactly as authored — the same image, wording and
colours on a receipt, a Form A2, a transcript and the budget sheet. No document
carries a name typed into the code.

---

## 3.3 Academic operations

Everything a school does between admission and graduation.

**Admissions** — an online application portal with a step-by-step form, document
checklist, qualification history, guardians, declaration and payment; the form
itself is configurable per degree type, so a university and a secondary school
ask different questions from the same system. Applications carry a timeline: every
change of stage is recorded with who did it and why. Once the admission fee is
approved, the application submits itself — no button left unpressed.

**Students** — enrolment, promotion, transfer in and out, archives, alumni,
notes, guardians, and a student portal.

**Academics** — faculties or forms, programmes, subjects, sessions, semesters or
terms, sections, streams, batches, class routines, joint class and exam
schedules, room and timetable management.

**Assessment** — subject marking, continuous assessment and examinations, grade
scales, result contribution weighting, results preview and summary, academic
standings, **senate deliberation**, resit requests and resit fees, exam
publishing, and marksheets.

**Attendance** — student, subject-level and staff; daily and hourly; with
eligibility rules that decide whether a student may sit an examination.

**Human resources** — staff records, designations, departments, assignments,
leave, work shifts, notes, and payroll as above.

**Support services** — library (books, issue, return, requests, due dates),
transport (routes, vehicles, members), hostel and rooms, study materials,
noticeboards, visitor logs, postal exchange, phone logs.

**Communication** — SMS and email notifications, announcements, and a chat
assistant with a knowledge base.

---

## 3.4 Governance, security and audit

This is the section a finance council and an auditor should read twice.

### Everyone manages their own role

Roles and permissions are granular — not "admin" and "everyone else". A bursar
who may record a walk-in payment need not be the person who may remove a fee; a
person who may view the admission fees report need not be the one who may verify
receipts. Each screen and each action is separately grantable, and roles are
edited in the open.

**Separation of duties is enforceable**, which is the control that most directly
addresses *Cash Shortages*: the person who takes money need not be the person who
confirms it, and neither need be the person who can reverse it.

### The activity log

Every change of consequence is recorded: **who**, **what model**, **what
changed** (old values and new values), **from what IP address**, **with what
browser**, and **when**. An auditor asking "who altered this student's fee, and
when?" has an answer rather than a recollection.

### Security

- Two-factor authentication for administrative accounts.
- **IP whitelisting** and blocked-IP lists — a bursary can be restricted to the
  school's own network.
- Security logs and a health status page.
- Password policies and forced resets.

### Nothing is deleted where money is concerned

A payment recorded in error is **reversed**, not removed. The receipt survives,
marked reversed, with the reason and the person who did it. The original journal
entry survives, corrected by an opposing entry. A fee that carries an approved
payment cannot be deleted at all until that payment is reversed first — because
deleting it would orphan a receipt, a ledger entry and an account credit.

**This is what makes the books auditable**: the record shows what happened,
including the mistakes and their corrections.

---

# Part Four — Institutional Business Management

The same institution-management philosophy, for everything a diocese runs that is
not a school. This exists **today** and is in production use.

## 4.1 It is not only a hospital system

It is built to be installed for **many kinds of business**, and the module set is
chosen at installation:

| Business | What it runs |
|---|---|
| **Hospital / clinic** | admissions, consultations, wards and beds, nursing, theatre, laboratory, imaging, maternity, morgue |
| **Pharmacy** | stock, batches, racks, expiry, purchases, sales, price groups |
| **Shop / bookshop / provision store** | point of sale, inventory, barcodes, discounts, customer groups |
| **Restaurant / canteen** | tables, orders, kitchen |
| **Any trading business** | purchases, sales, stock counts, cash registers, invoicing |

Because a diocese's canteen, bookshop, farm shop and pharmacy are genuinely
different businesses that all need the same disciplines — stock, cash and margin.

## 4.2 Clinical management

- **Patient records** and visits, with charges captured per visit.
- **Admissions**, wards, beds, ward transfers and caretakers.
- **Consultations** with prescriptions.
- **Nursing** — vitals, notes, and medication administration records.
- **Laboratory** — test catalogue, requests and result lines.
- **Imaging** — catalogue and requests.
- **Maternity** — antenatal records and visits, delivery records.
- **Theatre** bookings and procedures.
- **Morgue** — slots, records and family visits. Mission hospitals run these and
  most systems ignore them.

## 4.3 Pharmacy, stock and trade

- Products with variations, units, brands, categories and racks.
- **Purchases** with supplier records and landed cost.
- **Stock adjustments** and **inventory count sessions** — a real stocktake
  workflow, not a guess.
- **Selling price groups** and discounts.
- **Cash registers** with denomination counts at close.
- Barcodes, printers and invoice layouts.
- Warranties where they apply.

## 4.4 Its own accounting

A full accounting module: chart of accounts with parent and child accounts,
account types and sub-types, **journal entries**, **budgets**, **transfers**,
**reconciliation**, and reports for **trial balance**, **balance sheet** and
**receivable / payable ageing**.

**Multi-business and multi-location** by design: one installation can carry
several businesses, each with its own locations, users and books.

---

# Part Five — EdutrustPay

**To build.** This is the new work.

## 5.1 The public portal

A parent, a guardian, or a relative abroad opens one page.

```
  1. Search for the school            "St. John's College, Kumba"
  2. Identify the student             matricule + surname
  3. See exactly what is owed         fees assigned, paid, outstanding
  4. Choose how to pay
       ├── MTN Mobile Money           instant
       ├── Orange Money               instant
       └── Bank transfer              upload the proof
  5. Receive a receipt                with a verification code
```

### What the parent sees

- Every fee assigned to that student — tuition, registration, boarding, PTA levy,
  examination — with what has been paid and what remains.
- The balance **as at a stated time**, so it is never presented as fresher than it
  is.
- Term instalments, where the school uses them.

### Paying from the bank

Not everyone will use Mobile Money, and larger fees are often paid at a bank
counter. The portal accepts a **bank payment upload**: the payer enters the date,
the reference and the amount, and attaches the bank slip. It arrives at the school
as a pending payment.

### Where the money goes

**Directly into the school's own Mobile Money or bank account.** EdutrustPay
never holds the funds. This is deliberate:

- No float, and no dependence on us for settlement.
- No payment-institution licensing exposure.
- The school's own bank statement is the record.

Our fee is invoiced to the institution separately, in the open.

## 5.2 What the bursar sees

The other half of the portal, inside the school's own system.

- **Pending payments** arrive in a verification queue — Mobile Money confirmed
  automatically, bank uploads with the slip attached.
- The bursar **verifies and validates**: approve, and the payment posts to the
  student's fee, the cash book and the ledger exactly as a counter payment does;
  reject, with a reason the payer can see.
- **A receipt issues with a verification code** the moment it is approved.
- **Reversal** remains available if something was wrong, unwinding everywhere.
- **Sync health** is visible — *"last synced 4 minutes ago · 3 payments
  waiting"* — so silence is never mistaken for safety.

## 5.3 It works when the internet does not

Many schools will run on a server in the bursar's office, subject to power cuts.

- **The school system stays fully usable with no internet at all** — admissions,
  counter payments, the daybook, receipts.
- **No public address, no tunnel, nothing opened to the outside.** The school's
  system reaches out; nothing reaches in. A phone hotspot is enough.
- **Parents can still pay while the school is offline**, because balances were
  published in advance. The payment reaches the school when it returns.
- **Nothing is lost or counted twice.** Counter payments and online payments
  reconcile on reconnect, matched by payment reference.

**Stated honestly:** after a long outage a published balance may be slightly out
of date, so a parent could pay for a bill just settled at the counter. The page
shows the time the balance was taken, never accepts more than is owed, and any
overlap becomes **a credit on the student's account, not a lost payment**. A
duplicate can be corrected; a parent turned away after travelling cannot.

## 5.4 Ideas worth including

1. **Shareable payment link or QR per student** — *"send this to your uncle in
   Douala or Maryland."* Diaspora remittance funds a large share of school fees,
   and no local platform serves it well.
2. **Several payers per student** — father, mother, sponsor and guardian each
   paying a part, which is how it actually works here.
3. **Reminders before each instalment deadline**, by SMS.
4. **Payment on behalf of a class or a sponsor group** — a benefactor paying for
   twenty pupils in one transaction.
5. **SMS/USSD confirmation fallback** for payers on 2G.
6. **A scan-to-verify page** for any receipt, so a school gate, a bursar or a
   parent can confirm a document in seconds.

---

# Part Six — The Body Management Console

**To build.** This is what the Archdiocese logs into.

## 6.1 Bodies and institutions

One generic model, so a new kind of institution never requires a new platform:

```
Body — Archdiocese of Bamenda
 ├── Institution  school     St. John's College          → secondary edition
 ├── Institution  school     PAX Higher Institute        → university edition
 ├── Institution  hospital   St. Mary's Hospital         → business system
 ├── Institution  pharmacy   St. Mary's Pharmacy         → business system
 └── Institution  business   Diocesan Press · Farm       → business system
```

Bodies may nest — a diocese with deaneries, a congregation with provinces — so a
region can see its own institutions and the centre can see all of them.

## 6.2 How consolidation actually works

**Not by forcing every institution onto one chart of accounts.** The hospital
system has its own chart and its own ledger; rewriting it would be a rewrite of a
working system, and it would have to be done again for every new institution type.

**Consolidation happens at OHADA class and group level.** OHADA is the statutory
framework in Cameroon; the schools already use it; the hospital can map its
existing accounts to OHADA groups without changing its chart. Each institution
maps **its own accounts to OHADA groups once, locally**, and the body receives a
comparable number.

The result: **the diocesan consolidated statement arrives in the framework the
auditors already use.**

### The reporting contract

Every institution reports, per period:

- **Income** by OHADA class 7 group
- **Expenditure** by OHADA class 6 group
- **Surplus or deficit**
- **Cash position** — opening, movement, closing
- **Receivables and payables**, with ageing
- **Budget against actual**, where a budget exists

Plus operational figures, so the body sees more than money:

| Institution | Also reports |
|---|---|
| School | enrolment, fee collection rate, arrears ageing |
| Hospital | admissions, bed occupancy, patient debt |
| Pharmacy / shop | turnover, gross margin, stock value |

**Patient-level data never leaves a hospital.** Only financial and operational
totals reach the body — no patient row, ever. A school publishes a student roster
because paying fees requires identifying a student; a hospital has no such need,
and the sensitivity is far higher.

## 6.3 What the console shows

- **One consolidated position** for the whole body, at any moment — not after
  year end.
- **Drill down** to any single institution, and from there to any line.
- **Compare like with like** — schools against schools, and a college against a
  clinic, because both arrive expressed in OHADA classes.
- **Collection rates** per school, and **arrears ageing** across the body.
- **Budget against actual** for every institution, and for the body as a whole.
- **Institutions that have not reported**, and how long since each last synced.
- **Fee structure templates** pushed down from the body, showing which schools
  deviate.
- **A daily agreement check** between the platform's records and each
  institution's own ledger — differences reported, not discovered.

## 6.4 The General Bursar's view

The diocesan bursar who oversees every institution gets what no spreadsheet can
give:

- A **single trial balance** for the body, and one per institution.
- **Inter-institution transfers** visible from both ends — the diocese subsidising
  a school, a school paying the press for magazines.
- **The subvention question answered**: which institutions are net contributors
  and which are net dependents, with the figures behind it.
- **Audit preparation as a report, not a project** — the ledger, the trial
  balance, the asset register and the activity log are already there.

---

# Part Seven — What the Archdiocese gets from this

## Transparency

Every franc has a trail: who recorded it, from which account, against which
student or patient, posted to which ledger account, approved by whom. Not because
anyone is suspected, but because a system where the record is complete protects
the honest bursar as much as it deters the dishonest one.

## Auditing

An audit today means assembling workbooks, reconciling them by hand and trusting
the assembly. With this in place:

- The **trial balance** proves the books balance.
- The **activity log** shows every change, its author, and its before and after.
- **Reversals are visible** rather than invisible — a corrected mistake is on the
  record, which is what an auditor wants to see.
- **Receipts are verifiable** — a sampled receipt can be confirmed in seconds
  rather than traced through a book.
- **Fixed assets** are a maintained register with depreciation, not an annual
  reconstruction.

## Reduced cash handling

The most direct answer to the *Cash Shortages* line: cash that never enters a
drawer cannot go missing from one.

## Fees actually collected

The most direct answer to *Bad Debts* and *Provision For Uncollected Fees*: fees
that can be paid from a phone, from another town, or by a relative abroad, are
more likely to be paid — and arrears become visible in the term they arise rather
than at the year's close.

## Everyone in their own lane

Roles mean the accountant does accounting, the registrar does records, the class
teacher enters marks, and the bursar handles money — each seeing what they need
and nothing more. Separation of duties stops being a policy on paper.

## The institutions keep what is theirs

- Each keeps its **own chart of accounts** and remains the authority on its own
  ledger.
- The **daybook stays in the shape the bursars know** and can be exported at any
  time.
- **If the Archdiocese left tomorrow, it would leave with its accounts intact.**

---

# Part Eight — What each institution must provide

These, not the software, will set the pace. Better said now than discovered in
month three.

| # | Requirement | Why | Who |
|---|---|---|---|
| 1 | **Mobile Money or bank merchant account** in the institution's own name | So payments land with the school and never with us. Needs business registration and identity checks — **start first; it takes the longest** | Schools |
| 2 | **Power backup on the server** — solar with battery | A server that dies with the grid takes the bursary's day with it, offline capability or not | All |
| 3 | **Some internet access**, however intermittent | A router, dongle or phone hotspot. Only brief outward access is needed | All |
| 4 | **Chart of accounts mapped once** | Existing lines matched to OHADA groups, so consolidation is comparable and audit-ready | All |
| 5 | **A bursar who will be trained** | Half a day. The daybook they know does not change shape | All |
| 6 | **A named data owner** per institution | Someone accountable for who has access | All |

---

# Part Nine — The pilot

One school, proven completely, before anything is promised to many.

### Stage One — One school, end to end
A single college connected: balances published, a real payment taken, the fee,
receipt and ledger all moving, and the books agreeing to the franc. Nothing is
rolled out until this is dull and repeatable.

### Stage Two — The bursary
The daybook digitised — cash book, the 155 analysis columns, the monthly summary,
the profit centres — and the workbook generated in the shape the bursars already
use.

### Stage Three — The diocesan console
Consolidation across the pilot schools: one position, per-institution drill-down,
collection rates, arrears, and budget against actual across the body.

### Stage Four — Hospitals and the rest
The hospitals report into the same console. Pharmacies, shops and other
institutions follow as they are ready.

## What success looks like

- A measurable share of fees paid without anyone queueing at a bursary.
- **Cash Shortages** falling, because less cash is handled.
- Arrears visible in the term they arise, not at the year's close.
- A diocesan position available on any day of the year, not after it.
- An audit that begins with a report rather than a request for files.
- Bursars who would refuse to go back to typing.

---

# Part Ten — What we will not do

A proposal about money should be as clear about its limits as its promises.

| | |
|---|---|
| **We never hold your money** | Payments go straight into each institution's own account. We are not a bank and do not act as one. |
| **Patient records never leave a hospital** | Only financial and operational totals reach the Archdiocese. No patient row, ever. |
| **We do not take over your books** | Each institution keeps its own chart of accounts and remains the authority on its own ledger. |
| **We do not lock you in** | The daybook exports in its own shape; the accounts export in full. |
| **We do not invent figures** | See below. |

---

# Appendix A — An honest note on figures

The workbook we studied is a **template**: it carries the accounts and the budget
structure, but no amounts — what appear to be 131 figures are account codes. We
have therefore **quoted no monetary figures anywhere in this proposal**.

When the Archdiocese shares real figures, we will model the effect properly. We
would rather show nothing than show numbers we invented.

---

# Appendix B — Decisions still open

Four questions we would like settled with the Archdiocese rather than assumed:

1. **How a student is identified on the public portal.** Searching by name would
   expose who attends which school. Our recommendation is **matricule plus
   surname**, never a browsable list, with rate limiting.
2. **How our service fee is charged** — invoiced monthly to the institution, or
   surcharged to the payer at the point of payment. The second is common locally
   and must be decided before the payment page is designed.
3. **What happens to a payment for a student who has left**, or whose fee was
   settled at the counter minutes earlier. Our recommendation is that it becomes
   a credit, never a refusal.
4. **Naming.** Once this carries hospitals and businesses, "EdutrustPay"
   describes one part of what it does. The payment portal can keep the name; the
   body console probably should not.

---

# Appendix C — Where the claims in this document come from

Every capability described as **Today** was verified in the running systems, not
recalled from a specification:

| Claim | Verified in |
|---|---|
| OHADA chart, journal, fiscal years, periods | `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `fiscal_years`, `accounting_periods` |
| Automatic posting and reversal | `TransactionAutoMapService`, `DefaultAccountMapping`, `PaymentReversalService` |
| Income & Expenditure sheet, reconciliation | `BudgetActualsService`, `BudgetReconciliationService`, budget sheet screens |
| Receipt verification by HMAC | `AdmissionFeesReportController::verificationCodeFor()` — HMAC-SHA256 over receipt attributes, signed with the application key |
| Student QR verification | `StudentVerificationController`, `verify-form-a3` |
| ID cards, transcripts, marksheets, Form A2/A3 | ID card and setting modules, transcript, marksheet and form views |
| Configurable letterhead across documents | `LetterheadService`, `partials/document-header` |
| Activity log with before/after and IP | `AuditLog` — `user_id`, `action`, `model_type`, `old_values`, `new_values`, `ip_address`, `user_agent` |
| Roles and granular permissions | Role and permission management, per-action gates |
| Two-factor, IP whitelist, security logs | Security module |
| Payroll, tax groups, staff tax report | Payroll, `TaxGroup`, `TaxSetting`, staff tax report |
| Fixed assets and depreciation | Asset register, depreciation report |
| Hospital clinical modules | `Admission`, `Consultation`, `Ward`, `Bed`, `LabRequest`, `ImagingRequest`, `TheatreBooking`, `AntenatalRecord`, `DeliveryRecord`, `MorgueRecord`, `NursingVital`, `MedicationAdministration` |
| Pharmacy, stock, POS | `Product`, `ProductVariation`, `InventoryCountSession`, `StockAdjustmentLine`, `CashRegister`, `Transaction` |
| Multi-business, multi-location | `Business`, `BusinessLocation` |
| Hospital accounting and reports | Accounting module — trial balance, balance sheet, receivable/payable ageing |

The Daybook figures — 27 sheets, 155 columns, 33 income lines, 100 expense lines,
9 trading activities, and the four provision columns `DG`, `DE`, `EH`, `EI` —
were read directly from `Daybook Sec - correction.xlsm`.
