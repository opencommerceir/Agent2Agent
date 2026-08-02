<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 2 (Multi-warehouse Inventory, §7.22). `requested_by`/
 * `approved_by` are real foreign keys to `agents` — the same
 * `orders.agent_id` shape HANDOFF gotcha #8 warns about (a bare int like
 * `1` in a test will fail its FK constraint; use a real registered
 * Agent). `approved_by` is nullable (unset until Approve);
 * `completed_at` is nullable (unset until Complete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses');
            $table->foreignId('destination_warehouse_id')->constrained('warehouses');
            $table->string('status');
            $table->foreignId('requested_by')->constrained('agents');
            $table->foreignId('approved_by')->nullable()->constrained('agents');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfers');
    }
};
