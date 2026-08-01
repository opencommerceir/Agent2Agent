<?php

namespace App\Modules\Notifications\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A thin wrapper, deliberately with no format validation — unlike Email
 * (Commerce's own VO), a Recipient's shape depends entirely on which
 * ChannelType is about to read it (an email address for `Email`, a phone
 * number for `Sms`, an arbitrary URL for `Webhook`, an internal
 * identifier for `InApp`). Validating "is this a valid X" would require
 * knowing the channel at construction time, which would couple this VO
 * to a specific channel — the channel-specific Sender is where that
 * validation belongs instead, if it's ever added.
 */
final class Recipient
{
    private readonly string $value;

    public function __construct(string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Recipient cannot be empty.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
