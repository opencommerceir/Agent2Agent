<?php

namespace App\Modules\Demo\Application\Actions;

use App\Modules\Demo\Application\DTOs\TimeData;
use DateTimeImmutable;

final class GetCurrentTimeAction
{
    /**
     * @param array<string, mixed> $input
     * @return array{utc: string, unix: int}
     */
    public function execute(array $input): array
    {
        $now = new DateTimeImmutable();

        return (new TimeData(
            utc: $now->format(DATE_ATOM),
            unix: $now->getTimestamp(),
        ))->toArray();
    }
}
