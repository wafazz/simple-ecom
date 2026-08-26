<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\EasyParcelService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** REQ-006 — AJAX rate lookup from the checkout page (Planning §11.B.4). */
class ShippingController extends Controller
{
    public function __construct(
        private readonly EasyParcelService $easyparcel,
        private readonly CartService $cart,
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'state' => ['required', 'string', Rule::in(array_keys(config('shop.states')))],
        ]);

        if ($this->cart->isEmpty()) {
            return response()->json(['quotes' => [], 'message' => 'Your cart is empty.'], 422);
        }

        $quotes = $this->easyparcel->quote(
            $data['postcode'],
            $data['state'],
            $this->cart->totalWeightG(),
        );

        return response()->json([
            'quotes' => array_map(fn ($q): array => [
                'service_id' => $q->serviceId,
                'label' => $q->label(),
                'price_minor' => $q->priceMinor,
                'price' => Money::display($q->priceMinor, config('shop.currency_symbol')),
                'delivery_duration' => $q->deliveryDuration,
                'is_flat' => $q->isFlat(),
            ], $quotes),
        ]);
    }
}
