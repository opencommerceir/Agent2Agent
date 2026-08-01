<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default / Supported MCP Gateway Versions
    |--------------------------------------------------------------------------
    |
    | 'default_version' is what VersionDetector falls back to when a
    | request carries no URL/header/query version signal at all — today
    | that can only happen via the header/query tiers, since every real
    | route (routes/mcp.php) already pins an explicit /v1/ or /v2/ segment.
    |
    */
    'default_version' => 'v1',

    'supported_versions' => ['v1', 'v2'],

    /*
    |--------------------------------------------------------------------------
    | Deprecation Schedule
    |--------------------------------------------------------------------------
    |
    | One entry per deprecated version. A version with no entry here is
    | treated as not deprecated at all (DeprecationNotifier::isDeprecated()
    | — no Deprecation/Sunset/Link/Warning headers get attached, no log
    | line gets written). v2 has no entry yet, deliberately — it becomes
    | one only once a real v3 supersedes it.
    |
    */
    'deprecation' => [
        'v1' => [
            'deprecated_at' => '2026-08-02',
            'sunset_at' => '2028-01-01',
            'successor' => 'v2',
            'migration_guide' => 'https://docs.opencommerce.ir/migration/v1-to-v2',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Header Names
    |--------------------------------------------------------------------------
    |
    | Named here, not hardcoded in ApiVersioning, so a future deployment
    | that needs different header names doesn't mean editing the
    | middleware itself.
    |
    */
    'headers' => [
        'version' => 'X-API-Version',
        'deprecation' => 'Deprecation',
        'sunset' => 'Sunset',
        'link' => 'Link',
        'warning' => 'Warning',
    ],
];
