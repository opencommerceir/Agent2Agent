<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No unique(order_id, product_id): unlike CartItem, an Order's items
     * are a frozen historical record written once (Immutable Order Items
     * rule) — nothing ever merges two lines for the same product after
     * the fact, so there is no dedup invariant to enforce here.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_amount');
            $table->string('unit_price_currency', 3);
            $table->unsignedBigInteger('total_price_amount');
            $table->string('total_price_currency', 3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
