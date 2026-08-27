<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual fulfilment — the AWB the admin uploads by hand.
 *
 * Separate from `label_url`, which holds an absolute URL on EasyParcel's own
 * domain and is rendered straight into an href. This one is a path on a PRIVATE
 * disk: an airway bill carries the customer's name, full address and phone, so
 * it is never served from public/, and is streamed through an authenticated
 * admin route instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('label_path')->nullable()->after('label_url');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('label_path');
        });
    }
};
