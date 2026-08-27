<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was actually printed on this line, captured at order time.
 *
 * A snapshot like product_name and unit_price_minor beside it: turning the
 * product's nameset off later, or changing its price, must not rewrite what a
 * customer already bought.
 *
 * nameset_price_minor is the per-shirt fee. `unit_price_minor` stays the
 * garment alone, so the two remain separable on an invoice —
 * line_total_minor is (unit + nameset) x qty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('nameset_name', 20)->nullable()->after('variation_label');
            $table->string('nameset_number', 3)->nullable()->after('nameset_name');
            $table->unsignedInteger('nameset_price_minor')->default(0)->after('nameset_number');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['nameset_name', 'nameset_number', 'nameset_price_minor']);
        });
    }
};
