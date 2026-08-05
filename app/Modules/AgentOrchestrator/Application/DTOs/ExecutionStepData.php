<?php

namespace App\Modules\AgentOrchestrator\Application\DTOs;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;

final class ExecutionStepData
{
    /**
     * @param array<string, mixed> $input
     * @param ?array<string, mixed> $output
     */
    public function __construct(
        public readonly string $capability,
        public readonly array $input,
        public readonly string $priority,
        public readonly string $status,
        public readonly ?array $output,
        public readonly ?string $error,
    ) {
    }

    public static function fromEntity(ExecutionStep $step): self
    {
        return new self(
            capability: $step->capability,
            input: $step->input,
            priority: $step->priority->value,
            status: $step->status()->value,
            output: $step->output(),
            error: $step->errorMessage(),
        );
    }

    /**
     * @return array{capability: string, input: array, priority: string, status: string, output: ?array, error: ?string}
     */
    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'input' => $this->input,
            'priority' => $this->priority,
            'status' => $this->status,
            'output' => $this->output,
            'error' => $this->error,
        ];
    }
}
