<?php

return [
    'attachments' => [
        'disk' => 'local',
        'directory' => 'support/attachments',
        'max_kilobytes' => (int) env('SUPPORT_ATTACHMENT_MAX_KB', 20480),
        'signed_url_ttl_minutes' => (int) env('SUPPORT_ATTACHMENT_SIGNED_URL_TTL', 30),
    ],
    'auto_assignment' => [
        'overload_score' => 140.0,
        'default_response_minutes' => 240.0,
        'category_skills' => [
            'technical_issue' => 'tech',
            'payment_issue' => 'finance',
            'refund_request' => 'finance',
        ],
        'agent_skills' => [
            'default' => ['tech', 'finance', 'general'],
            'by_id' => [],
            'by_email' => [],
        ],
    ],
    'smart_reassignment' => [
        'enabled' => env('SUPPORT_SMART_REASSIGNMENT_ENABLED', false),
        'minimum_workload_improvement' => 5,
    ],
];