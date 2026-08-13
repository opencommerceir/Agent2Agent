<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9/M3 — an immutable delivery-attempt ledger, the same
     * append-only shape CreditTransaction/LLMUsageLog already established
     * (no `updated_at`, `created_at->useCurrent()`): one row per attempted
     * HTTP delivery, success or failure, so a developer's portal view can
     * show exactly what Nexus tried to send and what happened, without
     * relying on the receiving server's own logs.
     */
    public function up(): void
    {
        Schema::create('nexus_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('nexus_webhook_subscriptions')->cascadeOnDelete();
            $table->string('event');
            $table->string('url');
            $table->boolean('succeeded');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_webhook_deliveries');
    }
};
