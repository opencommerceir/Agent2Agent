<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No `tenant_id` column — tenant isolation is inherited through
     * `shipment_id`, the same shape `order_items`/`ticket_comments`/
     * `workflow_rules` already have relative to their own parent. No
     * `updated_at` — a TrackingEvent is an append-only audit log entry,
     * written once and never edited (same immutable-child-record shape
     * every other "history" table in this codebase has).
     */
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status');
            $table->string('location')->nullable();
            $table->string('description');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('shipment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
