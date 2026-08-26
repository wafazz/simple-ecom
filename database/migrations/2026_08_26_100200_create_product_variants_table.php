<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-002 / REQ-008 — the purchasable unit. Planning §7.1, §12.2.
 *
 * The variant carries its own option labels rather than pointing at a global
 * option dictionary. That is the deliberate trade in Planning §7.1: it buys a
 * one-query product page and structural uniqueness, and it costs catalogue-wide
 * faceting. Spec §9 forbids an attribute engine, which settles it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();

            // Money is INT UNSIGNED in sen, suffixed _minor. PDO returns DECIMAL
            // as a string, so the first $price * $qty silently becomes a float
            // (Planning §12.1).
            $table->unsignedInteger('price_minor');

            $table->unsignedInteger('stock_qty')->default(0);

            // Required by the EasyParcel quotation API; the spec never mentions
            // product weight (OQ-01). A store-level default backs this up so a
            // quote can never be requested at zero weight.
            $table->unsignedInteger('weight_g')->default(0);

            $table->string('status', 20)->default('active');

            // MUST default to '' and MUST NOT be nullable. MySQL treats NULLs as
            // distinct in a unique index, so nullable columns would allow two
            // "no-option" variants on the same product (Planning §7.1).
            $table->string('option1_name', 50)->default('');
            $table->string('option1_value', 100)->default('');
            $table->string('option2_name', 50)->default('');
            $table->string('option2_value', 100)->default('');

            $table->timestamps();

            // The structural guarantee against duplicate combinations.
            $table->unique(
                ['product_id', 'option1_value', 'option2_value'],
                'product_variants_combination_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
