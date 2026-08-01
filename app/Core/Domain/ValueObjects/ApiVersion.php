<?php

namespace App\Core\Domain\ValueObjects;

/**
 * The set of MCP Gateway wire versions the platform knows about. `V1` and
 * `V2` are real, routed versions (routes/mcp.php); `V3` is a modeled,
 * unimplemented future intent — the same "enum case exists before its own
 * implementation does" shape `ShippingProviderName::Usps/Fedex/Dhl` and
 * `RewardType::FreeProduct/FreeShipping` already establish elsewhere in
 * this codebase. Adding a real v3 later means adding v3 routes/controllers,
 * not touching this enum.
 */
enum ApiVersion: string
{
    case V1 = 'v1';
    case V2 = 'v2';
    case V3 = 'v3';
}
