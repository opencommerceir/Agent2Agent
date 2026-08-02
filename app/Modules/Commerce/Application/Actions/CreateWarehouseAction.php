<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseData;
use App\Modules\Commerce\Domain\Entities\Warehouse;
use App\Modules\Commerce\Domain\Events\WarehouseWasCreated;
use App\Modules\Commerce\Domain\Exceptions\DuplicateWarehouseCodeException;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseCode;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
use Illuminate\Support\Facades\Event;

final class CreateWarehouseAction
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
    ) {
    }

    public function execute(
        int $tenantId,
        string $code,
        string $name,
        float $latitude,
        float $longitude,
        string $address,
    ): WarehouseData {
        $warehouseCode = new WarehouseCode($code);

        if ($this->warehouses->codeExists($warehouseCode, $tenantId)) {
            throw new DuplicateWarehouseCodeException("Warehouse code [{$warehouseCode->value()}] already exists for this tenant.");
        }

        $warehouse = Warehouse::create(
            tenantId: $tenantId,
            code: $warehouseCode,
            name: $name,
            location: new WarehouseLocation($latitude, $longitude, $address),
        );
        $warehouse = $this->warehouses->save($warehouse);

        Event::dispatch(new WarehouseWasCreated($warehouse));

        return WarehouseData::fromEntity($warehouse);
    }
}
