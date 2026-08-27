<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home page banner slides, maintained by the admin.
 *
 * The artwork and the wording are SEPARATE columns rather than one flattened
 * picture. Text baked into an image cannot reflow, is cropped away on a phone
 * and is invisible to a search engine — so the image is the backdrop and the
 * words stay words.
 *
 * `image_path` is nullable on purpose: a slide with only wording is the
 * type-led hero this shop started with, and it stays a valid slide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->id();

            // Relative to the `uploads` disk. Public by design — unlike an AWB,
            // a shop banner is meant to be fetched by anyone.
            $table->string('image_path')->nullable();

            // Where to hold the picture when it is cropped: the wording sits on
            // the left, so a subject on the right survives a narrow screen.
            $table->string('focal', 16)->default('center');

            $table->string('eyebrow', 80)->nullable();
            $table->string('headline', 120);
            $table->string('subtext', 300)->nullable();

            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta2_label', 40)->nullable();
            $table->string('cta2_url')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The storefront reads exactly this: active slides in order.
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides');
    }
};
