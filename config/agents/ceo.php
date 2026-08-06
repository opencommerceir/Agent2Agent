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
 *
 * `delegate` (Showcase prep, Phase 2) is the cheapest available increment
 * HANDOFF §8.85 itself already named: one profile naming
 * `agent.collaboration.delegate` in a real planning_rules entry, exactly
 * like any other capability -- no PlannerInterface/ExecuteGoalAction
 * change, the same config-driven mechanism this module has used since
 * §7.27. Declared first, before `sales`, so a goal containing "delegate"
 * can never be shadowed by a broader keyword declared later
 * (first-match-wins order, docs/agent-profiles.md) -- no existing goal
 * string CEOAgentTest/GoalExecutionTest assert on contains "delegate", so
 * this reordering changes nothing about which rule those goals resolve
 * to. `task`'s own literal text deliberately contains "promotion" so it
 * resolves against config/agents/sales.php's own `promotion` rule (2 real
 * capabilities) rather than its thinner `default` rule -- the same task
 * text shape MultiAgentCollaborationTest's own scenario already proved.
 */
return [
    'name' => 'CEO Agent',
    'description' => 'Strategic decision-making agent for business oversight — sales, revenue, and inventory health at a glance.',

    'planning_rules' => [
        'delegate' => [
            'agent.collaboration.delegate',
        ],
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
        'agent.collaboration.delegate' => [
            'from_agent' => 'ceo',
            'to_agent' => 'sales',
            'task' => 'Create a 15% discount coupon for a summer promotion',
        ],
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
        'agent.collaboration.delegate',
    ],
];
