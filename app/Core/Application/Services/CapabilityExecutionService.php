<?php

namespace App\Core\Application\Services;

use App\Core\Application\DTOs\CapabilityData;

/**
 * The seam where a real Domain Module's capability handler will be wired
 * in later (Commerce, in Phase 2). Today there are no Domain Modules
 * registered yet, so execution is a mock acknowledgement — MCP itself must
 * never contain that handler's business logic (Decision 007), so this
 * class deliberately does nothing capability-specific; it only validates
 * shape and measures timing.
 */
final class CapabilityExecutionService
{
    public function __construct(
        private readonly MCPRequestValidationService $requestValidation,
    ) {
    }

    /**
     * @return array{result: array, executionTimeMs: int}
     */
    public function execute(CapabilityData $capability, array $input): array
    {
        $this->requestValidation->validate($capability, $input);

        $startedAt = microtime(true);

        $result = [
            'message' => "Capability [{$capability->name}] acknowledged (mock execution — no Domain Module wired yet).",
            'input' => $input,
        ];

        $executionTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'result' => $result,
            'executionTimeMs' => $executionTimeMs,
        ];
    }
}
