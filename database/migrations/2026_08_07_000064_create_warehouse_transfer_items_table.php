<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 2 (Multi-warehouse Inventory, §7.22). No `tenant_id` of
 * its own (inherited through `transfer_id`, the same shape
 * `order_items`/`ticket_comments`/`workflow_rules` already have) and only
 * `created_at` (no `updated_at`) — a WarehouseTransferItem is frozen at
 * creation, never edited (WarehouseTransfer's own docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
    }
};
