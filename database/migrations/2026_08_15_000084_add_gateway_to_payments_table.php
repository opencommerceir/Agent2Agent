<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive, nullable (§7.37, HANDOFF §3 pattern #6) — every existing
     * row (all charged through MockPaymentGateway before this stage)
     * simply reads back as null; ProcessPaymentAction's own call site
     * now passes 'mock' explicitly going forward, purely cosmetic.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gateway');
        });
    }
};
