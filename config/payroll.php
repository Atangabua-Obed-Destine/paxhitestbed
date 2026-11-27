<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allow payroll to run with no income tax in force
    |--------------------------------------------------------------------------
    |
    | Normally, if no income-tax rates are active for a month, payroll REFUSES
    | to run — so a deactivated or half-configured tax table can never quietly
    | produce a zero-tax payslip (silent PAYE under-withholding).
    |
    | An institution that deliberately withholds no income tax (for now) can set
    | PAYROLL_ALLOW_UNTAXED=true. Payroll then runs with zero income tax when no
    | rates are in force, and the preview/payslip carries a note saying so. It
    | has NO effect whenever an income-tax group is active — tax is computed
    | normally then. A gap in a partly-configured table still blocks, always.
    |
    */

    'allow_untaxed' => (bool) env('PAYROLL_ALLOW_UNTAXED', false),

];
