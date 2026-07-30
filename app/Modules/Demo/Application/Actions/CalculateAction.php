<?php

namespace App\Modules\Demo\Application\Actions;

use App\Modules\Demo\Application\DTOs\CalculationData;
use App\Modules\Demo\Domain\ValueObjects\CalculatorOperation;
use InvalidArgumentException;

final class CalculateAction
{
    /**
     * @param array<string, mixed> $input
     * @return array{result: float}
     */
    public function execute(array $input): array
    {
        $operation = CalculatorOperation::tryFrom($input['operation'] ?? '');

        if (! $operation) {
            throw new InvalidArgumentException(
                'The [operation] input field must be one of: add, subtract, multiply, divide.'
            );
        }

        if (! is_numeric($input['a'] ?? null) || ! is_numeric($input['b'] ?? null)) {
            throw new InvalidArgumentException('The [a] and [b] input fields are required and must be numbers.');
        }

        $a = (float) $input['a'];
        $b = (float) $input['b'];

        $result = match ($operation) {
            CalculatorOperation::Add => $a + $b,
            CalculatorOperation::Subtract => $a - $b,
            CalculatorOperation::Multiply => $a * $b,
            CalculatorOperation::Divide => $this->divide($a, $b),
        };

        return (new CalculationData($result))->toArray();
    }

    private function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new InvalidArgumentException('Division by zero is not allowed.');
        }

        return $a / $b;
    }
}
