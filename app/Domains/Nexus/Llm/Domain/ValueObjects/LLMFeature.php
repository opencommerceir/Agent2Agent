<?php

namespace App\Domains\Nexus\Llm\Domain\ValueObjects;

/**
 * The four routable "consumers" docs/claude/llm-strategy.md defines
 * (§3/§4) — each has its own admin-configurable provider (LLMSettingsService)
 * and its own default in config/nexus/platform.php's llm.feature_providers.
 */
enum LLMFeature: string
{
    case Reasoning = 'reasoning';
    case Negotiation = 'negotiation';
    case Classification = 'classification';
    case Fallback = 'fallback';
}
