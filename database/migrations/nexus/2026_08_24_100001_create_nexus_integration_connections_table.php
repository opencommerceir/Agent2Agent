<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9/M6 — Integration Marketplace. A Business's own configured
     * outbound sync target (their ERP/CRM/Accounting/Logistics system, or
     * anything else that accepts a JSON POST) — see IntegrationConnection's
     * own docblock for why this is one generic, category-tagged connector
     * shape rather than named vendor-specific integrations.
     */
    public function up(): void
    {
        Schema::create('nexus_integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('category');
            $table->string('name');
            $table->string('target_url');
            $table->text('auth_token')->nullable();
            $table->json('field_mapping');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_integration_connections');
    }
};
