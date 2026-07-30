<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * owner_type/owner_id is the same polymorphic pattern
     * organization_members already uses for member_type/member_id — no
     * DB-level uniqueness on (tenant_id, owner_type, owner_id, status):
     * "at most one Active cart per owner" is an application-level
     * invariant (CartRepositoryInterface::findActiveByOwner + the
     * get-or-create in AddToCartAction/GetCartAction), not a schema one,
     * since an owner may accumulate multiple checked_out/abandoned carts
     * over time.
     */
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'owner_type', 'owner_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
