<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Providers (Phase 4 — LLM Provider System)
    |--------------------------------------------------------------------------
    |
    | Nexus negotiates and reasons through the same class of LLM providers
    | the base Agent Orchestrator supports (config/agent-orchestrator.php),
    | but under its own NEXUS_* credentials so a Nexus deployment can point
    | at different keys/models than the underlying platform, and through its
    | own LLMProviderRegistry (app/Domains/Nexus/Llm) rather than
    | AgentOrchestrator's single active LLMClientInterface binding — Nexus
    | needs several simultaneously-available providers (admin picks one per
    | feature, with a fallback chain), not one global choice.
    |
    | Nothing read `llm.*` before Phase 4 (it was a dead placeholder since
    | Phase 0), so this block was free to grow beyond its original 3 keys
    | without a breaking rename of any env var already in real use.
    |
    */
    'llm' => [
        // One entry per LLMProviderRegistry key (6 = the roadmap's full
        // provider list: OpenAI, Anthropic, OpenRouter, Groq, self-hosted
        // Qwen, local Llama). 'claude' stays the config/registry key for
        // the Anthropic vendor (matches the NEXUS_CLAUDE_* env vars already
        // in use since Phase 0) — the registry key and the adapter class
        // name (AnthropicLLMProvider) are independent; nothing requires
        // them to match.
        'providers' => [
            'openai' => [
                'api_key' => env('NEXUS_OPENAI_API_KEY'),
                'model' => env('NEXUS_OPENAI_MODEL', 'gpt-4o'),
                // Includes /v1, same "base_url already carries the API
                // version path segment" convention every other
                // AbstractOpenAiCompatibleProvider subclass below uses —
                // deliberately not bare https://api.openai.com (the shape
                // AgentOrchestrator's own OpenAIClient uses, which instead
                // hardcodes /v1 into its own request path constant).
                'base_url' => env('NEXUS_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            ],
            'claude' => [
                'api_key' => env('NEXUS_CLAUDE_API_KEY'),
                'model' => env('NEXUS_CLAUDE_MODEL', 'claude-3-opus-20240229'),
                'base_url' => env('NEXUS_CLAUDE_BASE_URL', 'https://api.anthropic.com'),
            ],
            'openrouter' => [
                'api_key' => env('NEXUS_OPENROUTER_API_KEY'),
                'model' => env('NEXUS_OPENROUTER_MODEL', 'meta-llama/llama-3.1-405b-instruct:free'),
                'base_url' => env('NEXUS_OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            ],
            'groq' => [
                'api_key' => env('NEXUS_GROQ_API_KEY'),
                'model' => env('NEXUS_GROQ_MODEL', 'llama-3.1-8b-instant'),
                'base_url' => env('NEXUS_GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            ],
            // Self-hosted/local — no real model server runs in this dev
            // environment (same honest limitation every other external
            // Connector in this codebase already documents); base_url
            // defaults to an Ollama-style OpenAI-compatible endpoint, which
            // vLLM/LM Studio/llama.cpp's server also all speak.
            'qwen-14b-local' => [
                'api_key' => env('NEXUS_QWEN_LOCAL_API_KEY', ''),
                'model' => env('NEXUS_QWEN_LOCAL_MODEL', 'qwen2.5:14b'),
                'base_url' => env('NEXUS_QWEN_LOCAL_BASE_URL', 'http://localhost:11434/v1'),
            ],
            'llama-3.2-3b-local' => [
                'api_key' => env('NEXUS_LLAMA_LOCAL_API_KEY', ''),
                'model' => env('NEXUS_LLAMA_LOCAL_MODEL', 'llama3.2:3b'),
                'base_url' => env('NEXUS_LLAMA_LOCAL_BASE_URL', 'http://localhost:11434/v1'),
            ],
        ],

        // Declarative price-tier metadata — not part of LLMProviderInterface
        // itself (the roadmap specifies exactly chat()/estimateCost()/
        // supports(), tier isn't one of them), just plain config LLMRouter
        // and LLMBudgetGuard both read to decide fallback/budget behavior
        // ("never fallback from local to paid unless explicitly allowed",
        // "block paid providers, force local only").
        'provider_tiers' => [
            'openai' => 'paid',
            'claude' => 'paid',
            'openrouter' => 'free',
            'groq' => 'free',
            'qwen-14b-local' => 'free',
            'llama-3.2-3b-local' => 'free',
        ],

        // Fresh-install fallback only — the real, admin-editable mapping is
        // read through LLMSettingsService (hot-reload, same role
        // MarginSettingsService already plays for margin.*), never
        // config() directly once an admin override row exists.
        'feature_providers' => [
            'reasoning' => env('NEXUS_LLM_REASONING_PROVIDER', 'qwen-14b-local'),
            'negotiation' => env('NEXUS_LLM_NEGOTIATION_PROVIDER', 'qwen-14b-local'),
            'classification' => env('NEXUS_LLM_CLASSIFICATION_PROVIDER', 'llama-3.2-3b-local'),
            'fallback' => env('NEXUS_LLM_FALLBACK_PROVIDER', 'openrouter'),
        ],

        // Ordered chain LLMRouter walks when the chosen provider's chat()
        // throws — relabeled from llm-strategy.md §11's illustrative ids
        // (openrouter-free/groq-free/local-qwen-14b) to this registry's
        // actual keys; tier is already tracked above so no redundant
        // "-free" suffix is baked into the identifier itself.
        'fallback_chain' => explode(',', (string) env('NEXUS_LLM_FALLBACK_CHAIN', 'openrouter,groq,qwen-14b-local')),

        'cost_control' => [
            // Toman (matches credit.currency below) since these are
            // business/agent-facing budget figures per
            // docs/nexus-roadmap.md — LLMUsageLog itself stores cost in USD
            // (what providers actually bill in); LLMBudgetGuard is the one
            // explicit place these two currencies meet (see its own
            // docblock), via usd_to_irt_rate below.
            'daily_budget_per_agent_irt' => (int) env('NEXUS_LLM_DAILY_BUDGET_PER_AGENT', 50000),
            'monthly_budget_per_business_irt' => (int) env('NEXUS_LLM_MONTHLY_BUDGET_PER_BUSINESS', 1000000),
            // Seed rate only, same "seed defaults" language margin.* below
            // already uses — not a real-time FX feed.
            'usd_to_irt_rate' => (float) env('NEXUS_LLM_USD_TO_IRT_RATE', 600000),
        ],

        'behavior' => [
            'enable_fallback' => (bool) env('NEXUS_LLM_ENABLE_FALLBACK', true),
            'allow_local_to_paid_fallback' => (bool) env('NEXUS_LLM_ALLOW_LOCAL_TO_PAID_FALLBACK', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit System
    |--------------------------------------------------------------------------
    |
    | Businesses and Agents spend credits to negotiate and transact on the
    | marketplace. The Credit domain (Phase 3) owns the actual ledger/spend
    | logic (app/Domains/Nexus/Credit) — `action_costs` is its price list,
    | read by SpendCreditsForActionAction (the roadmap's "CostGate") on
    | every gated MCP capability. Values from docs/claude/monetization.md;
    | a $0/missing key means the action is free (read-only capabilities
    | like nexus.negotiation.status are simply never charged).
    |
    */
    'credit' => [
        'currency' => env('NEXUS_CREDIT_CURRENCY', 'IRT'),
        'starting_balance' => (int) env('NEXUS_CREDIT_STARTING_BALANCE', 0),

        'action_costs' => [
            'nexus.marketplace.search' => (int) env('NEXUS_COST_MARKETPLACE_SEARCH', 5),
            'nexus.negotiation.propose' => (int) env('NEXUS_COST_NEGOTIATION_PROPOSE', 20),
            'nexus.negotiation.counter' => (int) env('NEXUS_COST_NEGOTIATION_COUNTER', 2),
            'nexus.negotiation.accept' => (int) env('NEXUS_COST_NEGOTIATION_ACCEPT', 2),
            'nexus.negotiation.reject' => (int) env('NEXUS_COST_NEGOTIATION_REJECT', 2),
            'contract.generate' => (int) env('NEXUS_COST_CONTRACT_GENERATE', 50),
            'contract.escrow.hold' => (int) env('NEXUS_COST_ESCROW_HOLD', 100),
            // Phase 5 — small, deliberately nonzero: nexus.growth.referral.status
            // stays free (checking your own standing must never be gated,
            // same reasoning as nexus.credit.balance), but sending an
            // invite reaches an external inbox and costs real deliverability
            // risk, so a flat fee discourages spam the same way every other
            // outbound-effect capability in this table already is priced.
            'nexus.invite.send' => (int) env('NEXUS_COST_GROWTH_INVITE_SEND', 5),
            'nexus.coalition.create' => (int) env('NEXUS_COST_GROWTH_COALITION_CREATE', 10),
            // Phase 7/M5 — Private Marketplaces. Search priced the same as
            // nexus.marketplace.search (membership doesn't make lookups
            // free); listing a new item is a small anti-spam fee, same
            // reasoning as nexus.invite.send but cheaper since it never
            // reaches an outside inbox.
            'nexus.private_marketplace.search' => (int) env('NEXUS_COST_PRIVATE_MARKETPLACE_SEARCH', 5),
            'nexus.private_marketplace.list_listing' => (int) env('NEXUS_COST_PRIVATE_MARKETPLACE_LIST_LISTING', 3),
            // Phase 8/M2 — Market Intelligence reaches beyond the caller's
            // own numbers into an aggregate view of other Businesses, same
            // reasoning nexus.marketplace.search is priced instead of free
            // (nexus.analytics.business, Phase 8/M1, IS free — it only
            // ever returns the caller's own data).
            'nexus.analytics.market' => (int) env('NEXUS_COST_ANALYTICS_MARKET', 5),
            // Phase 8/M3 — AI Recommendations. Priced like
            // nexus.marketplace.search (both surface OTHER Businesses'
            // identities); nexus.marketplace.rank_suppliers is deliberately
            // absent here (free) — it only re-orders businessIds the caller
            // already has, no new discovery happens.
            'nexus.marketplace.recommendations' => (int) env('NEXUS_COST_MARKETPLACE_RECOMMENDATIONS', 5),
            'nexus.marketplace.alternatives' => (int) env('NEXUS_COST_MARKETPLACE_ALTERNATIVES', 5),
            'nexus.marketplace.negotiation_timing' => (int) env('NEXUS_COST_MARKETPLACE_NEGOTIATION_TIMING', 3),
            // Phase 8/M4 — Automation Workflows. One flat fee for creating
            // any rule shape (recurring order/inventory alert/price alert),
            // anti-spam only, same reasoning nexus.coalition.create/
            // nexus.invite.send are priced — the real recurring cost of a
            // triggered recurring-order rule is its own existing
            // nexus.negotiation.propose charge each time it fires.
            'nexus.automation.rule.create' => (int) env('NEXUS_COST_AUTOMATION_RULE_CREATE', 10),
            // Phase 8/M5 — Predictive Intelligence. nexus.analytics.forecast
            // stays free (same category as nexus.reputation.score — a
            // public trust signal about any Business); risk/scenario are
            // heavier decision-support computations, priced like
            // nexus.analytics.market.
            'nexus.analytics.risk' => (int) env('NEXUS_COST_ANALYTICS_RISK', 5),
            'nexus.analytics.scenario' => (int) env('NEXUS_COST_ANALYTICS_SCENARIO', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Growth (Phase 5 — Viral Growth Engine)
    |--------------------------------------------------------------------------
    |
    | Not in docs/claude/monetization.md (that document predates Phase 5) —
    | these are new, reasoned-from-scratch defaults documented here and in
    | docs/nexus/nexus_handoff.md rather than invented silently. Referral
    | rewards are credited (GrantReferralRewardOnBusinessVerifiedListener),
    | never spent, so they live in their own `growth` section instead of
    | `credit.action_costs` (which is exclusively CostGate's price list for
    | outgoing spend).
    |
    */
    'growth' => [
        'referral' => [
            // Two-sided (docs/nexus-roadmap.md Phase 5: "پاداش کردیت
            // دوطرفه") — referrer earns more than the referee since they
            // did the work of bringing in real business.
            'referrer_reward_credits' => (int) env('NEXUS_GROWTH_REFERRER_REWARD', 200),
            'referee_reward_credits' => (int) env('NEXUS_GROWTH_REFEREE_REWARD', 100),
            // Multi-tier: one hop past the direct referrer, deliberately
            // smaller and deliberately not recursive further (roadmap says
            // "Multi-tier tracking", not "unbounded chain").
            'tier2_reward_credits' => (int) env('NEXUS_GROWTH_TIER2_REWARD', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Margin
    |--------------------------------------------------------------------------
    |
    | Seed defaults only, from docs/claude/monetization.md — once Phase
    | 3/M5's Admin Margin Settings lands, the real, hot-reloadable values
    | are read through MarginSettingsService, never config() directly;
    | these stay here purely as the fallback for a fresh install with no
    | admin-set overrides yet.
    |
    */
    'margin' => [
        'platform_fee_percent' => (float) env('NEXUS_PLATFORM_FEE_PERCENT', 5.0),
        'llm_cost_markup_percent' => (float) env('NEXUS_MARGIN_LLM_COST_MARKUP', 30.0),
        'transaction_fee_percent' => (float) env('NEXUS_MARGIN_TRANSACTION_FEE', 0.5),
        'subscription_markup_percent' => (float) env('NEXUS_MARGIN_SUBSCRIPTION_MARKUP', 20.0),
        'negotiation_fee_percent' => (float) env('NEXUS_MARGIN_NEGOTIATION_FEE', 1.0),
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
    | Reputation (Phase 6 — Trust & Reputation)
    |--------------------------------------------------------------------------
    |
    | CalculateReputationScoreAction's weights (must sum to 1000, the
    | roadmap's own "امتیاز ۰ تا ۱۰۰۰" scale) and the threshold badges are
    | derived at, not stored. Read once per call (not hot-reloadable like
    | margin.* or llm.* — no dedicated admin settings page exists for these,
    | unlike MarginSettingsService/LLMSettingsService, since nothing in
    | Phase 6's scope asked for one).
    |
    */
    'reputation' => [
        'weights' => [
            'success_rate' => (int) env('NEXUS_REPUTATION_WEIGHT_SUCCESS_RATE', 500),
            'rating' => (int) env('NEXUS_REPUTATION_WEIGHT_RATING', 400),
            'longevity' => (int) env('NEXUS_REPUTATION_WEIGHT_LONGEVITY', 100),
        ],
        // Months of Business tenure before the longevity component maxes out.
        'longevity_full_months' => (int) env('NEXUS_REPUTATION_LONGEVITY_FULL_MONTHS', 12),

        'badges' => [
            'top_rated_min_reviews' => (int) env('NEXUS_REPUTATION_TOP_RATED_MIN_REVIEWS', 5),
            'top_rated_min_average' => (float) env('NEXUS_REPUTATION_TOP_RATED_MIN_AVERAGE', 4.5),
            'gold_partner_min_score' => (int) env('NEXUS_REPUTATION_GOLD_PARTNER_MIN_SCORE', 800),
            'gold_partner_min_deals' => (int) env('NEXUS_REPUTATION_GOLD_PARTNER_MIN_DEALS', 10),
        ],

        // Phase 6/M3 — a dispute an arbiter actually ruled against you,
        // not merely one you were involved in (raising/receiving a
        // dispute that gets resolved in your favor costs nothing).
        'dispute_penalty_per_loss' => (int) env('NEXUS_REPUTATION_DISPUTE_PENALTY_PER_LOSS', 50),
        'dispute_penalty_max' => (int) env('NEXUS_REPUTATION_DISPUTE_PENALTY_MAX', 300),

        // Phase 6/M4 — Fraud Detection's only rule: DetectFraudSignalsAction
        // auto-suspends a Business once an arbiter has ruled against it
        // this many times within the rolling window below.
        'fraud' => [
            'dispute_loss_threshold' => (int) env('NEXUS_FRAUD_DISPUTE_LOSS_THRESHOLD', 3),
            'dispute_loss_window_days' => (int) env('NEXUS_FRAUD_DISPUTE_LOSS_WINDOW_DAYS', 30),
        ],
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

    /*
    |--------------------------------------------------------------------------
    | SSO (Phase 7/M6-M8)
    |--------------------------------------------------------------------------
    |
    | Google's own credentials live in config('services.google') (Socialite's
    | conventional location) — this section is only for the enterprise
    | protocols with no package installed yet (SamlSsoProvider/LdapSsoProvider,
    | both honestly stubbed, see their own docblocks). Real config keys so an
    | admin configuring a real IdP later has a real, discoverable place to
    | put entity ID/certificate/host — not a placeholder that gets renamed
    | once real wiring happens.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Analytics & Market Intelligence (Phase 8 — Intelligence & Automation)
    |--------------------------------------------------------------------------
    |
    | Read once per call (not hot-reloadable — no dedicated admin settings
    | page exists for these, same as reputation.* above). The sample-size
    | floors exist purely for k-anonymity: an aggregate built from too few
    | distinct competing Businesses would let a Business reverse-engineer a
    | single named competitor's exact price, so both benchmark and market
    | intelligence queries suppress (return null) any aggregate whose
    | contributing-business count is below these thresholds.
    |
    */
    'analytics' => [
        'min_benchmark_sample_size' => (int) env('NEXUS_ANALYTICS_MIN_BENCHMARK_SAMPLE', 3),
        'min_market_intelligence_sample_size' => (int) env('NEXUS_ANALYTICS_MIN_MARKET_SAMPLE', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automation Workflows (Phase 8/M4 — Intelligence & Automation)
    |--------------------------------------------------------------------------
    |
    | Read once per call (not hot-reloadable — no dedicated admin settings
    | page exists for this, same as reputation/analytics settings above).
    | Applies to inventory_alert/price_alert rules only — recurring_order has its
    | own per-rule intervalDays instead of one global cooldown, since "every
    | N days" is exactly what the rule itself already configures.
    |
    */
    'automation' => [
        'alert_cooldown_hours' => (int) env('NEXUS_AUTOMATION_ALERT_COOLDOWN_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Predictive Intelligence (Phase 8/M5 — Intelligence & Automation)
    |--------------------------------------------------------------------------
    |
    | Read once per call, same as analytics/automation above — no admin
    | settings page exists for these either. `risk_weights` must sum to 100
    | (the roadmap's own 0-100 risk scale, same "weights sum to the scale's
    | max" convention reputation.weights already follows for its 0-1000
    | scale).
    |
    */
    'intelligence' => [
        'trend_recent_window_days' => (int) env('NEXUS_INTELLIGENCE_TREND_RECENT_DAYS', 90),
        'trend_prior_window_days' => (int) env('NEXUS_INTELLIGENCE_TREND_PRIOR_DAYS', 90),
        'trend_min_sample_size' => (int) env('NEXUS_INTELLIGENCE_TREND_MIN_SAMPLE', 3),

        'risk_dispute_window_days' => (int) env('NEXUS_INTELLIGENCE_RISK_DISPUTE_WINDOW_DAYS', 90),
        'risk_weights' => [
            'reputation_max_points' => (int) env('NEXUS_INTELLIGENCE_RISK_REPUTATION_MAX', 50),
            'points_per_recent_dispute_loss' => (int) env('NEXUS_INTELLIGENCE_RISK_POINTS_PER_DISPUTE', 10),
            'dispute_max_points' => (int) env('NEXUS_INTELLIGENCE_RISK_DISPUTE_MAX', 30),
            'deal_size_max_points' => (int) env('NEXUS_INTELLIGENCE_RISK_DEAL_SIZE_MAX', 20),
        ],
    ],

    'sso' => [
        'saml' => [
            'entity_id' => env('NEXUS_SAML_ENTITY_ID'),
            'sso_url' => env('NEXUS_SAML_SSO_URL'),
            'certificate' => env('NEXUS_SAML_CERTIFICATE'),
        ],
        'ldap' => [
            'host' => env('NEXUS_LDAP_HOST'),
            'base_dn' => env('NEXUS_LDAP_BASE_DN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public API (Phase 9/M2 — Ecosystem & API)
    |--------------------------------------------------------------------------
    |
    | Per-ApiKey rate limit for routes/nexus/api.php (RateLimiter::for
    | 'nexus-api', registered in NexusServiceProvider::boot()). A single
    | flat number rather than a hot-reloadable per-tier setting — no plan
    | tiering exists anywhere in this codebase yet (Credit is a flat
    | balance, not a subscription plan), so there is nothing to key a
    | per-tier limit off of. Revisit if/when plan tiers land.
    |
    */
    'api' => [
        'rate_limit_per_minute' => (int) env('NEXUS_API_RATE_LIMIT_PER_MINUTE', 60),
    ],
];
