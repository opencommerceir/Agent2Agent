<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 1 (Product Variants, §7.21). Nullable, additive —
 * `order_items` never had a `unique(order_id, product_id)` constraint to
 * begin with (this table's own creation migration docblock: Order items
 * are a frozen historical record, no dedup invariant), so unlike
 * cart_items this is a pure addition with nothing else to widen.
 * OrderItem::fromCartItem() copies variantId straight through from the
 * CartItem it's freezing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
