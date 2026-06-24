<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Simplified ERP Mode
    |--------------------------------------------------------------------------
    |
    | The Tables simplified version keeps operational ERP workflows and blocks
    | sales, billing, points, performance, and advanced analytics modules.
    |
    */

    'enabled' => env('ERP_SIMPLIFIED_MODE', true),

    'disabled_modules' => [
        'advanced_analytics',
        'advanced_deductions',
        'billing',
        'client_billing',
        'code_reviews',
        'contracts',
        'employee_reviews',
        'financial_reports',
        'innovation',
        'internal_costs',
        'lead_activities',
        'leads',
        'performance',
        'points',
        'pricing',
        'proposals',
        'quotations',
        'rewards',
        'salary_adjustments',
        'sales',
        'sales_exports',
        'ui_approval',
    ],

    'disabled_jobs' => [
        'activity_reminders',
        'invoice_overdue',
        'performance_periods',
        'quotation_expiry',
        'subscription_renewals',
        'expiry_warnings',
    ],
];
