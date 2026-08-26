<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move existing orders onto the client's operational status vocabulary.
 *
 * `order_status` is a VARCHAR, so this is a data remap rather than a schema
 * change — but it MUST run, or historical orders keep values the enum can no
 * longer cast and every read of them throws.
 */
return new class extends Migration
{
    private const RENAMES = [
        'pending_payment' => 'pending',
        'paid' => 'new_order',
        'shipped' => 'in_delivery',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('orders')->where('order_status', $from)->update(['order_status' => $to]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_status', 32)->default('pending')->change();
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::RENAMES, true) as $from => $to) {
            DB::table('orders')->where('order_status', $to)->update(['order_status' => $from]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_status', 32)->default('pending_payment')->change();
        });
    }
};
