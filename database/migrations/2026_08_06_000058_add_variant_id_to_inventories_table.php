<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 1 (Product Variants, §7.21) — see
 * `Inventory` entity's own docblock and this stage's
 * `create_product_variants_table` migration for the full reasoning: this
 * is the one place variant-level stock actually lives, reusing the
 * existing reserve/commit lifecycle rather than building a second one.
 *
 * `nullOnDelete()` rather than cascade: a variant is soft-deleted in
 * normal operation (ProductVariant mirrors Product's own SoftDeletes), so
 * this FK action only matters for a genuine hard delete, which the app
 * itself never performs — chosen for safety over cascade regardless.
 *
 * The old `unique(tenant_id, product_id)` constraint is replaced with
 * `unique(tenant_id, product_id, variant_id)`. Known, accepted gap: MySQL
 * (and SQLite) treat every NULL as distinct in a unique index, so this
 * does **not** by itself prevent two parent-level rows (variant_id NULL)
 * for the same product — the real safety net is
 * `EloquentInventoryRepository::save()`'s own find-or-new lookup (already
 * queries by the full tuple before inserting), the same
 * application-level uniqueness enforcement this codebase already uses
 * elsewhere (e.g. `orderNumberExists()`/`invoiceNumberExists()` checks
 * before insert, not a bare DB constraint alone).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventories_tenant_id_product_id_unique');
            $table->unique(['tenant_id', 'product_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'product_id', 'variant_id']);
            $table->dropConstrainedForeignId('variant_id');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->unique(['tenant_id', 'product_id']);
        });
    }
};
