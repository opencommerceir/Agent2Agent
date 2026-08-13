<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9/M3 — a Business's outbound webhook subscriptions. Unlike
     * ApiKey (Phase 9/M1), `secret` must stay retrievable (not one-way
     * hashed) since DispatchWebhookEventAction needs the plaintext to
     * compute an HMAC signature on every delivery — stored via Eloquent's
     * `encrypted` cast instead (encrypted at rest, decryptable by the
     * application, never exposed again after creation on the portal side).
     */
    public function up(): void
    {
        Schema::create('nexus_webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('url');
            $table->text('secret');
            $table->json('events');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_webhook_subscriptions');
    }
};
