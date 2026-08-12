<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_coalitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('target_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('catalog_item_type', 16);
            $table->unsignedBigInteger('catalog_item_id');
            $table->unsignedBigInteger('unit_price_amount');
            $table->string('unit_price_currency', 3);
            $table->unsignedInteger('min_participants');
            $table->decimal('discount_percent', 5, 2);
            $table->string('status', 16)->default('forming');
            // Nullable until close() — the real Negotiation this coalition's
            // bulk order becomes. Real FK, same convention
            // nexus_contracts.negotiation_id already uses.
            $table->foreignId('negotiation_id')->nullable()->constrained('negotiations')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_coalitions');
    }
};
