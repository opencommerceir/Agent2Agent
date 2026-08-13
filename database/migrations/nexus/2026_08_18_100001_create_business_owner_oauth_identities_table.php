<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_owner_oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_owner_id')->constrained('business_owners')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_owner_oauth_identities');
    }
};
