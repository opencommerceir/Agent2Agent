<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 1 (Product Variants, §7.21). Deliberately **no
 * `stock_quantity` column here** — the request's own literal schema asked
 * for one, but that would have built a second, independent stock-tracking
 * mechanism alongside the existing `inventories` table's own two-phase
 * reserve/commit lifecycle (Inventory::reserve()/commit(), concurrency-safe
 * row locking). Confirmed with the user before writing any migration:
 * extend `inventories` with a nullable `variant_id` instead (this stage's
 * own alter migration on that table) — every ProductVariant's stock lives
 * in exactly the same place and goes through exactly the same
 * reserve/release/commit/restore machinery a plain Product's always has,
 * just with a real variant_id instead of null.
 *
 * `attributes` (JSON) is a denormalized snapshot of the combination this
 * variant represents (e.g. `{"Color": "Red", "Size": "L"}`) — the
 * queryable, human-readable copy; `sku` is generated from it
 * (VariantSKU::generate()). Like Product's own `attributes` bag, this is
 * a free-form JSON column with no registry-level validation that each
 * key/value actually matches a real VariantAttribute/VariantAttributeValue
 * row when set via CreateProductVariantAction directly (only
 * GenerateVariantCombinationsAction is registry-driven) — the same
 * documented looseness Shipping's own `weight_grams` convention already
 * has on `Product.attributes` (§8.34).
 *
 * SoftDeletes (`deleted_at`), mirroring `products` itself — a Product can
 * be soft-deleted while still referenced by historical Orders, and a
 * ProductVariant needs the identical guarantee for `order_items.variant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku');
            $table->unsignedBigInteger('price_amount');
            $table->string('price_currency', 3);
            $table->json('attributes');
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
