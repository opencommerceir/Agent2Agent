<?php

namespace Tests\Feature\Nexus\Growth;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_sendsInviteAndRedirectsWithStatus(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100, CreditTransactionType::AdminGrant, 'test.seed');
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($owner, 'business')->post(route('nexus.growth.invites.store'), [
            'invitee_name' => 'Lead Co',
            'invitee_email' => 'lead@example.com',
        ]);

        $response->assertRedirect(route('nexus.growth.invites.index'));
        $this->assertDatabaseHas('nexus_invites', ['inviter_business_id' => $business->id, 'invitee_email' => 'lead@example.com']);
    }

    public function test_index_listsSentInvites(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100, CreditTransactionType::AdminGrant, 'test.seed');
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Ali Rezaei',
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);
        app(\App\Domains\Nexus\Growth\Application\Actions\SendAgentInviteAction::class)->execute($business->id, 'Lead Co', 'lead@example.com');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.growth.invites.index'));

        $response->assertOk();
        $response->assertSee('lead@example.com');
    }
}
