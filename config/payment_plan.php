<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Late Fees
    |--------------------------------------------------------------------------
    |
    | Off. The schools using this system do not fine students for paying an
    | instalment late, so nothing may charge one: the create and edit forms show
    | the percentage as nothing and will not let it be set, applyLateFee()
    | refuses, and `payment-plan:maintain --apply-late-fees` says it is switched
    | off rather than doing anything.
    |
    | Instalments are still marked overdue and students are still reminded —
    | that reports what is true without taking money off anybody.
    |
    | A school that genuinely charges for late payment turns this back on with
    | PAYMENT_PLAN_LATE_FEES=true in its .env, and the percentage becomes
    | settable again on the plan.
    */
    'late_fees_enabled' => filter_var(
        env('PAYMENT_PLAN_LATE_FEES', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
