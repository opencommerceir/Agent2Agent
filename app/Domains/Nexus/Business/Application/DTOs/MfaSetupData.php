<?php

namespace App\Domains\Nexus\Business\Application\DTOs;

final class MfaSetupData
{
    public function __construct(
        public readonly string $secret,
        public readonly string $otpauthUri,
    ) {
    }
}
