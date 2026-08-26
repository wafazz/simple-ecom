<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Support\Money;
use App\Support\ShippingRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * REQ-006 — the delivery charge for an address, as the customer types it.
 *
 * Priced from the store's own weight table, so this makes no outbound call and
 * cannot be slowed down or broken by a courier API.
 */
class ShippingController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'state' => ['required', 'string', Rule::in(array_keys(config('shop.states')))],
        ]);

        if ($this->cart->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $weightG = $this->cart->totalWeightG();
        $quote = ShippingRate::quoteFor($data['state'], $weightG);

        // Advisory only. The same calculation runs again when the order is
        // placed, from the address that was actually submitted — this response
        // is what the page displays, never what the customer is charged.
        return response()->json([
            'zone' => ShippingRate::zoneLabel(ShippingRate::zoneFor($data['state'])),
            'kilos' => ShippingRate::billableKilos($weightG),
            'price_minor' => $quote->priceMinor,
            'price' => Money::display($quote->priceMinor, config('shop.currency_symbol')),
        ]);
    }
}
