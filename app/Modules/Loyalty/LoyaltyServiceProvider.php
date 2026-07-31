<?php

namespace App\Modules\Loyalty;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Commerce\Domain\Events\OrderWasPlaced;
use App\Modules\Loyalty\Application\Actions\CreateLoyaltyAccountAction;
use App\Modules\Loyalty\Application\Actions\CreateRewardAction;
use App\Modules\Loyalty\Application\Actions\EarnPointsAction;
use App\Modules\Loyalty\Application\Actions\GetLoyaltyAccountAction;
use App\Modules\Loyalty\Application\Actions\GetPointTransactionsAction;
use App\Modules\Loyalty\Application\Actions\GetRewardAction;
use App\Modules\Loyalty\Application\Actions\ListRewardsAction;
use App\Modules\Loyalty\Application\Actions\RedeemPointsAction;
use App\Modules\Loyalty\Application\DTOs\LoyaltyAccountData;
use App\Modules\Loyalty\Application\Listeners\OrderPlacedListener;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;
use App\Modules\Loyalty\Domain\Repositories\RewardRepositoryInterface;
use App\Modules\Loyalty\Infrastructure\Repositories\EloquentLoyaltyAccountRepository;
use App\Modules\Loyalty\Infrastructure\Repositories\EloquentPointTransactionRepository;
use App\Modules\Loyalty\Infrastructure\Repositories\EloquentRewardRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Loyalty module — Phase 3, Stage 4, built on Phase 1/2's
 * infrastructure and Phase 3's own established Module -> Module pattern
 * without changing Commerce/CRM/Finance/Workflows at all (unlike
 * Finance's Stage, this stage needed no new event on Commerce —
 * `OrderWasPlaced` already existed and already carried everything
 * needed, see OrderPlacedListener's own docblock).
 *
 * `OrderPlacedListener` is the platform's second real cross-module
 * Domain Event Listener (after Workflows' InventoryLowListener) — the
 * second `Event::listen()` call to ever exist in this codebase.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows the
 * established seeder pattern instead (LoyaltyCapabilitiesSeeder), same
 * RefreshDatabase-ordering reason documented there.
 */
class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoyaltyAccountRepositoryInterface::class, EloquentLoyaltyAccountRepository::class);
        $this->app->bind(PointTransactionRepositoryInterface::class, EloquentPointTransactionRepository::class);
        $this->app->bind(RewardRepositoryInterface::class, EloquentRewardRepository::class);
    }

    public function boot(): void
    {
        Event::listen(OrderWasPlaced::class, OrderPlacedListener::class);

        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('loyalty.account.get', function (array $input, AuthContext $context) {
            /** @var LoyaltyAccountData $account */
            $account = $this->app->make(GetLoyaltyAccountAction::class)->execute($context->tenantId, (int) $input['customer_id']);

            return ['account' => $account->toArray()];
        });

        $handlers->register('loyalty.account.create', function (array $input, AuthContext $context) {
            /** @var LoyaltyAccountData $account */
            $account = $this->app->make(CreateLoyaltyAccountAction::class)->execute($context->tenantId, (int) $input['customer_id']);

            return ['account' => $account->toArray()];
        });

        $handlers->register(
            'loyalty.points.earn',
            fn (array $input, AuthContext $context) => $this->app->make(EarnPointsAction::class)->execute(
                tenantId: $context->tenantId,
                customerId: (int) $input['customer_id'],
                points: (int) $input['points'],
                description: $input['description'] ?? null,
                referenceId: isset($input['reference_id']) ? (int) $input['reference_id'] : null,
            ),
        );

        $handlers->register(
            'loyalty.points.redeem',
            fn (array $input, AuthContext $context) => $this->app->make(RedeemPointsAction::class)->execute(
                tenantId: $context->tenantId,
                customerId: (int) $input['customer_id'],
                points: (int) $input['points'],
                rewardId: (int) $input['reward_id'],
            ),
        );

        $handlers->register('loyalty.reward.create', function (array $input, AuthContext $context) {
            $reward = $this->app->make(CreateRewardAction::class)->execute(
                tenantId: $context->tenantId,
                name: $input['name'],
                rewardType: $input['reward_type'],
                pointsRequired: (int) $input['points_required'],
                discountAmount: isset($input['discount_amount']) ? (int) $input['discount_amount'] : null,
                description: $input['description'] ?? null,
            );

            return ['reward' => $reward->toArray()];
        });

        $handlers->register('loyalty.reward.get', function (array $input, AuthContext $context) {
            $reward = $this->app->make(GetRewardAction::class)->execute((int) $input['reward_id'], $context->tenantId);

            return ['reward' => $reward->toArray()];
        });

        $handlers->register(
            'loyalty.reward.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListRewardsAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register(
            'loyalty.transaction.list',
            fn (array $input, AuthContext $context) => $this->app->make(GetPointTransactionsAction::class)->execute(
                tenantId: $context->tenantId,
                customerId: (int) $input['customer_id'],
                limit: isset($input['limit']) ? (int) $input['limit'] : null,
            ),
        );
    }
}
