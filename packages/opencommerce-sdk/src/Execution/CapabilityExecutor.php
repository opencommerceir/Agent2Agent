<?php

namespace OpenCommerce\SDK\Execution;

use OpenCommerce\SDK\Authentication\AuthenticatedRequest;
use OpenCommerce\SDK\DTOs\CapabilityInput;
use OpenCommerce\SDK\Exceptions\MCPException;

/**
 * Calls POST /{version}/execute (MCPConfig::$baseUrl already carries the
 * version segment). Any non-2xx response is turned into the matching
 * MCPException subclass immediately.
 *
 * Reads whichever envelope key the server actually used — `result`
 * (v2, docs/api/v2/changes.md) if present, else `data` (v1) — same for
 * `metadata`/`meta` — so this one class works against either wire version
 * without the caller needing to know which. This is envelope-shape
 * detection only, not the server's own VersionDetector logic duplicated
 * client-side: the SDK never guesses which version it's talking to, it
 * just accepts either response shape that comes back.
 */
final class CapabilityExecutor
{
    public function __construct(
        private readonly AuthenticatedRequest $request,
    ) {
    }

    public function execute(string $capabilityName, CapabilityInput $input): ExecutionResult
    {
        $response = $this->request->post('execute', [
            'capability' => $capabilityName,
            'input' => $input->toArray(),
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw MCPException::fromResponse($response['status'], $response['body']);
        }

        return ExecutionResult::fromResponse(
            data: $response['body']['result'] ?? $response['body']['data'] ?? [],
            meta: $response['body']['metadata'] ?? $response['body']['meta'] ?? [],
        );
    }
}
