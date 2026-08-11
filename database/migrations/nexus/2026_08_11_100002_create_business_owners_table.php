<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Business owner's login credential — deliberately NOT a row in Core's
 * own `users` table. Core's User/UserRole are documented as
 * platform-level (Dashboard operators only, no tenant_id); giving a
 * Business owner a `users` row would either misuse that platform-level
 * concept or require extending UserRole with a Nexus-domain case inside
 * Core, both of which violate "Core must never depend on business
 * domains." This table backs its own `business` auth guard instead
 * (config/auth.php) — the same session-guard mechanism Core's `web`
 * guard already uses, just against a new, Nexus-owned identity table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_owners');
    }
};
