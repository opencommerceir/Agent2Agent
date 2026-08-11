<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Providers
    |--------------------------------------------------------------------------
    |
    | Nexus negotiates and reasons through the same class of LLM providers
    | the base Agent Orchestrator supports (config/agent-orchestrator.php),
    | but under its own NEXUS_* credentials so a Nexus deployment can point
    | at different keys/models than the underlying platform.
    |
    */
    'llm' => [
        'default_provider' => env('NEXUS_LLM_PROVIDER', 'openai'),

        'providers' => [
            'openai' => [
                'api_key' => env('NEXUS_OPENAI_API_KEY'),
                'model' => env('NEXUS_OPENAI_MODEL', 'gpt-4'),
            ],
            'claude' => [
                'api_key' => env('NEXUS_CLAUDE_API_KEY'),
                'model' => env('NEXUS_CLAUDE_MODEL', 'claude-3-opus-20240229'),
            ],
            'openrouter' => [
                'api_key' => env('NEXUS_OPENROUTER_API_KEY'),
                'model' => env('NEXUS_OPENROUTER_MODEL', 'meta-llama/llama-3.1-405b-instruct:free'),
                'base_url' => env('NEXUS_OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit System
    |--------------------------------------------------------------------------
    |
    | Businesses and Agents spend credits to negotiate and transact on the
    | marketplace. Defined here as foundation-only defaults — the Credit
    | domain (Phase 1+) owns the actual ledger/spend logic.
    |
    */
    'credit' => [
        'currency' => env('NEXUS_CREDIT_CURRENCY', 'IRT'),
        'starting_balance' => (int) env('NEXUS_CREDIT_STARTING_BALANCE', 0),
        'negotiation_cost' => (int) env('NEXUS_CREDIT_NEGOTIATION_COST', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Margin
    |--------------------------------------------------------------------------
    */
    'margin' => [
        'platform_fee_percent' => (float) env('NEXUS_PLATFORM_FEE_PERCENT', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Negotiation Rules
    |--------------------------------------------------------------------------
    */
    'negotiation' => [
        'max_rounds' => (int) env('NEXUS_NEGOTIATION_MAX_ROUNDS', 5),
        'timeout_seconds' => (int) env('NEXUS_NEGOTIATION_TIMEOUT', 300),
        'auto_accept_threshold_percent' => (float) env('NEXUS_NEGOTIATION_AUTO_ACCEPT_THRESHOLD', 2.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme (Jarvis)
    |--------------------------------------------------------------------------
    |
    | Kept in sync by hand with the CSS tokens in resources/css/nexus.css —
    | Blade views can read these for things like meta theme-color, but the
    | actual rendered colors come from the Tailwind @theme tokens.
    |
    */
    'theme' => [
        'mode' => env('NEXUS_THEME_MODE', 'dark'),
        'primary' => env('NEXUS_THEME_PRIMARY', '#00F0FF'),
        'secondary' => env('NEXUS_THEME_SECONDARY', '#A855F7'),
        'background' => env('NEXUS_THEME_BACKGROUND', '#05060A'),
    ],
];
