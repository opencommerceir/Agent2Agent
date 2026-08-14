<?php

namespace App\Console\Commands;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\SendCounterOfferAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money as NegotiationMoney;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Console\Command;

/**
 * There is no UI button or autonomous background process that opens a
 * Negotiation between two arbitrary Businesses (confirmed: the only
 * autonomous path in this codebase is the opt-in RecurringOrder
 * automation rule, `nexus:process-automation-rules`, which only fires
 * for a pre-configured counterparty/item a Business explicitly set up —
 * never on registration/matching). A real Negotiation is meant to be
 * driven by an actual external AI Agent calling `nexus.negotiation.*`
 * over MCP with its own Bearer token — this codebase provides the rails
 * (reasoning traces, escrow, contracts), not a standing agent "brain."
 *
 * This command calls the exact same Application Actions those MCP
 * capabilities call (`InitiateNegotiationAction`/`SendCounterOfferAction`/
 * `AcceptDealAction`) directly, against a real existing supplier
 * Business's real catalog, so a propose -> counter -> accept round can be
 * watched live in the admin Negotiation Monitor
 * (/dashboard/nexus/negotiations) without needing a real external Agent
 * client wired up yet. Paces each step with a real sleep() so the
 * monitor's 3-second poll shows messages arriving one at a time, the same
 * as a real Agent conversation would, rather than all three appearing
 * instantly on page load.
 */
class DemoNegotiationCommand extends Command
{
    protected $signature = 'nexus:demo-negotiation
        {supplier : Business ID of the existing supplier (must be verified, with at least one product)}
        {--buyer-name-fa= : Persian name for the auto-created buyer Business}
        {--buyer-name-en= : English name for the auto-created buyer Business}
        {--product= : Product ID to negotiate over (defaults to the supplier\'s first product)}
        {--quantity=1 : Quantity to propose}';

    protected $description = 'Simulate a real Agent-to-Agent negotiation (propose -> counter -> accept) against a real supplier Business, for watching live in the admin Negotiation Monitor.';

    public function handle(
        BusinessRepositoryInterface $businesses,
        ProductRepositoryInterface $products,
        RegisterBusinessAction $registerBusiness,
        VerifyBusinessAction $verifyBusiness,
        GrantCreditsAction $grantCredits,
        GetCreditBalanceAction $getCreditBalance,
        InitiateNegotiationAction $initiateNegotiation,
        SendCounterOfferAction $sendCounterOffer,
        AcceptDealAction $acceptDeal,
    ): int {
        $supplierId = (int) $this->argument('supplier');
        $supplier = $businesses->findById($supplierId);

        if (! $supplier) {
            $this->error("Business [{$supplierId}] does not exist.");

            return self::FAILURE;
        }

        if (! $supplier->isVerified()) {
            $this->error("Business [{$supplierId}] ({$supplier->nameEn()}) is not verified yet — verify it first at /dashboard/nexus/verification.");

            return self::FAILURE;
        }

        $productId = $this->option('product') ? (int) $this->option('product') : null;
        $product = $productId ? $products->findById($productId) : ($products->findByBusinessId($supplierId)[0] ?? null);

        if (! $product || $product->businessId() !== $supplierId) {
            $this->error("No usable product found for Business [{$supplierId}] — add one first at /nexus/catalog.");

            return self::FAILURE;
        }

        $buyerNameFa = $this->option('buyer-name-fa') ?? 'شرکت خریدار دمو';
        $buyerNameEn = $this->option('buyer-name-en') ?? 'Demo Buyer Co';

        $this->info("Registering buyer Business \"{$buyerNameEn}\"...");
        $buyer = $registerBusiness->execute($buyerNameFa, $buyerNameEn, BusinessType::Company, $supplier->industry());
        $verifyBusiness->execute($buyer->id);
        $grantCredits->execute($buyer->id, 100_000, CreditTransactionType::AdminGrant, 'nexus:demo-negotiation seed');

        if ($getCreditBalance->execute($supplierId)->balance < 1000) {
            $grantCredits->execute($supplierId, 100_000, CreditTransactionType::AdminGrant, 'nexus:demo-negotiation seed');
        }

        $quantity = (int) $this->option('quantity');
        $currency = $product->price()->currency();
        $askPrice = $product->price()->amount();
        $offerPrice = (int) round($askPrice * 0.85);

        $this->info("Buyer proposing {$offerPrice} {$currency} x {$quantity} for \"{$product->nameEn()}\" (list price {$askPrice} {$currency})...");
        $negotiation = $initiateNegotiation->execute(
            $buyer->id,
            $supplierId,
            CatalogItemType::Product,
            $product->id(),
            new NegotiationTerms(NegotiationMoney::fromAmount($offerPrice, $currency), $quantity, 'Auto-demo proposal'),
        );

        $this->line("  -> negotiation #{$negotiation->id} opened. Watch it live: /dashboard/nexus/negotiations/{$negotiation->id}");
        $this->pause(4);

        $counterPrice = (int) round(($askPrice + $offerPrice) / 2);
        $this->info("Supplier countering at {$counterPrice} {$currency}...");
        $sendCounterOffer->execute(
            $negotiation->id,
            $supplierId,
            new NegotiationTerms(NegotiationMoney::fromAmount($counterPrice, $currency), $quantity, 'Auto-demo counter'),
        );
        $this->pause(4);

        $this->info('Buyer accepting the deal...');
        $accepted = $acceptDeal->execute($negotiation->id, $buyer->id);

        $this->newLine();
        $this->info("Done. Negotiation #{$accepted->id} is now [{$accepted->status}].");
        $this->line("View it: /dashboard/nexus/negotiations/{$accepted->id}");

        return self::SUCCESS;
    }

    private function pause(int $seconds): void
    {
        $this->line('  (waiting so the admin monitor\'s live poll picks this up...)');

        // Skipped under the test suite — the pacing is a demo/UX nicety
        // for a human watching the admin monitor live, not something any
        // test asserts on, and it would otherwise add ~8s to every run.
        if (! app()->runningUnitTests()) {
            sleep($seconds);
        }
    }
}
