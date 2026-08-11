<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class VerifyBusinessActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_onPendingBusiness_setsVerificationStatusToVerified(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        Event::fake();
        $result = app(VerifyBusinessAction::class)->execute($business->id);

        $this->assertSame('verified', $result->verificationStatus);
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'verification_status' => 'verified']);
        Event::assertDispatched(BusinessWasVerified::class, fn ($event) => $event->business->id() === $business->id);
    }

    public function test_execute_withNonExistentBusiness_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(VerifyBusinessAction::class)->execute(9999);
    }
}
