<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-011 — admin-editable integration credentials.
 *
 * Separate from `settings` on purpose: that table is documented as NON-SECRET
 * and is read into a cache. Everything here is ciphertext and is never cached,
 * because caching a decrypted secret writes it to storage/framework/cache in
 * plaintext and defeats the encryption entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secure_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            // Text, not string: ciphertext is substantially longer than input.
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_settings');
    }
};
