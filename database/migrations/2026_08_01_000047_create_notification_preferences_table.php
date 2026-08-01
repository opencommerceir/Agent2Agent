<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No FK on `recipient_id` — it's polymorphic (a Customer id when
     * `recipient_type` is `customer`, an Agent id when `agent`), the same
     * `member_roles.member_id`/`organization_members.member_id`
     * polymorphic-no-FK shape Core already established for
     * `MemberType`-discriminated ids.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->string('notification_type');
            $table->string('channel_type');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'recipient_type', 'recipient_id', 'notification_type', 'channel_type'],
                'notification_preferences_unique_key',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
