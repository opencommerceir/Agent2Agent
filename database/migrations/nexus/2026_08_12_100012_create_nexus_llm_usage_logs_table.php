<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The LLM cost/usage ledger — immutable audit trail of every provider
     * call attempt (CLAUDE.md's "هر LLM call را با cost دقیق ثبت کن"), same
     * shape nexus_credit_transactions already documents for an immutable
     * log table. business_id/agent_id are both nullable: admin
     * "test connection" pings have neither (see LLMUsageLog's own
     * docblock).
     */
    public function up(): void
    {
        Schema::create('nexus_llm_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('feature');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('prompt_tokens');
            $table->unsignedInteger('completion_tokens');
            $table->unsignedInteger('total_tokens');
            $table->decimal('real_cost_usd', 12, 6);
            $table->decimal('charged_cost_usd', 12, 6);
            $table->decimal('margin_usd', 12, 6);
            $table->unsignedInteger('latency_ms');
            $table->boolean('from_fallback')->default(false);
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_llm_usage_logs');
    }
};
