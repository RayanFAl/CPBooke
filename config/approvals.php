<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Refund approval thresholds (default currency context: LYD)
    |--------------------------------------------------------------------------
    */
    'refund_direct_threshold' => (float) env('APPROVAL_REFUND_DIRECT_THRESHOLD', 100),

    /*
    | Full refund on orders older than this many hours requires approval
    | even when amount is below the direct threshold.
    */
    'refund_full_after_hours' => (int) env('APPROVAL_REFUND_FULL_AFTER_HOURS', 24),

    /*
    | Order statuses that require approval before cancellation.
    */
    'cancel_issued_statuses' => [
        'confirmed',
        'ticketed',
        'completed',
    ],

    /*
    | Wallet operations always require approval unless requester is super admin.
    */
    'wallet_always_requires_approval' => true,

    /*
    | Roles that may execute refunds at or below the direct threshold without approval.
    */
    'refund_direct_roles' => [
        'super_admin',
        'finance_manager',
        'support_agent',
    ],
];
