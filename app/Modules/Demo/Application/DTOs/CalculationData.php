<?php

namespace App\Modules\Demo\Application\DTOs;

final class CalculationData
{
    public function __construct(
        public readonly float $result,
    ) {
    }

    /**
     * @return array{result: float}
     */
    public function toArray(): array
    {
        return ['result' => $this->result];
    }
}
