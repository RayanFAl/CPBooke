<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cost difference tolerance (absolute amount)
    |--------------------------------------------------------------------------
    | Differences at or below this amount are treated as matched.
    */
    'cost_tolerance' => (float) env('SETTLEMENT_COST_TOLERANCE', 0.01),

    /*
    | Default currency when creating a settlement period.
    */
    'default_currency' => env('SETTLEMENT_DEFAULT_CURRENCY', 'LYD'),
];
