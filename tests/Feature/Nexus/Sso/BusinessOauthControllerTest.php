<?php

namespace Tests\Feature\Nexus\Sso;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
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

class BusinessOauthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_sendsToTheUrlSocialiteBuilds(): void
    {
        $redirect = Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        $redirect->shouldReceive('getTargetUrl')->andReturn('https://accounts.google.com/o/oauth2/auth?fake=1');

        $provider = Mockery::mock(SocialiteProviderContract::class);
        $provider->shouldReceive('redirect')->andReturn($redirect);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('nexus.business.oauth.redirect', 'google'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth?fake=1');
    }

    public function test_callback_withAlreadyLinkedIdentity_logsInAndFixesSessionUserId(): void
    {
        // phpunit.xml forces SESSION_DRIVER=array for speed everywhere else
        // in this suite — this one test needs the real 'database' driver to
        // verify the actual regression (Laravel's DatabaseSessionHandler
        // resolving the wrong guard) rather than asserting against a
        // driver that never touches the sessions table at all.
        config(['session.driver' => 'database']);
        $owner = $this->ownerWithBusiness('owner@example.com');
        BusinessOwnerOauthIdentity::query()->create([
            'business_owner_id' => $owner->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
            'created_at' => now(),
        ]);
        $this->mockSocialiteUser('google-123', 'owner@example.com', 'Owner Person');

        $response = $this->get(route('nexus.business.oauth.callback', 'google'));

        $response->assertRedirect(route('nexus.business.dashboard'));
        $this->assertAuthenticated('business');
        $this->assertDatabaseHas('sessions', ['user_id' => $owner->id]);
    }

    public function test_callback_withMatchingEmailButNoLink_redirectsToLinkConfirmation(): void
    {
        $this->ownerWithBusiness('owner@example.com');
        $this->mockSocialiteUser('google-999', 'owner@example.com', 'Owner Person');

        $response = $this->get(route('nexus.business.oauth.callback', 'google'));

        $response->assertRedirect(route('nexus.business.oauth.link.show'));
        $this->assertGuest('business');
    }

    public function test_confirmLink_withCorrectPassword_linksAndLogsIn(): void
    {
        $owner = $this->ownerWithBusiness('owner@example.com', 'correct-password');
        $this->mockSocialiteUser('google-999', 'owner@example.com', 'Owner Person');
        $this->get(route('nexus.business.oauth.callback', 'google'));

        $response = $this->post(route('nexus.business.oauth.link.store'), ['password' => 'correct-password']);

        $response->assertRedirect(route('nexus.business.dashboard'));
        $this->assertAuthenticated('business');
        $this->assertDatabaseHas('business_owner_oauth_identities', [
            'business_owner_id' => $owner->id,
            'provider' => 'google',
            'provider_user_id' => 'google-999',
        ]);
    }

    public function test_confirmLink_withWrongPassword_isRejected(): void
    {
        $this->ownerWithBusiness('owner@example.com', 'correct-password');
        $this->mockSocialiteUser('google-999', 'owner@example.com', 'Owner Person');
        $this->get(route('nexus.business.oauth.callback', 'google'));

        $response = $this->post(route('nexus.business.oauth.link.store'), ['password' => 'wrong-password']);

        $response->assertSessionHasErrors('password');
        $this->assertGuest('business');
        $this->assertDatabaseMissing('business_owner_oauth_identities', ['provider_user_id' => 'google-999']);
    }

    public function test_callback_withNoMatchingAccount_neverCreatesOne(): void
    {
        $this->mockSocialiteUser('google-000', 'nobody@example.com', 'Nobody');

        $response = $this->get(route('nexus.business.oauth.callback', 'google'));

        $response->assertRedirect(route('nexus.business.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest('business');
    }

    private function mockSocialiteUser(string $id, string $email, string $name): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = $id;
        $socialiteUser->email = $email;
        $socialiteUser->name = $name;

        $provider = Mockery::mock(SocialiteProviderContract::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function ownerWithBusiness(string $email, string $password = 'password123'): BusinessOwner
    {
        $business = app(RegisterBusinessAction::class)->execute('نام تست', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => $email,
            'password' => $password,
        ]);
    }
}
