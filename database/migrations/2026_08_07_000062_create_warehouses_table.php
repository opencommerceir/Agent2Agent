<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 2 (Multi-warehouse Inventory, §7.22). `latitude`/
 * `longitude` are included directly here rather than as a separate
 * "add_location_to_warehouses_table" migration — the request's own
 * schema section already lists them as columns on `warehouses` itself
 * (a redundant 5th migration in the request's own file list would have
 * altered a table this same PR creates one migration earlier, which is
 * never done anywhere else in this codebase; see HANDOFF §7.22).
 * `decimal(10,7)`/`decimal(10,7)` gives enough precision for
 * WarehouseDistanceCalculator's Haversine math (~1cm resolution).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
