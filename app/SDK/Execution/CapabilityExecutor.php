<?php

namespace App\SDK\Execution;

use App\SDK\Authentication\AuthenticatedRequest;
use App\SDK\DTOs\CapabilityInput;
use App\SDK\Exceptions\MCPException;

/**
 * Calls POST /mcp/v1/execute. Any non-2xx response is turned into the
 * matching MCPException subclass immediately — callers never have to
 * inspect a status code themselves.
 */
final class CapabilityExecutor
{
    public function __construct(
        private readonly AuthenticatedRequest $request,
    ) {
    }

    public function execute(string $capabilityName, CapabilityInput $input): ExecutionResult
    {
        $response = $this->request->client()->post('execute', [
            'capability' => $capabilityName,
            'input' => $input->toArray(),
        ]);

        if ($response->failed()) {
            throw MCPException::fromResponse($response->status(), $response->json() ?? []);
        }

        return ExecutionResult::fromResponse(
            data: $response->json('data', []),
            meta: $response->json('meta', []),
        );
    }
}
