<?php

namespace App\Modules\Demo\Interfaces\MCP;

/**
 * The capability manifest for the Demo module — what
 * DemoServiceProvider registers into the Capability Registry and wires
 * into CapabilityHandlerRegistry. Kept as plain data here, separate from
 * the provider's boot()/idempotency plumbing, so "what capabilities does
 * Demo expose" is readable on its own.
 *
 * Named `demo.tools.*` rather than the originally-proposed `demo.echo` /
 * `demo.time` / `demo.calculator`: CapabilityName enforces the same
 * strict 3-segment `domain.resource.action` format PermissionKey does
 * (Phase 4) — a 2-segment name would fail validation the moment
 * DemoServiceProvider tried to register it. The required permissions
 * (`demo.echo.execute`, `demo.time.read`, `demo.calculator.execute`) were
 * already 3 segments, so those are unchanged from the original request.
 */
final class DemoCapabilities
{
    /**
     * @return list<array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, string>,
     *     outputSchema: array<string, string>,
     *     requiredPermissions: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'demo.tools.echo',
                'description' => 'Echoes back the provided message with a timestamp.',
                'inputSchema' => ['message' => 'string'],
                'outputSchema' => ['echo' => 'string', 'timestamp' => 'string'],
                'requiredPermissions' => ['demo.echo.execute'],
            ],
            [
                'name' => 'demo.tools.time',
                'description' => 'Returns the current server time.',
                'inputSchema' => [],
                'outputSchema' => ['utc' => 'string', 'unix' => 'int'],
                'requiredPermissions' => ['demo.time.read'],
            ],
            [
                'name' => 'demo.tools.calculator',
                'description' => 'Performs a simple arithmetic operation (add, subtract, multiply, divide).',
                'inputSchema' => ['operation' => 'string', 'a' => 'number', 'b' => 'number'],
                'outputSchema' => ['result' => 'number'],
                'requiredPermissions' => ['demo.calculator.execute'],
            ],
        ];
    }
}
