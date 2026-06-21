<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Resit Fee
    |--------------------------------------------------------------------------
    |
    | This value represents the baseline fee charged for every resit request.
    | It can be overridden via the RESIT_DEFAULT_FEE environment variable and
    | should be referenced whenever a new resit request is created.
    */                                                                      
    'default_fee' => (float) env('RESIT_DEFAULT_FEE', 150.00),

    /*
    |--------------------------------------------------------------------------
    | Resit Fee Category Slug|--------------------------------------------------------------------------
    
    |
    | The fees category slug that should be used when creating the fee record
    | for a resit request. The category will be created automatically if it
    | does not exist in the system.
    */
    'fee_category_slug' => env('RESIT_FEE_CATEGORY_SLUG', 'resit-fee'),

    /*
    |--------------------------------------------------------------------------
    | Resit Fee Category Name
    |--------------------------------------------------------------------------
    |
    | The human-readable title of the fees category that groups all resit fee
    | assignments. Only used when the category has to be generated on demand.
    */
    'fee_category_name' => env('RESIT_FEE_CATEGORY_NAME', 'Resit Fee'),

    /*
    |--------------------------------------------------------------------------
    | Default Due Window (Days)
    |--------------------------------------------------------------------------
    |
    | Number of days after assignment that the resit fee should be due. This
    | value helps keep the automatically generated fee aligned with existing
    | collection workflows.
    */
    'fee_due_days' => (int) env('RESIT_FEE_DUE_DAYS', 7),
];
