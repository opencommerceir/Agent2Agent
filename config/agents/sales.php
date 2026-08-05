<?php

/**
 * Sales Agent profile (Phase 6, Stage 2, §7.27) — built specifically to
 * demonstrate extensibility (this stage's own explicit "add a new Agent =
 * just a config file" requirement): its `promotion`/`campaign` rules are
 * genuinely different from the CEO profile's own rules, so the same goal
 * text can plan differently depending on which Agent persona receives it.
 */
return [
    'name' => 'Sales Agent',
    'description' => 'Sales growth and promotional campaign agent.',

    'planning_rules' => [
        'promotion' => [
            'commerce.coupon.create',
            'notification.message.send',
        ],
        'campaign' => [
            'commerce.coupon.create',
            'notification.message.send',
        ],
        'sales' => [
            'report.sales.generate',
            'analytics.kpi.calculate',
        ],
        'default' => [
            'report.sales.generate',
        ],
    ],

    'default_inputs' => [
        'report.sales.generate' => [
            'start_date' => '{date:-7}',
            'end_date' => '{date:0}',
        ],
        'analytics.kpi.calculate' => [
            'kpi_type' => 'top_products',
            'time_period' => 'weekly',
            'start_date' => '{date:-7}',
            'end_date' => '{date:0}',
        ],
        'commerce.coupon.create' => [
            'code' => '{coupon_code}',
            'discount_type' => 'percentage',
            'discount_value' => '{discount_percent}',
        ],
        'notification.message.send' => [
            'type' => 'promotion_announcement',
            'channel' => 'email',
            'recipient' => 'marketing@opencommerce.local',
            'variables' => [],
        ],
    ],

    'permissions' => [
        'reporting.sales.read',
        'analytics.kpis.read',
        'commerce.coupons.create',
        'notifications.messages.send',
    ],
];
