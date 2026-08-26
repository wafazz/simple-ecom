<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-009 — admin authentication.
 *
 * Laravel's default users table, reused rather than a bespoke `admins` table:
 * spec §16 asks for Laravel's standard auth and no large auth ecosystem, and
 * this buys the guard, hashing and throttling for free.
 *
 * The skeleton's `password_reset_tokens` and `sessions` tables are deliberately
 * NOT created — single admin with no self-service reset, and SESSION_DRIVER=file
 * (Planning §12.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
