<?php

namespace OpenCommerce\SDK\Execution;

use OpenCommerce\SDK\Authentication\AuthenticatedRequest;
use OpenCommerce\SDK\DTOs\CapabilityInput;
use OpenCommerce\SDK\Exceptions\MCPException;

/**
 * Calls POST /mcp/v1/execute. Any non-2xx response is turned into the
 * matching MCPException subclass immediately.
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
            data: $response['body']['data'] ?? [],
            meta: $response['body']['meta'] ?? [],
        );
    }
}
