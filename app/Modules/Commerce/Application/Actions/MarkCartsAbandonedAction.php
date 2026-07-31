<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Events\CartWasAbandoned;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

/**
 * The per-tenant unit the scheduled `commerce:check-abandoned-carts`
 * command (HANDOFF §8.23/§8.27) iterates over. Finds every Cart idle past
 * $hoursIdle (CartRepositoryInterface::findStaleActive(), added alongside
 * this Action, using the `updated_at` timestamp Eloquent already tracks),
 * transitions each through Cart::abandon() (an entity mutator that
 * existed since Phase 3 but nothing ever called until now), and dispatches
 * CartWasAbandoned per cart — the event Workflows' CartAbandonedListener
 * has been waiting on.
 */
final class MarkCartsAbandonedAction
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
    ) {
    }

    public function execute(int $tenantId, int $hoursIdle = 24): int
    {
        $threshold = (new DateTimeImmutable())->modify("-{$hoursIdle} hours");
        $staleCarts = $this->carts->findStaleActive($tenantId, $threshold);

        foreach ($staleCarts as $cart) {
            $cart->abandon();
            $this->carts->save($cart);

            Event::dispatch(new CartWasAbandoned($tenantId, $cart->id(), $cart->ownerType(), $cart->ownerId()));
        }

        return count($staleCarts);
    }
}
