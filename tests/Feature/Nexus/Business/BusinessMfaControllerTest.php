<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\Services\TotpService;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class BusinessMfaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_thenConfirmWithRealCode_enablesMfaAndShowsRecoveryCodesOnce(): void
    {
        $owner = $this->ownerWithBusiness('owner@example.com', 'password123');

        $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.start'));
        $secret = $owner->fresh()->mfa_secret;
        $code = app(TotpService::class)->code($this->rawKey($secret), intdiv(time(), 30));

        $response = $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.confirm'), ['code' => $code]);

        $response->assertRedirect(route('nexus.business.mfa.edit'));
        $this->assertNotNull($owner->fresh()->mfa_enabled_at);
        $this->assertDatabaseCount('business_owner_recovery_codes', 10);
    }

    public function test_confirm_withWrongCode_doesNotEnable(): void
    {
        $owner = $this->ownerWithBusiness('owner@example.com', 'password123');
        $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.start'));

        $response = $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.confirm'), ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertNull($owner->fresh()->mfa_enabled_at);
    }

    public function test_disable_requiresCorrectPassword(): void
    {
        $owner = $this->ownerWithBusiness('owner@example.com', 'password123');
        $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.start'));
        $secret = $owner->fresh()->mfa_secret;
        $code = app(TotpService::class)->code($this->rawKey($secret), intdiv(time(), 30));
        $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.confirm'), ['code' => $code]);

        $wrongAttempt = $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.disable'), ['password' => 'wrong-password']);
        $wrongAttempt->assertSessionHasErrors('password');
        $this->assertNotNull($owner->fresh()->mfa_enabled_at);

        $correctAttempt = $this->actingAs($owner, 'business')->post(route('nexus.business.mfa.disable'), ['password' => 'password123']);
        $correctAttempt->assertRedirect(route('nexus.business.mfa.edit'));
        $this->assertNull($owner->fresh()->mfa_enabled_at);
        $this->assertDatabaseCount('business_owner_recovery_codes', 0);
    }

    private function rawKey(string $base32Secret): string
    {
        $decode = new ReflectionMethod(TotpService::class, 'base32Decode');
        $decode->setAccessible(true);

        return $decode->invoke(app(TotpService::class), $base32Secret);
    }

    private function ownerWithBusiness(string $email, string $password): BusinessOwner
    {
        $business = app(RegisterBusinessAction::class)->execute('نام تست', 'Test Company', BusinessType::Company, Industry::Technology);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => $email,
            'password' => $password,
        ]);
    }
}
