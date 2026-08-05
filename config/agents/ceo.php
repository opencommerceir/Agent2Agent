<?php

/**
 * CEO Agent profile (Phase 6, Stage 2, §7.27).
 *
 * A real, documented correction from the original request's own literal
 * example: `start_date`/`end_date` used the raw strings `'-7 days'`/`'now'`
 * and `code` used `'AUTO_{date}'` — neither survives contact with the
 * real capabilities. `report.sales.generate` needs a `Y-m-d` string
 * (`{date:N}` resolves to one); `commerce.coupon.create`'s own `CouponCode`
 * VO requires the literal `COUPON-XXXXX` format, which `'AUTO_{date}'`
 * can never become no matter how `{date}` is interpolated (wrong prefix
 * entirely) — replaced with `{coupon_code}`. See
 * `DeterministicPlanner`'s own docblock for the full list of recognized
 * tokens.
 *
 * The `sales` rule includes `notification.message.send` (4 capabilities,
 * matching this stage's own end-to-end scenario) even though the
 * original request's own config example showed only 3 for this specific
 * rule (`notification.message.send` appeared only in `default`) — the
 * same request's own worked scenario explicitly expects 4 steps for a
 * "sales" goal, so the rule was written to match the testable behavior,
 * not the earlier, narrower example.
 */
return [
    'name' => 'CEO Agent',
    'description' => 'Strategic decision-making agent for business oversight — sales, revenue, and inventory health at a glance.',

    'planning_rules' => [
        'sales' => [
            'report.sales.generate',
            'analytics.kpi.calculate',
            'commerce.coupon.create',
            'notification.message.send',
        ],
        'revenue' => [
            'report.revenue.generate',
            'analytics.kpi.calculate',
        ],
        'inventory' => [
            'analytics.kpi.calculate',
        ],
        'default' => [
            'report.sales.generate',
            'analytics.kpi.calculate',
            'commerce.coupon.create',
            'notification.message.send',
        ],
    ],

    'default_inputs' => [
        'report.sales.generate' => [
            'start_date' => '{date:-7}',
            'end_date' => '{date:0}',
        ],
        'report.revenue.generate' => [
            'start_date' => '{date:-30}',
            'end_date' => '{date:0}',
        ],
        'analytics.kpi.calculate' => [
            'kpi_type' => 'revenue',
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
        'reporting.revenue.read',
        'analytics.kpis.read',
        'commerce.coupons.create',
        'notifications.messages.send',
    ],
];
