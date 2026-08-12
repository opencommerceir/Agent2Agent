<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negotiation is this codebase's first genuinely cross-tenant aggregate —
 * every other table in the app is scoped to exactly one tenant_id. A deal
 * between two different Businesses' Agents is inherently shared between
 * two tenants, so both sides are stored explicitly (mirrors how `agents`
 * already stores both tenant_id AND organization_id explicitly rather
 * than joining) instead of forcing this into the single-tenant_id shape
 * every other repository in the app uses.
 *
 * catalog_item_id has no FK constraint — it polymorphically points at
 * either nexus_products or nexus_services (catalog_item_type), and a
 * single FK can't target either table conditionally (same reasoning
 * Core's own polymorphic member_type/member_id columns skip a hard FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiator_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('initiator_tenant_id');
            $table->foreignId('counterparty_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('counterparty_tenant_id');
            $table->string('catalog_item_type');
            $table->unsignedBigInteger('catalog_item_id');
            $table->string('status')->default('proposed');
            $table->json('current_terms');
            $table->unsignedInteger('round_count')->default(1);
            $table->unsignedInteger('max_rounds');
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiations');
    }
};
