<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery images for a product — REQ-001.
 *
 * `products.image_path` is deliberately left alone and keeps its meaning: the
 * cover, the one image used in listings, the cart and the order screens. This
 * table holds the ADDITIONAL views shown on the product page.
 *
 * Splitting it that way means no data migration, no backfill, and no window
 * where an existing product loses its picture — the cover already works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Relative to the `uploads` disk, exactly like products.image_path.
            $table->string('path');

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // The gallery is always read for one product, in display order.
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
