<?php

namespace Tests\Unit\Nexus\Growth;

use App\Domains\Nexus\Growth\Domain\Entities\Invite;
use App\Domains\Nexus\Growth\Domain\ValueObjects\InviteStatus;
use PHPUnit\Framework\TestCase;

class InviteTest extends TestCase
{
    public function test_send_startsInSentStatus(): void
    {
        $invite = Invite::send(1, 'Lead Co', 'lead@example.com', 'REF-ABC123');

        $this->assertSame(InviteStatus::Sent, $invite->status());
        $this->assertNull($invite->convertedBusinessId());
        $this->assertNull($invite->convertedAt());
        $this->assertSame('a', $invite->messageVariant());
    }

    public function test_convert_transitionsToConverted(): void
    {
        $invite = Invite::send(1, 'Lead Co', 'lead@example.com', 'REF-ABC123');

        $invite->convert(42);

        $this->assertSame(InviteStatus::Converted, $invite->status());
        $this->assertSame(42, $invite->convertedBusinessId());
        $this->assertNotNull($invite->convertedAt());
    }

    public function test_convert_isIdempotent(): void
    {
        $invite = Invite::send(1, 'Lead Co', 'lead@example.com', 'REF-ABC123');
        $invite->convert(42);
        $firstConvertedAt = $invite->convertedAt();

        $invite->convert(99);

        $this->assertSame(42, $invite->convertedBusinessId());
        $this->assertSame($firstConvertedAt, $invite->convertedAt());
    }
}
