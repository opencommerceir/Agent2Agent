<?php

namespace App\Modules\Demo\Application\DTOs;

final class TimeData
{
    public function __construct(
        public readonly string $utc,
        public readonly int $unix,
    ) {
    }

    /**
     * @return array{utc: string, unix: int}
     */
    public function toArray(): array
    {
        return ['utc' => $this->utc, 'unix' => $this->unix];
    }
}
