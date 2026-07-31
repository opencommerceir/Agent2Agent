<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * rate_percentage is the percentage times 100 (9.00% -> 900) — Money
     * as Integer, applied to a rate instead of an amount, so no
     * float-typed tax value ever exists (HANDOFF gotcha #4 territory).
     * region "DEFAULT" is a reserved, documented value (TaxRegion's own
     * docblock) — a tenant's fallback rate when no rate is configured for
     * a more specific region.
     */
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('region');
            $table->unsignedInteger('rate_percentage');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'region']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
