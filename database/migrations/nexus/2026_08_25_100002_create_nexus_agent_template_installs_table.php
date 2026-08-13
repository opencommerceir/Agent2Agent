<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9/M7 — an immutable ledger of every real (cross-business, paid)
     * template install, same append-only shape CreditTransaction/
     * WebhookDeliveryLog already established. A publisher installing their
     * own template (free, no revenue-share — see InstallAgentStrategyTemplateAction)
     * still gets a row here, with priceCredits/platformFeeCredits/
     * publisherEarningsCredits all zero, so a publisher's own install
     * history stays complete.
     */
    public function up(): void
    {
        Schema::create('nexus_agent_template_installs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('nexus_agent_strategy_templates')->cascadeOnDelete();
            $table->foreignId('installing_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('publisher_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedInteger('price_credits');
            $table->unsignedInteger('platform_fee_credits');
            $table->unsignedInteger('publisher_earnings_credits');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_agent_template_installs');
    }
};
