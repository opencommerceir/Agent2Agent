<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 Stage 2 (Shipping Provider Connector). Two new nullable
     * columns — the external provider's own name/tracking number/reference
     * — deliberately separate from this table's existing `tracking_number`
     * (Shipping's own internal `TRK-XXXXXXXX` reference, generated at
     * CreateShipmentAction time). No FK, no format constraint on
     * `provider_tracking_number`: a real provider's tracking number format
     * is unknown/unconstrained (Shipment::assignProviderTracking()'s own
     * docblock has the full reasoning).
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('provider_name')->nullable()->after('delivered_at');
            $table->string('provider_tracking_number')->nullable()->after('provider_name');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['provider_name', 'provider_tracking_number']);
        });
    }
};
