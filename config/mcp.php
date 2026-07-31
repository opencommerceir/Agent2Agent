<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Gateway Rate Limit
    |--------------------------------------------------------------------------
    |
    | Requests an individual Agent may make to /mcp/v1/execute per rolling
    | minute, enforced by EnforceRateLimitAction. Keyed per-agent-id, not
    | global — one Agent hitting this never affects any other Agent.
    |
    */
    'rate_limit_per_minute' => (int) env('MCP_RATE_LIMIT_PER_MINUTE', 100),
];
