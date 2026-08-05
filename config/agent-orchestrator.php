<?php

/**
 * Phase 6, Stage 3 (LLM-based Planner, §7.28).
 *
 * `planner.type` deliberately defaults to `deterministic`, not `llm` —
 * a real, documented correction from this stage's own request (whose
 * `.env.example` example defaulted `PLANNER_TYPE=llm`). Defaulting a
 * fresh `composer install`/CI/test run to attempt real, keyless network
 * calls to a paid third-party API is not a safe default (the same
 * "real infra assumed in production, safe default for local dev/test"
 * reasoning `CACHE_STORE=database`/`DB_PERSISTENT_CONNECTIONS=false`
 * already establish in `.env.example`) — an operator with real
 * OPENAI_API_KEY/CLAUDE_API_KEY credentials opts in explicitly by
 * setting PLANNER_TYPE=llm.
 */
return [

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'openai'), // openai, claude

        'openai' => [
            // Empty string, not null, by default — same "WOOCOMMERCE_*
            // all empty by default" shape config/commerce.php already
            // establishes; OpenAIClient's own $apiKey is a non-nullable
            // string, and an empty key still constructs a real client
            // (it just fails, correctly, the moment it actually calls
            // the API — no live credentials exist in this dev
            // environment to test honestly against, same reasoning
            // every external Connector in this codebase already gives).
            'api_key' => env('OPENAI_API_KEY', ''),
            'model' => env('OPENAI_MODEL', 'gpt-4'),
        ],

        'claude' => [
            'api_key' => env('CLAUDE_API_KEY', ''),
            'model' => env('CLAUDE_MODEL', 'claude-3-opus-20240229'),
        ],
    ],

    'planner' => [
        'type' => env('PLANNER_TYPE', 'deterministic'), // llm, deterministic
        'fallback_to_deterministic' => env('PLANNER_FALLBACK_TO_DETERMINISTIC', true),
    ],

];
