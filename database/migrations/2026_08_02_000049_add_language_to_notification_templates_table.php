<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 Stage 4 (i18n) — lets a tenant register more than one
 * NotificationTemplate per type+channel, one per Language, rather than
 * restructuring the existing one-row-per-template shape into a nested
 * translations blob. Additive and backward-compatible (HANDOFF §3 pattern
 * #6): every existing row gets 'en' via the column default, so a tenant
 * that never configures a second language sees no behavior change at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('language', 2)->default('en')->after('channel_type');
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
