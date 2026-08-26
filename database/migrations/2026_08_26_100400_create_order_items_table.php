<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-004 — immutable purchase-time snapshot. Planning §9.3, §12.2.
 *
 * product_name, variation_label, sku and unit_price_minor are COPIED here at
 * order creation. A later catalogue edit or price change must never rewrite a
 * historical order. The FK to product_variants is restrictOnDelete for the same
 * reason: history outlives the catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();

            $table->string('product_name');
            $table->string('variation_label')->default('');
            $table->string('sku', 64);
            $table->unsignedInteger('unit_price_minor');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('line_total_minor');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
