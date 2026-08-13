<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\ConfirmMfaSetupAction;
use App\Domains\Nexus\Business\Application\Actions\EnableMfaAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\Services\TotpService;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwnerOauthIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProviderContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MfaLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_withMfaEnabled_requiresChallengeBeforeReachingDashboard(): void
    {
        [$owner, $secret] = $this->ownerWithMfaEnabled('owner@example.com', 'password123');

        $response = $this->post(route('nexus.business.login.store'), [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('nexus.business.mfa-challenge.show'));
        $this->assertGuest('business');

        $code = app(TotpService::class)->code($this->rawKey($secret), intdiv(time(), 30));
        $verify = $this->post(route('nexus.business.mfa-challenge.verify'), ['code' => $code]);

        $verify->assertRedirect(route('nexus.business.dashboard'));
        $this->assertAuthenticatedAs($owner->fresh(), 'business');
    }

    public function test_challenge_withWrongCode_isRejectedWithoutLoggingIn(): void
    {
        $this->ownerWithMfaEnabled('owner@example.com', 'password123');

        $this->post(route('nexus.business.login.store'), [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post(route('nexus.business.mfa-challenge.verify'), ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest('business');
    }

    public function test_challenge_withoutPriorPasswordStep_bouncesToLogin(): void
    {
        // Hitting the challenge endpoint directly (no 'nexus.mfa.pending'
        // session state) never leaks whether MFA exists for anyone.
        $response = $this->get(route('nexus.business.mfa-challenge.show'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_recoveryCode_isSingleUse(): void
    {
        [$owner, , $recoveryCodes] = $this->ownerWithMfaEnabledAndRecoveryCodes('owner@example.com', 'password123');

        $this->post(route('nexus.business.login.store'), ['email' => 'owner@example.com', 'password' => 'password123']);
        $first = $this->post(route('nexus.business.mfa-challenge.verify'), ['code' => $recoveryCodes[0]]);
        $first->assertRedirect(route('nexus.business.dashboard'));
        $this->post(route('nexus.business.logout'));

        $this->post(route('nexus.business.login.store'), ['email' => 'owner@example.com', 'password' => 'password123']);
        $second = $this->post(route('nexus.business.mfa-challenge.verify'), ['code' => $recoveryCodes[0]]);

        $second->assertSessionHasErrors('code');
        $this->assertGuest('business');
    }

    public function test_oauthLogin_withMfaEnabled_alsoRequiresChallenge(): void
    {
        [$owner, $secret] = $this->ownerWithMfaEnabled('owner@example.com', 'password123');
        BusinessOwnerOauthIdentity::query()->create([
            'business_owner_id' => $owner->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'created_at' => now(),
        ]);

        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-123';
        $socialiteUser->email = 'owner@example.com';
        $socialiteUser->name = 'Owner Person';
        $provider = Mockery::mock(SocialiteProviderContract::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('nexus.business.oauth.callback', 'google'));

        $response->assertRedirect(route('nexus.business.mfa-challenge.show'));
        $this->assertGuest('business');
    }

    /**
     * @return array{0: BusinessOwner, 1: string}
     */
    private function ownerWithMfaEnabled(string $email, string $password): array
    {
        $business = app(RegisterBusinessAction::class)->execute('نام تست', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => $email,
            'password' => $password,
        ]);

        $setup = app(EnableMfaAction::class)->execute($owner->id);
        $code = app(TotpService::class)->code($this->rawKey($setup->secret), intdiv(time(), 30));
        app(ConfirmMfaSetupAction::class)->execute($owner->id, $code);

        return [$owner, $setup->secret];
    }

    /**
     * @return array{0: BusinessOwner, 1: string, 2: list<string>}
     */
    private function ownerWithMfaEnabledAndRecoveryCodes(string $email, string $password): array
    {
        $business = app(RegisterBusinessAction::class)->execute('نام تست', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => $email,
            'password' => $password,
        ]);

        $setup = app(EnableMfaAction::class)->execute($owner->id);
        $code = app(TotpService::class)->code($this->rawKey($setup->secret), intdiv(time(), 30));
        $recoveryCodes = app(ConfirmMfaSetupAction::class)->execute($owner->id, $code);

        return [$owner, $setup->secret, $recoveryCodes];
    }

    private function rawKey(string $base32Secret): string
    {
        $decode = new \ReflectionMethod(TotpService::class, 'base32Decode');
        $decode->setAccessible(true);

        return $decode->invoke(app(TotpService::class), $base32Secret);
    }
}
