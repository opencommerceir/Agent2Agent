<?php

namespace Tests\Feature\Nexus\Sso;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_listsOnlySessionsBelongingToCaller(): void
    {
        $owner = $this->ownerWithBusiness('owner@example.com');
        $otherOwner = $this->ownerWithBusiness('other@example.com');
        $this->seedSessionRow('session-mine', $owner->id);
        $this->seedSessionRow('session-not-mine', $otherOwner->id);

        $response = $this->actingAs($owner, 'business')->get(route('nexus.business.sessions.index'));
        $response->assertOk();

        $sessions = app(\App\Domains\Nexus\Business\Application\Actions\ListMyActiveSessionsAction::class)->execute($owner->id, 'irrelevant');
        $this->assertCount(1, $sessions);
        $this->assertSame('session-mine', $sessions[0]->id);
    }

    public function test_revoke_deletesOnlyCallersOwnSession(): void
    {
        $owner = $this->ownerWithBusiness('owner@example.com');
        $otherOwner = $this->ownerWithBusiness('other@example.com');
        $this->seedSessionRow('session-mine', $owner->id);
        $this->seedSessionRow('session-not-mine', $otherOwner->id);

        $this->actingAs($owner, 'business')->delete(route('nexus.business.sessions.destroy', 'session-mine'));

        $this->assertDatabaseMissing('sessions', ['id' => 'session-mine']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-not-mine']);
    }

    public function test_revoke_cannotDeleteAnotherOwnersSession(): void
    {
        $owner = $this->ownerWithBusiness('owner@example.com');
        $otherOwner = $this->ownerWithBusiness('other@example.com');
        $this->seedSessionRow('session-not-mine', $otherOwner->id);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Domains\Nexus\Business\Application\Actions\RevokeSessionAction::class)->execute('session-not-mine', $owner->id);
    }

    private function seedSessionRow(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => Str::random(40),
            'last_activity' => time(),
        ]);
    }

    private function ownerWithBusiness(string $email): BusinessOwner
    {
        $business = app(RegisterBusinessAction::class)->execute('نام تست', 'Test Company '.Str::random(4), BusinessType::Company, Industry::Technology);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner Person',
            'email' => $email,
            'password' => 'password123',
        ]);
    }
}
