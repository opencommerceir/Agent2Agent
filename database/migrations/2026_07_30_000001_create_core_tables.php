<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('pending');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('tenant_id')->constrained('organizations')->nullOnDelete();
            $table->string('status')->default('active')->after('password');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('token_hash')->unique();
            $table->json('permissions')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['tenant_id', 'organization_id', 'status']);
        });

        Schema::dropIfExists('organizations');
        Schema::dropIfExists('tenants');
    }
};
