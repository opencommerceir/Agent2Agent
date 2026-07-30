<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Customer as CustomerEntity;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Address;
use App\Modules\Commerce\Domain\ValueObjects\CustomerStatus;
use App\Modules\Commerce\Domain\ValueObjects\Email;
use App\Modules\Commerce\Infrastructure\Models\Customer as CustomerModel;
use DateTimeImmutable;

class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?CustomerEntity
    {
        $model = CustomerModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByEmail(Email $email, int $tenantId): ?CustomerEntity
    {
        $model = CustomerModel::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email->value())
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function emailExists(Email $email, int $tenantId): bool
    {
        return CustomerModel::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email->value())
            ->exists();
    }

    public function listByTenant(int $tenantId, ?CustomerStatus $status, int $limit): array
    {
        $builder = CustomerModel::query()->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (CustomerModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(CustomerEntity $customer): CustomerEntity
    {
        $model = $customer->id()
            ? CustomerModel::query()->where('tenant_id', $customer->tenantId())->findOrFail($customer->id())
            : new CustomerModel();

        $model->tenant_id = $customer->tenantId();
        $model->first_name = $customer->firstName();
        $model->last_name = $customer->lastName();
        $model->email = $customer->email()->value();
        $model->phone = $customer->phone();
        $model->status = $customer->status()->value;
        $model->default_address = $customer->defaultAddress()?->toArray();
        $model->notes = $customer->notes();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CustomerModel $model): CustomerEntity
    {
        return new CustomerEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: new Email($model->email),
            phone: $model->phone,
            status: CustomerStatus::from($model->status),
            defaultAddress: $model->default_address ? Address::fromArray($model->default_address) : null,
            notes: $model->notes,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
