<?php

namespace App\Core\Application\Services;

use App\Core\Application\DTOs\CapabilityData;

/**
 * Where a Capability actually runs. Was a hardcoded mock through Phase 4
 * ("no Domain Module wired yet") — now dispatches to whatever the calling
 * capability has registered in CapabilityHandlerRegistry (see Demo module
 * for the reference pattern). MCP itself still contains no business
 * logic (Decision 007): this class only validates shape, looks up the
 * handler, calls it, and measures timing — the handler itself lives in
 * the owning Domain Module.
 */
final class CapabilityExecutionService
{
    public function __construct(
        private readonly MCPRequestValidationService $requestValidation,
        private readonly CapabilityHandlerRegistry $handlers,
    ) {
    }

    /**
     * @return array{result: array, executionTimeMs: int}
     */
    public function execute(CapabilityData $capability, array $input, int $tenantId): array
    {
        $this->requestValidation->validate($capability, $input);

        $handler = $this->handlers->getHandler($capability->name);

        $startedAt = microtime(true);
        $result = $handler($input, $tenantId);
        $executionTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'result' => $result,
            'executionTimeMs' => $executionTimeMs,
        ];
    }
}
