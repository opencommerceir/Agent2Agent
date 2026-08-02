<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * `BulkInventoryUpdate` wasn't in the request's own 5-case enum list
 * (§الف) but `BulkInventoryUpdateAction`/`commerce.bulk.update_inventory`
 * were both explicitly requested elsewhere in the same brief (§ب/§و) — a
 * BulkOperation tracking that Action's own progress needs a real case to
 * report, the same "add unprompted what the request's own other sections
 * already imply" reasoning every prior stage's own additions give
 * (HANDOFF §3 pattern #12).
 */
enum BulkOperationType: string
{
    case ImportProducts = 'import_products';
    case ImportCustomers = 'import_customers';
    case ExportOrders = 'export_orders';
    case BulkPriceUpdate = 'bulk_price_update';
    case BulkStatusUpdate = 'bulk_status_change';
    case BulkInventoryUpdate = 'bulk_inventory_update';
}
