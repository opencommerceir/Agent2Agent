<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Domain\Entities\Cart as CartEntity;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\CartStatus;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use App\Modules\Commerce\Infrastructure\Models\Cart as CartModel;
use App\Modules\Commerce\Infrastructure\Models\CartItem as CartItemModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Persists a Cart aggregate by replacing its items wholesale on every
 * save() — delete all `cart_items` rows for this cart, then re-insert
 * whatever the entity currently holds — rather than diffing old vs. new
 * rows. Carts are small (a handful of line items), so this trades a
 * little redundant I/O for not needing per-CartItem identity tracking
 * (Domain\Entities\CartItem's own docblock).
 *
 * Every read method eager-loads `items` (Phase 4 Stage 8, Performance
 * Optimization, §7.20) — toEntity() always reads $model->items;
 * findStaleActive() in particular fed a real N+1 (one query per stale
 * Cart on top of the one that found them) into the hourly
 * commerce:check-abandoned-carts scheduled command.
 */
class EloquentCartRepository implements CartRepositoryInterface
{
    public function findActiveByOwner(int $tenantId, MemberType $ownerType, int $ownerId): ?CartEntity
    {
        $model = CartModel::query()
            ->with('items')
            ->where('tenant_id', $tenantId)
            ->where('owner_type', $ownerType->value)
            ->where('owner_id', $ownerId)
            ->where('status', CartStatus::Active->value)
            ->latest('id')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findById(int $id, int $tenantId): ?CartEntity
    {
        $model = CartModel::query()->with('items')->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findStaleActive(int $tenantId, DateTimeImmutable $before): array
    {
        return CartModel::query()
            ->with('items')
            ->where('tenant_id', $tenantId)
            ->where('status', CartStatus::Active->value)
            ->where('updated_at', '<', $before)
            ->get()
            ->map(fn (CartModel $model) => $this->toEntity($model))
            ->all();
    }

    public function countCreatedBetween(int $tenantId, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return CartModel::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    public function save(CartEntity $cart): CartEntity
    {
        return DB::transaction(function () use ($cart) {
            $model = $cart->id()
                ? CartModel::query()->where('tenant_id', $cart->tenantId())->findOrFail($cart->id())
                : new CartModel();

            $model->tenant_id = $cart->tenantId();
            $model->owner_type = $cart->ownerType()->value;
            $model->owner_id = $cart->ownerId();
            $model->status = $cart->status()->value;
            $model->save();

            $model->items()->delete();

            foreach ($cart->items() as $item) {
                $model->items()->create([
                    'product_id' => $item->productId(),
                    'quantity' => $item->quantity()->value(),
                    'price_amount' => $item->unitPrice()->amount(),
                    'price_currency' => $item->unitPrice()->currency(),
                ]);
            }

            return $this->toEntity($model->fresh('items'));
        });
    }

    private function toEntity(CartModel $model): CartEntity
    {
        $items = $model->items->map(fn (CartItemModel $itemModel) => CartItem::create(
            productId: $itemModel->product_id,
            quantity: new Quantity($itemModel->quantity),
            unitPrice: Money::fromAmount($itemModel->price_amount, $itemModel->price_currency),
        ))->all();

        return new CartEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            ownerType: MemberType::from($model->owner_type),
            ownerId: $model->owner_id,
            status: CartStatus::from($model->status),
            items: $items,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
