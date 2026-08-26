<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-006 — EasyParcel OAuth tokens. Planning §11.B.3, §12.2.
 *
 * The ONLY table holding credentials. It exists because the refresh token
 * rotates at runtime and therefore cannot live in .env — and after
 * `config:cache`, .env is not read at all.
 *
 * Both token columns use the Eloquent `encrypted` cast under AES-256-GCM
 * (config/app.php). Columns are text, not string: ciphertext is substantially
 * longer than the plaintext token.
 *
 * NOT created if the EasyParcel account turns out to be on the legacy Connect
 * API, which takes a flat key (OQ-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_tokens');
    }
};
