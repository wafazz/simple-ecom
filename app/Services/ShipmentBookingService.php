<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\ShipmentBookingFailed;
use App\Exceptions\ShipmentOutcomeUnknown;
use App\Models\Order;
use App\Models\Shipment;
use App\Support\Money;
use App\Support\ShipmentPayload;
use Illuminate\Support\Facades\Log;

/**
 * Books ONE courier shipment for an order. REQ-013 — Planning §11.B.5.
 *
 * This is the only place in the application that spends real money from the
 * store's EasyParcel credit balance, so the ordering below is deliberate and
 * not merely tidy:
 *
 *   1. Refuse anything that cannot possibly succeed, BEFORE any request.
 *   2. Write the shipment row first. UNIQUE(order_id) makes a second
 *      concurrent booking a database error rather than a second charge.
 *   3. Claim the row with a guarded UPDATE. Only the caller that actually
 *      moved it out of `pending_submit` is allowed to call the API.
 *   4. Record the outcome — including "we do not know", which is a distinct
 *      state and is never auto-retried.
 */
class ShipmentBookingService
{
    public function __construct(private readonly EasyParcelService $easyParcel) {}

    /**
     * @param  string  $serviceId  the EasyParcel courier service the admin
     *                             chose. Never taken from the order: what the
     *                             customer paid is a weight-table figure, not
     *                             a courier product.
     */
    public function book(Order $order, string $serviceId): Shipment
    {
        $order->loadMissing('items.variant');

        $this->assertBookable($order);

        // Written BEFORE the API call. A booking that succeeds but cannot be
        // saved is worse than one that never happened, because nothing in the
        // system would ever show that the money left.
        $shipment = Shipment::firstOrCreate(
            ['order_id' => $order->id],
            [
                'provider' => 'easyparcel',
                'status' => ShipmentStatus::PendingSubmit,
                'service_id' => $serviceId,
            ],
        );

        if (! $shipment->status->isRetryable()) {
            throw new ShipmentBookingFailed(
                "This order already has a shipment ({$shipment->status->label()}). "
                .'It cannot be booked again.'
            );
        }

        if (! Shipment::transitionAtomically($shipment->id, $shipment->status, ShipmentStatus::Submitting)) {
            // Someone else claimed it between the read and here.
            throw new ShipmentBookingFailed('This shipment is already being booked.');
        }

        try {
            $result = $this->easyParcel->submitOrder(ShipmentPayload::for($order, $serviceId));
        } catch (ShipmentOutcomeUnknown $e) {
            // NOT `failed` — failed is retryable and this must never be
            // retried. An admin clears it against the EasyParcel dashboard.
            $shipment->forceFill([
                'status' => ShipmentStatus::NeedsReconciliation,
                'raw_response' => ['error' => $e->getMessage()],
            ])->save();

            Log::error('Shipment outcome unknown — reconcile by hand.', [
                'order_no' => $order->order_no,
                'shipment_id' => $shipment->id,
                'reason' => $e->getMessage(),
            ]);

            throw $e;
        } catch (ShipmentBookingFailed $e) {
            // Nothing was charged, so the row goes back to a retryable state.
            $shipment->forceFill([
                'status' => ShipmentStatus::Failed,
                'raw_response' => ['error' => $e->getMessage()],
            ])->save();

            throw $e;
        }

        return $this->record($shipment, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function record(Shipment $shipment, array $result): Shipment
    {
        $shipment->forceFill([
            'status' => ShipmentStatus::Booked,
            'provider_shipment_ref' => $result['shipment_number'] ?? $result['_order_number'] ?? null,
            'awb_no' => $result['awb_number'] ?? null,
            // The courier's own tracking number is the AWB; EasyParcel gives
            // the URL separately and may not have issued either yet at submit
            // time (both are null in the published success example).
            'tracking_no' => $result['awb_number'] ?? null,
            'tracking_url' => $result['tracking_url'] ?? null,
            'label_url' => data_get($result, 'awb_urls_by_format.A4') ?: ($result['awb_url'] ?: null),
            'courier_name' => $result['courier'] ?? null,
            'service_id' => $result['courier_service'] ?? $shipment->service_id,
            'cost_minor' => $this->costMinor($result),
            'raw_response' => $result,
            'booked_at' => now(),
        ])->save();

        return $shipment;
    }

    /**
     * What EasyParcel actually charged, in sen.
     *
     * A decimal STRING in the response, never minor units — converted here
     * exactly once. This is the store's real cost and may differ from what the
     * customer paid for shipping; that gap is the admin's to see (§12.2).
     *
     * @param  array<string, mixed>  $result
     */
    private function costMinor(array $result): ?int
    {
        $amount = data_get($result, 'pricing_breakdown.total_paid_amount');

        if ($amount === null) {
            return null;
        }

        try {
            return Money::fromDecimalString((string) $amount);
        } catch (\InvalidArgumentException) {
            // An unparseable price must not lose the booking that succeeded.
            return null;
        }
    }

    private function assertBookable(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            // Booking an unpaid order spends courier credit against revenue
            // that may never arrive.
            throw new ShipmentBookingFailed('Only a paid order can be booked with the courier.');
        }

        $missing = ShipmentPayload::missingFor($order);

        if ($missing !== []) {
            throw new ShipmentBookingFailed(
                'Cannot book yet — still missing: '.implode('; ', $missing).'.'
            );
        }
    }
}
