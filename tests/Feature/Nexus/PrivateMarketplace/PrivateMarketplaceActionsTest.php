<?php

namespace Tests\Feature\Nexus\PrivateMarketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\AcceptMemberInvitationAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\AddListingAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\CreatePrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\GetPrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\InviteMemberAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\SearchPrivateMarketplaceAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PrivateMarketplaceActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_thenInviteAndAccept_makesMemberActive(): void
    {
        $owner = $this->verifiedBusiness('Owner Co');
        $member = $this->verifiedBusiness('Member Co');
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner->id, 'الف', 'Alpha Market', '#00F0FF');

        app(InviteMemberAction::class)->execute($marketplace->id, $owner->id, $member->id);
        $invited = app(GetPrivateMarketplaceAction::class)->execute($marketplace->id);
        $this->assertSame('invited', $invited->members[0]['status']);

        app(AcceptMemberInvitationAction::class)->execute($invited->members[0]['id'], $member->id);

        $accepted = app(GetPrivateMarketplaceAction::class)->execute($marketplace->id);
        $this->assertSame('active', $accepted->members[0]['status']);
    }

    public function test_search_byNonMember_returnsEmpty(): void
    {
        $owner = $this->verifiedBusiness('Owner Co', 100);
        $outsider = $this->verifiedBusiness('Outsider Co', 100);
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner->id, 'الف', 'Alpha Market');
        app(AddListingAction::class)->execute($marketplace->id, $owner->id, CatalogItemType::Product, 1, 50000, 'IRT');

        $result = app(SearchPrivateMarketplaceAction::class)->execute($marketplace->id, $outsider->id);

        $this->assertSame([], $result['listings']);
    }

    public function test_search_byActiveMember_seesConfidentialPrice(): void
    {
        $owner = $this->verifiedBusiness('Owner Co', 100);
        $member = $this->verifiedBusiness('Member Co', 100);
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner->id, 'الف', 'Alpha Market');
        app(AddListingAction::class)->execute($marketplace->id, $owner->id, CatalogItemType::Product, 1, 50000, 'IRT');
        $this->joinAsActiveMember($marketplace->id, $owner->id, $member->id);

        $result = app(SearchPrivateMarketplaceAction::class)->execute($marketplace->id, $member->id);

        $this->assertCount(1, $result['listings']);
        $this->assertSame(50000, $result['listings'][0]['specialPriceAmount']);
    }

    public function test_privateListing_neverLeaksIntoPublicMarketplaceSearch(): void
    {
        $owner = $this->verifiedBusiness('Owner Co', 100);
        $outsider = $this->verifiedBusiness('Outsider Co', 100);
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner->id, 'الف', 'Alpha Market');
        app(AddListingAction::class)->execute($marketplace->id, $owner->id, CatalogItemType::Product, 999, 50000, 'IRT');

        $publicResult = app(SearchMarketplaceAction::class)->execute($outsider->id);

        // The owner Business itself may appear (it's verified), but no
        // product with id 999 (which only exists as a private listing
        // reference, never a real Catalog row) is ever surfaced.
        $allProductIds = collect($publicResult['listings'])
            ->flatMap(fn ($listing) => array_column($listing['products'], 'id'));
        $this->assertFalse($allProductIds->contains(999));
    }

    public function test_addListing_byNonMember_throws(): void
    {
        $owner = $this->verifiedBusiness('Owner Co', 100);
        $outsider = $this->verifiedBusiness('Outsider Co', 100);
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner->id, 'الف', 'Alpha Market');

        $this->expectException(InvalidArgumentException::class);

        app(AddListingAction::class)->execute($marketplace->id, $outsider->id, CatalogItemType::Product, 1, 50000, 'IRT');
    }

    private function joinAsActiveMember(int $marketplaceId, int $ownerId, int $memberBusinessId): void
    {
        app(InviteMemberAction::class)->execute($marketplaceId, $ownerId, $memberBusinessId);
        $data = app(GetPrivateMarketplaceAction::class)->execute($marketplaceId);
        $memberRow = collect($data->members)->firstWhere('businessId', $memberBusinessId);
        app(AcceptMemberInvitationAction::class)->execute($memberRow['id'], $memberBusinessId);
    }

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        if ($credits > 0) {
            app(GrantCreditsAction::class)->execute($business->id, $credits, CreditTransactionType::AdminGrant, 'test.seed');
        }

        return $business;
    }
}
