<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-004 / REQ-007 — one row per checkout. Planning §9, §12.2. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ORD-YYYYMMDD-NNNN. This unique key is the real guard against a
            // collision — never SELECT MAX() (Planning §9.3).
            $table->string('order_no', 32)->unique();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 32);

            $table->string('address_line');
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postcode', 16);
            $table->string('country', 2)->default('MY');

            // All computed server-side from DB values. Posted amounts are
            // ignored (spec §17, Planning §9.3).
            $table->unsignedInteger('subtotal_minor');
            $table->unsignedInteger('shipping_fee_minor')->default(0);
            $table->unsignedInteger('grand_total_minor');

            $table->string('courier_name')->nullable();
            $table->string('courier_service_id')->nullable();
            // 'api' when EasyParcel quoted it, 'flat' when the fallback fired
            // (Planning §11.B.6) — surfaced in admin so a fallback is visible.
            $table->string('shipping_rate_source', 10)->nullable();

            $table->string('order_status', 32)->default('pending_payment');
            $table->string('payment_status', 32)->default('pending');

            $table->timestamps();

            $table->index(['payment_status', 'order_status']);
            $table->index('created_at');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
