<?php

namespace Tests\Unit\Nexus\Growth;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralCode;
use PHPUnit\Framework\TestCase;

class ReferralCodeTest extends TestCase
{
    public function test_issue_createsCodeForBusiness(): void
    {
        $code = ReferralCode::issue(1, 'REF-ABC123');

        $this->assertNull($code->id());
        $this->assertSame(1, $code->businessId());
        $this->assertSame('REF-ABC123', $code->code());
    }
}
