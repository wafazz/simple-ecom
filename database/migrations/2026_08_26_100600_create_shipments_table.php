<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-013 — courier booking, AWB and tracking. Planning §11.B.5, §12.2.
 *
 * Booking spends REAL money from the store's EasyParcel credit balance, which
 * makes this the riskiest table in the schema. Two structural safeguards:
 *
 *  1. UNIQUE(order_id) — one shipment per order. A second "Book shipment" click
 *     hits a duplicate-key error, not a second real-money charge.
 *  2. The row is written in `pending_submit` BEFORE any API call and is never
 *     deleted, so a failed DB write can never leave a paid booking unrecorded.
 *
 * An ambiguous `pay` outcome (timeout) goes to `needs_reconciliation`, never to
 * `failed`, and is never auto-retried — retrying a payment that may have
 * succeeded is how a store pays twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            // One shipment per order — the anti-double-booking guard.
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('provider', 32)->default('easyparcel');
            $table->string('provider_shipment_ref', 128)->nullable();
            $table->string('awb_no', 64)->nullable();
            $table->string('tracking_no', 64)->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('label_url')->nullable();

            $table->string('courier_name')->nullable();
            $table->string('service_id')->nullable();

            // What EasyParcel actually charged. May differ from the customer's
            // orders.shipping_fee_minor — that gap is the store's margin or loss
            // on shipping and the admin needs to see it (Planning §12.2).
            $table->unsignedInteger('cost_minor')->nullable();

            $table->string('status', 32)->default('pending_submit');
            $table->json('raw_response')->nullable();

            $table->timestamp('booked_at')->nullable();
            $table->timestamp('last_tracked_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('awb_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
