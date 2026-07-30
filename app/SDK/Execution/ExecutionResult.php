<?php

namespace App\SDK\Execution;

use App\SDK\DTOs\CapabilityOutput;

/**
 * The return value of MCPClient::execute(). Wraps the response's `data`
 * (as a CapabilityOutput) and `meta`.
 *
 * isSuccess()/getError() always return true/null today: CapabilityExecutor
 * turns every HTTP-level failure into a thrown MCPException rather than
 * returning a "failed" ExecutionResult, so any instance that reaches
 * calling code already succeeded. They're kept — not removed — for a
 * predictable Result-shaped API, in case a future non-throwing execution
 * mode is ever added; today, catching MCPException is the only way
 * execute() failures surface.
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
