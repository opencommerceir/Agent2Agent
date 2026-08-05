<?php

/**
 * Support Agent profile (Phase 6, Stage 2, §7.27) — not requested by this
 * stage's own file list, added so `/api/agents/support` keeps working the
 * way it did in Stage 1 (§7.26's own hardcoded `crm.ticket.list` rule),
 * satisfying this stage's own explicit "backward compatible" requirement.
 * Migrating Stage 1's own hardcoded per-type keyword branches to their
 * config-driven equivalent is exactly the shape this stage's own
 * `finance.php` profile follows too.
 */
return [
    'name' => 'Support Agent',
    'description' => 'Customer support and ticket triage agent.',

    'planning_rules' => [
        'support' => ['crm.ticket.list'],
        'ticket' => ['crm.ticket.list'],
        'default' => ['crm.ticket.list'],
    ],

    'default_inputs' => [
        'crm.ticket.list' => [
            'status' => 'open',
        ],
    ],

    'permissions' => [
        'crm.tickets.read',
    ],
];
