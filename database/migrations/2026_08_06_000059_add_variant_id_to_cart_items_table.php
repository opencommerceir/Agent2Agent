<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 1 (Product Variants, §7.21). Nullable, additive — a
 * CartItem for a Product with no variants is completely unaffected
 * (backward compatibility, per this stage's own explicit rule). Two
 * different variants of the same Product can now coexist as two separate
 * CartItem lines (Cart::findItem()'s own uniqueness check now matches on
 * (productId, variantId) together, not productId alone) — which means
 * the original `unique(cart_id, product_id)` constraint from this
 * table's own creation migration has to widen to
 * `unique(cart_id, product_id, variant_id)` too, or inserting a second
 * variant of the same Product would hit a raw DB constraint violation
 * instead of succeeding. Same NULL-is-distinct caveat
 * `add_variant_id_to_inventories_table` already documents — the real
 * safety net is `Cart::findItem()`'s own application-level match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id']);
            $table->unique(['cart_id', 'product_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id', 'variant_id']);
            $table->dropConstrainedForeignId('variant_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id']);
        });
    }
};
