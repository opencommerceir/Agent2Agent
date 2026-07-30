<?php

namespace OpenCommerce\SDK\Execution;

use OpenCommerce\SDK\DTOs\CapabilityOutput;

/**
 * The return value of MCPClient::execute(). isSuccess()/getError() always
 * return true/null: CapabilityExecutor turns every HTTP-level failure into
 * a thrown MCPException rather than returning a "failed" ExecutionResult,
 * so any instance that reaches calling code already succeeded. Kept for a
 * predictable Result-shaped API rather than removed.
 */
final class ExecutionResult
{
    /**
     * @param array<string, mixed> $meta
     */
    private function __construct(
        private readonly CapabilityOutput $output,
        private readonly array $meta,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function fromResponse(array $data, array $meta): self
    {
        return new self(CapabilityOutput::fromArray($data), $meta);
    }

    public function isSuccess(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->output->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getError(): ?array
    {
        return null;
    }
}
