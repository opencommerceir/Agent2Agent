<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_private_marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_marketplace_id')->constrained('nexus_private_marketplaces')->cascadeOnDelete();
            $table->foreignId('listing_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('catalog_item_type', 16);
            $table->unsignedBigInteger('catalog_item_id');
            $table->unsignedBigInteger('special_price_amount');
            $table->string('special_price_currency', 3);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_private_marketplace_listings');
    }
};
