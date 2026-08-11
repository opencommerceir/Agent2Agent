<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name_fa');
            $table->string('name_en');
            $table->unsignedBigInteger('price_amount');
            $table->string('price_currency', 3);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'name_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_services');
    }
};
