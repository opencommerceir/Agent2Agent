<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_private_marketplaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name_fa');
            $table->string('name_en');
            // "Custom branding" (Phase 7 roadmap) interpreted narrowly: one
            // accent color plus the owner's existing Business logo — no
            // theming engine anywhere in this codebase to extend.
            $table->string('branding_primary_color', 7)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_private_marketplaces');
    }
};
