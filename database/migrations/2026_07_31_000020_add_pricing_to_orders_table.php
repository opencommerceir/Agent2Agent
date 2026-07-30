<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * total_amount/total_currency already exist (Order Management,
     * Stage 3) — only tax_amount and discount_amount are new. Both
     * default to 0 so every existing Stage-3 Order row backfills
     * correctly with no tax/discount, matching its original placement
     * behavior exactly.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_amount')->default(0)->after('subtotal_currency');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'discount_amount']);
        });
    }
};
