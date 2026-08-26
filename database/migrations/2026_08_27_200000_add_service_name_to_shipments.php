<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The human name of the courier service that was booked — REQ-013.
 *
 * `service_id` is an EasyParcel identifier ("EP-CS0W"); `courier_name` is the
 * carrier ("J&T"). Neither answers "which service did we buy?" on a listing.
 * The submit response does not reliably carry it — `courier_service` is null
 * in EasyParcel's own success example — so it is resolved from the quotation
 * at booking time and stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('service_name')->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('service_name');
        });
    }
};
