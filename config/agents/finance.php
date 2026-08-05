<?php

/**
 * Finance Agent profile (Phase 6, Stage 2, §7.27) — not requested by this
 * stage's own file list, added so `/api/agents/finance` keeps working the
 * way it did in Stage 1 (§7.26's own hardcoded 2-step revenue+invoice
 * rule), satisfying this stage's own explicit "backward compatible"
 * requirement — see `support.php`'s own docblock for the identical
 * reasoning.
 */
return [
    'name' => 'Finance Agent',
    'description' => 'Financial reporting and invoicing oversight agent.',

    'planning_rules' => [
        'finance' => ['report.revenue.generate', 'finance.invoice.list'],
        'revenue' => ['report.revenue.generate', 'finance.invoice.list'],
        'invoice' => ['report.revenue.generate', 'finance.invoice.list'],
        'default' => ['report.revenue.generate', 'finance.invoice.list'],
    ],

    'default_inputs' => [
        'report.revenue.generate' => [
            'start_date' => '{date:-30}',
            'end_date' => '{date:0}',
        ],
        'finance.invoice.list' => [
            'status' => 'issued',
        ],
    ],

    'permissions' => [
        'reporting.revenue.read',
        'finance.invoices.read',
    ],
];
