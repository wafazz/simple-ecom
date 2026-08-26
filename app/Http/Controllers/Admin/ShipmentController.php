<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShipmentBookingFailed;
use App\Exceptions\ShipmentOutcomeUnknown;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ShipmentBookingService;
use Illuminate\Http\RedirectResponse;

/** REQ-013 — courier booking. Planning §11.B.5. */
class ShipmentController extends Controller
{
    /**
     * Book the courier. Spends real credit, so it is POST-only and never
     * reachable by a link or a prefetch.
     */
    public function store(Order $order, ShipmentBookingService $booking): RedirectResponse
    {
        $back = redirect()->route('admin.orders.show', $order);

        try {
            $shipment = $booking->book($order);
        } catch (ShipmentOutcomeUnknown $e) {
            return $back->with('error',
                'Booking outcome UNKNOWN — the courier may already have been paid. '
                .'Check the EasyParcel dashboard for this order before trying again. '
                .'This shipment will not retry on its own.');
        } catch (ShipmentBookingFailed $e) {
            // Safe to show: the message names missing fields, never a secret.
            return $back->with('error', $e->getMessage());
        }

        return $back->with('status', $shipment->awb_no
            ? "Shipment booked. AWB {$shipment->awb_no}."
            : 'Shipment booked. The courier has not issued an AWB number yet.');
    }
}
