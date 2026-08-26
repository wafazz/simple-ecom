<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-013 — EasyParcel's submit_orders requires parcel and item dimensions.
 *
 * Stored in MILLIMETRES as integers, for the same reason money is stored in sen:
 * the API wants `double(8,2)` centimetres, and holding that as a float invites
 * the rounding class of bug. Converted to cm once, at the request boundary.
 *
 * 0 means "not set" and the store default applies — a parcel can never be
 * submitted at zero size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('length_mm')->default(0)->after('weight_g');
            $table->unsignedInteger('width_mm')->default(0)->after('length_mm');
            $table->unsignedInteger('height_mm')->default(0)->after('width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['length_mm', 'width_mm', 'height_mm']);
        });
    }
};
