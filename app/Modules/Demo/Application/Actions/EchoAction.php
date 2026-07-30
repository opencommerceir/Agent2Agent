<?php

namespace App\Modules\Demo\Application\Actions;

use App\Modules\Demo\Application\DTOs\EchoData;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Deliberately trivial: Demo capabilities exist only to prove a
 * capability can go from "registered" to "actually executed by MCP
 * Gateway" end to end. No business logic, no persistence.
 *
 * execute() takes a plain `array $input` — not typed named parameters
 * like Core's Actions — because this Action doubles as the callable
 * CapabilityHandlerRegistry invokes directly with the raw input array
 * MCP Gateway received.
 */
final class EchoAction
{
    /**
     * @param array<string, mixed> $input
     * @return array{echo: string, timestamp: string}
     */
    public function execute(array $input): array
    {
        if (! isset($input['message']) || ! is_string($input['message'])) {
            throw new InvalidArgumentException('The [message] input field is required and must be a string.');
        }

        return (new EchoData(
            echo: $input['message'],
            timestamp: (new DateTimeImmutable())->format(DATE_ATOM),
        ))->toArray();
    }
}
