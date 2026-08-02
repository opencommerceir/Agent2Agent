<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 2 (Multi-warehouse Inventory, §7.22) — mirrors
 * `add_variant_id_to_inventories_table` (Phase 5, Stage 1, §7.21)
 * exactly, the same "widen with an optional trailing FK" shape: null
 * means this row tracks the tenant's own default (non-warehouse-scoped)
 * stock, exactly as every row did before this stage — full backward
 * compatibility for AddToCartAction/PlaceOrderAction/CheckInventoryAction,
 * none of which pass a warehouse_id this stage.
 *
 * `nullOnDelete()` rather than cascade, same reasoning as variant_id's
 * own migration — a Warehouse is deactivated in normal operation, never
 * hard-deleted by the app itself.
 *
 * The old `unique(tenant_id, product_id, variant_id)` constraint widens
 * to `unique(tenant_id, product_id, variant_id, warehouse_id)`. Same
 * documented, accepted NULL-is-distinct gap the variant_id migration
 * already carries — EloquentInventoryRepository::save()'s own
 * find-or-new lookup (already queries the full 4-column tuple) remains
 * the real safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('variant_id')
                ->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'product_id', 'variant_id']);
            $table->unique(['tenant_id', 'product_id', 'variant_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'product_id', 'variant_id', 'warehouse_id']);
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->unique(['tenant_id', 'product_id', 'variant_id']);
        });
    }
};
