<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete for orders, so an admin can clear a mistaken or abandoned order
 * off the list without destroying the record of it.
 *
 * ⚠ `orders.order_no` is UNIQUE, and a soft-deleted row KEEPS its number. That
 * is deliberate — a deleted order's number must never be handed to a different
 * order, or two records share an identity the customer has already been given.
 * CheckoutController::generateOrderNumber() therefore counts withTrashed():
 * counting only live rows would reuse the number of anything deleted today and
 * fail on the unique key for the rest of the day (Planning §12.4 notes the same
 * hazard for categories, which is why THAT table deactivates instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
