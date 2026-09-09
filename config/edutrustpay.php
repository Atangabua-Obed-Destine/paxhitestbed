<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EdutrustPay reporting client
    |--------------------------------------------------------------------------
    |
    | This institution PUSHES a signed summary of each calendar month to its
    | body's EdutrustPay console. EdutrustPay never reaches in here: there is no
    | inbound endpoint, no tunnel and no database access on its side. If this
    | block is not configured, nothing here does anything.
    |
    | Turning this off does not affect any other part of the system. The client
    | reads the ledger and writes only to its own outbox table.
    |
    */

    'enabled' => env('EDUTRUSTPAY_ENABLED', false),

    'endpoint' => env('EDUTRUSTPAY_ENDPOINT'),

    /*
     * Issued by the EdutrustPay operator, once, per institution.
     *
     * The secret is shown a single time at issue or rotation and cannot be
     * recovered from the console afterwards — only replaced. Keep it in .env,
     * out of version control, and out of the database backup.
     */
    'key_id' => env('EDUTRUSTPAY_KEY_ID'),
    'secret' => env('EDUTRUSTPAY_SECRET'),

    /* The institution's UUID on the platform — the contract's institution_ref. */
    'institution_ref' => env('EDUTRUSTPAY_INSTITUTION_REF'),

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | Reports are written to a local outbox first and delivered separately. A
    | bursary server here runs on unreliable power and may have a phone hotspot
    | for a few minutes a day, so a failed send must never lose a report, and a
    | month must never be skipped because the network was down when it closed.
    |
    */

    'timeout' => (int) env('EDUTRUSTPAY_TIMEOUT', 30),

    'max_attempts' => (int) env('EDUTRUSTPAY_MAX_ATTEMPTS', 12),

    /*
     * Backoff between attempts, in minutes. Grows, then plateaus: there is no
     * point hammering an endpoint every minute from a site whose connectivity
     * comes back once a day, and no point giving up either.
     */
    'backoff_minutes' => [1, 5, 15, 60, 240, 720],

    /*
    |--------------------------------------------------------------------------
    | What this institution reports
    |--------------------------------------------------------------------------
    |
    | Declare only what this system can genuinely produce. A capability listed
    | here that cannot be computed will make the client fail loudly rather than
    | send a zero — which is the intended behaviour: on the console, a missing
    | figure and a zero figure are different facts and must never be conflated.
    |
    | Remove a line rather than letting it report nothing.
    |
    */

    'capabilities' => [
        'ledger',       // income/expenditure by OHADA group, cash, control totals
        'receivables',  // Accounting\AgingReportService, class 41
        'payables',     // Accounting\AgingReportService, class 40
        'payroll',      // Payroll.total_cost + payroll_tax_lines
        'integrity',    // ledger hash, reversals, late entries

        /*
         * NOT DECLARED: 'budget'.
         *
         * This system has no monthly budget to report. `budgets` are annual
         * (start_date/end_date/total_amount), `budget_allocations` go no finer
         * than a quarter, and `budget_line_forecasts` are annual student-count
         * times rate. BudgetActualsService produces actuals per budget line for
         * any date range, but there is nothing to compare them against for a
         * single month.
         *
         * Dividing an annual budget by twelve was the obvious workaround and is
         * the wrong answer. A school's spending is seasonal — three term starts
         * carry most of it — so a twelfth is not what anyone budgeted, and the
         * console would raise budget_variance findings every month on pure
         * seasonality until people learned to ignore them.
         *
         * Better to report nothing and have the console show "not yet
         * reporting", which is true, than a plausible number that is not.
         * Declaring this properly needs either a monthly budget here or a
         * year-to-date budget block in the contract.
         */
        // 'budget',
    ],

    /*
    |--------------------------------------------------------------------------
    | Period status
    |--------------------------------------------------------------------------
    |
    | A month is reported as `final` once its accounting period is closed, and
    | `provisional` before that. The distinction matters at the other end:
    | restating a FINAL month is a control event, while a provisional month
    | changing is ordinary bookkeeping.
    |
    */

    'assume_final_after_days' => (int) env('EDUTRUSTPAY_FINAL_AFTER_DAYS', 10),

];
