<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9/M7 — Agent Developer Platform. A publishable, installable
     * Agent personality/tone/strategies preset — see
     * AgentStrategyTemplate's own docblock for why a marketplace of these
     * (rather than a marketplace of literal third-party Agent processes)
     * is the honest scope for "third-party Agent marketplace" in this
     * codebase.
     */
    public function up(): void
    {
        Schema::create('nexus_agent_strategy_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name_fa');
            $table->string('name_en');
            $table->text('description_fa');
            $table->text('description_en');
            $table->string('personality')->nullable();
            $table->string('tone')->nullable();
            $table->json('strategies');
            $table->unsignedInteger('price_credits');
            $table->unsignedInteger('install_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_agent_strategy_templates');
    }
};
