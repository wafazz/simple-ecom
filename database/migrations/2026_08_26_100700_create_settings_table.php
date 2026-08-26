<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-011 — store configuration. Planning §12.2.
 *
 * NON-SECRET ONLY. API credentials live in .env and are surfaced through
 * config/services.php (spec §16, §31). The admin Settings screen shows a
 * Configured / Not configured badge, never a credential value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
