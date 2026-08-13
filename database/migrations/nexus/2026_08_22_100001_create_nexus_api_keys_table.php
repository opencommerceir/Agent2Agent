<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9/M1 — Public API credential a Business issues to authenticate
     * its own (or a third-party integration's) calls against the Public
     * REST API (M2), separate from the Agent-to-Agent AgentToken.
     */
    public function up(): void
    {
        Schema::create('nexus_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('key_hash', 64)->unique();
            // Shown in the portal list so a Business can tell keys apart
            // without the full plaintext ever being displayed again after
            // issue time — same non-secret-prefix convention GitHub/Stripe
            // API keys use.
            $table->string('key_prefix', 16);
            $table->string('label')->nullable();
            $table->json('scopes');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_api_keys');
    }
};
