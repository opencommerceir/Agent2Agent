<?php

namespace App\Modules\Demo\Application\DTOs;

final class EchoData
{
    public function __construct(
        public readonly string $echo,
        public readonly string $timestamp,
    ) {
    }

    /**
     * @return array{echo: string, timestamp: string}
     */
    public function toArray(): array
    {
        return ['echo' => $this->echo, 'timestamp' => $this->timestamp];
    }
}
