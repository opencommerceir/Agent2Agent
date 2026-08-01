<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No FK on `template_id` into `notification_templates` — a
     * Notification must remain a durable record of what was actually
     * sent even if its originating Template is later changed/removed,
     * the same "history survives its source" reasoning
     * `report_results`/`workflow_logs` already establish. Only
     * `created_at` — a sent Notification is never edited, only
     * transitioned via `sent_at`/`delivered_at`/`failed_at` (each
     * nullable, set at most once).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type');
            $table->string('channel_type');
            $table->string('recipient');
            $table->string('subject');
            $table->text('body');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
