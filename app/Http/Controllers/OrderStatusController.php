<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderStatusRequest;
use App\Models\Order;
use Illuminate\View\View;

/** REQ-007 — public order lookup. No customer accounts (spec §11). */
class OrderStatusController extends Controller
{
    public function show(): View
    {
        return view('storefront.order_status', ['order' => null]);
    }

    public function lookup(OrderStatusRequest $request): View
    {
        $data = $request->validated();

        $order = Order::query()
            ->where('order_no', $data['order_no'])
            // Both must match. Without the email an order number alone would
            // expose another customer's name, address and items.
            ->where('customer_email', $data['email'])
            ->with(['items', 'shipment'])
            ->first();

        return view('storefront.order_status', [
            'order' => $order,
            // Deliberately does not distinguish "no such order" from "wrong
            // email" — that difference is an enumeration oracle.
            'notFound' => $order === null,
        ]);
    }
}
