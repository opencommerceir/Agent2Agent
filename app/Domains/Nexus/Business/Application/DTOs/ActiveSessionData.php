<?php

namespace App\Domains\Nexus\Business\Application\DTOs;

final class ActiveSessionData
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly int $lastActivity,
        public readonly bool $isCurrent,
    ) {
    }
}
