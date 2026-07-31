<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No unique(invoice_id, ...): like order_items, an Invoice's items
     * are a frozen historical record written once (Immutable Order Items
     * rule, mirrored here). No updated_at — items are immutable
     * (InvoiceItem Model's own docblock).
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_amount');
            $table->string('unit_price_currency', 3);
            $table->unsignedBigInteger('total_amount');
            $table->string('total_currency', 3);
            $table->timestamp('created_at')->useCurrent();

            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
