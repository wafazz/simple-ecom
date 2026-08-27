<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nameset — printing a name and number on a jersey, for an extra fee.
 *
 * A per-PRODUCT switch and price, not a variant option: the print costs the
 * same whichever size is bought, and modelling it as a variation would multiply
 * every size by "with" and "without" for no gain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('nameset_enabled')->default(false)->after('is_active');
            // Sen, like every other amount here. Charged PER SHIRT, so two of
            // the same jersey are two prints and two fees.
            $table->unsignedInteger('nameset_price_minor')->default(0)->after('nameset_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['nameset_enabled', 'nameset_price_minor']);
        });
    }
};
