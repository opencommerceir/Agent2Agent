<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_private_marketplace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_marketplace_id')->constrained('nexus_private_marketplaces')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('status', 16)->default('invited');
            $table->timestamp('invited_at');

            // Unlike Holding subsidiary membership, a Business CAN belong
            // to many Private Marketplaces at once — only one row per
            // (marketplace, business) pair is enforced, not a blanket
            // unique on business_id.
            $table->unique(['private_marketplace_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_private_marketplace_members');
    }
};
