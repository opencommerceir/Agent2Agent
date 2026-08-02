<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 3 (Bulk Operations, §7.23). No `tenant_id` of its own —
 * inherited through `bulk_operation_id`, the same shape `order_items`/
 * `warehouse_transfer_items` already have. Only `processed_at` (no
 * `created_at`/`updated_at`) — a row is written exactly once, the instant
 * it's processed, by `BulkOperationRepositoryInterface::saveItem()`; there
 * is no separate "created" moment to distinguish it from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_operation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_operation_id')->constrained('bulk_operations')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->index(['bulk_operation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_operation_items');
    }
};
