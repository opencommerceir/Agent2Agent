<?php

namespace Tests\Feature\Nexus\Business;

use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralSignupRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegisterBusinessControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_withValidData_createsBusinessAndOwnerAndLogsIn(): void
    {
        Storage::fake('public');

        $response = $this->post(route('nexus.business.register.store'), [
            'owner_name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name_fa' => 'شرکت آزمایشی',
            'name_en' => 'Test Company',
            'type' => 'company',
            'industry' => 'technology',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertRedirect(route('nexus.business.dashboard'));
        $this->assertDatabaseHas('business_owners', ['email' => 'ali@example.com']);
        $this->assertDatabaseHas('businesses', ['name_en' => 'Test Company', 'verification_status' => 'pending']);

        $owner = BusinessOwner::query()->where('email', 'ali@example.com')->firstOrFail();
        Storage::disk('public')->assertExists($owner->business->logo_path);
        $this->assertAuthenticated('business');
    }

    public function test_store_withDuplicateEmail_isRejected(): void
    {
        BusinessOwner::query()->create([
            'business_id' => $this->registerBusinessForOwnerFixture(),
            'name' => 'Existing Owner',
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);

        $response = $this->from(route('nexus.business.register'))->post(route('nexus.business.register.store'), [
            'owner_name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name_fa' => 'شرکت آزمایشی',
            'name_en' => 'Test Company',
            'type' => 'company',
            'industry' => 'technology',
        ]);

        $response->assertRedirect(route('nexus.business.register'));
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('business_owners', 1);
    }

    public function test_create_whenAlreadyLoggedIn_redirectsToDashboard(): void
    {
        Storage::fake('public');

        $this->post(route('nexus.business.register.store'), [
            'owner_name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name_fa' => 'شرکت آزمایشی',
            'name_en' => 'Test Company',
            'type' => 'company',
            'industry' => 'technology',
        ]);

        $response = $this->get(route('nexus.business.register'));

        $response->assertRedirect(route('nexus.business.dashboard'));
    }

    public function test_create_withRefQueryParam_prefillsHiddenFieldAndStore_recordsReferralSignup(): void
    {
        $referrer = app(\App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction::class)->execute(
            'معرف', 'Referrer Co',
            \App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType::Company,
            \App\Domains\Nexus\Business\Domain\ValueObjects\Industry::Retail,
        );
        app(VerifyBusinessAction::class)->execute($referrer->id);
        $code = app(ReferralCodeRepositoryInterface::class)->findByBusinessId($referrer->id);

        $createResponse = $this->get(route('nexus.business.register', ['ref' => $code->code()]));
        $createResponse->assertSee($code->code(), false);

        $this->post(route('nexus.business.register.store'), [
            'owner_name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name_fa' => 'شرکت آزمایشی',
            'name_en' => 'Test Company',
            'type' => 'company',
            'industry' => 'technology',
            'referral_code' => $code->code(),
        ]);

        $newBusiness = BusinessOwner::query()->where('email', 'ali@example.com')->firstOrFail();
        $signup = app(ReferralSignupRepositoryInterface::class)->findByRefereeId($newBusiness->business_id);

        $this->assertNotNull($signup);
        $this->assertSame($referrer->id, $signup->referrerBusinessId());
    }

    public function test_store_withUnknownReferralCode_stillRegistersSuccessfully(): void
    {
        $response = $this->post(route('nexus.business.register.store'), [
            'owner_name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name_fa' => 'شرکت آزمایشی',
            'name_en' => 'Test Company',
            'type' => 'company',
            'industry' => 'technology',
            'referral_code' => 'REF-BOGUS1',
        ]);

        $response->assertRedirect(route('nexus.business.dashboard'));
        $this->assertDatabaseHas('business_owners', ['email' => 'ali@example.com']);
    }

    private function registerBusinessForOwnerFixture(): int
    {
        $business = app(\App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction::class)->execute(
            'شرکت اول',
            'First Company',
            \App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType::Company,
            \App\Domains\Nexus\Business\Domain\ValueObjects\Industry::Retail,
        );

        return $business->id;
    }
}
