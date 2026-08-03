<?php

return [
    'enabled' => (bool) env('AUDIT_ENABLED', true),

    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 180),

    'timeline_limit' => (int) env('AUDIT_TIMELINE_LIMIT', 100),

    'search_limit_per_group' => (int) env('AUDIT_SEARCH_LIMIT_PER_GROUP', 10),
];
