<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * order_id/customer_id both use the default (no cascade/nullOnDelete)
     * FK behavior — an Invoice is a permanent financial record, the same
     * "don't lose the audit trail" reasoning orders.agent_id and
     * tickets.customer_id already give. customer_id is nullable because
     * Commerce's own orders.customer_id is nullable — an Order placed
     * without a linked Customer can still be invoiced.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('invoice_number');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('subtotal_amount');
            $table->string('subtotal_currency', 3);
            $table->unsignedBigInteger('tax_amount');
            $table->string('tax_currency', 3);
            $table->unsignedBigInteger('total_amount');
            $table->string('total_currency', 3);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
