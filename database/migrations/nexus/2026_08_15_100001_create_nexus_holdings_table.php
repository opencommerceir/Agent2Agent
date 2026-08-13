<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_holdings', function (Blueprint $table) {
            $table->id();
            // One Holding per parent Business — a Business cannot administer
            // more than one Holding (HoldingRepositoryInterface's own
            // docblock).
            $table->foreignId('parent_business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('name_fa');
            $table->string('name_en');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_holdings');
    }
};
