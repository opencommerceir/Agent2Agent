<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 1 (Product Variants, §7.21). A denormalized convenience
 * flag, not the source of truth for "does this Product have variants" —
 * that fact is always derivable from whether `product_variants` has any
 * row for this `product_id`. Kept anyway (matching the literal request)
 * because it lets `AddToCartAction`/the Dashboard decide "does this
 * Product require a variant_id" with a plain column read instead of an
 * EXISTS query on every add-to-cart call. Maintained by
 * `CreateProductVariantAction` (set true on that Product's first
 * variant) — nothing currently reverts it to false if every variant is
 * later deleted, a known, minor drift risk the same shape
 * `KPIValue.value_currency` doubling as a unit tag already accepted
 * (§7.18) rather than adding a table/trigger just to keep one boolean
 * perfectly in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_parent')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_parent');
        });
    }
};
